<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityCacheService;
use UnitTester;

/**
 * Mock Cache component for Craft::$app->cache
 */
class MockCache {
    private $data = [];
    public function get($key) {
        return $this->data[$key] ?? null;
    }
    public function set($key, $value, $duration) {
        $this->data[$key] = $value;
        return true;
    }
    public function delete($key) {
        unset($this->data[$key]);
        return true;
    }
}

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
        if (!isset(\Craft::$app)) {
            $mockApp = new \stdClass();
            $mockApp->cache = new MockCache();
            \Craft::$app = $mockApp;
        } else {
            \Craft::$app->cache = new MockCache();
        }

        $this->service = new AvailabilityCacheService();
    }

    /**
     * Helper to call private/protected methods
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function testBuildCacheKey()
    {
        $date = '2025-12-25';
        
        $key1 = $this->invokeMethod($this->service, 'buildCacheKey', [$date, 1, 10]);
        $key2 = $this->invokeMethod($this->service, 'buildCacheKey', [$date, 1, 10]);
        $key3 = $this->invokeMethod($this->service, 'buildCacheKey', [$date, 2, 10]);
        $key4 = $this->invokeMethod($this->service, 'buildCacheKey', [$date, null, null]);

        $this->assertEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertStringContainsString($date, $key1);
        $this->assertStringContainsString('1', $key1);
        $this->assertStringContainsString('10', $key1);
        $this->assertStringContainsString('all', $key4);
    }

    public function testGetAndSetCache()
    {
        $date = '2025-12-25';
        $slots = [['time' => '09:00'], ['time' => '10:00']];
        
        $this->service->setCachedAvailability($date, $slots, 1, 10);
        $cached = $this->service->getCachedAvailability($date, 1, 10);
        
        $this->assertEquals($slots, $cached);
        
        $missing = $this->service->getCachedAvailability('2025-12-26', 1, 10);
        $this->assertNull($missing);
    }

    public function testInvalidateCache()
    {
        $date = '2025-12-25';
        $slots = [['time' => '09:00']];
        
        $this->service->setCachedAvailability($date, $slots, 1, 10);
        $this->service->invalidateCache($date, 1, 10);
        
        $cached = $this->service->getCachedAvailability($date, 1, 10);
        $this->assertNull($cached);
    }
}
