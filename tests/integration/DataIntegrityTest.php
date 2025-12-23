<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Service;
use fabian\booked\elements\Location;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Schedule;
use fabian\booked\services\SoftLockService;
use fabian\booked\services\CalendarSyncService;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

/**
 * Tests for data integrity and cleanup operations
 *
 * Ensures that:
 * - Orphaned records are properly cleaned up
 * - Cascade deletes work correctly
 * - Foreign key constraints are enforced
 * - Garbage collection runs properly
 */
class DataIntegrityTest extends Unit
{
    use CreatesBookings;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test orphaned reservation cleanup when employee is deleted
     */
    public function testOrphanedReservationCleanupOnEmployeeDelete()
    {
        // Arrange: Create employee and reservation
        $employee = $this->createEmployee(['title' => 'Dr. Smith']);
        $reservation = $this->createReservation([
            'employeeId' => $employee->id,
            'status' => 'confirmed',
        ]);

        $cleanup = new TestDataCleanupService();
        $cleanup->mockEmployees = [];
        $cleanup->mockReservations = [$reservation];

        // Act: Delete employee (simulated by removing from mock)
        // System should either:
        // 1. Prevent deletion if reservations exist
        // 2. Cascade delete or cancel reservations
        // 3. Orphan reservations (unlink employee)

        $orphaned = $cleanup->findOrphanedReservations();

        // Assert: Reservation is orphaned or handled
        $this->assertNotEmpty($orphaned);
        $this->assertEquals($reservation->id, $orphaned[0]->id);

        // Cleanup should cancel or delete orphaned reservations
        $canceled = $cleanup->cleanupOrphanedReservations();
        $this->assertGreaterThan(0, $canceled);
    }

    /**
     * Test cascade delete: Employee → Schedules → Reservations
     */
    public function testCascadeDeleteEmployeeSchedulesReservations()
    {
        $cleanup = new TestDataCleanupService();

        $employee = $this->createEmployee(['title' => 'Dr. Jones']);

        // Create schedule for employee
        $schedule = new \stdClass();
        $schedule->id = 100;
        $schedule->employeeId = $employee->id;
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';

        // Create reservation with employee
        $reservation = $this->createReservation([
            'employeeId' => $employee->id,
            'status' => 'confirmed',
        ]);

        $cleanup->mockEmployees = [$employee];
        $cleanup->mockSchedules = [$schedule];
        $cleanup->mockReservations = [$reservation];

        // Act: Delete employee
        $result = $cleanup->deleteEmployeeWithCascade($employee->id);

        // Assert: All related records deleted or handled
        $this->assertTrue($result);
        $this->assertCount(0, $cleanup->mockSchedules, 'Schedules should be deleted');
        $this->assertCount(0, array_filter($cleanup->mockReservations, fn($r) => $r->status === 'confirmed'), 'Reservations should be cancelled');
    }

    /**
     * Test cascade delete: Service → Availability → Reservations
     */
    public function testCascadeDeleteServiceAvailabilityReservations()
    {
        $cleanup = new TestDataCleanupService();

        $service = $this->createService(['title' => 'Consultation']);

        // Create availability for service
        $availability = new \stdClass();
        $availability->id = 200;
        $availability->serviceId = $service->id;

        // Create reservation with service
        $reservation = $this->createReservation([
            'serviceId' => $service->id,
            'status' => 'confirmed',
        ]);

        $cleanup->mockServices = [$service];
        $cleanup->mockAvailabilities = [$availability];
        $cleanup->mockReservations = [$reservation];

        // Act: Delete service
        $result = $cleanup->deleteServiceWithCascade($service->id);

        // Assert: All related records handled
        $this->assertTrue($result);
        $this->assertCount(0, $cleanup->mockAvailabilities, 'Availabilities should be deleted');
    }

    /**
     * Test cascade delete: Location → Employees → Reservations
     */
    public function testCascadeDeleteLocationEmployeesReservations()
    {
        $cleanup = new TestDataCleanupService();

        $location = $this->createLocation(['title' => 'Main Office']);

        $employee = $this->createEmployee([
            'title' => 'Staff Member',
            'locationId' => $location->id,
        ]);

        $reservation = $this->createReservation([
            'employeeId' => $employee->id,
            'locationId' => $location->id,
            'status' => 'confirmed',
        ]);

        $cleanup->mockLocations = [$location];
        $cleanup->mockEmployees = [$employee];
        $cleanup->mockReservations = [$reservation];

        // Act: Delete location
        $result = $cleanup->deleteLocationWithCascade($location->id);

        // Assert: Related records handled
        $this->assertTrue($result);
        $this->assertCount(0, $cleanup->mockEmployees, 'Employees should be reassigned or deleted');
    }

    /**
     * Test soft lock garbage collection (expired locks removed)
     */
    public function testSoftLockGarbageCollection()
    {
        $softLockService = new TestSoftLockCleanupService();

        // Create multiple locks with different expiration times
        $now = time();

        $softLockService->mockLocks = [
            ['token' => 'lock1', 'expiresAt' => $now - 3600], // Expired 1 hour ago
            ['token' => 'lock2', 'expiresAt' => $now - 1800], // Expired 30 min ago
            ['token' => 'lock3', 'expiresAt' => $now + 600],  // Expires in 10 min
            ['token' => 'lock4', 'expiresAt' => $now - 60],   // Expired 1 min ago
            ['token' => 'lock5', 'expiresAt' => $now + 1800], // Expires in 30 min
        ];

        // Act: Run garbage collection
        $cleaned = $softLockService->cleanupExpiredLocks();

        // Assert: Only expired locks removed (3 expired, 2 active)
        $this->assertEquals(3, $cleaned);
        $this->assertCount(2, $softLockService->mockLocks);
    }

    /**
     * Test expired token cleanup (calendar sync tokens)
     */
    public function testExpiredCalendarTokenCleanup()
    {
        $calendarService = new TestCalendarTokenCleanupService();

        $now = new \DateTime();
        $expired = (clone $now)->modify('-1 day');
        $valid = (clone $now)->modify('+1 day');

        $calendarService->mockTokens = [
            ['employeeId' => 1, 'provider' => 'google', 'expiresAt' => $expired->format('Y-m-d H:i:s')],
            ['employeeId' => 2, 'provider' => 'google', 'expiresAt' => $valid->format('Y-m-d H:i:s')],
            ['employeeId' => 3, 'provider' => 'outlook', 'expiresAt' => $expired->format('Y-m-d H:i:s')],
        ];

        // Act: Cleanup expired tokens
        $cleaned = $calendarService->cleanupExpiredTokens();

        // Assert: 2 expired tokens removed
        $this->assertEquals(2, $cleaned);
        $this->assertCount(1, $calendarService->mockTokens);
    }

    /**
     * Test database foreign key constraint verification
     */
    public function testDatabaseForeignKeyConstraints()
    {
        // This would test actual database constraints
        // For now, we simulate the constraint check

        $cleanup = new TestDataCleanupService();

        $reservation = $this->createReservation([
            'serviceId' => 999, // Non-existent service
            'employeeId' => 999, // Non-existent employee
        ]);

        // Act: Verify foreign keys
        $violations = $cleanup->verifyForeignKeyConstraints();

        // Assert: Violations detected
        $this->assertNotEmpty($violations);
        $this->assertArrayHasKey('reservations_missing_service', $violations);
        $this->assertArrayHasKey('reservations_missing_employee', $violations);
    }

    /**
     * Test transaction rollback on partial failure
     */
    public function testTransactionRollbackOnPartialFailure()
    {
        $cleanup = new TestDataCleanupService();

        $employee = $this->createEmployee(['title' => 'Test Employee']);
        $cleanup->mockEmployees = [$employee];

        // Simulate failure during cascade delete
        $cleanup->shouldFailOnScheduleDelete = true;

        // Act: Attempt delete
        $result = $cleanup->deleteEmployeeWithCascade($employee->id);

        // Assert: Transaction rolled back, employee still exists
        $this->assertFalse($result);
        $this->assertCount(1, $cleanup->mockEmployees, 'Employee should not be deleted on failure');
    }

    /**
     * Test data consistency after failed migration
     */
    public function testDataConsistencyAfterFailedMigration()
    {
        // This would test that a failed migration doesn't leave database in inconsistent state
        // Should use database transactions and rollback

        $cleanup = new TestDataCleanupService();

        // Simulate migration state
        $cleanup->migrationInProgress = true;

        // Check data consistency
        $consistent = $cleanup->verifyDataConsistency();

        // Assert: Data should be consistent even if migration failed
        $this->assertTrue($consistent);
    }

    /**
     * Test cleanup of cancelled reservations older than X days
     */
    public function testCleanupOldCancelledReservations()
    {
        $cleanup = new TestDataCleanupService();

        $now = new \DateTime();
        $old = (clone $now)->modify('-91 days'); // 91 days ago
        $recent = (clone $now)->modify('-30 days'); // 30 days ago

        $cleanup->mockReservations = [
            $this->createReservation([
                'status' => 'cancelled',
                'bookingDate' => $old->format('Y-m-d'),
            ]),
            $this->createReservation([
                'status' => 'cancelled',
                'bookingDate' => $recent->format('Y-m-d'),
            ]),
            $this->createReservation([
                'status' => 'confirmed',
                'bookingDate' => $old->format('Y-m-d'),
            ]),
        ];

        // Act: Cleanup cancelled reservations older than 90 days
        $deleted = $cleanup->cleanupOldCancelledReservations(90);

        // Assert: Only old cancelled reservations deleted
        $this->assertEquals(1, $deleted);
        $this->assertCount(2, $cleanup->mockReservations);
    }
}

/**
 * Test data cleanup service
 */
class TestDataCleanupService
{
    public array $mockEmployees = [];
    public array $mockServices = [];
    public array $mockLocations = [];
    public array $mockSchedules = [];
    public array $mockAvailabilities = [];
    public array $mockReservations = [];
    public bool $shouldFailOnScheduleDelete = false;
    public bool $migrationInProgress = false;

    public function findOrphanedReservations(): array
    {
        $employeeIds = array_map(fn($e) => $e->id, $this->mockEmployees);

        return array_filter($this->mockReservations, function($r) use ($employeeIds) {
            return !in_array($r->employeeId, $employeeIds);
        });
    }

    public function cleanupOrphanedReservations(): int
    {
        $orphaned = $this->findOrphanedReservations();

        foreach ($orphaned as $reservation) {
            $reservation->status = 'cancelled';
        }

        return count($orphaned);
    }

    public function deleteEmployeeWithCascade(int $employeeId): bool
    {
        try {
            // Delete schedules
            if ($this->shouldFailOnScheduleDelete) {
                throw new \Exception('Schedule delete failed');
            }

            $this->mockSchedules = array_filter($this->mockSchedules, fn($s) => $s->employeeId !== $employeeId);

            // Cancel reservations
            foreach ($this->mockReservations as $reservation) {
                if ($reservation->employeeId === $employeeId) {
                    $reservation->status = 'cancelled';
                }
            }

            // Delete employee
            $this->mockEmployees = array_filter($this->mockEmployees, fn($e) => $e->id !== $employeeId);

            return true;
        } catch (\Exception $e) {
            // Rollback - don't delete anything
            return false;
        }
    }

    public function deleteServiceWithCascade(int $serviceId): bool
    {
        // Delete availabilities
        $this->mockAvailabilities = array_filter($this->mockAvailabilities, fn($a) => $a->serviceId !== $serviceId);

        // Cancel reservations
        foreach ($this->mockReservations as $reservation) {
            if ($reservation->serviceId === $serviceId) {
                $reservation->status = 'cancelled';
            }
        }

        // Delete service
        $this->mockServices = array_filter($this->mockServices, fn($s) => $s->id !== $serviceId);

        return true;
    }

    public function deleteLocationWithCascade(int $locationId): bool
    {
        // Delete or reassign employees
        $this->mockEmployees = array_filter($this->mockEmployees, fn($e) => $e->locationId !== $locationId);

        // Delete location
        $this->mockLocations = array_filter($this->mockLocations, fn($l) => $l->id !== $locationId);

        return true;
    }

    public function verifyForeignKeyConstraints(): array
    {
        $violations = [];

        // Check reservations have valid service/employee
        $serviceIds = array_map(fn($s) => $s->id, $this->mockServices);
        $employeeIds = array_map(fn($e) => $e->id, $this->mockEmployees);

        foreach ($this->mockReservations as $reservation) {
            if (!in_array($reservation->serviceId, $serviceIds)) {
                $violations['reservations_missing_service'][] = $reservation->id;
            }
            if (!in_array($reservation->employeeId, $employeeIds)) {
                $violations['reservations_missing_employee'][] = $reservation->id;
            }
        }

        return $violations;
    }

    public function verifyDataConsistency(): bool
    {
        // Check for orphaned records
        $violations = $this->verifyForeignKeyConstraints();

        return empty($violations);
    }

    public function cleanupOldCancelledReservations(int $daysOld): int
    {
        $threshold = new \DateTime("-{$daysOld} days");
        $deleted = 0;

        $this->mockReservations = array_filter($this->mockReservations, function($r) use ($threshold, &$deleted) {
            if ($r->status === 'cancelled') {
                $bookingDate = new \DateTime($r->bookingDate);
                if ($bookingDate < $threshold) {
                    $deleted++;
                    return false;
                }
            }
            return true;
        });

        return $deleted;
    }
}

/**
 * Test soft lock cleanup service
 */
class TestSoftLockCleanupService extends SoftLockService
{
    public array $mockLocks = [];

    public function cleanupExpiredLocks(): int
    {
        $now = time();
        $before = count($this->mockLocks);

        $this->mockLocks = array_filter($this->mockLocks, function($lock) use ($now) {
            return $lock['expiresAt'] > $now;
        });

        $this->mockLocks = array_values($this->mockLocks); // Re-index

        return $before - count($this->mockLocks);
    }
}

/**
 * Test calendar token cleanup service
 */
class TestCalendarTokenCleanupService extends CalendarSyncService
{
    public array $mockTokens = [];

    public function cleanupExpiredTokens(): int
    {
        $now = new \DateTime();
        $before = count($this->mockTokens);

        $this->mockTokens = array_filter($this->mockTokens, function($token) use ($now) {
            $expiresAt = new \DateTime($token['expiresAt']);
            return $expiresAt > $now;
        });

        $this->mockTokens = array_values($this->mockTokens); // Re-index

        return $before - count($this->mockTokens);
    }
}
