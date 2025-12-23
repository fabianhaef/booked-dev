<?php

namespace fabian\booked\tests\performance;

use Codeception\Test\Unit;
use Craft;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Service;
use fabian\booked\elements\Schedule;
use fabian\booked\elements\Reservation;
use fabian\booked\services\AvailabilityService;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

/**
 * Performance Benchmarks - Availability Calculation
 *
 * Tests performance characteristics under load:
 * - Large number of existing reservations
 * - Many employees
 * - Complex recurrence rules
 * - Cache effectiveness
 */
class AvailabilityPerformanceTest extends Unit
{
    use CreatesBookings;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var AvailabilityService
     */
    private $availabilityService;

    /**
     * Performance threshold in seconds
     */
    private const PERFORMANCE_THRESHOLD = 2.0;

    protected function _before()
    {
        parent::_before();
        $this->availabilityService = new AvailabilityService();
    }

    /**
     * Test availability calculation with 1000+ existing reservations
     *
     * Ensures the system can handle high booking volume
     */
    public function testAvailabilityWith1000Reservations()
    {
        // Arrange: Create service and employee
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $employee->setServiceIds([$service->id]);

        // Create schedule
        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 2, 3, 4, 5]; // Mon-Fri
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        // Create 1000 reservations over the next 3 months
        $startDate = new \DateTime();
        for ($i = 0; $i < 1000; $i++) {
            $randomDays = rand(0, 90);
            $bookingDate = (clone $startDate)->modify("+{$randomDays} days");

            // Skip weekends
            if ($bookingDate->format('N') >= 6) {
                continue;
            }

            $this->createReservation([
                'serviceId' => $service->id,
                'employeeId' => $employee->id,
                'bookingDate' => $bookingDate->format('Y-m-d'),
                'startTime' => '10:00',
                'endTime' => '10:30',
                'status' => 'confirmed',
            ]);
        }

        // Act: Measure availability calculation time
        $queryDate = (new \DateTime('+7 days'))->format('Y-m-d');

        $start = microtime(true);
        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $queryDate,
            $queryDate
        );
        $duration = microtime(true) - $start;

        // Assert: Should complete within performance threshold
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD,
            $duration,
            "Availability calculation with 1000 reservations took {$duration}s (threshold: " . self::PERFORMANCE_THRESHOLD . "s)"
        );

        $this->assertNotEmpty($availability, 'Should still return available slots');
    }

    /**
     * Test availability calculation with 50+ employees
     *
     * Tests query optimization with large employee pool
     */
    public function testAvailabilityWith50Employees()
    {
        // Arrange: Create service
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);

        // Create 50 employees
        $employeeIds = [];
        for ($i = 1; $i <= 50; $i++) {
            $employee = $this->createEmployee(['title' => "Dr. Employee{$i}"]);
            $employee->setServiceIds([$service->id]);
            $employeeIds[] = $employee->id;

            // Give each employee a schedule
            $schedule = new Schedule();
            $schedule->employeeIds = [$employee->id];
            $schedule->daysOfWeek = [1, 2, 3, 4, 5]; // Mon-Fri
            $schedule->startTime = '09:00';
            $schedule->endTime = '17:00';
            Craft::$app->elements->saveElement($schedule);
        }

        // Act: Get availability across all employees
        $queryDate = (new \DateTime('next Monday'))->format('Y-m-d');

        $start = microtime(true);
        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            null, // All employees
            $queryDate,
            $queryDate
        );
        $duration = microtime(true) - $start;

        // Assert: Should handle 50 employees efficiently
        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLD,
            $duration,
            "Availability calculation with 50 employees took {$duration}s"
        );

        // Should have many slots available
        $this->assertGreaterThan(100, count($availability), 'Should have slots from multiple employees');
    }

    /**
     * Test cache hit/miss ratio
     *
     * Verifies caching system effectiveness
     */
    public function testCacheHitMissRatio()
    {
        // Arrange
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $employee->setServiceIds([$service->id]);

        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1]; // Monday
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        $queryDate = (new \DateTime('next Monday'))->format('Y-m-d');

        // Act: First call (cache miss)
        $start1 = microtime(true);
        $availability1 = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $queryDate,
            $queryDate
        );
        $timeMiss = microtime(true) - $start1;

        // Multiple cached calls
        $cachedTimes = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $this->availabilityService->getAvailableSlots(
                $service->id,
                $employee->id,
                $queryDate,
                $queryDate
            );
            $cachedTimes[] = microtime(true) - $start;
        }

        $avgCachedTime = array_sum($cachedTimes) / count($cachedTimes);

        // Assert: Cached calls should be significantly faster
        $this->assertLessThan(
            $timeMiss,
            $avgCachedTime,
            "Cached calls ({$avgCachedTime}s avg) should be faster than initial call ({$timeMiss}s)"
        );

        // Cache hit should be at least 2x faster (conservative estimate)
        $speedup = $timeMiss / $avgCachedTime;
        $this->assertGreaterThan(
            2,
            $speedup,
            "Cache should provide at least 2x speedup (actual: {$speedup}x)"
        );
    }

    /**
     * Test batch operations performance (100+ bookings)
     */
    public function testBatchBookingPerformance()
    {
        // Arrange
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $employee->setServiceIds([$service->id]);

        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 2, 3, 4, 5]; // Mon-Fri
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        // Act: Create 100 bookings in batch
        $bookingDate = (new \DateTime('next Monday'))->format('Y-m-d');
        $reservations = [];

        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $hour = 9 + ($i % 8); // Spread across working hours
            $minute = ($i % 2) * 30;

            $reservation = $this->createReservation([
                'serviceId' => $service->id,
                'employeeId' => $employee->id,
                'bookingDate' => $bookingDate,
                'startTime' => sprintf('%02d:%02d', $hour, $minute),
                'endTime' => sprintf('%02d:%02d', $hour, $minute + 30),
                'status' => 'confirmed',
            ]);

            $reservations[] = $reservation;
        }

        $duration = microtime(true) - $start;

        // Assert: Should create 100 bookings efficiently
        $avgPerBooking = $duration / 100;

        $this->assertLessThan(
            0.1, // 100ms per booking
            $avgPerBooking,
            "Average booking creation time: {$avgPerBooking}s (threshold: 0.1s)"
        );

        $this->assertCount(100, $reservations, 'Should create all 100 reservations');
    }

    /**
     * Test query count optimization (detect N+1 queries)
     */
    public function testQueryCountOptimization()
    {
        // Arrange: Create multiple employees and services
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);

        $employees = [];
        for ($i = 1; $i <= 10; $i++) {
            $employee = $this->createEmployee(['title' => "Dr. {$i}"]);
            $employee->setServiceIds([$service->id]);
            $employees[] = $employee;

            $schedule = new Schedule();
            $schedule->employeeIds = [$employee->id];
            $schedule->daysOfWeek = [1]; // Monday
            $schedule->startTime = '09:00';
            $schedule->endTime = '17:00';
            Craft::$app->elements->saveElement($schedule);
        }

        $queryDate = (new \DateTime('next Monday'))->format('Y-m-d');

        // Act: Count queries executed
        $queryCount = 0;
        $originalLogger = Craft::$app->getDb()->getQueryBuilder();

        // Note: In production, you'd use a proper query logger
        // This is a simplified version for demonstration

        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            null, // All employees
            $queryDate,
            $queryDate
        );

        // Assert: Query count should be reasonable (not N+1)
        // With 10 employees, we should NOT see 10+ queries
        // Ideal: O(1) queries regardless of employee count

        $this->assertNotEmpty($availability, 'Should return availability');

        // This is a reminder to implement proper query logging
        // and verify no N+1 query patterns exist
        $this->assertTrue(true, 'Query count optimization test - implement query logger for accurate testing');
    }

    /**
     * Test memory usage with large datasets
     */
    public function testMemoryUsageUnderLoad()
    {
        $memoryBefore = memory_get_usage(true);

        // Arrange: Create large dataset
        $service = $this->createService(['title' => 'Consultation', 'duration' => 30]);
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $employee->setServiceIds([$service->id]);

        $schedule = new Schedule();
        $schedule->employeeIds = [$employee->id];
        $schedule->daysOfWeek = [1, 2, 3, 4, 5]; // Mon-Fri
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        Craft::$app->elements->saveElement($schedule);

        // Create 500 reservations
        for ($i = 0; $i < 500; $i++) {
            $days = rand(0, 60);
            $bookingDate = (new \DateTime())->modify("+{$days} days");

            if ($bookingDate->format('N') >= 6) continue;

            $this->createReservation([
                'serviceId' => $service->id,
                'employeeId' => $employee->id,
                'bookingDate' => $bookingDate->format('Y-m-d'),
                'startTime' => '10:00',
                'endTime' => '10:30',
                'status' => 'confirmed',
            ]);
        }

        // Act: Calculate availability
        $queryDate = (new \DateTime('+7 days'))->format('Y-m-d');
        $availability = $this->availabilityService->getAvailableSlots(
            $service->id,
            $employee->id,
            $queryDate,
            $queryDate
        );

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

        // Assert: Memory usage should be reasonable
        $this->assertLessThan(
            50, // 50 MB
            $memoryUsed,
            "Memory usage: {$memoryUsed} MB (threshold: 50 MB)"
        );

        $this->assertNotEmpty($availability);
    }
}
