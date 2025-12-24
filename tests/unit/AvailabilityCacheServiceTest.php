<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityCacheService;
use UnitTester;
use Craft;

/**
 * Tests for AvailabilityCacheService
 * Ensures tag-based cache invalidation works correctly after O(n²) → O(1) optimization
 */
class AvailabilityCacheServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var AvailabilityCacheService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();

        // Mock Craft::$app->cache
        $this->mockCraftCache();

        // Create service instance
        $this->service = new AvailabilityCacheService();
    }

    /**
     * Test that cache can be set and retrieved
     */
    public function testSetAndGetCachedAvailability()
    {
        $date = '2025-12-26';
        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Test Employee'],
            ['time' => '11:00', 'employeeId' => 1, 'employeeName' => 'Test Employee'],
        ];

        // Set cache
        $result = $this->service->setCachedAvailability($date, $slots, 1, 100);
        $this->assertTrue($result, 'Should successfully set cache');

        // Get cache
        $cached = $this->service->getCachedAvailability($date, 1, 100);
        $this->assertEquals($slots, $cached, 'Should retrieve same slots from cache');
    }

    /**
     * Test that cache with different parameters creates different cache keys
     */
    public function testDifferentParametersCreateDifferentCacheKeys()
    {
        $date = '2025-12-26';
        $slots1 = [['time' => '10:00']];
        $slots2 = [['time' => '11:00']];

        // Set cache with employeeId=1
        $this->service->setCachedAvailability($date, $slots1, 1, null);

        // Set cache with employeeId=2
        $this->service->setCachedAvailability($date, $slots2, 2, null);

        // Retrieve and verify they're different
        $cached1 = $this->service->getCachedAvailability($date, 1, null);
        $cached2 = $this->service->getCachedAvailability($date, 2, null);

        $this->assertEquals($slots1, $cached1);
        $this->assertEquals($slots2, $cached2);
        $this->assertNotEquals($cached1, $cached2, 'Different employees should have different cached data');
    }

    /**
     * Test that invalidating by date clears all cache entries for that date
     */
    public function testInvalidateDateCacheClearsAllEntriesForDate()
    {
        $date = '2025-12-26';
        $slots = [['time' => '10:00']];

        // Set multiple cache entries for same date but different employees
        $this->service->setCachedAvailability($date, $slots, 1, null);
        $this->service->setCachedAvailability($date, $slots, 2, null);
        $this->service->setCachedAvailability($date, $slots, null, 100);

        // Verify all are cached
        $this->assertNotNull($this->service->getCachedAvailability($date, 1, null));
        $this->assertNotNull($this->service->getCachedAvailability($date, 2, null));
        $this->assertNotNull($this->service->getCachedAvailability($date, null, 100));

        // Invalidate date cache (should invalidate ALL entries for this date)
        $result = $this->service->invalidateDateCache($date);
        $this->assertTrue($result, 'Should successfully invalidate date cache');

        // Verify all entries for this date are cleared
        $this->assertNull($this->service->getCachedAvailability($date, 1, null), 'Employee 1 cache should be cleared');
        $this->assertNull($this->service->getCachedAvailability($date, 2, null), 'Employee 2 cache should be cleared');
        $this->assertNull($this->service->getCachedAvailability($date, null, 100), 'Service 100 cache should be cleared');
    }

    /**
     * Test that invalidating by employee clears all cache entries for that employee
     */
    public function testInvalidateAllForEmployeeClearsAllEntriesForEmployee()
    {
        $employeeId = 1;
        $slots = [['time' => '10:00']];

        // Set multiple cache entries for same employee but different dates
        $this->service->setCachedAvailability('2025-12-26', $slots, $employeeId, null);
        $this->service->setCachedAvailability('2025-12-27', $slots, $employeeId, null);
        $this->service->setCachedAvailability('2025-12-28', $slots, $employeeId, 100);

        // Set cache for different employee (should NOT be cleared)
        $this->service->setCachedAvailability('2025-12-26', $slots, 2, null);

        // Verify all are cached
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', $employeeId, null));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-27', $employeeId, null));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-28', $employeeId, 100));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', 2, null));

        // Invalidate employee cache (should invalidate ALL entries for this employee)
        $result = $this->service->invalidateAllForEmployee($employeeId);
        $this->assertTrue($result, 'Should successfully invalidate employee cache');

        // Verify all entries for this employee are cleared
        $this->assertNull($this->service->getCachedAvailability('2025-12-26', $employeeId, null), 'Dec 26 cache should be cleared');
        $this->assertNull($this->service->getCachedAvailability('2025-12-27', $employeeId, null), 'Dec 27 cache should be cleared');
        $this->assertNull($this->service->getCachedAvailability('2025-12-28', $employeeId, 100), 'Dec 28 cache should be cleared');

        // Verify other employee's cache is NOT cleared
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', 2, null), 'Other employee cache should remain');
    }

    /**
     * Test that invalidating by service clears all cache entries for that service
     */
    public function testInvalidateAllForServiceClearsAllEntriesForService()
    {
        $serviceId = 100;
        $slots = [['time' => '10:00']];

        // Set multiple cache entries for same service but different dates and employees
        $this->service->setCachedAvailability('2025-12-26', $slots, 1, $serviceId);
        $this->service->setCachedAvailability('2025-12-27', $slots, 2, $serviceId);
        $this->service->setCachedAvailability('2025-12-28', $slots, null, $serviceId);

        // Set cache for different service (should NOT be cleared)
        $this->service->setCachedAvailability('2025-12-26', $slots, 1, 200);

        // Verify all are cached
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', 1, $serviceId));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-27', 2, $serviceId));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-28', null, $serviceId));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', 1, 200));

        // Invalidate service cache (should invalidate ALL entries for this service)
        $result = $this->service->invalidateAllForService($serviceId);
        $this->assertTrue($result, 'Should successfully invalidate service cache');

        // Verify all entries for this service are cleared
        $this->assertNull($this->service->getCachedAvailability('2025-12-26', 1, $serviceId), 'Dec 26 cache should be cleared');
        $this->assertNull($this->service->getCachedAvailability('2025-12-27', 2, $serviceId), 'Dec 27 cache should be cleared');
        $this->assertNull($this->service->getCachedAvailability('2025-12-28', null, $serviceId), 'Dec 28 cache should be cleared');

        // Verify other service's cache is NOT cleared
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', 1, 200), 'Other service cache should remain');
    }

    /**
     * Test that cache returns null for non-existent entries
     */
    public function testGetCachedAvailabilityReturnsNullForNonExistent()
    {
        $cached = $this->service->getCachedAvailability('2025-12-26', 999, 999);
        $this->assertNull($cached, 'Should return null for non-existent cache entry');
    }

    /**
     * Test that clear all cache works
     */
    public function testClearAllCache()
    {
        // Set multiple cache entries
        $this->service->setCachedAvailability('2025-12-26', [['time' => '10:00']], 1, null);
        $this->service->setCachedAvailability('2025-12-27', [['time' => '11:00']], 2, null);
        $this->service->setCachedAvailability('2025-12-28', [['time' => '12:00']], null, 100);

        // Verify all are cached
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-26', 1, null));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-27', 2, null));
        $this->assertNotNull($this->service->getCachedAvailability('2025-12-28', null, 100));

        // Clear all cache
        $result = $this->service->clearAllCache();
        $this->assertTrue($result, 'Should successfully clear all cache');

        // Verify all entries are cleared
        $this->assertNull($this->service->getCachedAvailability('2025-12-26', 1, null));
        $this->assertNull($this->service->getCachedAvailability('2025-12-27', 2, null));
        $this->assertNull($this->service->getCachedAvailability('2025-12-28', null, 100));
    }

    /**
     * Test performance improvement: O(1) invalidation vs O(n²)
     * This is a conceptual test to document the performance improvement
     */
    public function testTagBasedInvalidationIsO1()
    {
        // Before optimization: invalidating date required iterating through all employees and services
        // With 10 employees and 5 services = 50 cache deletions
        // With tag-based approach: 1 tag invalidation

        $date = '2025-12-26';
        $slots = [['time' => '10:00']];

        // Simulate multiple employees and services using same date
        for ($emp = 1; $emp <= 10; $emp++) {
            for ($svc = 1; $svc <= 5; $svc++) {
                $this->service->setCachedAvailability($date, $slots, $emp, $svc);
            }
        }

        // With tag-based invalidation, this should be O(1) regardless of how many cache entries exist
        $startTime = microtime(true);
        $this->service->invalidateDateCache($date);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        // Verify all 50 entries are cleared with single tag invalidation
        for ($emp = 1; $emp <= 10; $emp++) {
            for ($svc = 1; $svc <= 5; $svc++) {
                $this->assertNull(
                    $this->service->getCachedAvailability($date, $emp, $svc),
                    "Cache for employee $emp and service $svc should be cleared"
                );
            }
        }

        // Document that this is significantly faster than O(n²) approach
        $this->assertLessThan(0.1, $executionTime, 'Tag-based invalidation should complete in < 100ms');
    }

    /**
     * Mock Craft cache
     */
    private function mockCraftCache()
    {
        if (!isset(Craft::$app)) {
            $app = new class {
                public $cache;

                public function __construct()
                {
                    // Mock Cache service with tag dependency support
                    $this->cache = new class {
                        private $storage = [];
                        private $tags = [];

                        public function set($key, $value, $ttl = null, $dependency = null)
                        {
                            $this->storage[$key] = $value;

                            // Store tag associations
                            if ($dependency && isset($dependency->tags)) {
                                foreach ($dependency->tags as $tag) {
                                    if (!isset($this->tags[$tag])) {
                                        $this->tags[$tag] = [];
                                    }
                                    $this->tags[$tag][] = $key;
                                }
                            }

                            return true;
                        }

                        public function get($key)
                        {
                            return $this->storage[$key] ?? false;
                        }

                        public function delete($key)
                        {
                            unset($this->storage[$key]);
                            return true;
                        }

                        public function flush()
                        {
                            $this->storage = [];
                            $this->tags = [];
                            return true;
                        }

                        // Simulate tag invalidation
                        public function invalidateTags(array $tags)
                        {
                            foreach ($tags as $tag) {
                                if (isset($this->tags[$tag])) {
                                    foreach ($this->tags[$tag] as $key) {
                                        unset($this->storage[$key]);
                                    }
                                    unset($this->tags[$tag]);
                                }
                            }
                        }
                    };
                }
            };

            Craft::$app = $app;
        }

        // Mock TagDependency::invalidate to use our mock cache
        if (!class_exists('\yii\caching\TagDependency', false)) {
            eval('
                namespace yii\caching;
                class TagDependency {
                    public $tags;
                    public static function invalidate($cache, $tags) {
                        if (!is_array($tags)) {
                            $tags = [$tags];
                        }
                        $cache->invalidateTags($tags);
                    }
                }
            ');
        }
    }
}
