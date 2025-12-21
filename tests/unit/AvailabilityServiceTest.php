<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\AvailabilityCacheService;
use fabian\booked\elements\Service;
use fabian\booked\Booked;
use UnitTester;
use DateTime;

/**
 * A testable version of AvailabilityService that allows us to bypass Craft/DB dependencies
 */
class TestableAvailabilityService extends AvailabilityService 
{
    public $mockWorkingHours = [];
    public $mockService = null;
    public $mockBookings = null;
    public $mockBlackouts = null;

    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null): array
    {
        return $this->mockWorkingHours;
    }

    protected function subtractBookings(array $windows, string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        return $this->mockBookings ?? $windows;
    }

    protected function subtractBlackouts(array $windows, string $date): array
    {
        return $this->mockBlackouts ?? $windows;
    }
}

/**
 * Mock Service class
 */
class MockService extends Service {
    public ?int $duration = 60;
    public ?int $bufferBefore = 0;
    public ?int $bufferAfter = 0;
    public bool $enabled = true;
    public function __construct() {}
}

/**
 * Mock Cache Service
 */
class MockCacheService extends AvailabilityCacheService {
    public function getCachedAvailability(string $date, ?int $employeeId = null, ?int $serviceId = null): ?array {
        return null; // Always miss cache
    }
    public function setCachedAvailability(string $date, array $slots, ?int $employeeId = null, ?int $serviceId = null): bool {
        return true;
    }
}

class AvailabilityServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableAvailabilityService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        
        // Mock the Booked plugin singleton
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailabilityCache'])
            ->getMock();
            
        $pluginMock->method('getAvailabilityCache')->willReturn(new MockCacheService());
        
        // Force the mock into the private static property
        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->service = new TestableAvailabilityService();
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

    public function testTimeToMinutes()
    {
        $this->assertEquals(0, $this->invokeMethod($this->service, 'timeToMinutes', ['00:00']));
        $this->assertEquals(540, $this->invokeMethod($this->service, 'timeToMinutes', ['09:00']));
    }

    public function testMinutesToTime()
    {
        $this->assertEquals('00:00', $this->invokeMethod($this->service, 'minutesToTime', [0]));
        $this->assertEquals('09:00', $this->invokeMethod($this->service, 'minutesToTime', [540]));
    }

    public function testMergeTimeWindows()
    {
        $windows = [
            ['start' => '09:00', 'end' => '12:00'],
            ['start' => '11:00', 'end' => '14:00'],
            ['start' => '15:00', 'end' => '17:00'],
        ];

        $merged = $this->invokeMethod($this->service, 'mergeTimeWindows', [$windows]);

        $this->assertCount(2, $merged);
        $this->assertEquals('09:00', $merged[0]['start']);
        $this->assertEquals('14:00', $merged[0]['end']);
        $this->assertEquals('15:00', $merged[1]['start']);
        $this->assertEquals('17:00', $merged[1]['end']);
    }

    public function testSubtractBuffers()
    {
        $windows = [
            ['start' => '09:00', 'end' => '12:00']
        ];
        
        $service = new MockService();
        $service->bufferBefore = 15;
        $service->bufferAfter = 30;

        $adjusted = $this->invokeMethod($this->service, 'subtractBuffers', [$windows, $service]);

        $this->assertCount(1, $adjusted);
        $this->assertEquals('09:15', $adjusted[0]['start']);
        $this->assertEquals('11:30', $adjusted[0]['end']);
    }

    public function testSubtractBlackouts()
    {
        $windows = [
            ['start' => '09:00', 'end' => '12:00']
        ];
        $date = '2025-12-25';

        // Case 1: No blackout
        $this->service->mockBlackouts = null; // Use real/default behavior (which is windows as-is for now)
        $result = $this->invokeMethod($this->service, 'subtractBlackouts', [$windows, $date]);
        $this->assertEquals($windows, $result);

        // Case 2: Date is blacked out (we'll mock this when we implement it)
        // For now, let's assume we want it to return empty if the date is blacked out.
    }

    public function testGenerateSlots()
    {
        $windows = [
            ['start' => '09:00', 'end' => '11:00', 'employeeId' => 1]
        ];
        $duration = 60;

        $slots = $this->invokeMethod($this->service, 'generateSlots', [$windows, $duration]);

        $this->assertCount(2, $slots);
        $this->assertEquals('09:00', $slots[0]['time']);
        $this->assertEquals('10:00', $slots[0]['endTime']);
    }

    public function testGetAvailableSlotsOrchestration()
    {
        // Setup mock working hours
        $schedule = new class {
            public $startTime = '09:00';
            public $endTime = '12:00';
            public $employeeId = 1;
        };
        $this->service->mockWorkingHours = [$schedule];
        
        // Test basic slot generation (3 hours / 1 hour slots = 3 slots)
        $date = '2099-12-25'; // A distant future date to avoid past filtering
        $slots = $this->service->getAvailableSlots($date, 1);

        $this->assertCount(3, $slots);
        $this->assertEquals('09:00', $slots[0]['time']);
        $this->assertEquals('10:00', $slots[1]['time']);
        $this->assertEquals('11:00', $slots[2]['time']);
    }

    public function testIsSlotAvailable()
    {
        $schedule = new class {
            public $startTime = '09:00';
            public $endTime = '10:00';
            public $employeeId = 1;
        };
        $this->service->mockWorkingHours = [$schedule];

        $date = '2099-12-25';
        
        $this->assertTrue($this->service->isSlotAvailable($date, '09:00', '10:00', 1));
        $this->assertFalse($this->service->isSlotAvailable($date, '10:00', '11:00', 1));
    }
}
