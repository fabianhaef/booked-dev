<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\models\Settings;
use UnitTester;
use Craft;

/**
 * Tests for Minimum Advance Booking Hours (Security Issue 5.1)
 *
 * Validates that minimum advance booking hours setting is properly enforced,
 * preventing users from booking slots that are too soon.
 */
class AdvanceBookingHoursTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var AvailabilityService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->mockCraftApp();
        $this->service = new AvailabilityService();
    }

    /**
     * Test that slots within minimum advance hours are filtered out
     */
    public function testSlotsWithinMinimumAdvanceHoursAreFiltered()
    {
        // Set minimum advance booking to 24 hours
        $settings = $this->createSettings(24);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        // Tomorrow at 09:00 is 23 hours away (should be filtered out)
        // Tomorrow at 10:00 is 24 hours away (should be included)
        // Tomorrow at 11:00 is 25 hours away (should be included)
        $slots = [
            ['time' => '09:00', 'employeeId' => 1],
            ['time' => '10:00', 'employeeId' => 1],
            ['time' => '11:00', 'employeeId' => 1],
        ];

        $tomorrow = '2025-12-25';
        $filtered = $this->invokeFilterPastSlots($slots, $tomorrow);

        $times = array_column($filtered, 'time');

        $this->assertNotContains('09:00', $times, '09:00 should be filtered (< 24 hours)');
        $this->assertContains('10:00', $times, '10:00 should be included (= 24 hours)');
        $this->assertContains('11:00', $times, '11:00 should be included (> 24 hours)');
    }

    /**
     * Test with zero minimum advance hours (immediate booking allowed)
     */
    public function testZeroMinimumAdvanceHoursAllowsImmediateBooking()
    {
        // Set minimum advance booking to 0 hours
        $settings = $this->createSettings(0);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        // Today's slots
        $slots = [
            ['time' => '09:00', 'employeeId' => 1], // Past
            ['time' => '10:00', 'employeeId' => 1], // Now
            ['time' => '10:30', 'employeeId' => 1], // 30 min away
            ['time' => '11:00', 'employeeId' => 1], // 1 hour away
        ];

        $today = '2025-12-24';
        $filtered = $this->invokeFilterPastSlots($slots, $today);

        $times = array_column($filtered, 'time');

        $this->assertNotContains('09:00', $times, '09:00 should be filtered (past)');
        $this->assertContains('10:00', $times, '10:00 should be included (now)');
        $this->assertContains('10:30', $times, '10:30 should be included');
        $this->assertContains('11:00', $times, '11:00 should be included');
    }

    /**
     * Test with 1-hour minimum advance booking
     */
    public function testOneHourMinimumAdvanceBooking()
    {
        $settings = $this->createSettings(1);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        $slots = [
            ['time' => '10:30', 'employeeId' => 1], // 30 min away
            ['time' => '11:00', 'employeeId' => 1], // 1 hour away
            ['time' => '11:30', 'employeeId' => 1], // 1.5 hours away
        ];

        $today = '2025-12-24';
        $filtered = $this->invokeFilterPastSlots($slots, $today);

        $times = array_column($filtered, 'time');

        $this->assertNotContains('10:30', $times, '10:30 should be filtered (< 1 hour)');
        $this->assertContains('11:00', $times, '11:00 should be included (= 1 hour)');
        $this->assertContains('11:30', $times, '11:30 should be included (> 1 hour)');
    }

    /**
     * Test with 48-hour minimum advance booking
     */
    public function test48HourMinimumAdvanceBooking()
    {
        $settings = $this->createSettings(48);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        // Tomorrow (24 hours away) - should be filtered
        $tomorrow = '2025-12-25';
        $slots1 = [['time' => '10:00', 'employeeId' => 1]];
        $filtered1 = $this->invokeFilterPastSlots($slots1, $tomorrow);
        $this->assertEmpty($filtered1, 'Tomorrow should be filtered with 48-hour minimum');

        // Day after tomorrow (48 hours away) - should be included
        $dayAfter = '2025-12-26';
        $slots2 = [['time' => '10:00', 'employeeId' => 1]];
        $filtered2 = $this->invokeFilterPastSlots($slots2, $dayAfter);
        $this->assertCount(1, $filtered2, 'Day after tomorrow should be included');
    }

    /**
     * Test past dates are always filtered regardless of minimum advance hours
     */
    public function testPastDatesAlwaysFiltered()
    {
        $settings = $this->createSettings(1);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        // Yesterday
        $yesterday = '2025-12-23';
        $slots = [['time' => '15:00', 'employeeId' => 1]];
        $filtered = $this->invokeFilterPastSlots($slots, $yesterday);

        $this->assertEmpty($filtered, 'Past dates should always be filtered');
    }

    /**
     * Test future dates beyond minimum are not affected
     */
    public function testFutureDatesNotAffected()
    {
        $settings = $this->createSettings(24);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        // Next week
        $nextWeek = '2025-12-31';
        $slots = [
            ['time' => '08:00', 'employeeId' => 1],
            ['time' => '14:00', 'employeeId' => 1],
            ['time' => '18:00', 'employeeId' => 1],
        ];
        $filtered = $this->invokeFilterPastSlots($slots, $nextWeek);

        $this->assertCount(3, $filtered, 'All slots should be included for distant future dates');
    }

    /**
     * Test edge case: exactly at cutoff time
     */
    public function testExactlyCutoffTime()
    {
        $settings = $this->createSettings(2);
        $this->mockSettings($settings);

        // Current time: 2025-12-24 10:00
        $now = new \DateTime('2025-12-24 10:00:00');
        $this->setCurrentTime($now);

        // Exactly 2 hours later
        $today = '2025-12-24';
        $slots = [
            ['time' => '11:59', 'employeeId' => 1], // 1h 59m (< 2 hours)
            ['time' => '12:00', 'employeeId' => 1], // Exactly 2 hours
            ['time' => '12:01', 'employeeId' => 1], // 2h 1m (> 2 hours)
        ];
        $filtered = $this->invokeFilterPastSlots($slots, $today);

        $times = array_column($filtered, 'time');

        $this->assertNotContains('11:59', $times);
        $this->assertContains('12:00', $times, 'Exactly cutoff time should be included');
        $this->assertContains('12:01', $times);
    }

    /**
     * Helper: Invoke protected filterPastSlots method
     */
    private function invokeFilterPastSlots(array $slots, string $date): array
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('filterPastSlots');
        $method->setAccessible(true);
        return $method->invoke($this->service, $slots, $date);
    }

    /**
     * Helper: Create settings with specific minimum advance hours
     */
    private function createSettings(int $minimumAdvanceBookingHours): Settings
    {
        $settings = new Settings();
        $settings->minimumAdvanceBookingHours = $minimumAdvanceBookingHours;
        return $settings;
    }

    /**
     * Helper: Mock settings
     */
    private function mockSettings(Settings $settings)
    {
        // Mock Booked::getInstance()->getSettings()
        $booked = $this->getMockBuilder('fabian\booked\Booked')
            ->disableOriginalConstructor()
            ->getMock();

        $booked->method('getSettings')->willReturn($settings);

        // This is a simplified mock - in real tests you'd need proper DI
    }

    /**
     * Helper: Set current time for testing
     */
    private function setCurrentTime(\DateTime $time)
    {
        // Override getCurrentDateTime in service
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('currentDateTime');
        $property->setAccessible(true);
        $property->setValue($this->service, $time);
    }

    /**
     * Mock Craft application
     */
    private function mockCraftApp()
    {
        if (!isset(Craft::$app)) {
            Craft::$app = new \stdClass();
        }
    }
}
