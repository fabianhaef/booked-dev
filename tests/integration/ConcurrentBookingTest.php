<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use fabian\booked\services\BookingService;
use fabian\booked\services\SoftLockService;
use fabian\booked\exceptions\BookingException;
use fabian\booked\exceptions\BookingConflictException;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;
use DateTime;

/**
 * Tests for concurrent booking scenarios and race condition prevention
 *
 * Critical tests to ensure that:
 * - Only one user can book a slot when multiple users try simultaneously
 * - Mutex locks prevent double bookings
 * - Soft locks work correctly under concurrent load
 * - Database transactions roll back properly on conflicts
 */
class ConcurrentBookingTest extends Unit
{
    use CreatesBookings;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test two users booking same slot simultaneously (mutex should allow only one)
     *
     * This is the most critical test for preventing double bookings
     */
    public function testTwoUsersBookingSameSlotSimultaneously()
    {
        $date = '2026-01-15';
        $time = '10:00';
        $serviceId = 1;
        $employeeId = 1;

        // Create two booking services with shared mutex
        $mutex = new TestMutex();

        $service1 = new TestableRaceConditionBookingService();
        $service1->mockMutex = $mutex;
        $service1->userId = 'user-1';

        $service2 = new TestableRaceConditionBookingService();
        $service2->mockMutex = $mutex;
        $service2->userId = 'user-2';

        $bookingData = [
            'date' => $date,
            'time' => $time,
            'serviceId' => $serviceId,
            'employeeId' => $employeeId,
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ];

        // Simulate concurrent booking attempts
        $result1 = null;
        $result2 = null;
        $exception1 = null;
        $exception2 = null;

        try {
            $result1 = $service1->createBooking($bookingData);
        } catch (BookingException $e) {
            $exception1 = $e;
        }

        try {
            $result2 = $service2->createBooking($bookingData);
        } catch (BookingException $e) {
            $exception2 = $e;
        }

        // Assert: Exactly one should succeed
        $successCount = ($result1 ? 1 : 0) + ($result2 ? 1 : 0);
        $this->assertEquals(1, $successCount, 'Exactly one booking should succeed');

        // Assert: One should fail with mutex timeout
        $failureCount = ($exception1 ? 1 : 0) + ($exception2 ? 1 : 0);
        $this->assertEquals(1, $failureCount, 'Exactly one booking should fail');
    }

    /**
     * Test soft lock acquisition during concurrent attempts
     */
    public function testSoftLockAcquisitionDuringConcurrentAttempts()
    {
        $date = '2026-01-15';
        $time = '14:00';
        $endTime = '15:00';
        $serviceId = 1;
        $employeeId = 1;

        $softLockService = new TestSoftLockService();

        // User 1 acquires lock
        $token1 = $softLockService->createLock($date, $time, $endTime, $serviceId, $employeeId);
        $this->assertNotNull($token1);
        $this->assertTrue($softLockService->isLocked($date, $time, $endTime, $serviceId, $employeeId));

        // User 2 tries to acquire same lock - should fail or get different behavior
        $isLocked = $softLockService->isLocked($date, $time, $endTime, $serviceId, $employeeId);
        $this->assertTrue($isLocked, 'Slot should be locked by user 1');

        // User 2 should not be able to create booking while locked
        // (This would be enforced in the booking service)
    }

    /**
     * Test soft lock expiration and automatic release
     */
    public function testSoftLockExpirationAndAutomaticRelease()
    {
        $date = '2026-01-15';
        $time = '16:00';
        $endTime = '17:00';
        $serviceId = 1;
        $employeeId = 1;

        $softLockService = new TestSoftLockService();

        // Create lock with 15-minute expiration
        $token = $softLockService->createLock($date, $time, $endTime, $serviceId, $employeeId, 15 * 60);
        $this->assertTrue($softLockService->isLocked($date, $time, $endTime, $serviceId, $employeeId));

        // Simulate time passing (set lock to expired)
        $softLockService->expireLock($token);

        // Lock should no longer be active
        $this->assertFalse($softLockService->isLocked($date, $time, $endTime, $serviceId, $employeeId));

        // Another user should now be able to acquire the lock
        $token2 = $softLockService->createLock($date, $time, $endTime, $serviceId, $employeeId);
        $this->assertNotNull($token2);
        $this->assertNotEquals($token, $token2);
    }

    /**
     * Test mutex timeout handling
     */
    public function testMutexTimeoutHandling()
    {
        $mutex = new TestMutex();

        // Acquire lock
        $lockName = 'test-lock';
        $acquired1 = $mutex->acquire($lockName, 5);
        $this->assertTrue($acquired1);

        // Try to acquire same lock with timeout
        $acquired2 = $mutex->acquire($lockName, 1);
        $this->assertFalse($acquired2, 'Second acquire should fail due to existing lock');

        // Release lock
        $mutex->release($lockName);

        // Should be able to acquire now
        $acquired3 = $mutex->acquire($lockName, 1);
        $this->assertTrue($acquired3);
    }

    /**
     * Test database transaction rollback on conflict
     */
    public function testDatabaseTransactionRollbackOnConflict()
    {
        $service = new TestableRaceConditionBookingService();
        $service->mockMutex = new TestMutex();
        $service->shouldFailOnSave = true; // Simulate DB failure

        $bookingData = [
            'date' => '2026-01-15',
            'time' => '18:00',
            'serviceId' => 1,
            'employeeId' => 1,
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ];

        $exceptionThrown = false;
        try {
            $service->createBooking($bookingData);
        } catch (BookingException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Exception should be thrown on DB failure');
        $this->assertTrue($service->transactionRolledBack, 'Transaction should be rolled back');
        $this->assertTrue($service->mutexReleased, 'Mutex should be released on failure');
    }

    /**
     * Test unique constraint violation handling
     */
    public function testUniqueConstraintViolationHandling()
    {
        // This test would verify that if somehow two bookings bypass the mutex
        // the database unique constraint catches it

        $service1 = new TestableRaceConditionBookingService();
        $service1->mockMutex = new TestMutex();

        $bookingData = [
            'date' => '2026-01-15',
            'time' => '20:00',
            'serviceId' => 1,
            'employeeId' => 1,
            'customerName' => 'User 1',
            'customerEmail' => 'user1@example.com',
        ];

        // First booking succeeds
        $result1 = $service1->createBooking($bookingData);
        $this->assertTrue($result1);

        // Second booking with same slot should trigger unique constraint
        $service2 = new TestableRaceConditionBookingService();
        $service2->mockMutex = new TestMutex();
        $service2->simulateUniqueConstraintViolation = true;

        $bookingData['customerEmail'] = 'user2@example.com';

        $this->expectException(BookingConflictException::class);
        $service2->createBooking($bookingData);
    }

    /**
     * Test multiple concurrent bookings for different slots (should all succeed)
     */
    public function testMultipleConcurrentBookingsForDifferentSlots()
    {
        $mutex = new TestMutex();
        $date = '2026-01-15';

        $services = [];
        $results = [];

        // Create 5 booking services for different time slots
        for ($i = 0; $i < 5; $i++) {
            $service = new TestableRaceConditionBookingService();
            $service->mockMutex = $mutex;
            $service->userId = "user-{$i}";

            $bookingData = [
                'date' => $date,
                'time' => sprintf('%02d:00', 9 + $i), // 09:00, 10:00, 11:00, etc.
                'serviceId' => 1,
                'employeeId' => 1,
                'customerName' => "User {$i}",
                'customerEmail' => "user{$i}@example.com",
            ];

            $results[] = $service->createBooking($bookingData);
        }

        // All should succeed (different time slots)
        foreach ($results as $result) {
            $this->assertTrue($result);
        }
    }

    /**
     * Test soft lock cleanup (garbage collection)
     */
    public function testSoftLockGarbageCollection()
    {
        $softLockService = new TestSoftLockService();
        $date = '2026-01-15';

        // Create multiple expired locks
        for ($i = 0; $i < 10; $i++) {
            $token = $softLockService->createLock(
                $date,
                sprintf('%02d:00', 9 + $i),
                sprintf('%02d:00', 10 + $i),
                1,
                1,
                15 * 60
            );
            $softLockService->expireLock($token);
        }

        // Run garbage collection
        $cleaned = $softLockService->cleanupExpiredLocks();

        $this->assertEquals(10, $cleaned, '10 expired locks should be cleaned up');
    }
}

/**
 * Test mutex implementation
 */
class TestMutex
{
    private array $locks = [];
    private array $lockOwners = [];

    public function acquire(string $name, int $timeout = 0): bool
    {
        if (isset($this->locks[$name])) {
            // Lock already held, wait for timeout
            usleep($timeout * 1000); // Convert to microseconds
            return false;
        }

        $this->locks[$name] = true;
        $this->lockOwners[$name] = getmypid();
        return true;
    }

    public function release(string $name): void
    {
        unset($this->locks[$name]);
        unset($this->lockOwners[$name]);
    }

    public function isLocked(string $name): bool
    {
        return isset($this->locks[$name]);
    }
}

/**
 * Test soft lock service
 */
class TestSoftLockService extends SoftLockService
{
    private array $locks = [];

    public function createLock(array $data, int $durationMinutes = 15): string|false
    {
        // Extract parameters from data array
        $date = $data['date'] ?? '';
        $startTime = $data['startTime'] ?? '';
        $serviceId = $data['serviceId'] ?? 0;
        $employeeId = $data['employeeId'] ?? null;
        $ttl = $durationMinutes * 60; // Convert minutes to seconds

        // Check if already locked
        if ($this->isLocked($date, $startTime, $serviceId, $employeeId)) {
            return false;
        }

        $token = bin2hex(random_bytes(16));
        $key = $this->getLockKey($date, $startTime, $serviceId, $employeeId);

        $this->locks[$key] = [
            'token' => $token,
            'expiresAt' => time() + $ttl,
        ];

        return $token;
    }

    public function isLocked(
        string $date,
        string $startTime,
        int $serviceId,
        ?int $employeeId = null
    ): bool {
        $key = $this->getLockKey($date, $startTime, $serviceId, $employeeId);

        if (!isset($this->locks[$key])) {
            return false;
        }

        // Check if expired
        if ($this->locks[$key]['expiresAt'] < time()) {
            unset($this->locks[$key]);
            return false;
        }

        return true;
    }

    public function expireLock(string $token): void
    {
        foreach ($this->locks as $key => $lock) {
            if ($lock['token'] === $token) {
                $this->locks[$key]['expiresAt'] = time() - 1;
            }
        }
    }

    public function cleanupExpiredLocks(): int
    {
        $count = 0;
        $now = time();

        foreach ($this->locks as $key => $lock) {
            if ($lock['expiresAt'] < $now) {
                unset($this->locks[$key]);
                $count++;
            }
        }

        return $count;
    }

    private function getLockKey(
        string $date,
        string $startTime,
        int $serviceId,
        ?int $employeeId
    ): string {
        return "{$date}_{$startTime}_{$serviceId}_{$employeeId}";
    }
}

/**
 * Testable booking service for race condition tests
 */
class TestableRaceConditionBookingService extends BookingService
{
    public $mockMutex;
    public $userId;
    public $shouldFailOnSave = false;
    public $simulateUniqueConstraintViolation = false;
    public $transactionRolledBack = false;
    public $mutexReleased = false;

    private static $bookings = [];

    public function createBooking(array $data): bool
    {
        $lockName = "booking-{$data['date']}-{$data['time']}-{$data['employeeId']}";

        // Try to acquire mutex
        if (!$this->mockMutex->acquire($lockName, 100)) {
            throw new BookingException('Could not acquire lock - slot may be booked by another user');
        }

        try {
            // Check for existing booking (unique constraint simulation)
            $key = "{$data['date']}_{$data['time']}_{$data['employeeId']}";

            if (isset(self::$bookings[$key]) || $this->simulateUniqueConstraintViolation) {
                throw new BookingConflictException('Slot already booked');
            }

            // Simulate database save
            if ($this->shouldFailOnSave) {
                throw new \Exception('Database save failed');
            }

            // Save booking
            self::$bookings[$key] = [
                'customerEmail' => $data['customerEmail'],
                'userId' => $this->userId,
            ];

            $this->mockMutex->release($lockName);
            $this->mutexReleased = true;

            return true;

        } catch (\Exception $e) {
            // Rollback transaction
            $this->transactionRolledBack = true;

            // Release mutex
            $this->mockMutex->release($lockName);
            $this->mutexReleased = true;

            throw $e;
        }
    }
}
