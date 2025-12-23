<?php

namespace fabian\booked\services;

use craft\base\Component;
use DateTime;
use DateTimeZone;

/**
 * Timezone Service
 * 
 * Handles timezone conversions between locations, UTC, and users.
 */
class TimezoneService extends Component
{
    /**
     * Convert local time to UTC
     */
    public function convertToUtc(string $date, string $time, string $timezone): DateTime
    {
        $dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($timezone));
        $dateTime->setTimezone(new DateTimeZone('UTC'));
        return $dateTime;
    }

    /**
     * Convert UTC string or DateTime to local time
     */
    public function convertFromUtc($utcDateTime, string $timezone): DateTime
    {
        if ($utcDateTime instanceof DateTime) {
            $dateTime = clone $utcDateTime;
            $dateTime->setTimezone(new DateTimeZone($timezone));
        } else {
            $dateTime = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone($timezone));
        }
        return $dateTime;
    }

    /**
     * Convert time between two timezones
     */
    public function convertBetweenTimezones(string $date, string $time, string $fromTimezone, string $toTimezone): string
    {
        $dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($fromTimezone));
        $dateTime->setTimezone(new DateTimeZone($toTimezone));
        return $dateTime->format('H:i');
    }

    /**
     * Check if a time is valid for a given date in a timezone (handles DST transitions)
     */
    public function isValidTimeForDate(string $date, string $time, string $timezone): bool
    {
        try {
            $dateTime = new DateTime($date . ' ' . $time, new DateTimeZone($timezone));

            // Check if the time was adjusted due to DST
            $expectedTime = $time;
            $actualTime = $dateTime->format('H:i');

            // If times don't match, it means this time doesn't exist (DST spring forward)
            return $expectedTime === $actualTime;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Detect timezone (for now returns UTC, can be extended to detect from user)
     */
    public function detectTimezone(): string
    {
        // In a real implementation, this would detect from:
        // - User settings
        // - Browser/request headers
        // - IP geolocation
        return 'UTC';
    }

    /**
     * Shift slots from one timezone to another
     */
    public function shiftSlots(array $slots, string $date, string $fromTz, string $toTz): array
    {
        if ($fromTz === $toTz) {
            return $slots;
        }

        $shifted = [];
        foreach ($slots as $slot) {
            $start = $this->convertToUtc($date, $slot['time'], $fromTz);
            $start->setTimezone(new DateTimeZone($toTz));

            $end = $this->convertToUtc($date, $slot['endTime'], $fromTz);
            $end->setTimezone(new DateTimeZone($toTz));

            $newSlot = $slot;
            $newSlot['time'] = $start->format('H:i');
            $newSlot['endTime'] = $end->format('H:i');

            // If the slot shifts to a different day, we might need special handling
            // For now, we assume the user is looking at slots for a specific day
            // and we only return slots that still fall on that day (mostly).
            // Actually, for simplicity, we just shift the time strings.
            $shifted[] = $newSlot;
        }

        return $shifted;
    }
}

