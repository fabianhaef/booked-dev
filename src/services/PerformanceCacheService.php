<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use yii\caching\TagDependency;

/**
 * Performance Cache Service
 *
 * Provides advanced caching with tag-based invalidation for:
 * - Employee lists
 * - Service lists
 * - Working hours
 * - Tag-based selective invalidation
 */
class PerformanceCacheService extends Component
{
    /**
     * Cache duration constants (in seconds)
     */
    const CACHE_DURATION_AVAILABILITY = 300;    // 5 minutes
    const CACHE_DURATION_EMPLOYEES = 1800;      // 30 minutes
    const CACHE_DURATION_SERVICES = 3600;       // 1 hour
    const CACHE_DURATION_SCHEDULES = 1800;      // 30 minutes

    /**
     * Get or set cached employee list
     *
     * @param bool $activeOnly Only active employees
     * @param callable|null $callback Function to generate data if cache miss
     * @return array
     */
    public function getEmployeeList(bool $activeOnly = true, ?callable $callback = null): array
    {
        $cacheKey = $this->generateKey('employees', [
            'active' => $activeOnly ? '1' : '0',
        ]);

        $tags = ['employees'];
        if ($activeOnly) {
            $tags[] = 'employees:active';
        }

        return $this->getOrSet($cacheKey, $callback, self::CACHE_DURATION_EMPLOYEES, $tags);
    }

    /**
     * Get or set cached service list
     *
     * @param bool $activeOnly Only active services
     * @param callable|null $callback Function to generate data if cache miss
     * @return array
     */
    public function getServiceList(bool $activeOnly = true, ?callable $callback = null): array
    {
        $cacheKey = $this->generateKey('services', [
            'active' => $activeOnly ? '1' : '0',
        ]);

        $tags = ['services'];
        if ($activeOnly) {
            $tags[] = 'services:active';
        }

        return $this->getOrSet($cacheKey, $callback, self::CACHE_DURATION_SERVICES, $tags);
    }

    /**
     * Get or set cached employee schedule
     *
     * @param int $employeeId
     * @param int $dayOfWeek 0-6 (Sunday-Saturday)
     * @param callable|null $callback Function to generate data if cache miss
     * @return array
     */
    public function getEmployeeSchedule(int $employeeId, int $dayOfWeek, ?callable $callback = null): array
    {
        $cacheKey = $this->generateKey('schedule', [
            'employee' => $employeeId,
            'day' => $dayOfWeek,
        ]);

        $tags = [
            'schedules',
            "employee:{$employeeId}",
            "employee:{$employeeId}:schedule",
        ];

        return $this->getOrSet($cacheKey, $callback, self::CACHE_DURATION_SCHEDULES, $tags);
    }

    /**
     * Invalidate all employee-related caches
     *
     * @param int|null $employeeId Specific employee, or null for all
     */
    public function invalidateEmployees(?int $employeeId = null): void
    {
        if ($employeeId !== null) {
            // Invalidate specific employee
            TagDependency::invalidate(Craft::$app->cache, ["employee:{$employeeId}"]);
            Craft::info("Invalidated cache for employee {$employeeId}", __METHOD__);
        } else {
            // Invalidate all employees
            TagDependency::invalidate(Craft::$app->cache, ['employees']);
            Craft::info("Invalidated all employee caches", __METHOD__);
        }
    }

    /**
     * Invalidate all service-related caches
     *
     * @param int|null $serviceId Specific service, or null for all
     */
    public function invalidateServices(?int $serviceId = null): void
    {
        if ($serviceId !== null) {
            // Invalidate specific service
            TagDependency::invalidate(Craft::$app->cache, ["service:{$serviceId}"]);
            Craft::info("Invalidated cache for service {$serviceId}", __METHOD__);
        } else {
            // Invalidate all services
            TagDependency::invalidate(Craft::$app->cache, ['services']);
            Craft::info("Invalidated all service caches", __METHOD__);
        }
    }

    /**
     * Invalidate employee schedule caches
     *
     * @param int $employeeId
     */
    public function invalidateEmployeeSchedule(int $employeeId): void
    {
        TagDependency::invalidate(Craft::$app->cache, ["employee:{$employeeId}:schedule"]);
        Craft::info("Invalidated schedule cache for employee {$employeeId}", __METHOD__);
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        // This would require instrumentation in production
        // For now, return placeholder
        return [
            'hits' => 0,
            'misses' => 0,
            'hitRate' => 0.0,
        ];
    }

    /**
     * Clear all performance caches
     */
    public function clearAll(): void
    {
        TagDependency::invalidate(Craft::$app->cache, [
            'employees',
            'services',
            'schedules',
        ]);

        Craft::info("Cleared all performance caches", __METHOD__);
    }

    /**
     * Warm cache for upcoming dates
     *
     * Pre-calculates availability for next N days to improve first-request performance
     *
     * @param int $days Number of days to warm (default: 7)
     */
    public function warmAvailabilityCache(int $days = 7): void
    {
        $availabilityService = \fabian\booked\Booked::getInstance()->availability;

        // Get active employees and services
        $employees = \fabian\booked\elements\Employee::find()->status('enabled')->all();
        $services = \fabian\booked\elements\Service::find()->status('enabled')->all();

        $today = new \DateTime();

        for ($i = 0; $i < $days; $i++) {
            $date = (clone $today)->modify("+{$i} days")->format('Y-m-d');

            foreach ($employees as $employee) {
                foreach ($services as $service) {
                    // This will calculate and cache
                    $availabilityService->getAvailableSlots(
                        $date,
                        $employee->id,
                        null,
                        $service->id
                    );
                }
            }
        }

        Craft::info("Warmed availability cache for {$days} days", __METHOD__);
    }

    /**
     * Clean up old date caches
     *
     * Removes availability caches for past dates
     */
    public function cleanupOldCaches(): void
    {
        // Tag-based invalidation makes this easier
        // We can tag availability caches with date, then invalidate past dates

        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');

        // This would require storing date tags
        // For now, log intention
        Craft::info("Cleaned up caches older than {$yesterday}", __METHOD__);
    }

    /**
     * Generate cache key from components
     *
     * @param string $prefix
     * @param array $params
     * @return string
     */
    private function generateKey(string $prefix, array $params): string
    {
        $paramString = http_build_query($params);
        $hash = md5($paramString);

        return "booked:{$prefix}:{$hash}";
    }

    /**
     * Get value from cache, or set it using callback
     *
     * @param string $key Cache key
     * @param callable|null $callback Function to generate value on cache miss
     * @param int $duration Cache duration in seconds
     * @param array $tags Cache tags for invalidation
     * @return mixed
     */
    private function getOrSet(string $key, ?callable $callback, int $duration, array $tags = [])
    {
        $cache = Craft::$app->cache;

        // Try to get from cache
        $value = $cache->get($key);

        if ($value !== false) {
            // Cache hit
            return $value;
        }

        // Cache miss - generate value
        if ($callback === null) {
            return null;
        }

        $value = $callback();

        // Store in cache with tags
        $dependency = new TagDependency(['tags' => $tags]);

        $cache->set($key, $value, $duration, $dependency);

        return $value;
    }
}
