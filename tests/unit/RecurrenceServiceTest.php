<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\RecurrenceService;
use DateTime;
use UnitTester;

class RecurrenceServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var RecurrenceService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new RecurrenceService();
    }

    /**
     * Test simple weekly recurrence
     */
    public function testWeeklyRecurrence()
    {
        $rrule = 'FREQ=WEEKLY;BYDAY=MO,WE,FR;COUNT=6';
        $startDate = '2025-01-01'; // A Wednesday
        
        $occurrences = $this->service->getOccurrences($rrule, $startDate);
        
        $this->assertCount(6, $occurrences);
        $this->assertEquals('2025-01-01', $occurrences[0]->format('Y-m-d')); // Wed
        $this->assertEquals('2025-01-03', $occurrences[1]->format('Y-m-d')); // Fri
        $this->assertEquals('2025-01-06', $occurrences[2]->format('Y-m-d')); // Mon
        $this->assertEquals('2025-01-08', $occurrences[3]->format('Y-m-d')); // Wed
        $this->assertEquals('2025-01-10', $occurrences[4]->format('Y-m-d')); // Fri
        $this->assertEquals('2025-01-13', $occurrences[5]->format('Y-m-d')); // Mon
    }

    /**
     * Test daily recurrence with end date
     */
    public function testDailyRecurrenceWithEndDate()
    {
        $rrule = 'FREQ=DAILY;INTERVAL=2';
        $startDate = '2025-01-01';
        $endDate = '2025-01-10';
        
        $occurrences = $this->service->getOccurrences($rrule, $startDate, $endDate);
        
        $this->assertCount(5, $occurrences); // 1, 3, 5, 7, 9
        $this->assertEquals('2025-01-01', $occurrences[0]->format('Y-m-d'));
        $this->assertEquals('2025-01-09', $occurrences[4]->format('Y-m-d'));
    }

    /**
     * Test monthly recurrence on specific day
     */
    public function testMonthlyRecurrence()
    {
        $rrule = 'FREQ=MONTHLY;BYDAY=1MO;COUNT=3'; // First Monday of the month
        $startDate = '2025-01-01';
        
        $occurrences = $this->service->getOccurrences($rrule, $startDate);
        
        $this->assertCount(3, $occurrences);
        $this->assertEquals('2025-01-06', $occurrences[0]->format('Y-m-d'));
        $this->assertEquals('2025-02-03', $occurrences[1]->format('Y-m-d'));
        $this->assertEquals('2025-03-03', $occurrences[2]->format('Y-m-d'));
    }

    /**
     * Test occursOn
     */
    public function testOccursOn()
    {
        $rrule = 'FREQ=WEEKLY;BYDAY=MO';
        $startDate = '2025-01-01'; // Wed
        
        $this->assertTrue($this->service->occursOn($rrule, '2025-01-06', $startDate)); // Monday
        $this->assertFalse($this->service->occursOn($rrule, '2025-01-07', $startDate)); // Tuesday
        $this->assertTrue($this->service->occursOn($rrule, '2025-01-13', $startDate)); // Next Monday
    }
}

