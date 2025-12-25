<?php

namespace fabian\booked\tests\_support\Mocks;

use fabian\booked\services\RecurrenceService;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\BookingService;

/**
 * Factory for creating consistent test mocks across all tests
 *
 * This centralizes mock creation to:
 * - Reduce code duplication
 * - Ensure consistency across test files
 * - Make it easy to update all mocks when services change
 */
class ServiceMockFactory
{
    /**
     * Create a testable RecurrenceService with common methods implemented
     */
    public static function createRecurrenceService(array $occurrences = []): RecurrenceService
    {
        return new class($occurrences) extends RecurrenceService {
            private array $mockOccurrences;

            public function __construct(array $occurrences = [])
            {
                $this->mockOccurrences = $occurrences;
                // Don't call parent::__construct() to avoid Craft dependencies
            }

            public function expandRecurrence(
                string $rrule,
                \DateTime $start,
                ?\DateTime $end = null,
                int $limit = 100
            ): array {
                // Return mock occurrences or generate based on parameters
                if (!empty($this->mockOccurrences)) {
                    return $this->mockOccurrences;
                }

                // Simple expansion for common patterns
                if (str_contains($rrule, 'FREQ=DAILY')) {
                    return $this->expandDaily($start, $end, $limit);
                } elseif (str_contains($rrule, 'FREQ=WEEKLY')) {
                    return $this->expandWeekly($start, $end, $limit, $rrule);
                } elseif (str_contains($rrule, 'FREQ=MONTHLY')) {
                    return $this->expandMonthly($start, $end, $limit, $rrule);
                } elseif (str_contains($rrule, 'FREQ=YEARLY')) {
                    return $this->expandYearly($start, $end, $limit, $rrule);
                }

                return [];
            }

            private function expandDaily(\DateTime $start, ?\DateTime $end, int $limit): array
            {
                $occurrences = [];
                $current = clone $start;
                $maxDate = $end ?? (clone $start)->modify('+1 year');

                for ($i = 0; $i < $limit && $current <= $maxDate; $i++) {
                    $occurrences[] = ['date' => clone $current, 'type' => 'occurrence'];
                    $current->modify('+1 day');
                }

                return $occurrences;
            }

            private function expandWeekly(\DateTime $start, ?\DateTime $end, int $limit, string $rrule): array
            {
                $occurrences = [];
                $current = clone $start;
                $maxDate = $end ?? (clone $start)->modify('+1 year');

                // Parse INTERVAL if present
                $interval = 1;
                if (preg_match('/INTERVAL=(\d+)/', $rrule, $matches)) {
                    $interval = (int)$matches[1];
                }

                for ($i = 0; $i < $limit && $current <= $maxDate; $i++) {
                    $occurrences[] = ['date' => clone $current, 'type' => 'occurrence'];
                    $current->modify("+{$interval} week");
                }

                return $occurrences;
            }

            private function expandMonthly(\DateTime $start, ?\DateTime $end, int $limit, string $rrule): array
            {
                $occurrences = [];
                $current = clone $start;
                $maxDate = $end ?? (clone $start)->modify('+1 year');

                // Parse BYMONTHDAY if present
                $byMonthDays = [];
                if (preg_match('/BYMONTHDAY=([0-9,]+)/', $rrule, $matches)) {
                    $byMonthDays = array_map('intval', explode(',', $matches[1]));
                }

                // Parse INTERVAL if present
                $interval = 1;
                if (preg_match('/INTERVAL=(\d+)/', $rrule, $matches)) {
                    $interval = (int)$matches[1];
                }

                if (!empty($byMonthDays)) {
                    // Specific days of month
                    $monthCurrent = clone $start;
                    $monthCurrent->setDate((int)$monthCurrent->format('Y'), (int)$monthCurrent->format('m'), 1);

                    while (count($occurrences) < $limit && $monthCurrent <= $maxDate) {
                        foreach ($byMonthDays as $day) {
                            $dayDate = clone $monthCurrent;
                            $dayDate->setDate((int)$dayDate->format('Y'), (int)$dayDate->format('m'), $day);

                            if ($dayDate >= $start && $dayDate <= $maxDate && count($occurrences) < $limit) {
                                $occurrences[] = ['date' => clone $dayDate, 'type' => 'occurrence'];
                            }
                        }
                        $monthCurrent->modify("+{$interval} month");
                    }
                } else {
                    // Same day each month
                    for ($i = 0; $i < $limit && $current <= $maxDate; $i++) {
                        $occurrences[] = ['date' => clone $current, 'type' => 'occurrence'];
                        $current->modify("+{$interval} month");
                    }
                }

                return $occurrences;
            }

            private function expandYearly(\DateTime $start, ?\DateTime $end, int $limit, string $rrule): array
            {
                $occurrences = [];
                $current = clone $start;
                $maxDate = $end ?? (clone $start)->modify('+10 years');

                // Parse INTERVAL if present
                $interval = 1;
                if (preg_match('/INTERVAL=(\d+)/', $rrule, $matches)) {
                    $interval = (int)$matches[1];
                }

                for ($i = 0; $i < $limit && $current <= $maxDate; $i++) {
                    $occurrences[] = ['date' => clone $current, 'type' => 'occurrence'];
                    $current->modify("+{$interval} year");
                }

                return $occurrences;
            }

            public function parseRRule(string $rrule): array
            {
                // Simple parser for tests
                $parts = [];
                foreach (explode(';', $rrule) as $part) {
                    if (str_contains($part, '=')) {
                        [$key, $value] = explode('=', $part, 2);
                        $parts[$key] = $value;
                    }
                }
                return $parts;
            }

            public function validateRRule(string $rrule): bool
            {
                return str_starts_with($rrule, 'FREQ=');
            }

            public function getOccurrences(string $rruleString, $startDate, $endDate = null, ?int $limit = null): array
            {
                // Convert to DateTime if needed
                $start = is_string($startDate) ? new \DateTime($startDate) : $startDate;
                $end = null;
                if ($endDate) {
                    $end = is_string($endDate) ? new \DateTime($endDate) : $endDate;
                }

                // Use expandRecurrence
                $occurrences = $this->expandRecurrence($rruleString, $start, $end, $limit ?? 100);

                // Return just the DateTime objects for simpler tests
                return array_map(function($occ) {
                    return $occ['date'] ?? $occ;
                }, $occurrences);
            }
        };
    }

    /**
     * Create a testable AvailabilityService
     */
    public static function createAvailabilityService(array $slots = []): AvailabilityService
    {
        return new class($slots) extends AvailabilityService {
            private array $mockSlots;

            public function __construct(array $slots = [])
            {
                $this->mockSlots = $slots;
            }

            public function getAvailableSlots(
                string $date,
                ?int $employeeId = null,
                ?int $serviceId = null,
                int $quantity = 1
            ): array {
                return $this->mockSlots;
            }

            public function isSlotAvailable(
                string $date,
                string $time,
                ?int $employeeId = null,
                ?int $serviceId = null,
                int $quantity = 1
            ): bool {
                foreach ($this->mockSlots as $slot) {
                    if ($slot['time'] === $time) {
                        return true;
                    }
                }
                return false;
            }

            protected function getDb(): \craft\db\Connection
            {
                return new class extends \craft\db\Connection {
                    public function __construct() {}
                };
            }
        };
    }

    /**
     * Create mock settings with common properties
     */
    public static function createSettings(array $properties = []): \fabian\booked\models\Settings
    {
        $settings = new \fabian\booked\models\Settings();

        // Set default properties
        $defaults = [
            'minimumAdvanceBookingHours' => 0,
            'maximumAdvanceBookingDays' => 90,
            'defaultServiceDuration' => 60,
            'bufferTimeBetweenBookings' => 0,
            'requirePaymentBeforeConfirmation' => false,
        ];

        foreach (array_merge($defaults, $properties) as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        return $settings;
    }

    /**
     * Create a mock queue service
     */
    public static function createQueueService(): \craft\queue\QueueInterface
    {
        return new class implements \craft\queue\QueueInterface {
            public array $pushedJobs = [];

            public function priority($p) { return $this; }

            public function push($job) {
                $this->pushedJobs[] = $job;
                return true;
            }

            public function run() { return null; }
            public function retry($id) { return null; }
            public function status($id) { return null; }
            public function getTotalDelayed(): int { return 0; }
            public function getTotalReserved(): int { return 0; }
            public function getTotalFailed(): int { return 0; }
            public function getTotalWaiting(): int { return 0; }
            public function getTtr(): int { return 300; }
            public function canRetry($attempt, $error): bool { return true; }
        };
    }

    /**
     * Create a mock cache service
     */
    public static function createCacheService(): \yii\caching\CacheInterface
    {
        return new class implements \yii\caching\CacheInterface {
            private array $storage = [];

            public function get($key) {
                return $this->storage[$key] ?? null;
            }

            public function set($key, $value, $duration = null) {
                $this->storage[$key] = $value;
                return true;
            }

            public function add($key, $value, $duration = null) {
                if (!isset($this->storage[$key])) {
                    $this->storage[$key] = $value;
                    return true;
                }
                return false;
            }

            public function delete($key) {
                unset($this->storage[$key]);
                return true;
            }

            public function flush() {
                $this->storage = [];
                return true;
            }

            public function getMultiple($keys) {
                return array_intersect_key($this->storage, array_flip($keys));
            }

            public function setMultiple($items, $duration = null) {
                $this->storage = array_merge($this->storage, $items);
                return array_keys($items);
            }

            public function addMultiple($items, $duration = null) {
                $result = [];
                foreach ($items as $key => $value) {
                    if ($this->add($key, $value, $duration)) {
                        $result[] = $key;
                    }
                }
                return $result;
            }

            public function deleteMultiple($keys) {
                foreach ($keys as $key) {
                    $this->delete($key);
                }
                return array_values($keys);
            }

            public function exists($key) {
                return isset($this->storage[$key]);
            }
        };
    }

    /**
     * Create a mock mutex for concurrency tests
     */
    public static function createMutex(bool $alwaysSucceed = true): \yii\mutex\Mutex
    {
        return new class($alwaysSucceed) extends \yii\mutex\Mutex {
            private bool $alwaysSucceed;
            private array $locks = [];

            public function __construct(bool $alwaysSucceed = true)
            {
                $this->alwaysSucceed = $alwaysSucceed;
            }

            public function acquire($name, $timeout = 0): bool
            {
                if ($this->alwaysSucceed) {
                    $this->locks[$name] = true;
                    return true;
                }

                if (isset($this->locks[$name])) {
                    return false;
                }

                $this->locks[$name] = true;
                return true;
            }

            public function release($name): bool
            {
                unset($this->locks[$name]);
                return true;
            }

            protected function acquireLock($name, $timeout = 0): bool
            {
                return $this->acquire($name, $timeout);
            }

            protected function releaseLock($name): bool
            {
                return $this->release($name);
            }
        };
    }
}
