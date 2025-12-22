<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\TimezoneService;
use DateTime;
use DateTimeZone;
use UnitTester;

class TimezoneServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TimezoneService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TimezoneService();
    }

    /**
     * Test converting a local "wall time" to UTC
     */
    public function testConvertToUtc()
    {
        $date = '2025-01-01';
        $time = '09:00';
        $timezone = 'Europe/Zurich'; // UTC+1 in winter
        
        $utcDateTime = $this->service->convertToUtc($date, $time, $timezone);
        
        $this->assertEquals('UTC', $utcDateTime->getTimezone()->getName());
        $this->assertEquals('2025-01-01 08:00:00', $utcDateTime->format('Y-m-d H:i:s'));
    }

    /**
     * Test converting from UTC back to a target timezone
     */
    public function testConvertFromUtc()
    {
        $utcString = '2025-01-01 08:00:00';
        $targetTimezone = 'America/New_York'; // UTC-5 in winter
        
        $localDateTime = $this->service->convertFromUtc($utcString, $targetTimezone);
        
        $this->assertEquals('America/New_York', $localDateTime->getTimezone()->getName());
        $this->assertEquals('2025-01-01 03:00:00', $localDateTime->format('Y-m-d H:i:s'));
    }

    /**
     * Test shifting a whole set of time slots from one timezone to another
     */
    public function testShiftSlots()
    {
        $slots = [
            ['time' => '09:00', 'endTime' => '10:00'],
            ['time' => '10:00', 'endTime' => '11:00'],
        ];
        $date = '2025-01-01';
        $fromTz = 'Europe/Zurich';
        $toTz = 'America/New_York';

        $shifted = $this->service->shiftSlots($slots, $date, $fromTz, $toTz);

        $this->assertCount(2, $shifted);
        $this->assertEquals('03:00', $shifted[0]['time']);
        $this->assertEquals('04:00', $shifted[0]['endTime']);
    }

    /**
     * Test that DST is handled correctly (Summer time)
     */
    public function testDaylightSavingsHandling()
    {
        $date = '2025-07-01'; // Summer
        $time = '09:00';
        $timezone = 'Europe/Zurich'; // UTC+2 in summer
        
        $utcDateTime = $this->service->convertToUtc($date, $time, $timezone);
        
        $this->assertEquals('2025-07-01 07:00:00', $utcDateTime->format('Y-m-d H:i:s'));
    }
}

