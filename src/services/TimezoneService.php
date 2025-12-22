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
     * Convert UTC string to local time
     */
    public function convertFromUtc(string $utcString, string $timezone): DateTime
    {
        $dateTime = new DateTime($utcString, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone($timezone));
        return $dateTime;
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

