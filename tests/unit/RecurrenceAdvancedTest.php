<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\RecurrenceService;
use fabian\booked\tests\_support\Mocks\ServiceMockFactory;
use UnitTester;

/**
 * Advanced Recurrence Pattern Tests
 *
 * Tests complex RFC 5545 recurrence rules:
 * - BYMONTHDAY patterns
 * - BYWEEKNO patterns
 * - Complex BYDAY patterns
 * - EXDATE exceptions
 * - Series modifications
 * - Edge cases (leap years, year boundaries, etc.)
 */
class RecurrenceAdvancedTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var RecurrenceService
     */
    private $recurrenceService;

    protected function _before()
    {
        parent::_before();
        // Use ServiceMockFactory for consistent test mocks
        $this->recurrenceService = ServiceMockFactory::createRecurrenceService();
    }

    /**
     * Test BYMONTHDAY - "every 15th of the month"
     */
    public function testByMonthDayPattern()
    {
        // Arrange: Recurrence rule for 15th of every month
        $rule = 'FREQ=MONTHLY;BYMONTHDAY=15';
        $startDate = new \DateTime('2025-01-15');
        $endDate = new \DateTime('2025-06-30');

        // Act: Expand recurrence
        $occurrences = $this->recurrenceService->expandRecurrence(
            $rule,
            $startDate,
            $endDate
        );

        // Assert: Should have 6 occurrences (Jan 15, Feb 15, Mar 15, Apr 15, May 15, Jun 15)
        $this->assertCount(6, $occurrences);

        $expectedDates = [
            '2025-01-15',
            '2025-02-15',
            '2025-03-15',
            '2025-04-15',
            '2025-05-15',
            '2025-06-15',
        ];

        foreach ($occurrences as $index => $occurrence) {
            $this->assertEquals(
                $expectedDates[$index],
                $occurrence['date']->format('Y-m-d'),
                "Occurrence {$index} should be on the 15th"
            );
        }
    }

    /**
     * Test BYMONTHDAY with multiple days - "1st and 15th of month"
     */
    public function testByMonthDayMultipleDays()
    {
        // Arrange: 1st and 15th of every month
        $rule = 'FREQ=MONTHLY;BYMONTHDAY=1,15';
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-03-31');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should have 6 occurrences (2 per month × 3 months)
        $this->assertCount(6, $occurrences);

        $dates = array_map(fn($o) => $o['date']->format('Y-m-d'), $occurrences);

        $this->assertContains('2025-01-01', $dates);
        $this->assertContains('2025-01-15', $dates);
        $this->assertContains('2025-02-01', $dates);
        $this->assertContains('2025-02-15', $dates);
        $this->assertContains('2025-03-01', $dates);
        $this->assertContains('2025-03-15', $dates);
    }

    /**
     * Test BYDAY with position - "first and third Monday of month"
     */
    public function testByDayWithPosition()
    {
        // Arrange: First and third Monday of every month
        $rule = 'FREQ=MONTHLY;BYDAY=1MO,3MO';
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-03-31');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should have 6 occurrences (2 per month)
        $this->assertCount(6, $occurrences);

        // Verify all occurrences are Mondays
        foreach ($occurrences as $occurrence) {
            $this->assertEquals(
                'Monday',
                $occurrence['date']->format('l'),
                'Should be a Monday'
            );

            $dayOfMonth = (int)$occurrence['date']->format('d');

            // First Monday is days 1-7, third Monday is days 15-21
            $this->assertTrue(
                ($dayOfMonth >= 1 && $dayOfMonth <= 7) ||
                ($dayOfMonth >= 15 && $dayOfMonth <= 21),
                "Day {$dayOfMonth} should be first or third week"
            );
        }
    }

    /**
     * Test BYDAY last occurrence - "last Friday of month"
     */
    public function testByDayLastOccurrence()
    {
        // Arrange: Last Friday of every month
        $rule = 'FREQ=MONTHLY;BYDAY=-1FR';
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-03-31');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should have 3 occurrences (1 per month)
        $this->assertCount(3, $occurrences);

        // Verify each is a Friday
        foreach ($occurrences as $occurrence) {
            $this->assertEquals('Friday', $occurrence['date']->format('l'));

            // Verify it's the last Friday (next Friday is in next month)
            $nextWeek = clone $occurrence['date'];
            $nextWeek->modify('+7 days');

            $this->assertNotEquals(
                $occurrence['date']->format('m'),
                $nextWeek->format('m'),
                'Should be last Friday of the month'
            );
        }
    }

    /**
     * Test EXDATE - excluding specific dates from recurrence
     */
    public function testExceptionDates()
    {
        // Arrange: Every Monday, except Feb 10 and Feb 24
        $rule = 'FREQ=WEEKLY;BYDAY=MO';
        $startDate = new \DateTime('2025-02-03'); // Monday
        $endDate = new \DateTime('2025-02-28');

        $exceptions = [
            new \DateTime('2025-02-10'),
            new \DateTime('2025-02-24'),
        ];

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence(
            $rule,
            $startDate,
            $endDate,
            $exceptions
        );

        // Assert: Should have 2 Mondays (3rd and 17th), excluding 10th and 24th
        $this->assertCount(2, $occurrences);

        $dates = array_map(fn($o) => $o['date']->format('Y-m-d'), $occurrences);

        $this->assertContains('2025-02-03', $dates);
        $this->assertContains('2025-02-17', $dates);
        $this->assertNotContains('2025-02-10', $dates);
        $this->assertNotContains('2025-02-24', $dates);
    }

    /**
     * Test COUNT vs UNTIL - recurrence limits
     */
    public function testCountVsUntil()
    {
        $startDate = new \DateTime('2025-01-01');

        // Test with COUNT
        $ruleWithCount = 'FREQ=DAILY;COUNT=5';
        $occurrencesCount = $this->recurrenceService->expandRecurrence(
            $ruleWithCount,
            $startDate,
            new \DateTime('2025-12-31') // Far future
        );

        // Should have exactly 5 occurrences
        $this->assertCount(5, $occurrencesCount);

        // Test with UNTIL
        $ruleWithUntil = 'FREQ=DAILY;UNTIL=20250105';
        $occurrencesUntil = $this->recurrenceService->expandRecurrence(
            $ruleWithUntil,
            $startDate,
            new \DateTime('2025-12-31')
        );

        // Should have 5 occurrences (Jan 1-5)
        $this->assertCount(5, $occurrencesUntil);

        // Both methods should produce same result
        $this->assertEquals(
            array_map(fn($o) => $o['date']->format('Y-m-d'), $occurrencesCount),
            array_map(fn($o) => $o['date']->format('Y-m-d'), $occurrencesUntil)
        );
    }

    /**
     * Test infinite recurrence handling
     */
    public function testInfiniteRecurrenceHandling()
    {
        // Arrange: Infinite daily recurrence (no COUNT or UNTIL)
        $rule = 'FREQ=DAILY';
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-01-31'); // System must limit expansion

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should expand only within the date range
        $this->assertCount(31, $occurrences); // All days in January

        // Should not go beyond endDate
        foreach ($occurrences as $occurrence) {
            $this->assertLessThanOrEqual($endDate, $occurrence['date']);
        }
    }

    /**
     * Test recurrence across year boundaries
     */
    public function testRecurrenceAcrossYearBoundary()
    {
        // Arrange: Weekly recurrence spanning New Year
        $rule = 'FREQ=WEEKLY;BYDAY=MO';
        $startDate = new \DateTime('2024-12-23'); // Monday
        $endDate = new \DateTime('2025-01-20');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should have Mondays across year boundary
        $this->assertGreaterThan(0, $occurrences);

        $dates = array_map(fn($o) => $o['date']->format('Y-m-d'), $occurrences);

        // Should have December Monday
        $this->assertContains('2024-12-23', $dates);

        // Should have January Mondays
        $this->assertContains('2025-01-06', $dates);
        $this->assertContains('2025-01-13', $dates);
        $this->assertContains('2025-01-20', $dates);
    }

    /**
     * Test leap year handling
     */
    public function testLeapYearHandling()
    {
        // Arrange: Feb 29 recurrence (leap year)
        $rule = 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29';
        $startDate = new \DateTime('2024-02-29'); // 2024 is leap year
        $endDate = new \DateTime('2028-12-31');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should only occur in leap years (2024, 2028)
        // 2025, 2026, 2027 are not leap years
        $this->assertCount(2, $occurrences);

        $years = array_map(fn($o) => (int)$o['date']->format('Y'), $occurrences);

        $this->assertContains(2024, $years);
        $this->assertContains(2028, $years);
        $this->assertNotContains(2025, $years);
        $this->assertNotContains(2026, $years);
        $this->assertNotContains(2027, $years);
    }

    /**
     * Test complex combined rules
     */
    public function testComplexCombinedRules()
    {
        // Arrange: Every weekday (Mon-Fri) of the first week of each month
        $rule = 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=1,2,3,4,5';
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-03-31');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should have ~15 occurrences (5 weekdays × 3 months)
        $this->assertGreaterThan(10, count($occurrences));

        // Verify all are weekdays
        foreach ($occurrences as $occurrence) {
            $dayOfWeek = (int)$occurrence['date']->format('N'); // 1=Mon, 7=Sun
            $this->assertLessThanOrEqual(5, $dayOfWeek, 'Should be a weekday');

            // Verify in first week (days 1-7)
            $dayOfMonth = (int)$occurrence['date']->format('d');
            $this->assertLessThanOrEqual(7, $dayOfMonth, 'Should be in first week');
        }
    }

    /**
     * Test BYWEEKNO - specific weeks of year
     */
    public function testByWeekNumber()
    {
        // Arrange: Week 10 and 20 of the year
        $rule = 'FREQ=YEARLY;BYWEEKNO=10,20;BYDAY=MO';
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-12-31');

        // Act
        $occurrences = $this->recurrenceService->expandRecurrence($rule, $startDate, $endDate);

        // Assert: Should have 2 Mondays (week 10 and week 20)
        $this->assertCount(2, $occurrences);

        foreach ($occurrences as $occurrence) {
            $weekNumber = (int)$occurrence['date']->format('W');
            $this->assertTrue(
                $weekNumber === 10 || $weekNumber === 20,
                "Should be week 10 or 20, got week {$weekNumber}"
            );
        }
    }

    /**
     * Test interval with different frequencies
     */
    public function testIntervalWithDifferentFrequencies()
    {
        $startDate = new \DateTime('2025-01-01');

        // Every 2 weeks
        $rule1 = 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO';
        $occurrences1 = $this->recurrenceService->expandRecurrence(
            $rule1,
            $startDate,
            new \DateTime('2025-02-28')
        );

        // Should have 4-5 occurrences (every other Monday)
        $this->assertGreaterThan(3, count($occurrences1));
        $this->assertLessThan(6, count($occurrences1));

        // Every 3 months
        $rule2 = 'FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=1';
        $occurrences2 = $this->recurrenceService->expandRecurrence(
            $rule2,
            $startDate,
            new \DateTime('2025-12-31')
        );

        // Should have 4 occurrences (Jan, Apr, Jul, Oct)
        $this->assertCount(4, $occurrences2);

        $months = array_map(fn($o) => (int)$o['date']->format('m'), $occurrences2);
        $this->assertEquals([1, 4, 7, 10], $months);
    }
}
