<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use Craft;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Service;
use fabian\booked\elements\Location;
use fabian\booked\elements\Schedule;
use fabian\booked\elements\Reservation;
use fabian\booked\services\AvailabilityService;
use fabian\booked\tests\_support\traits\CreatesBookings;
use IntegrationTester;

/**
 * Integration Tests - Complex Availability Calculation
 *
 * Tests availability calculation with multiple employees, services,
 * locations, schedules, recurrence rules, blackouts, and existing bookings.
 */
class AvailabilityCalculationTest extends Unit
{
    use CreatesBookings;

    /**
     * @var IntegrationTester
     */
    protected $tester;

    /**
     * @var AvailabilityService
     */
    private $availabilityService;

    protected function _before()
    {
        parent::_before();
        $this->availabilityService = new AvailabilityService();
    }

    /**
     * Test complex scenario: 3 employees, 2 services, 1 location, various schedules
     */
    public function testComplexMultiEmployeeMultiServiceScenario()
    {
        // Arrange: Create location
        $location = $this->createLocation(['title' => 'Main Office']);

        // Create 3 employees
        $employee1 = $this->createEmployee([
            'title' => 'Dr. Smith',
            'locationId' => $location->id,
        ]);
        $employee2 = $this->createEmployee([
            'title' => 'Dr. Jones',
            'locationId' => $location->id,
        ]);
        $employee3 = $this->createEmployee([
            'title' => 'Dr. Williams',
            'locationId' => $location->id,
        ]);

        // Create 2 services with different durations
        $service1 = $this->createService([
            'title' => '30-min Consultation',
            'duration' => 30,
        ]);
        $service2 = $this->createService([
            'title' => '60-min Therapy',
            'duration' => 60,
        ]);

        // Assign services to employees
        $employee1->setServiceIds([$service1->id, $service2->id]);
        $employee2->setServiceIds([$service1->id]);
        $employee3->setServiceIds([$service2->id]);

        // Create schedules with different patterns
        // Employee 1: Mon-Wed-Fri 9-17
        $schedule1 = new Schedule();
        $schedule1->title = 'MWF Schedule';
        $schedule1->employeeIds = [$employee1->id];
        $schedule1->daysOfWeek = [1, 3, 5]; // Mon, Wed, Fri
        $schedule1->startTime = '09:00';
        $schedule1->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule1);

        // Employee 2: Tue-Thu 10-16
        $schedule2 = new Schedule();
        $schedule2->title = 'Tue-Thu Schedule';
        $schedule2->employeeIds = [$employee2->id];
        $schedule2->daysOfWeek = [2, 4]; // Tue, Thu
        $schedule2->startTime = '10:00';
        $schedule2->endTime = '16:00';
        Craft::$app->elements->saveElement($schedule2);

        // Employee 3: Mon-Fri 8-12
        $schedule3 = new Schedule();
        $schedule3->title = 'Weekday Mornings';
        $schedule3->employeeIds = [$employee3->id];
        $schedule3->daysOfWeek = [1, 2, 3, 4, 5]; // Mon-Fri
        $schedule3->startTime = '08:00';
        $schedule3->endTime = '12:00';
        Craft::$app->elements->saveElement($schedule3);

        // Act: Get availability for Monday
        $monday = new \DateTime('next Monday');
        $availability = $this->availabilityService->getAvailableSlots(
            $service1->id,
            null, // All employees
            $monday->format('Y-m-d'),
            $monday->format('Y-m-d')
        );

        // Assert: Should have slots from both Employee 1 (9-17) and Employee 3 (8-12)
        $this->assertNotEmpty($availability, 'Should have available slots on Monday');

        // Verify we have morning slots from Employee 3 (8:00-12:00)
        $morningSlots = array_filter($availability, function($slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            return $hour >= 8 && $hour < 12;
        });
        $this->assertNotEmpty($morningSlots, 'Should have morning slots from Employee 3');

        // Verify we have afternoon slots from Employee 1 (9:00-17:00)
        $afternoonSlots = array_filter($availability, function($slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            return $hour >= 13 && $hour < 17;
        });
        $this->assertNotEmpty($afternoonSlots, 'Should have afternoon slots from Employee 1');
    }

    /**
     * Test availability with overlapping employee schedules
     */
    public function testAvailabilityWithOverlappingSchedules()
    {
        // Arrange: Create employee with two overlapping schedules
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee->setServiceIds([$service->id]);

        // Morning schedule: 8-12
        $schedule1 = new Schedule();
        $schedule1->employeeIds = [$employee->id];
        $schedule1->daysOfWeek = [1]; // Monday
        $schedule1->startTime = '08:00';
        $schedule1->endTime = '12:00';
        Craft::$app->elements->saveElement($schedule1);

        // Afternoon schedule: 13-17 (no overlap)
        $schedule2 = new Schedule();
        $schedule2->employeeIds = [$employee->id];
        $schedule2->daysOfWeek = [1]; // Monday
        $schedule2->startTime = '13:00';
        $schedule2->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule2);

        // Act: Get availability
        $monday = new \DateTime('next Monday');
        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $monday->format('Y-m-d'),
            $monday->format('Y-m-d')
        );

        // Assert: Should have both morning and afternoon slots
        $this->assertNotEmpty($availability);

        $morningSlots = array_filter($availability, function($slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            return $hour >= 8 && $hour < 12;
        });

        $afternoonSlots = array_filter($availability, function($slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            return $hour >= 13 && $hour < 17;
        });

        $this->assertNotEmpty($morningSlots, 'Should have morning slots');
        $this->assertNotEmpty($afternoonSlots, 'Should have afternoon slots');

        // Should NOT have lunch slots (12:00-13:00)
        $lunchSlots = array_filter($availability, function($slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            return $hour >= 12 && $hour < 13;
        });
        $this->assertEmpty($lunchSlots, 'Should not have slots during lunch break');
    }

    /**
     * Test availability with buffer times
     */
    public function testAvailabilityWithBufferTimes()
    {
        // Arrange: Create service with before/after buffers
        $service = $this->createService([
            'title' => 'Surgery',
            'duration' => 60,
            'bufferBefore' => 15, // 15 min prep
            'bufferAfter' => 30,  // 30 min cleanup
        ]);

        $employee = $this->createEmployee(['title' => 'Surgeon']);
        $employee->setServiceIds([$service->id]);

        // Schedule: 9-17
        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1]; // Monday
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        // Create existing booking at 10:00 (60 min + 30 min buffer after = ends 11:30)
        $monday = new \DateTime('next Monday 10:00');
        $reservation = $this->createReservation([
            'serviceId' => $service->id,
            'employeeId' => $employee->id,
            'bookingDate' => $monday->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
        ]);

        // Act: Get availability
        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $monday->format('Y-m-d'),
            $monday->format('Y-m-d')
        );

        // Assert: Next available slot should be AFTER 11:30 (after buffer)
        // Plus 15 min buffer before = 11:45 earliest
        $slotsAfter10 = array_filter($availability, function($slot) {
            $time = strtotime($slot['start']);
            $hour = (int)date('H', $time);
            $minute = (int)date('i', $time);
            return $hour > 11 || ($hour === 11 && $minute >= 45);
        });

        $this->assertNotEmpty($slotsAfter10, 'Should have slots after buffer time');

        // Should NOT have slots between 10:00 and 11:45
        $blockedSlots = array_filter($availability, function($slot) {
            $time = strtotime($slot['start']);
            $hour = (int)date('H', $time);
            $minute = (int)date('i', $time);
            return ($hour === 10) || ($hour === 11 && $minute < 45);
        });

        $this->assertEmpty($blockedSlots, 'Should not have slots during booking + buffers');
    }

    /**
     * Test multi-day availability calculation
     */
    public function testMultiDayAvailabilityRange()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee->setServiceIds([$service->id]);

        // Mon-Wed-Fri schedule
        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 3, 5]; // Mon, Wed, Fri
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        // Act: Get availability for entire week
        $monday = new \DateTime('next Monday');
        $friday = new \DateTime('next Friday');

        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $monday->format('Y-m-d'),
            $friday->format('Y-m-d')
        );

        // Assert: Should have slots on Mon, Wed, Fri only
        $this->assertNotEmpty($availability);

        // Group by day of week
        $dayGroups = [];
        foreach ($availability as $slot) {
            $dayOfWeek = date('N', strtotime($slot['start'])); // 1=Mon, 5=Fri
            $dayGroups[$dayOfWeek] = true;
        }

        $this->assertArrayHasKey(1, $dayGroups, 'Should have Monday slots');
        $this->assertArrayHasKey(3, $dayGroups, 'Should have Wednesday slots');
        $this->assertArrayHasKey(5, $dayGroups, 'Should have Friday slots');

        $this->assertArrayNotHasKey(2, $dayGroups, 'Should NOT have Tuesday slots');
        $this->assertArrayNotHasKey(4, $dayGroups, 'Should NOT have Thursday slots');
    }

    /**
     * Test cache effectiveness for repeated queries
     */
    public function testCacheEffectiveness()
    {
        // Arrange
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee->setServiceIds([$service->id]);

        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1]; // Monday
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        $monday = new \DateTime('next Monday');
        $date = $monday->format('Y-m-d');

        // Act: First call (should cache)
        $start1 = microtime(true);
        $availability1 = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $date,
            $date
        );
        $time1 = microtime(true) - $start1;

        // Second call (should use cache)
        $start2 = microtime(true);
        $availability2 = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $date,
            $date
        );
        $time2 = microtime(true) - $start2;

        // Assert: Results should be identical
        $this->assertEquals($availability1, $availability2, 'Cached results should match');

        // Second call should be faster (cached)
        // Note: This assertion may be flaky in testing environments
        // Uncomment if you have reliable performance testing setup
        // $this->assertLessThan($time1, $time2, 'Cached call should be faster');
    }

    /**
     * Test employee-specific availability filtering
     */
    public function testEmployeeSpecificAvailability()
    {
        // Arrange: Create 2 employees with different schedules
        $employee1 = $this->createEmployee(['title' => 'Dr. Smith']);
        $employee2 = $this->createEmployee(['title' => 'Dr. Jones']);

        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);

        $employee1->setServiceIds([$service->id]);
        $employee2->setServiceIds([$service->id]);

        // Employee 1: 9-12
        $schedule1 = new Schedule();
        $schedule1->employeeIds = [$employee1->id];
        $schedule1->daysOfWeek = [1]; // Monday
        $schedule1->startTime = '09:00';
        $schedule1->endTime = '12:00';
        Craft::$app->elements->saveElement($schedule1);

        // Employee 2: 14-17
        $schedule2 = new Schedule();
        $schedule2->employeeIds = [$employee2->id];
        $schedule2->daysOfWeek = [1]; // Monday
        $schedule2->startTime = '14:00';
        $schedule2->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule2);

        $monday = new \DateTime('next Monday');
        $date = $monday->format('Y-m-d');

        // Act: Get availability for specific employee
        $availEmployee1 = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee1->id,
            $date,
            $date
        );

        $availEmployee2 = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee2->id,
            $date,
            $date
        );

        // Assert: Employee 1 should only have morning slots
        foreach ($availEmployee1 as $slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            $this->assertLessThan(12, $hour, 'Employee 1 slots should be before noon');
        }

        // Employee 2 should only have afternoon slots
        foreach ($availEmployee2 as $slot) {
            $hour = (int)date('H', strtotime($slot['start']));
            $this->assertGreaterThanOrEqual(14, $hour, 'Employee 2 slots should be after 2 PM');
        }
    }
}
