<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use DateInterval;
use DateTime;
use fabian\booked\Booked;

/**
 * Availability Cache Service
 * 
 * Caches pre-calculated availability windows to improve performance.
 * Cache key format: availability_{date}_{employeeId}_{serviceId}
 * TTL: 1 hour
 */
class AvailabilityCacheService extends Component
{
    /**
     * Cache key prefix
     */
    private const CACHE_KEY_PREFIX = 'booked_availability_';

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get cached availability for a specific date, employee, and service
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $employeeId Employee ID (null for all employees)
     * @param int|null $serviceId Service ID (null for all services)
     * @return array|null Cached availability slots or null if not cached
     */
    public function getCachedAvailability(string $date, ?int $employeeId = null, ?int $serviceId = null): ?array
    {
        $cacheKey = $this->buildCacheKey($date, $employeeId, $serviceId);
        $cached = Craft::$app->cache->get($cacheKey);
        return is_array($cached) ? $cached : null;
    }

    /**
     * Set cached availability for a specific date, employee, and service
     *
     * @param string $date Date in Y-m-d format
     * @param array $slots Availability slots to cache
     * @param int|null $employeeId Employee ID (null for all employees)
     * @param int|null $serviceId Service ID (null for all services)
     * @return bool Whether the cache was set successfully
     */
    public function setCachedAvailability(string $date, array $slots, ?int $employeeId = null, ?int $serviceId = null): bool
    {
        $cacheKey = $this->buildCacheKey($date, $employeeId, $serviceId);
        return Craft::$app->cache->set($cacheKey, $slots, self::CACHE_TTL);
    }

    /**
     * Invalidate cache for a specific date, employee, and service
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $employeeId Employee ID (null for all employees)
     * @param int|null $serviceId Service ID (null for all services)
     * @return bool Whether the cache was invalidated successfully
     */
    public function invalidateCache(string $date, ?int $employeeId = null, ?int $serviceId = null): bool
    {
        $cacheKey = $this->buildCacheKey($date, $employeeId, $serviceId);
        return Craft::$app->cache->delete($cacheKey);
    }

    /**
     * Invalidate all availability cache for a specific date
     * Useful when a booking is created/cancelled
     *
     * @param string $date Date in Y-m-d format
     * @return bool Whether the cache was invalidated successfully
     */
    public function invalidateDateCache(string $date): bool
    {
        // Invalidate cache for all possible employee/service combinations
        // We use a pattern-based approach: delete all keys matching the date pattern
        $pattern = self::CACHE_KEY_PREFIX . $date . '_*';
        
        // Since Craft's cache doesn't support pattern deletion directly,
        // we'll need to track cache keys or use a different approach
        // For now, we'll invalidate common combinations
        
        // Get all employees and services to invalidate their caches
        $employees = \fabian\booked\elements\Employee::find()->all();
        $services = \fabian\booked\elements\Service::find()->all();
        
        $invalidated = true;
        
        // Invalidate cache for all employees (no service filter)
        foreach ($employees as $employee) {
            $this->invalidateCache($date, $employee->id, null);
        }
        
        // Invalidate cache for all services (no employee filter)
        foreach ($services as $service) {
            $this->invalidateCache($date, null, $service->id);
        }
        
        // Invalidate cache for all employee/service combinations
        foreach ($employees as $employee) {
            foreach ($services as $service) {
                $this->invalidateCache($date, $employee->id, $service->id);
            }
        }
        
        // Also invalidate the "all" cache (null/null)
        $this->invalidateCache($date, null, null);
        
        return $invalidated;
    }

    /**
     * Invalidate all availability cache for a specific employee
     * Useful when external calendar events are synced
     *
     * @param int $employeeId Employee ID
     * @return bool Whether the cache was invalidated successfully
     */
    public function invalidateAllForEmployee(int $employeeId): bool
    {
        // For now, we'll invalidate next 30 days
        $startDate = new \DateTime();
        $invalidated = true;

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->format('Y-m-d');
            $this->invalidateCache($date, $employeeId, null);
            
            // Also invalidate combined caches
            $services = \fabian\booked\elements\Service::find()->all();
            foreach ($services as $service) {
                $this->invalidateCache($date, $employeeId, $service->id);
            }
            
            // Invalidate the "all" employee cache for this date as it might include this employee
            $this->invalidateCache($date, null, null);

            $startDate->modify('+1 day');
        }

        return $invalidated;
    }

    /**
     * Warm cache for popular dates (next 30 days)
     * This can be called via a queue job or cron
     *
     * @param int|null $employeeId Optional employee ID to warm cache for
     * @param int|null $serviceId Optional service ID to warm cache for
     * @param int $days Number of days to warm (default: 30)
     * @return int Number of dates cached
     */
    public function warmCache(?int $employeeId = null, ?int $serviceId = null, int $days = 30): int
    {
        // Note: This method should be called via a queue job to avoid circular dependencies
        // For now, return 0. This can be implemented when queue jobs are set up.
        return 0;
    }

    /**
     * Build cache key from date, employee ID, and service ID
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $employeeId Employee ID
     * @param int|null $serviceId Service ID
     * @return string Cache key
     */
    private function buildCacheKey(string $date, ?int $employeeId = null, ?int $serviceId = null): string
    {
        $parts = [
            self::CACHE_KEY_PREFIX,
            $date,
            $employeeId ?? 'all',
            $serviceId ?? 'all',
        ];

        return implode('_', $parts);
    }
}

