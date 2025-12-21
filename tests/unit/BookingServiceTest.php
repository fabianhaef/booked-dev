<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\BookingService;
use fabian\booked\services\AvailabilityService;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;
use UnitTester;
use DateTime;

/**
 * Testable version of BookingService
 */
class TestableBookingService extends BookingService {
    public $mockReservation = null;
    public function getReservationById(int $id): ?Reservation {
        return $this->mockReservation;
    }
}

/**
 * Real Mock extending Reservation to pass type hints
 */
class MockReservationElement extends Reservation {
    public function __construct() {}
}

class BookingServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableBookingService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        
        // Mock the Booked plugin singleton
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailability'])
            ->getMock();
            
        $pluginMock->method('getAvailability')->willReturn(new AvailabilityService());
            
        // Force the mock into the private static property
        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->service = new TestableBookingService();
    }

    public function testCanCancelReservation()
    {
        $now = new DateTime();
        $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
        $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');

        // Case 1: Future reservation
        $res1 = new MockReservationElement();
        $res1->bookingDate = $tomorrow;
        $res1->startTime = '10:00';
        $res1->status = 'confirmed';
        
        $this->assertTrue($this->invokeMethod($this->service, 'canCancelReservation', [$res1]));

        // Case 2: Past reservation
        $res2 = new MockReservationElement();
        $res2->bookingDate = $yesterday;
        $res2->startTime = '10:00';
        $res2->status = 'confirmed';
        
        $this->assertFalse($this->invokeMethod($this->service, 'canCancelReservation', [$res2]));

        // Case 3: Already cancelled
        $res3 = new MockReservationElement();
        $res3->bookingDate = $tomorrow;
        $res3->status = 'cancelled';
        
        $this->assertFalse($this->invokeMethod($this->service, 'canCancelReservation', [$res3]));
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        if (!$reflection->hasMethod($methodName)) {
            throw new \Exception("Method $methodName does not exist");
        }
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
