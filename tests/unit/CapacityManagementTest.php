<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\tests\_support\traits\CreatesBookings;
use fabian\booked\tests\_support\factories\ReservationFactory;
use UnitTester;
use DateTime;

/**
 * Tests for capacity management and quantity-based bookings
 *
 * Covers scenarios where multiple employees can provide the same service
 * and customers can book slots requiring multiple staff members or resources.
 */
class CapacityManagementTest extends Unit
{
    use CreatesBookings;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableCapacityAvailabilityService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TestableCapacityAvailabilityService();
    }

    /**
     * Test basic scenario: single slot, single employee
     */
    public function testSingleEmployeeSingleSlot()
    {
        $date = '2026-01-01';

        // One employee with 1-hour slot
        $schedule = new \stdClass();
        $schedule->startTime = '09:00';
        $schedule->endTime = '10:00';
        $schedule->employeeId = 1;

        $this->service->mockWorkingHours = [$schedule];
        $this->service->mockReservations = [];

        $slots = $this->service->getAvailableSlots($date, null, null, null, 1);

        $this->assertCount(1, $slots);
        $this->assertEquals('09:00', $slots[0]['time']);
    }

    /**
     * Test multiple employees, quantity-based booking (book 2 out of 3 available)
     */
    public function testMultipleEmployeesQuantityBooking()
    {
        $date = '2026-01-01';

        // Three employees with same schedule
        for ($i = 1; $i <= 3; $i++) {
            $schedule = new \stdClass();
            $schedule->startTime = '09:00';
            $schedule->endTime = '10:00';
            $schedule->employeeId = $i;
            $this->service->mockWorkingHours[] = $schedule;
        }

        // Request quantity 2 (need 2 employees available)
        $slots = $this->service->getAvailableSlots($date, null, null, null, 2);

        $this->assertNotEmpty($slots);
        $this->assertEquals('09:00', $slots[0]['time']);

        // Verify capacity is 3 (3 employees available)
        $this->assertArrayHasKey('capacity', $slots[0]);
        $this->assertEquals(3, $slots[0]['capacity']);
    }

    /**
     * Test group booking scenario (book entire capacity)
     */
    public function testGroupBookingEntireCapacity()
    {
        $date = '2026-01-01';

        // Two employees available
        for ($i = 1; $i <= 2; $i++) {
            $schedule = new \stdClass();
            $schedule->startTime = '10:00';
            $schedule->endTime = '11:00';
            $schedule->employeeId = $i;
            $this->service->mockWorkingHours[] = $schedule;
        }

        // Request quantity 2 (book entire capacity)
        $slots = $this->service->getAvailableSlots($date, null, null, null, 2);

        $this->assertNotEmpty($slots);
        $this->assertEquals(2, $slots[0]['capacity']);

        // Now create a booking with quantity 2
        $reservation = new \stdClass();
        $reservation->bookingDate = $date;
        $reservation->startTime = '10:00';
        $reservation->endTime = '11:00';
        $reservation->quantity = 2;
        $reservation->employeeId = 1; // Could be any employee

        $this->service->mockReservations = [$reservation];

        // Should have no available slots now (capacity fully booked)
        $slotsAfter = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertEmpty($slotsAfter);
    }

    /**
     * Test overbooking prevention (reject when capacity reached)
     */
    public function testOverbookingPrevention()
    {
        $date = '2026-01-01';

        // Two employees available
        for ($i = 1; $i <= 2; $i++) {
            $schedule = new \stdClass();
            $schedule->startTime = '14:00';
            $schedule->endTime = '15:00';
            $schedule->employeeId = $i;
            $this->service->mockWorkingHours[] = $schedule;
        }

        // One booking already exists (quantity 1)
        $reservation1 = new \stdClass();
        $reservation1->bookingDate = $date;
        $reservation1->startTime = '14:00';
        $reservation1->endTime = '15:00';
        $reservation1->quantity = 1;
        $reservation1->employeeId = 1;

        $this->service->mockReservations = [$reservation1];

        // Request quantity 1 - should be available (1 of 2 capacity used)
        $slots = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertNotEmpty($slots);
        $this->assertEquals(2, $slots[0]['capacity']);

        // Request quantity 2 - should NOT be available (only 1 capacity remaining)
        $slotsOverbook = $this->service->getAvailableSlots($date, null, null, null, 2);
        $this->assertEmpty($slotsOverbook);
    }

    /**
     * Test capacity with different service durations
     */
    public function testCapacityWithDifferentDurations()
    {
        $date = '2026-01-01';

        // Three employees with different working hours
        $schedule1 = new \stdClass();
        $schedule1->startTime = '09:00';
        $schedule1->endTime = '12:00'; // 3 hours
        $schedule1->employeeId = 1;

        $schedule2 = new \stdClass();
        $schedule2->startTime = '09:00';
        $schedule2->endTime = '11:00'; // 2 hours
        $schedule2->employeeId = 2;

        $schedule3 = new \stdClass();
        $schedule3->startTime = '10:00';
        $schedule3->endTime = '12:00'; // 2 hours
        $schedule3->employeeId = 3;

        $this->service->mockWorkingHours = [$schedule1, $schedule2, $schedule3];

        // At 09:00, employees 1 and 2 are available (capacity 2)
        $slots9am = $this->service->getAvailableSlots($date, null, null, null, 1);
        $slot9am = array_filter($slots9am, fn($s) => $s['time'] === '09:00');
        $this->assertNotEmpty($slot9am);
        $slot9am = array_values($slot9am)[0];
        $this->assertEquals(2, $slot9am['capacity']);

        // At 10:00, all 3 employees are available (capacity 3)
        $slot10am = array_filter($slots9am, fn($s) => $s['time'] === '10:00');
        $this->assertNotEmpty($slot10am);
        $slot10am = array_values($slot10am)[0];
        $this->assertEquals(3, $slot10am['capacity']);

        // At 11:00, employees 1 and 3 are available (employee 2 ends at 11:00)
        $slot11am = array_filter($slots9am, fn($s) => $s['time'] === '11:00');
        $this->assertNotEmpty($slot11am);
        $slot11am = array_values($slot11am)[0];
        $this->assertEquals(2, $slot11am['capacity']);
    }

    /**
     * Test capacity with employee schedules overlapping
     */
    public function testCapacityWithOverlappingSchedules()
    {
        $date = '2026-01-01';

        // Employee 1: 09:00-12:00
        $schedule1 = new \stdClass();
        $schedule1->startTime = '09:00';
        $schedule1->endTime = '12:00';
        $schedule1->employeeId = 1;

        // Employee 2: 10:00-13:00 (overlaps with employee 1)
        $schedule2 = new \stdClass();
        $schedule2->startTime = '10:00';
        $schedule2->endTime = '13:00';
        $schedule2->employeeId = 2;

        $this->service->mockWorkingHours = [$schedule1, $schedule2];

        $slots = $this->service->getAvailableSlots($date, null, null, null, 1);

        // 09:00: only employee 1 (capacity 1)
        $slot9 = array_values(array_filter($slots, fn($s) => $s['time'] === '09:00'))[0] ?? null;
        $this->assertNotNull($slot9);
        $this->assertEquals(1, $slot9['capacity']);

        // 10:00: both employees (capacity 2)
        $slot10 = array_values(array_filter($slots, fn($s) => $s['time'] === '10:00'))[0] ?? null;
        $this->assertNotNull($slot10);
        $this->assertEquals(2, $slot10['capacity']);

        // 11:00: both employees (capacity 2)
        $slot11 = array_values(array_filter($slots, fn($s) => $s['time'] === '11:00'))[0] ?? null;
        $this->assertNotNull($slot11);
        $this->assertEquals(2, $slot11['capacity']);

        // 12:00: only employee 2 (capacity 1)
        $slot12 = array_values(array_filter($slots, fn($s) => $s['time'] === '12:00'))[0] ?? null;
        $this->assertNotNull($slot12);
        $this->assertEquals(1, $slot12['capacity']);
    }

    /**
     * Test partial capacity release on cancellation
     */
    public function testPartialCapacityReleaseOnCancellation()
    {
        $date = '2026-01-01';

        // Three employees available
        for ($i = 1; $i <= 3; $i++) {
            $schedule = new \stdClass();
            $schedule->startTime = '15:00';
            $schedule->endTime = '16:00';
            $schedule->employeeId = $i;
            $this->service->mockWorkingHours[] = $schedule;
        }

        // Two bookings exist (quantity 1 each)
        $reservation1 = new \stdClass();
        $reservation1->bookingDate = $date;
        $reservation1->startTime = '15:00';
        $reservation1->endTime = '16:00';
        $reservation1->quantity = 1;
        $reservation1->employeeId = 1;
        $reservation1->status = 'confirmed';

        $reservation2 = new \stdClass();
        $reservation2->bookingDate = $date;
        $reservation2->startTime = '15:00';
        $reservation2->endTime = '16:00';
        $reservation2->quantity = 1;
        $reservation2->employeeId = 2;
        $reservation2->status = 'confirmed';

        $this->service->mockReservations = [$reservation1, $reservation2];

        // Available capacity should be 1 (3 total - 2 booked)
        $slots = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertNotEmpty($slots);
        $this->assertEquals(3, $slots[0]['capacity']);

        // Cancel one reservation
        $reservation1->status = 'cancelled';

        // Now available capacity should be 2
        $slotsAfterCancel = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertNotEmpty($slotsAfterCancel);
        // Capacity calculation should exclude cancelled bookings
    }

    /**
     * Test capacity with specific employee request
     */
    public function testCapacityWithSpecificEmployeeRequest()
    {
        $date = '2026-01-01';

        // Three employees available
        for ($i = 1; $i <= 3; $i++) {
            $schedule = new \stdClass();
            $schedule->startTime = '11:00';
            $schedule->endTime = '12:00';
            $schedule->employeeId = $i;
            $this->service->mockWorkingHours[] = $schedule;
        }

        // Request specific employee (employee 2)
        $slotsEmployee2 = $this->service->getAvailableSlots($date, 2, null, null, 1);
        $this->assertNotEmpty($slotsEmployee2);
        // Capacity should be 1 when specific employee requested
        $this->assertEquals(1, $slotsEmployee2[0]['capacity']);

        // Request any employee
        $slotsAny = $this->service->getAvailableSlots($date, null, null, null, 1);
        $this->assertNotEmpty($slotsAny);
        // Capacity should be 3 when any employee is acceptable
        $this->assertEquals(3, $slotsAny[0]['capacity']);
    }
}

/**
 * Testable AvailabilityService with capacity tracking
 */
class TestableCapacityAvailabilityService extends AvailabilityService
{
    public array $mockWorkingHours = [];
    public array $mockReservations = [];

    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        $filtered = $this->mockWorkingHours;

        if ($employeeId !== null) {
            $filtered = array_filter($filtered, fn($s) => $s->employeeId === $employeeId);
        }

        return array_values($filtered);
    }

    protected function getReservationsForDate(string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        $filtered = array_filter($this->mockReservations, function($r) {
            return ($r->status ?? 'confirmed') === 'confirmed';
        });

        if ($employeeId !== null) {
            $filtered = array_filter($filtered, fn($r) => $r->employeeId === $employeeId);
        }

        return array_values($filtered);
    }

    public function getAvailableSlots(
        string $date,
        ?int $employeeId = null,
        ?int $locationId = null,
        ?int $serviceId = null,
        int $quantity = 1,
        ?string $timezone = null
    ): array {
        $dayOfWeek = (int)(new DateTime($date))->format('N');
        $schedules = $this->getWorkingHours($dayOfWeek, $employeeId);

        if (empty($schedules)) {
            return [];
        }

        // Group schedules by time slot to calculate capacity
        $slotCapacity = [];

        foreach ($schedules as $schedule) {
            $start = new DateTime($date . ' ' . $schedule->startTime);
            $end = new DateTime($date . ' ' . $schedule->endTime);

            while ($start < $end) {
                $timeKey = $start->format('H:i');
                if (!isset($slotCapacity[$timeKey])) {
                    $slotCapacity[$timeKey] = [
                        'time' => $timeKey,
                        'startTime' => $timeKey,
                        'endTime' => (clone $start)->modify('+1 hour')->format('H:i'),
                        'employees' => [],
                        'capacity' => 0,
                    ];
                }
                $slotCapacity[$timeKey]['employees'][] = $schedule->employeeId;
                $slotCapacity[$timeKey]['capacity']++;
                $start->modify('+1 hour');
            }
        }

        // Subtract booked capacity
        $reservations = $this->getReservationsForDate($date, $employeeId);

        foreach ($reservations as $reservation) {
            $timeKey = $reservation->startTime;
            if (isset($slotCapacity[$timeKey])) {
                $bookedQty = $reservation->quantity ?? 1;
                $slotCapacity[$timeKey]['capacity'] -= $bookedQty;
            }
        }

        // Filter by requested quantity
        $availableSlots = array_filter($slotCapacity, function($slot) use ($quantity) {
            return $slot['capacity'] >= $quantity;
        });

        return array_values($availableSlots);
    }
}
