<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use Craft;
use fabian\booked\Booked;
use fabian\booked\elements\BookingSequence;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Schedule;
use fabian\booked\elements\Service;
use fabian\booked\exceptions\BookingException;
use fabian\booked\records\BookingSequenceRecord;
use fabian\booked\records\ReservationRecord;

/**
 * Sequential Booking Flow Integration Tests
 *
 * Tests the complete sequential booking workflow with database
 */
class SequentialBookingFlowTest extends Unit
{
    protected $tester;
    protected $sequentialService;
    protected $employee;
    protected $location;
    protected $services = [];

    protected function _before()
    {
        parent::_before();

        // Get sequential booking service
        $this->sequentialService = Booked::getInstance()->sequentialBooking;

        // Create test location
        $this->location = $this->tester->createLocation([
            'title' => 'Test Spa',
            'timezone' => 'Europe/Zurich',
        ]);

        // Create test employee
        $this->employee = $this->tester->createEmployee([
            'title' => 'Test Therapist',
            'locationId' => $this->location->id,
        ]);

        // Create test services
        $this->services[] = $this->tester->createService([
            'title' => 'Massage',
            'duration' => 60,
            'bufferAfter' => 15,
            'price' => 100.00,
        ]);

        $this->services[] = $this->tester->createService([
            'title' => 'Facial',
            'duration' => 45,
            'bufferAfter' => 10,
            'price' => 80.00,
        ]);

        $this->services[] = $this->tester->createService([
            'title' => 'Manicure',
            'duration' => 30,
            'bufferAfter' => 5,
            'price' => 50.00,
        ]);

        // Create employee schedule (9 AM - 5 PM)
        $this->tester->createSchedule([
            'employeeId' => $this->employee->id,
            'dayOfWeek' => 4, // Thursday
            'startTime' => '09:00',
            'endTime' => '17:00',
        ]);
    }

    /**
     * Test creating a simple two-service sequence
     */
    public function testCreateSimpleTwoServiceSequence()
    {
        $serviceIds = [
            $this->services[0]->id, // Massage (60 min)
            $this->services[1]->id, // Facial (45 min)
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25', // Thursday
            'startTime' => '10:00',
            'customerName' => 'Jane Doe',
            'customerEmail' => 'jane@example.com',
            'customerPhone' => '+41 79 111 22 33',
            'employeeId' => $this->employee->id,
            'locationId' => $this->location->id,
        ]);

        // Verify sequence created
        $this->assertInstanceOf(BookingSequence::class, $sequence);
        $this->assertNotNull($sequence->id);
        $this->assertEquals('Jane Doe', $sequence->customerName);
        $this->assertEquals('jane@example.com', $sequence->customerEmail);
        $this->assertEquals(BookingSequenceRecord::STATUS_PENDING, $sequence->status);
        $this->assertEquals(180.00, $sequence->totalPrice); // 100 + 80

        // Verify reservations created
        $items = $sequence->getItems();
        $this->assertCount(2, $items);

        // First reservation (Massage)
        $this->assertEquals(0, $items[0]->sequenceOrder);
        $this->assertEquals($this->services[0]->id, $items[0]->serviceId);
        $this->assertEquals('10:00', $items[0]->startTime);
        $this->assertEquals('11:00', $items[0]->endTime);
        $this->assertEquals($sequence->id, $items[0]->sequenceId);

        // Second reservation (Facial) - starts after massage + buffer
        $this->assertEquals(1, $items[1]->sequenceOrder);
        $this->assertEquals($this->services[1]->id, $items[1]->serviceId);
        $this->assertEquals('11:15', $items[1]->startTime); // 11:00 + 15 min buffer
        $this->assertEquals('12:00', $items[1]->endTime);   // 11:15 + 45 min
        $this->assertEquals($sequence->id, $items[1]->sequenceId);
    }

    /**
     * Test creating a three-service sequence
     */
    public function testCreateThreeServiceSequence()
    {
        $serviceIds = [
            $this->services[0]->id, // Massage (60 min + 15 buffer)
            $this->services[1]->id, // Facial (45 min + 10 buffer)
            $this->services[2]->id, // Manicure (30 min + 5 buffer)
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25',
            'startTime' => '09:00',
            'customerName' => 'Sarah Smith',
            'customerEmail' => 'sarah@example.com',
            'employeeId' => $this->employee->id,
            'locationId' => $this->location->id,
        ]);

        $items = $sequence->getItems();
        $this->assertCount(3, $items);

        // Verify sequence order and timing
        $this->assertEquals('09:00', $items[0]->startTime);
        $this->assertEquals('10:00', $items[0]->endTime);

        $this->assertEquals('10:15', $items[1]->startTime); // After 15 min buffer
        $this->assertEquals('11:00', $items[1]->endTime);

        $this->assertEquals('11:10', $items[2]->startTime); // After 10 min buffer
        $this->assertEquals('11:40', $items[2]->endTime);

        // Total price
        $this->assertEquals(230.00, $sequence->totalPrice); // 100 + 80 + 50
    }

    /**
     * Test total duration calculation
     */
    public function testCalculateTotalDuration()
    {
        $serviceIds = [
            $this->services[0]->id, // 60 min + 15 buffer
            $this->services[1]->id, // 45 min + 10 buffer
            $this->services[2]->id, // 30 min (no buffer after last)
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25',
            'startTime' => '09:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'employeeId' => $this->employee->id,
        ]);

        // Total: 60 + 15 + 45 + 10 + 30 = 160 minutes
        $this->assertEquals(160, $sequence->getTotalDuration());
    }

    /**
     * Test getting first and last reservations
     */
    public function testGetFirstAndLastReservations()
    {
        $serviceIds = [
            $this->services[0]->id,
            $this->services[1]->id,
            $this->services[2]->id,
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'employeeId' => $this->employee->id,
        ]);

        $first = $sequence->getFirstReservation();
        $last = $sequence->getLastReservation();

        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertEquals(0, $first->sequenceOrder);
        $this->assertEquals(2, $last->sequenceOrder);
        $this->assertEquals($this->services[0]->id, $first->serviceId);
        $this->assertEquals($this->services[2]->id, $last->serviceId);
    }

    /**
     * Test cancelling a sequence cancels all reservations
     */
    public function testCancelSequenceCancelsAllReservations()
    {
        $serviceIds = [
            $this->services[0]->id,
            $this->services[1]->id,
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'employeeId' => $this->employee->id,
        ]);

        // Verify initial status
        $this->assertEquals(BookingSequenceRecord::STATUS_PENDING, $sequence->status);
        foreach ($sequence->getItems() as $item) {
            $this->assertEquals(ReservationRecord::STATUS_PENDING, $item->status);
        }

        // Cancel the sequence
        $result = $sequence->cancel();
        $this->assertTrue($result);

        // Reload sequence
        $sequence = BookingSequence::find()->id($sequence->id)->one();

        // Verify sequence cancelled
        $this->assertEquals(BookingSequenceRecord::STATUS_CANCELLED, $sequence->status);

        // Verify all reservations cancelled
        foreach ($sequence->getItems() as $item) {
            $this->assertEquals(ReservationRecord::STATUS_CANCELLED, $item->status);
        }
    }

    /**
     * Test confirming a sequence confirms all reservations
     */
    public function testConfirmSequenceConfirmsAllReservations()
    {
        $serviceIds = [
            $this->services[0]->id,
            $this->services[1]->id,
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'employeeId' => $this->employee->id,
        ]);

        // Confirm the sequence
        $result = $this->sequentialService->confirmSequence($sequence->id);
        $this->assertTrue($result);

        // Reload sequence
        $sequence = BookingSequence::find()->id($sequence->id)->one();

        // Verify sequence confirmed
        $this->assertEquals(BookingSequenceRecord::STATUS_CONFIRMED, $sequence->status);

        // Verify all reservations confirmed
        foreach ($sequence->getItems() as $item) {
            $this->assertEquals(ReservationRecord::STATUS_CONFIRMED, $item->status);
        }
    }

    /**
     * Test finding sequences by customer email
     */
    public function testFindSequencesByCustomerEmail()
    {
        // Create two sequences for same customer
        $email = 'repeat-customer@example.com';

        $sequence1 = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[0]->id],
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Repeat Customer',
            'customerEmail' => $email,
        ]);

        $sequence2 = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[1]->id],
            'date' => '2025-12-26',
            'startTime' => '11:00',
            'customerName' => 'Repeat Customer',
            'customerEmail' => $email,
        ]);

        // Find sequences
        $sequences = $this->sequentialService->getSequencesByCustomerEmail($email);

        $this->assertCount(2, $sequences);
        $this->assertEquals($sequence2->id, $sequences[0]->id); // Most recent first
        $this->assertEquals($sequence1->id, $sequences[1]->id);
    }

    /**
     * Test finding sequence by ID
     */
    public function testFindSequenceById()
    {
        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[0]->id],
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);

        $found = $this->sequentialService->getSequenceById($sequence->id);

        $this->assertNotNull($found);
        $this->assertEquals($sequence->id, $found->id);
        $this->assertEquals($sequence->customerEmail, $found->customerEmail);
    }

    /**
     * Test finding non-existent sequence returns null
     */
    public function testFindNonExistentSequenceReturnsNull()
    {
        $found = $this->sequentialService->getSequenceById(999999);
        $this->assertNull($found);
    }

    /**
     * Test deleting sequence deletes all reservations (cascade)
     */
    public function testDeletingSequenceDeletesReservations()
    {
        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[0]->id, $this->services[1]->id],
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);

        $reservationIds = array_map(fn($item) => $item->id, $sequence->getItems());

        // Delete sequence
        Craft::$app->elements->deleteElement($sequence);

        // Verify reservations deleted
        foreach ($reservationIds as $resId) {
            $res = Reservation::find()->id($resId)->one();
            $this->assertNull($res);
        }
    }

    /**
     * Test reservation knows it's part of a sequence
     */
    public function testReservationKnowsItsPartOfSequence()
    {
        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[0]->id],
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);

        $reservation = $sequence->getFirstReservation();

        $this->assertTrue($reservation->isPartOfSequence());
        $this->assertEquals($sequence->id, $reservation->sequenceId);

        $foundSequence = $reservation->getSequence();
        $this->assertNotNull($foundSequence);
        $this->assertEquals($sequence->id, $foundSequence->id);
    }

    /**
     * Test standalone reservation is not part of sequence
     */
    public function testStandaloneReservationIsNotPartOfSequence()
    {
        $reservation = $this->tester->createReservation([
            'serviceId' => $this->services[0]->id,
            'employeeId' => $this->employee->id,
            'bookingDate' => '2025-12-25',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ]);

        $this->assertFalse($reservation->isPartOfSequence());
        $this->assertNull($reservation->sequenceId);
        $this->assertNull($reservation->getSequence());
    }

    /**
     * Test querying sequences by status
     */
    public function testQuerySequencesByStatus()
    {
        // Create sequences with different statuses
        $pending = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[0]->id],
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Pending User',
            'customerEmail' => 'pending@example.com',
        ]);

        $confirmed = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[1]->id],
            'date' => '2025-12-26',
            'startTime' => '11:00',
            'customerName' => 'Confirmed User',
            'customerEmail' => 'confirmed@example.com',
        ]);
        $this->sequentialService->confirmSequence($confirmed->id);

        $cancelled = $this->sequentialService->createSequentialBooking([
            'serviceIds' => [$this->services[2]->id],
            'date' => '2025-12-27',
            'startTime' => '12:00',
            'customerName' => 'Cancelled User',
            'customerEmail' => 'cancelled@example.com',
        ]);
        $cancelled->cancel();

        // Query pending
        $pendingSequences = BookingSequence::find()
            ->status(BookingSequenceRecord::STATUS_PENDING)
            ->all();
        $this->assertGreaterThanOrEqual(1, count($pendingSequences));

        // Query confirmed
        $confirmedSequences = BookingSequence::find()
            ->status(BookingSequenceRecord::STATUS_CONFIRMED)
            ->all();
        $this->assertGreaterThanOrEqual(1, count($confirmedSequences));

        // Query cancelled
        $cancelledSequences = BookingSequence::find()
            ->status(BookingSequenceRecord::STATUS_CANCELLED)
            ->all();
        $this->assertGreaterThanOrEqual(1, count($cancelledSequences));
    }

    /**
     * Test transaction rollback on failure
     */
    public function testTransactionRollbackOnFailure()
    {
        // Try to create booking with non-existent service
        try {
            $this->sequentialService->createSequentialBooking([
                'serviceIds' => [999999], // Non-existent
                'date' => '2025-12-25',
                'startTime' => '10:00',
                'customerName' => 'Test User',
                'customerEmail' => 'test@example.com',
            ]);

            $this->fail('Should have thrown exception');
        } catch (\Exception $e) {
            // Expected - verify no sequence created
            $sequences = BookingSequence::find()
                ->customerEmail('test@example.com')
                ->all();

            $this->assertEmpty($sequences);
        }
    }

    /**
     * Test reservation order is preserved
     */
    public function testReservationOrderIsPreserved()
    {
        $serviceIds = [
            $this->services[2]->id, // Manicure
            $this->services[0]->id, // Massage
            $this->services[1]->id, // Facial
        ];

        $sequence = $this->sequentialService->createSequentialBooking([
            'serviceIds' => $serviceIds,
            'date' => '2025-12-25',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);

        $items = $sequence->getItems();

        // Verify order matches input
        $this->assertEquals($this->services[2]->id, $items[0]->serviceId);
        $this->assertEquals($this->services[0]->id, $items[1]->serviceId);
        $this->assertEquals($this->services[1]->id, $items[2]->serviceId);

        // Verify sequence order
        $this->assertEquals(0, $items[0]->sequenceOrder);
        $this->assertEquals(1, $items[1]->sequenceOrder);
        $this->assertEquals(2, $items[2]->sequenceOrder);
    }
}
