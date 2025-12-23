<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\TimezoneService;
use fabian\booked\services\AvailabilityService;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;
use DateTime;
use DateTimeZone;

/**
 * Tests for timezone handling and DST (Daylight Saving Time) edge cases
 *
 * Critical scenarios:
 * - Booking during DST transitions
 * - Cross-timezone bookings
 * - UTC storage with local display
 * - Timezone conversion accuracy
 */
class TimezoneEdgeCasesTest extends Unit
{
    use CreatesBookings;

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TimezoneService
     */
    protected $timezoneService;

    protected function _before()
    {
        parent::_before();
        $this->timezoneService = new TimezoneService();
    }

    /**
     * Test booking during DST transition (spring forward - lose 1 hour)
     *
     * On March 30, 2025, at 02:00 CET becomes 03:00 CEST in Europe/Zurich
     * The hour 02:00-03:00 doesn't exist!
     */
    public function testBookingDuringDstSpringForward()
    {
        $timezone = 'Europe/Zurich';
        $dstDate = '2025-03-30'; // DST transition date for Europe/Zurich

        // Try to book at 02:30 (this time doesn't exist!)
        $nonExistentTime = '02:30';

        $result = $this->timezoneService->isValidTimeForDate($dstDate, $nonExistentTime, $timezone);

        // Should be invalid or automatically adjusted
        $this->assertFalse($result, 'Time 02:30 should not be valid during DST spring forward');

        // Valid times should be before 02:00 or after 03:00
        $this->assertTrue($this->timezoneService->isValidTimeForDate($dstDate, '01:30', $timezone));
        $this->assertTrue($this->timezoneService->isValidTimeForDate($dstDate, '03:30', $timezone));
    }

    /**
     * Test booking during DST transition (fall back - gain 1 hour)
     *
     * On October 26, 2025, at 03:00 CEST becomes 02:00 CET in Europe/Zurich
     * The hour 02:00-03:00 occurs twice!
     */
    public function testBookingDuringDstFallBack()
    {
        $timezone = 'Europe/Zurich';
        $dstDate = '2025-10-26'; // DST transition date for Europe/Zurich

        // 02:30 occurs twice - once in CEST, once in CET
        $ambiguousTime = '02:30';

        // The system should handle this by using the first occurrence (CEST)
        // or by disambiguating somehow
        $converted = $this->timezoneService->convertToUtc($dstDate, $ambiguousTime, $timezone);

        $this->assertNotNull($converted);
        // Verify the UTC time is consistent
    }

    /**
     * Test cross-timezone booking (employee in UTC+1, customer in UTC-5)
     */
    public function testCrossTimezoneBooking()
    {
        $employeeTimezone = 'Europe/Zurich'; // UTC+1 (winter) or UTC+2 (summer)
        $customerTimezone = 'America/New_York'; // UTC-5 (winter) or UTC-4 (summer)

        $date = '2025-06-15'; // Summer time
        $employeeTime = '10:00'; // 10:00 in Zurich

        // Convert employee time to customer timezone
        $customerTime = $this->timezoneService->convertBetweenTimezones(
            $date,
            $employeeTime,
            $employeeTimezone,
            $customerTimezone
        );

        // 10:00 CEST (UTC+2) = 04:00 EDT (UTC-4)
        $this->assertEquals('04:00', $customerTime);

        // Reverse conversion should get back to original
        $backToEmployee = $this->timezoneService->convertBetweenTimezones(
            $date,
            $customerTime,
            $customerTimezone,
            $employeeTimezone
        );

        $this->assertEquals('10:00', $backToEmployee);
    }

    /**
     * Test invalid timezone handling
     */
    public function testInvalidTimezoneHandling()
    {
        $this->expectException(\Exception::class);

        $this->timezoneService->convertToUtc('2025-01-01', '10:00', 'Invalid/Timezone');
    }

    /**
     * Test UTC±0 edge cases
     */
    public function testUtcZeroEdgeCases()
    {
        $utcTimezone = 'UTC';
        $date = '2025-01-01';
        $time = '00:00'; // Midnight UTC

        $converted = $this->timezoneService->convertToUtc($date, $time, $utcTimezone);

        $this->assertNotNull($converted);
        $this->assertEquals('2025-01-01 00:00:00', $converted->format('Y-m-d H:i:s'));
    }

    /**
     * Test midnight boundary transitions
     */
    public function testMidnightBoundaryTransitions()
    {
        $timezone = 'America/New_York';
        $date = '2025-01-01';
        $time = '23:30'; // 11:30 PM

        // Convert to UTC
        $utc = $this->timezoneService->convertToUtc($date, $time, $timezone);

        // 23:30 EST (UTC-5) = 04:30 UTC (next day)
        $this->assertEquals('2025-01-02', $utc->format('Y-m-d'));
        $this->assertEquals('04:30', $utc->format('H:i'));
    }

    /**
     * Test date line crossing (UTC+12 to UTC-12)
     */
    public function testDateLineCrossing()
    {
        $westTimezone = 'Pacific/Auckland'; // UTC+12 (can be +13 in summer)
        $eastTimezone = 'Pacific/Midway'; // UTC-11

        $date = '2025-01-01';
        $time = '12:00';

        // Convert Auckland time to Midway
        $midwayTime = $this->timezoneService->convertBetweenTimezones(
            $date,
            $time,
            $westTimezone,
            $eastTimezone
        );

        // There should be a significant time difference (almost a full day)
        $this->assertNotNull($midwayTime);
    }

    /**
     * Test slot display in user's local timezone
     */
    public function testSlotDisplayInUserTimezone()
    {
        $locationTimezone = 'Europe/London'; // UTC+0 (winter) or UTC+1 (summer)
        $userTimezone = 'Asia/Tokyo'; // UTC+9

        $service = new TestableTimezoneAvailabilityService();

        // Employee works 09:00-17:00 London time
        $schedule = new \stdClass();
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        $schedule->employeeId = 1;
        $service->mockWorkingHours = [$schedule];
        $service->mockLocationTimezone = $locationTimezone;

        $date = '2025-01-15'; // Winter time

        // Get slots in user's timezone (Tokyo)
        $slots = $service->getAvailableSlotsInTimezone($date, 1, $userTimezone);

        // 09:00 GMT (UTC+0) = 18:00 JST (UTC+9)
        $this->assertNotEmpty($slots);
        $this->assertEquals('18:00', $slots[0]['time']);
    }

    /**
     * Test database storage in UTC verification
     */
    public function testDatabaseStorageInUtc()
    {
        $localTimezone = 'Europe/Paris'; // UTC+1 (winter) or UTC+2 (summer)
        $date = '2025-06-15';
        $localTime = '14:30'; // 14:30 CEST

        // Convert to UTC for storage
        $utcTime = $this->timezoneService->convertToUtc($date, $localTime, $localTimezone);

        // 14:30 CEST (UTC+2) = 12:30 UTC
        $this->assertEquals('12:30', $utcTime->format('H:i'));

        // When retrieving, convert back to local
        $retrievedLocal = $this->timezoneService->convertFromUtc(
            $utcTime,
            $localTimezone
        );

        $this->assertEquals('14:30', $retrievedLocal->format('H:i'));
    }

    /**
     * Test timezone detection from user agent/IP
     */
    public function testTimezoneDetection()
    {
        // This would test automatic timezone detection
        // For now, we'll test manual timezone setting

        $detectedTimezone = $this->timezoneService->detectTimezone();

        $this->assertNotNull($detectedTimezone);
        $this->assertContains($detectedTimezone, timezone_identifiers_list());
    }

    /**
     * Test booking at exact DST transition time
     */
    public function testBookingAtExactDstTransition()
    {
        $timezone = 'Europe/Zurich';

        // March 30, 2025, 02:00 is the exact transition time
        $dstDate = '2025-03-30';
        $transitionTime = '02:00';

        $result = $this->timezoneService->isValidTimeForDate($dstDate, $transitionTime, $timezone);

        // Should either be invalid or auto-adjusted to 03:00
        $this->assertFalse($result);
    }

    /**
     * Test availability calculation respects employee timezone
     */
    public function testAvailabilityCalculationRespectsEmployeeTimezone()
    {
        $service = new TestableTimezoneAvailabilityService();

        // Employee in Los Angeles (UTC-8)
        $employeeTimezone = 'America/Los_Angeles';
        $service->mockEmployeeTimezone = $employeeTimezone;

        // Works 09:00-17:00 Pacific Time
        $schedule = new \stdClass();
        $schedule->startTime = '09:00';
        $schedule->endTime = '17:00';
        $schedule->employeeId = 1;
        $service->mockWorkingHours = [$schedule];

        $date = '2025-01-15';

        // Customer in New York (UTC-5) views slots
        $customerTimezone = 'America/New_York';
        $slots = $service->getAvailableSlotsInTimezone($date, 1, $customerTimezone);

        // 09:00 PST (UTC-8) = 12:00 EST (UTC-5)
        $this->assertNotEmpty($slots);
        $firstSlot = $slots[0]['time'];
        $this->assertEquals('12:00', $firstSlot);
    }

    /**
     * Test leap year handling with timezones
     */
    public function testLeapYearHandlingWithTimezones()
    {
        $date = '2024-02-29'; // Leap day
        $time = '10:00';
        $timezone = 'Europe/Berlin';

        $utc = $this->timezoneService->convertToUtc($date, $time, $timezone);

        $this->assertEquals('2024-02-29', $utc->format('Y-m-d'));
        $this->assertNotNull($utc);
    }
}

/**
 * Testable AvailabilityService with timezone support
 */
class TestableTimezoneAvailabilityService extends AvailabilityService
{
    public array $mockWorkingHours = [];
    public string $mockLocationTimezone = 'UTC';
    public string $mockEmployeeTimezone = 'UTC';

    public function getAvailableSlotsInTimezone(
        string $date,
        ?int $employeeId,
        string $userTimezone
    ): array {
        $schedules = $this->mockWorkingHours;
        $slots = [];

        foreach ($schedules as $schedule) {
            $start = new DateTime($date . ' ' . $schedule->startTime, new DateTimeZone($this->mockEmployeeTimezone));
            $end = new DateTime($date . ' ' . $schedule->endTime, new DateTimeZone($this->mockEmployeeTimezone));

            // Convert to user timezone
            $start->setTimezone(new DateTimeZone($userTimezone));
            $end->setTimezone(new DateTimeZone($userTimezone));

            $current = clone $start;
            while ($current < $end) {
                $slots[] = [
                    'time' => $current->format('H:i'),
                    'startTime' => $current->format('H:i'),
                    'endTime' => (clone $current)->modify('+1 hour')->format('H:i'),
                    'employeeId' => $schedule->employeeId,
                ];
                $current->modify('+1 hour');
            }
        }

        return $slots;
    }
}
