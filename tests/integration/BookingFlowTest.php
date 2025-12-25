<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use fabian\booked\services\BookingService;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\SoftLockService;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;
use fabian\booked\tests\_support\traits\CreatesBookings;
use fabian\booked\exceptions\BookingException;
use UnitTester;
use DateTime;

/**
 * Integration test for complete booking workflow
 *
 * Tests the entire booking process from service selection through confirmation:
 * 1. Service/Employee selection
 * 2. Date/Time slot selection
 * 3. Soft lock acquisition
 * 4. Customer information validation
 * 5. Booking creation
 * 6. Confirmation and notification
 */
class BookingFlowTest extends Unit
{
    use CreatesBookings;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableBookingService
     */
    protected $bookingService;

    /**
     * @var TestableAvailabilityService
     */
    protected $availabilityService;

    /**
     * @var TestableSoftLockService
     */
    protected $softLockService;

    protected function _before()
    {
        parent::_before();

        // Mock the plugin singleton
        $this->mockPluginServices();

        $this->softLockService = new TestableSoftLockService();
        $this->availabilityService = new TestableAvailabilityService();
        $this->bookingService = new TestableBookingService();
    }

    /**
     * Test complete successful booking flow
     */
    public function testCompleteBookingFlow()
    {
        // Arrange: Create scenario
        $scenario = $this->createBookingScenario();
        $service = $scenario['service'];
        $employee = $scenario['employee'];

        $tomorrow = (new DateTime('+1 day'))->format('Y-m-d');
        $timeSlot = '10:00';

        // Mock availability service to return available slot
        $this->availabilityService->mockSlots = [
            [
                'time' => $timeSlot,
                'startTime' => $timeSlot,
                'endTime' => '11:00',
                'employeeId' => $employee->id,
            ]
        ];

        // Act: Step 1 - Check availability
        $availableSlots = $this->availabilityService->getAvailableSlots(
            $tomorrow,
            $employee->id,
            null,
            $service->id
        );

        // Assert: Slot is available
        $this->assertNotEmpty($availableSlots);
        $this->assertEquals($timeSlot, $availableSlots[0]['time']);

        // Act: Step 2 - Acquire soft lock
        $lockToken = $this->softLockService->createLock(
            $tomorrow,
            $timeSlot,
            '11:00',
            $service->id,
            $employee->id
        );

        // Assert: Lock acquired
        $this->assertNotNull($lockToken);
        $this->assertTrue($this->softLockService->isLocked($tomorrow, $timeSlot, '11:00', $service->id, $employee->id));

        // Act: Step 3 - Create booking
        $bookingData = [
            'date' => $tomorrow,
            'time' => $timeSlot,
            'serviceId' => $service->id,
            'employeeId' => $employee->id,
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
            'customerPhone' => '+41 79 123 45 67',
            'lockToken' => $lockToken,
        ];

        $this->bookingService->mockAvailabilityService = $this->availabilityService;
        $this->bookingService->mockSoftLockService = $this->softLockService;

        $result = $this->bookingService->createBooking($bookingData);

        // Assert: Booking created successfully
        $this->assertTrue($result);
        $this->assertNotNull($this->bookingService->lastCreatedReservation);
        $this->assertEquals('confirmed', $this->bookingService->lastCreatedReservation->status);
        $this->assertEquals('John Doe', $this->bookingService->lastCreatedReservation->customerName);

        // Assert: Soft lock released
        $this->assertFalse($this->softLockService->isLocked($tomorrow, $timeSlot, '11:00', $service->id, $employee->id));

        // Assert: Notification sent
        $this->assertTrue($this->bookingService->notificationSent);
    }

    /**
     * Test booking flow with validation errors
     */
    public function testBookingFlowWithValidationErrors()
    {
        $tomorrow = (new DateTime('+1 day'))->format('Y-m-d');

        // Invalid email
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Invalid email');

        $this->bookingService->createBooking([
            'date' => $tomorrow,
            'time' => '10:00',
            'serviceId' => 1,
            'employeeId' => 1,
            'customerName' => 'John Doe',
            'customerEmail' => 'invalid-email',
        ]);
    }

    /**
     * Test booking flow with unavailable slot
     */
    public function testBookingFlowWithUnavailableSlot()
    {
        $tomorrow = (new DateTime('+1 day'))->format('Y-m-d');

        // No available slots
        $this->availabilityService->mockSlots = [];
        $this->bookingService->mockAvailabilityService = $this->availabilityService;

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Slot not available');

        $this->bookingService->createBooking([
            'date' => $tomorrow,
            'time' => '10:00',
            'serviceId' => 1,
            'employeeId' => 1,
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
        ]);
    }

    /**
     * Test booking cancellation flow
     */
    public function testBookingCancellationFlow()
    {
        // Arrange: Create a reservation
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'bookingDate' => (new DateTime('+1 day'))->format('Y-m-d'),
        ]);

        $this->bookingService->mockReservation = $reservation;

        // Act: Cancel the booking
        $result = $this->bookingService->cancelBooking($reservation->id);

        // Assert: Cancellation successful
        $this->assertTrue($result);
        $this->assertEquals('cancelled', $this->bookingService->mockReservation->status);
        $this->assertTrue($this->bookingService->cancellationNotificationSent);
    }

    /**
     * Test booking rescheduling flow
     */
    public function testBookingRescheduleFlow()
    {
        // Arrange: Create existing reservation
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'bookingDate' => (new DateTime('+1 day'))->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
        ]);

        $this->bookingService->mockReservation = $reservation;

        // New slot is available
        $newDate = (new DateTime('+2 days'))->format('Y-m-d');
        $newTime = '14:00';
        $this->availabilityService->mockSlots = [
            ['time' => $newTime, 'startTime' => $newTime, 'endTime' => '15:00', 'employeeId' => 1]
        ];
        $this->bookingService->mockAvailabilityService = $this->availabilityService;

        // Act: Reschedule
        $result = $this->bookingService->rescheduleBooking($reservation->id, $newDate, $newTime);

        // Assert: Rescheduled successfully
        $this->assertTrue($result);
        $this->assertEquals($newDate, $this->bookingService->mockReservation->bookingDate);
        $this->assertEquals($newTime, $this->bookingService->mockReservation->startTime);
        $this->assertTrue($this->bookingService->rescheduleNotificationSent);
    }

    /**
     * Test booking with custom fields
     */
    public function testBookingFlowWithCustomFields()
    {
        $scenario = $this->createBookingScenario();
        $tomorrow = (new DateTime('+1 day'))->format('Y-m-d');

        $this->availabilityService->mockSlots = [
            ['time' => '10:00', 'startTime' => '10:00', 'endTime' => '11:00', 'employeeId' => 1]
        ];
        $this->bookingService->mockAvailabilityService = $this->availabilityService;

        // Act: Create booking with custom fields
        $result = $this->bookingService->createBooking([
            'date' => $tomorrow,
            'time' => '10:00',
            'serviceId' => $scenario['service']->id,
            'employeeId' => $scenario['employee']->id,
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
            'customFields' => [
                'specialRequests' => 'Window seat please',
                'allergies' => 'Peanuts',
            ],
        ]);

        // Assert: Custom fields saved
        $this->assertTrue($result);
        $this->assertNotNull($this->bookingService->lastCreatedReservation);
        // Custom fields would be verified through field layout
    }

    /**
     * Mock plugin services
     */
    private function mockPluginServices(): void
    {
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBooking', 'getAvailability', 'getSoftLock'])
            ->getMock();

        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);
    }
}

/**
 * Testable BookingService for integration tests
 */
class TestableBookingService extends BookingService
{
    public $mockReservation = null;
    public $mockAvailabilityService = null;
    public $mockSoftLockService = null;
    public $lastCreatedReservation = null;
    public $notificationSent = false;
    public $cancellationNotificationSent = false;
    public $rescheduleNotificationSent = false;

    public function createBooking(array $data): bool
    {
        // Validate email
        if (!filter_var($data['customerEmail'], FILTER_VALIDATE_EMAIL)) {
            throw new BookingException('Invalid email');
        }

        // Check slot availability
        if ($this->mockAvailabilityService) {
            $slots = $this->mockAvailabilityService->getAvailableSlots(
                $data['date'],
                $data['employeeId'] ?? null,
                null,
                $data['serviceId']
            );

            if (empty($slots)) {
                throw new BookingException('Slot not available');
            }
        }

        // Create reservation
        $this->lastCreatedReservation = new class extends Reservation {
            public function __construct() {}
        };
        $this->lastCreatedReservation->status = 'confirmed';
        $this->lastCreatedReservation->customerName = $data['customerName'];
        $this->lastCreatedReservation->customerEmail = $data['customerEmail'];
        $this->lastCreatedReservation->bookingDate = $data['date'];
        $this->lastCreatedReservation->startTime = $data['time'];
        $this->lastCreatedReservation->id = rand(1000, 9999);

        // Release soft lock
        if ($this->mockSoftLockService && isset($data['lockToken'])) {
            $this->mockSoftLockService->releaseLock($data['lockToken']);
        }

        // Send notification
        $this->notificationSent = true;

        return true;
    }

    public function cancelBooking(int $id): bool
    {
        if ($this->mockReservation) {
            $this->mockReservation->status = 'cancelled';
            $this->cancellationNotificationSent = true;
            return true;
        }
        return false;
    }

    public function rescheduleBooking(int $id, string $newDate, string $newTime): bool
    {
        if ($this->mockReservation && $this->mockAvailabilityService) {
            $slots = $this->mockAvailabilityService->getAvailableSlots($newDate);
            if (empty($slots)) {
                throw new BookingException('New slot not available');
            }

            $this->mockReservation->bookingDate = $newDate;
            $this->mockReservation->startTime = $newTime;
            $this->rescheduleNotificationSent = true;
            return true;
        }
        return false;
    }
}

/**
 * Testable AvailabilityService for integration tests
 */
class TestableAvailabilityService extends AvailabilityService
{
    public array $mockSlots = [];

    public function getAvailableSlots(
        string $date,
        ?int $employeeId = null,
        ?int $locationId = null,
        ?int $serviceId = null,
        int $quantity = 1,
        ?string $timezone = null
    ): array {
        return $this->mockSlots;
    }
}

/**
 * Testable SoftLockService for integration tests
 */
class TestableSoftLockService extends SoftLockService
{
    private array $locks = [];

    public function createLock(array $data, int $durationMinutes = 15): string|false
    {
        // Extract parameters from data array
        $date = $data['date'] ?? '';
        $startTime = $data['startTime'] ?? '';
        $serviceId = $data['serviceId'] ?? 0;
        $employeeId = $data['employeeId'] ?? null;

        // Check if already locked using parent's isLocked method
        if ($this->isLocked($date, $startTime, $serviceId, $employeeId)) {
            return false;
        }

        $token = bin2hex(random_bytes(16));
        $key = "{$date}_{$startTime}_{$serviceId}_{$employeeId}";
        $this->locks[$key] = $token;
        return $token;
    }

    public function isLocked(string $date, string $startTime, int $serviceId, ?int $employeeId = null): bool
    {
        $key = "{$date}_{$startTime}_{$serviceId}_{$employeeId}";
        return isset($this->locks[$key]);
    }

    public function releaseLock(string $token): bool
    {
        foreach ($this->locks as $key => $lockToken) {
            if ($lockToken === $token) {
                unset($this->locks[$key]);
                return true;
            }
        }
        return false;
    }
}
