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
     * Uses cache tagging for efficient invalidation
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

        // Build dependency tags for efficient invalidation
        $dependency = new \yii\caching\TagDependency([
            'tags' => $this->buildCacheTags($date, $employeeId, $serviceId),
        ]);

        return Craft::$app->cache->set($cacheKey, $slots, self::CACHE_TTL, $dependency);
    }

    /**
     * Build cache tags for a cache entry
     * This allows efficient invalidation by tag
     *
     * @param string $date
     * @param int|null $employeeId
     * @param int|null $serviceId
     * @return array
     */
    private function buildCacheTags(string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        $tags = [
            "availability:date:{$date}",  // Tag by date for invalidating entire day
        ];

        if ($employeeId !== null) {
            $tags[] = "availability:employee:{$employeeId}";  // Tag by employee
        }

        if ($serviceId !== null) {
            $tags[] = "availability:service:{$serviceId}";  // Tag by service
        }

        return $tags;
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
    /**
     * Invalidate all cache entries for a specific date
     * Uses cache tagging for O(1) invalidation instead of O(n²)
     *
     * @param string $date Date in Y-m-d format
     * @return bool Whether the cache was invalidated successfully
     */
    public function invalidateDateCache(string $date): bool
    {
        // Use cache tagging to invalidate all entries for this date at once
        // This is O(1) instead of O(n*m) where n=employees, m=services
        $tag = "availability:date:{$date}";

        try {
            \yii\caching\TagDependency::invalidate(\Craft::$app->cache, $tag);
            \Craft::info("Invalidated availability cache for date: {$date} using tag: {$tag}", __METHOD__);
            return true;
        } catch (\Exception $e) {
            \Craft::error("Failed to invalidate cache for date {$date}: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Invalidate all availability cache for a specific employee
     * Uses cache tagging for O(1) invalidation instead of O(n*m)
     * Useful when external calendar events are synced
     *
     * @param int $employeeId Employee ID
     * @return bool Whether the cache was invalidated successfully
     */
    public function invalidateAllForEmployee(int $employeeId): bool
    {
        // Use cache tagging to invalidate all entries for this employee at once
        $tag = "availability:employee:{$employeeId}";

        try {
            \yii\caching\TagDependency::invalidate(\Craft::$app->cache, $tag);
            \Craft::info("Invalidated availability cache for employee: {$employeeId} using tag: {$tag}", __METHOD__);
            return true;
        } catch (\Exception $e) {
            \Craft::error("Failed to invalidate cache for employee {$employeeId}: " . $e->getMessage(), __METHOD__);
            return false;
        }
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

