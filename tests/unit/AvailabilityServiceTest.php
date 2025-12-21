<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\AvailabilityCacheService;
use fabian\booked\services\BlackoutDateService;
use fabian\booked\elements\Service;
use fabian\booked\Booked;
use UnitTester;
use DateTime;
use Craft;

/**
 * A testable version of AvailabilityService that allows us to bypass Craft/DB dependencies
 */
class TestableAvailabilityService extends AvailabilityService 
{
    public $mockWorkingHours = [];
    public $mockBookings = null;

    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null): array
    {
        return $this->mockWorkingHours;
    }

    protected function subtractBookings(array $windows, string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        return $this->mockBookings ?? $windows;
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
        return null;
    }
    public function setCachedAvailability(string $date, array $slots, ?int $employeeId = null, ?int $serviceId = null): bool {
        return true;
    }
}

/**
 * Mock Blackout Service
 */
class MockBlackoutService extends BlackoutDateService {
    public $isBlackedOut = false;
    public function isDateBlackedOut(string $date): bool {
        return $this->isBlackedOut;
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
    
    /**
     * @var MockBlackoutService
     */
    protected $blackoutService;

    protected function _before()
    {
        parent::_before();
        
        $this->blackoutService = new MockBlackoutService();

        // Mock the Booked plugin singleton
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailabilityCache', 'getBlackoutDate'])
            ->getMock();
            
        $pluginMock->method('getAvailabilityCache')->willReturn(new MockCacheService());
        $pluginMock->method('getBlackoutDate')->willReturn($this->blackoutService);
        
        // Force the mock into the private static property
        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->service = new TestableAvailabilityService();
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function testTimeToMinutes()
    {
        $this->assertEquals(540, $this->invokeMethod($this->service, 'timeToMinutes', ['09:00']));
    }

    public function testSubtractBlackouts()
    {
        $windows = [['start' => '09:00', 'end' => '12:00']];
        $date = '2025-12-25';

        // Case 1: No blackout
        $this->blackoutService->isBlackedOut = false;
        $result = $this->invokeMethod($this->service, 'subtractBlackouts', [$windows, $date]);
        $this->assertEquals($windows, $result);

        // Case 2: Date is blacked out
        $this->blackoutService->isBlackedOut = true;
        $result = $this->invokeMethod($this->service, 'subtractBlackouts', [$windows, $date]);
        $this->assertEquals([], $result);
    }

    public function testSubtractBuffers()
    {
        $windows = [['start' => '09:00', 'end' => '12:00']];
        $service = new MockService();
        $service->bufferBefore = 15;
        $service->bufferAfter = 30;

        $adjusted = $this->invokeMethod($this->service, 'subtractBuffers', [$windows, $service]);

        $this->assertCount(1, $adjusted);
        $this->assertEquals('09:15', $adjusted[0]['start']);
        $this->assertEquals('11:30', $adjusted[0]['end']);
    }

    public function testGenerateSlots()
    {
        $windows = [['start' => '09:00', 'end' => '11:00']];
        $slots = $this->invokeMethod($this->service, 'generateSlots', [$windows, 60]);
        $this->assertCount(2, $slots);
    }

    public function testGetAvailableSlotsOrchestration()
    {
        $schedule = new \stdClass();
        $schedule->startTime = '09:00';
        $schedule->endTime = '12:00';
        $schedule->employeeId = 1;
        $this->service->mockWorkingHours = [$schedule];
        
        $date = '2099-12-25';
        $slots = $this->service->getAvailableSlots($date, 1);

        $this->assertCount(3, $slots);
    }
}
