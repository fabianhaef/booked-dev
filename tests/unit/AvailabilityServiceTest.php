<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\AvailabilityCacheService;
use fabian\booked\services\BlackoutDateService;
use fabian\booked\elements\Service;
use fabian\booked\Booked;
use UnitTester;
use DateTime;
use Craft;

/**
 * A testable version of AvailabilityService that allows us to bypass Craft/DB dependencies
 */
class TestableAvailabilityService extends AvailabilityService 
{
    public $mockWorkingHours = [];
    public $mockReservations = [];
    public $mockAvailabilities = [];
    public $mockBlackedOutDates = [];
    public $mockNow = null;
    public $mockEmployeeTimezone = 'Europe/Zurich'; // Default mock TZ

    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        $filtered = $this->mockWorkingHours;
        if ($employeeId !== null) {
            $filtered = array_filter($filtered, function($s) use ($employeeId) {
                return $s->employeeId === $employeeId;
            });
        }
        return array_values($filtered);
    }

    protected function subtractBlackouts(array $windows, string $date, ?int $employeeId = null): array
    {
        // Simple mock: return empty if date is in mockBlackedOutDates
        if (isset($this->mockBlackedOutDates) && in_array($date, $this->mockBlackedOutDates)) {
            return [];
        }
        return $windows;
    }

    protected function getEmployeeTimezone(int $employeeId): string
    {
        return $this->mockEmployeeTimezone;
    }

    protected function getAvailabilities(?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        // Simple mock for now
        return $this->mockAvailabilities;
    }

    protected function getReservationsForDate(string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        $filtered = $this->mockReservations;
        if ($employeeId !== null) {
            $filtered = array_filter($filtered, function($r) use ($employeeId) {
                return $r->employeeId === $employeeId;
            });
        }
        return array_values($filtered);
    }

    protected function getCurrentDateTime(): DateTime
    {
        return $this->mockNow ?? new DateTime();
    }

    protected function subtractExternalEvents(array $windows, string $date, int $employeeId): array
    {
        return $windows; // Mock: don't subtract anything by default
    }
}

/**
 * Mock Service class
 */
class MockService extends Service {
    public ?int $duration = 60;
    public ?int $bufferBefore = 0;
    public ?int $bufferAfter = 0;
    public bool $enabled = true;
    public function __construct() {}
}

/**
 * Mock Cache Service
 */
class MockCacheService extends AvailabilityCacheService {
    public function getCachedAvailability(string $date, ?int $employeeId = null, ?int $serviceId = null): ?array {
        return null;
    }
    public function setCachedAvailability(string $date, array $slots, ?int $employeeId = null, ?int $serviceId = null): bool {
        return true;
    }
}

/**
 * Mock Blackout Service
 */
class MockBlackoutService extends BlackoutDateService {
    public $isBlackedOut = false;
    public function isDateBlackedOut(string $date, ?int $employeeId = null, ?int $locationId = null): bool {
        return $this->isBlackedOut;
    }
}

/**
 * Mock Schedule element
 */
class MockScheduleElement extends \fabian\booked\elements\Schedule {
    public ?string $startTime = null;
    public ?string $endTime = null;
    public ?int $employeeId = null;
    public array $employeeIds = [];
    public function __construct() {}
    public function getEmployees(): array { return []; }
    public function getEmployee(): ?\fabian\booked\elements\Employee { return null; }
}

/**
 * Mock Reservation element
 */
class AvailabilityMockReservation extends \fabian\booked\elements\Reservation {
    public string $startTime = '';
    public string $endTime = '';
    public ?int $employeeId = null;
    public int $quantity = 1;
    public function __construct() {}
}

/**
 * Mock Availability element
 */
class MockAvailability extends \fabian\booked\elements\Availability {
    public function __construct() {}
}

class AvailabilityServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableAvailabilityService
     */
    protected $service;
    
    /**
     * @var MockBlackoutService
     */
    protected $blackoutService;

    protected function _before()
    {
        parent::_before();
        
        $this->blackoutService = new MockBlackoutService();

        // Mock the Booked plugin singleton
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailabilityCache', 'getBlackoutDate'])
            ->getMock();
            
        $pluginMock->method('getAvailabilityCache')->willReturn(new MockCacheService());
        $pluginMock->method('getBlackoutDate')->willReturn($this->blackoutService);
        
        // Force the mock into the private static property
        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->service = new TestableAvailabilityService();
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function testTimeToMinutes()
    {
        $this->assertEquals(540, $this->invokeMethod($this->service, 'timeToMinutes', ['09:00']));
    }

    public function testSubtractBlackouts()
    {
        $windows = [['start' => '09:00', 'end' => '12:00']];
        $date = '2025-12-25';

        // Case 1: No blackout
        $this->service->mockBlackedOutDates = [];
        $result = $this->invokeMethod($this->service, 'subtractBlackouts', [$windows, $date]);
        $this->assertEquals($windows, $result);

        // Case 2: Date is blacked out
        $this->service->mockBlackedOutDates = [$date];
        $result = $this->invokeMethod($this->service, 'subtractBlackouts', [$windows, $date]);
        $this->assertEquals([], $result);
    }

    public function testSubtractWindow()
    {
        $windows = [['start' => '09:00', 'end' => '12:00']];
        
        // Case 1: Subtract from middle
        $result = $this->invokeMethod($this->service, 'subtractWindow', [$windows, '10:00', '11:00']);
        $this->assertCount(2, $result);
        $this->assertEquals('09:00', $result[0]['start']);
        $this->assertEquals('10:00', $result[0]['end']);
        $this->assertEquals('11:00', $result[1]['start']);
        $this->assertEquals('12:00', $result[1]['end']);

        // Case 2: Subtract from start
        $result = $this->invokeMethod($this->service, 'subtractWindow', [$windows, '09:00', '10:00']);
        $this->assertCount(1, $result);
        $this->assertEquals('10:00', $result[0]['start']);
        $this->assertEquals('12:00', $result[0]['end']);

        // Case 3: Subtract from end
        $result = $this->invokeMethod($this->service, 'subtractWindow', [$windows, '11:00', '12:00']);
        $this->assertCount(1, $result);
        $this->assertEquals('09:00', $result[0]['start']);
        $this->assertEquals('11:00', $result[0]['end']);

        // Case 4: Subtract entire window
        $result = $this->invokeMethod($this->service, 'subtractWindow', [$windows, '09:00', '12:00']);
        $this->assertCount(0, $result);
    }

    public function testSubtractBookings()
    {
        $windows = [
            ['start' => '09:00', 'end' => '17:00', 'employeeId' => 1]
        ];
        $date = '2025-12-25';
        
        // Mock a reservation
        $res = new AvailabilityMockReservation();
        $res->startTime = '10:00';
        $res->endTime = '11:00';
        $res->employeeId = 1;
        
        $this->service->mockReservations = [$res];

        $result = $this->invokeMethod($this->service, 'subtractBookings', [$windows, $date, 1]);
        
        $this->assertCount(2, $result);
        $this->assertEquals('09:00', $result[0]['start']);
        $this->assertEquals('10:00', $result[0]['end']);
        $this->assertEquals('11:00', $result[1]['start']);
        $this->assertEquals('17:00', $result[1]['end']);
    }

    public function testSubtractBuffers()
    {
        $windows = [['start' => '09:00', 'end' => '12:00']];
        $service = new MockService();
        $service->bufferBefore = 15;
        $service->bufferAfter = 30;

        $adjusted = $this->invokeMethod($this->service, 'subtractBuffers', [$windows, $service]);

        $this->assertCount(1, $adjusted);
        $this->assertEquals('09:15', $adjusted[0]['start']);
        $this->assertEquals('11:30', $adjusted[0]['end']);
    }

    public function testGenerateSlots()
    {
        $windows = [['start' => '09:00', 'end' => '11:00']];
        $slots = $this->invokeMethod($this->service, 'generateSlots', [$windows, 60]);
        $this->assertCount(2, $slots);
    }

    public function testGetAvailableSlotsWithQuantity()
    {
        // Setup mock working hours for two employees
        $s1 = new MockScheduleElement();
        $s1->startTime = '09:00';
        $s1->endTime = '10:00';
        $s1->employeeId = 1;
        
        $s2 = new MockScheduleElement();
        $s2->startTime = '09:00';
        $s2->endTime = '10:00';
        $s2->employeeId = 2;
        
        $this->service->mockWorkingHours = [$s1, $s2];
        
        $date = '2099-12-25';
        
        // Case 1: Request quantity 1 (should see slots from both or merged)
        // With current merge logic, it merges them.
        $slots = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertNotEmpty($slots);

        // Case 2: One employee is booked
        $res = new AvailabilityMockReservation();
        $res->startTime = '09:00';
        $res->endTime = '10:00';
        $res->employeeId = 1;
        $res->quantity = 1;
        $this->service->mockReservations = [$res];
        
        // If we request employee 1, it should be empty
        $slotsEmp1 = $this->service->getAvailableSlots($date, 1, null, null, 1);
        $this->assertEmpty($slotsEmp1);
        
        // If we request any employee, it should still have one slot (from employee 2)
        $slotsAny = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertNotEmpty($slotsAny);
    }

    public function testGetAvailableSlotsWithAdvanceBooking()
    {
        $today = (new DateTime())->format('Y-m-d');
        
        $s1 = new MockScheduleElement();
        $s1->startTime = '09:00';
        $s1->endTime = '12:00';
        $s1->employeeId = 1;
        $this->service->mockWorkingHours = [$s1];
        
        // Mock current time to 08:00
        $this->service->mockNow = new DateTime($today . ' 08:00:00');
        
        // Case 1: All slots are in future
        $slots = $this->service->getAvailableSlots($today, 1);
        $this->assertCount(3, $slots); // 09:00, 10:00, 11:00
        $this->assertEquals('09:00', $slots[0]['time']);

        // Case 2: Some slots are in past
        $this->service->mockNow = new DateTime($today . ' 10:30:00');
        $slotsPart = $this->service->getAvailableSlots($today, 1);
        $this->assertCount(1, $slotsPart); // Only 11:00
        $this->assertEquals('11:00', $slotsPart[0]['time']);

        // Case 3: Past date
        $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
        $slotsPast = $this->service->getAvailableSlots($yesterday, 1);
        $this->assertEmpty($slotsPast);
    }

    public function testIsSlotAvailable()
    {
        $today = (new DateTime())->format('Y-m-d');
        $s1 = new MockScheduleElement();
        $s1->startTime = '09:00';
        $s1->endTime = '10:00';
        $s1->employeeId = 1;
        $this->service->mockWorkingHours = [$s1];
        $this->service->mockNow = new DateTime($today . ' 08:00:00');

        $this->assertTrue($this->service->isSlotAvailable($today, '09:00', '10:00', 1));
        $this->assertFalse($this->service->isSlotAvailable($today, '10:00', '11:00', 1));
        
        // With quantity
        $s2 = new MockScheduleElement();
        $s2->startTime = '09:00';
        $s2->endTime = '10:00';
        $s2->employeeId = 2;
        $this->service->mockWorkingHours = [$s1, $s2];
        
        $this->assertTrue($this->service->isSlotAvailable($today, '09:00', '10:00', null, null, null, 2));
        
        // One booked
        $res = new AvailabilityMockReservation();
        $res->startTime = '09:00';
        $res->endTime = '10:00';
        $res->employeeId = 1;
        $this->service->mockReservations = [$res];
        
        $this->assertFalse($this->service->isSlotAvailable($today, '09:00', '10:00', null, null, null, 2));
        $this->assertTrue($this->service->isSlotAvailable($today, '09:00', '10:00', null, null, null, 1));
    }

    public function testGetAvailableSlotsWithRecurrence()
    {
        $monday = '2026-01-05'; // A future Monday
        
        $avail = new MockAvailability();
        $avail->startTime = '09:00';
        $avail->endTime = '12:00';
        $avail->availabilityType = 'recurring';
        $avail->rrule = 'FREQ=WEEKLY;BYDAY=MO';
        $avail->isActive = true;
        $avail->sourceId = 1; // employeeId
        
        $this->service->mockAvailabilities = [$avail];
        $this->service->mockWorkingHours = []; // No base schedule
        
        $slots = $this->service->getAvailableSlots($monday, 1);
        
        $this->assertNotEmpty($slots);
        $this->assertEquals('09:00', $slots[0]['time']);
    }

    public function testGetAvailableSlotsWithTimezoneShift()
    {
        $today = '2026-01-01';
        $s1 = new MockScheduleElement();
        $s1->startTime = '09:00';
        $s1->endTime = '12:00';
        $s1->employeeId = 1;
        $this->service->mockWorkingHours = [$s1];
        
        // Mock the employee's location timezone
        // For now, we assume Location TZ is Europe/Zurich (UTC+1)
        // And user is in America/New_York (UTC-5)
        // Shift is -6 hours
        
        $userTz = 'America/New_York';
        $slots = $this->service->getAvailableSlots($today, null, null, null, 1, $userTz);
        
        $this->assertNotEmpty($slots);
        // 09:00 Zurich -> 03:00 New York
        $this->assertEquals('03:00', $slots[0]['time']);
    }
}
