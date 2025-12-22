<?php

namespace fabian\booked\services;

use craft\base\Component;
use RRule\RRule;
use DateTime;

/**
 * Recurrence Service
 * 
 * Handles RFC 5545 recurrence rules (RRULE) for availability and bookings.
 */
class RecurrenceService extends Component
{
    /**
     * Generate occurrence dates for a given RRULE string
     * 
     * @param string $rruleString The RRULE string
     * @param string|DateTime $startDate The start date of the series
     * @param string|DateTime|null $endDate The end date to limit occurrences (optional)
     * @param int|null $limit Maximum number of occurrences (optional)
     * @return DateTime[] Array of occurrence dates
     */
    public function getOccurrences(string $rruleString, $startDate, $endDate = null, ?int $limit = null): array
    {
        try {
            $rrule = new RRule($rruleString, $startDate);
            
            if ($endDate !== null) {
                return $rrule->getOccurrencesBetween($startDate, $endDate, $limit);
            }

            return $rrule->getOccurrences($limit);
        } catch (\Exception $e) {
            \Craft::error('RRULE Error: ' . $e->getMessage(), __METHOD__);
            return [];
        }
    }

    /**
     * Check if a specific date has an occurrence for the given RRULE
     * 
     * @param string $rruleString
     * @param string|DateTime $date The date to check
     * @param string|DateTime $startDate The start date of the series
     * @return bool
     */
    public function occursOn(string $rruleString, $date, $startDate): bool
    {
        try {
            $rrule = new RRule($rruleString, $startDate);
            return $rrule->occursAt($date);
        } catch (\Exception $e) {
            \Craft::error('RRULE Error: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}

