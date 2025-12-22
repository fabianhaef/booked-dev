<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\VirtualMeetingService;
use fabian\booked\elements\Reservation;
use UnitTester;
use Craft;

/**
 * Testable version of VirtualMeetingService to mock external API calls
 */
class TestableVirtualMeetingService extends VirtualMeetingService
{
    public bool $mockZoomSuccess = true;
    public bool $mockGoogleMeetSuccess = true;

    protected function createZoomMeeting(Reservation $reservation): ?string
    {
        return $this->mockZoomSuccess ? 'https://zoom.us/j/123456789' : null;
    }

    protected function createGoogleMeetLink(Reservation $reservation): ?string
    {
        return $this->mockGoogleMeetSuccess ? 'https://meet.google.com/abc-defg-hij' : null;
    }
}

class VirtualMeetingServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableVirtualMeetingService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TestableVirtualMeetingService();
    }

    public function testCreateMeetingZoom()
    {
        $reservation = $this->getMockBuilder(Reservation::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $url = $this->service->createMeeting($reservation, 'zoom');
        $this->assertEquals('https://zoom.us/j/123456789', $url);
    }

    public function testCreateMeetingGoogleMeet()
    {
        $reservation = $this->getMockBuilder(Reservation::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $url = $this->service->createMeeting($reservation, 'google');
        $this->assertEquals('https://meet.google.com/abc-defg-hij', $url);
    }

    public function testCreateMeetingInvalidProvider()
    {
        $reservation = $this->getMockBuilder(Reservation::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $url = $this->service->createMeeting($reservation, 'invalid');
        $this->assertNull($url);
    }
}

