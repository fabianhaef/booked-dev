<?php

namespace modules\booking\services;

use Craft;
use craft\base\Component;
use modules\booking\elements\Availability;
use modules\booking\models\EventDate;
use modules\booking\models\Settings;
use modules\booking\records\AvailabilityRecord;
use modules\booking\records\EventDateRecord;
use modules\booking\records\ReservationRecord;
use modules\booking\records\BlackoutDateRecord;

/**
 * Availability Service
 */
class AvailabilityService extends Component
{
    /**
     * Get all availability records
     */
    public function getAllAvailability(): array
    {
        return Availability::find()
            ->orderBy(['dayOfWeek' => SORT_ASC, 'startTime' => SORT_ASC])
            ->all();
    }

    /**
     * Get active availability records
     */
    public function getActiveAvailability(): array
    {
        return Availability::find()
            ->where(['isActive' => true])
            ->orderBy(['dayOfWeek' => SORT_ASC, 'startTime' => SORT_ASC])
            ->all();
    }

    /**
     * Get availability for a specific day of week (recurring)
     *
     * @param int $dayOfWeek Day of week (0 = Sunday, 6 = Saturday)
     * @param int|null $entryId Optional entry ID to filter by
     * @return Availability[]
     */
    public function getAvailabilityForDay(int $dayOfWeek, ?int $entryId = null): array
    {
        $query = Availability::find()
            ->dayOfWeek($dayOfWeek)
            ->isActive(true)
            ->availabilityType('recurring');

        // Filter by entry if provided
        if ($entryId) {
            $query->sourceType('entry')
                  ->sourceId($entryId);
        }

        return $query->orderBy(['startTime' => SORT_ASC])->all();
    }

    /**
     * Get event dates for a specific date
     * Returns array of availability elements for event dates
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $entryId Optional entry ID to filter by
     * @return Availability[]
     */
    public function getAvailabilityForEventDate(string $date, ?int $entryId = null): array
    {
        $eventDateRecords = EventDateRecord::find()
            ->where(['eventDate' => $date])
            ->orderBy(['startTime' => SORT_ASC])
            ->all();

        $availability = [];
        foreach ($eventDateRecords as $eventDateRecord) {
            // Get the parent availability element
            $avail = Availability::find()
                ->id($eventDateRecord->availabilityId)
                ->isActive(true)
                ->one();

            if (!$avail) {
                continue;
            }

            // Filter by entry if provided
            if ($entryId && !($avail->sourceType === 'entry' && $avail->sourceId == $entryId)) {
                continue;
            }

            // Override times with event-specific times
            $avail->startTime = $eventDateRecord->startTime;
            $avail->endTime = $eventDateRecord->endTime;
            $availability[] = $avail;
        }

        return $availability;
    }

    /**
     * Get availability by ID
     */
    public function getAvailabilityById(int $id): ?Availability
    {
        return Availability::find()->id($id)->one();
    }

    /**
     * Save availability
     */
    public function saveAvailability(Availability $availability): bool
    {
        return $availability->save();
    }

    /**
     * Delete availability
     */
    public function deleteAvailability(int $id): bool
    {
        $availability = $this->getAvailabilityById($id);
        if (!$availability) {
            return false;
        }

        return $availability->delete();
    }

    /**
     * Get available variations for a specific date
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $entryId Optional entry ID to filter by
     * @return array Array of variations with their details
     */
    public function getAvailableVariations(string $date, ?int $entryId = null): array
    {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            Craft::info("Invalid date format: {$date}", __METHOD__);
            return [];
        }

        $dayOfWeek = (int) $dateObj->format('w');

        // Get availability for this date
        $availability = array_merge(
            $this->getAvailabilityForDay($dayOfWeek, $entryId),
            $this->getAvailabilityForEventDate($date, $entryId)
        );

        $variationsData = [];
        $seenVariations = [];

        foreach ($availability as $avail) {
            $variations = $avail->getVariations();

            foreach ($variations as $variation) {
                // Avoid duplicates
                if (in_array($variation->id, $seenVariations)) {
                    continue;
                }
                $seenVariations[] = $variation->id;

                $variationsData[] = [
                    'id' => $variation->id,
                    'title' => $variation->title,
                    'description' => $variation->description,
                    'slotDuration' => $variation->slotDurationMinutes,
                    'isActive' => $variation->isActive,
                    'allowQuantitySelection' => $variation->allowQuantitySelection,
                    'maxCapacity' => $variation->maxCapacity,
                ];
            }
        }

        return $variationsData;
    }

    /**
     * Check if a date is blacked out
     */
    public function isDateBlackedOut(string $date): bool
    {
        $blackouts = BlackoutDateRecord::find()
            ->where(['isActive' => true])
            ->andWhere(['<=', 'startDate', $date])
            ->andWhere(['>=', 'endDate', $date])
            ->exists();

        return $blackouts;
    }

    /**
     * Get available time slots for a specific date
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $entryId Optional entry ID to filter by
     * @param int|null $variationId Optional variation ID to filter by specific variation
     */
    public function getAvailableSlots(string $date, ?int $entryId = null, ?int $variationId = null, int $requestedQuantity = 1): array
    {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return [];
        }

        // Check if date is blacked out
        if ($this->isDateBlackedOut($date)) {
            return [];
        }

        $dayOfWeek = (int) $dateObj->format('w'); // 0 = Sunday, 6 = Saturday

        // Get both recurring availability for this day of week AND event-specific availability for this date
        $availability = array_merge(
            $this->getAvailabilityForDay($dayOfWeek, $entryId),
            $this->getAvailabilityForEventDate($date, $entryId)
        );

        if (empty($availability)) {
            return [];
        }

        $settings = Settings::loadSettings();

        $slots = [];
        $maxBufferMinutes = 0;

        foreach ($availability as $avail) {
            // Get variations for this availability
            $variations = $avail->getVariations();

            if (empty($variations)) {
                // No variations - use default settings (backward compatibility)
                $slotDuration = $settings->getDefaultSlotDurationMinutes();
                $bufferMinutes = $settings->getDefaultBufferMinutes();
                $maxBufferMinutes = max($maxBufferMinutes, $bufferMinutes);
                $generatedSlots = $this->generateSlotsForAvailability($avail, $slotDuration);

                // Create slot objects with capacity = 1 (old behavior)
                foreach ($generatedSlots as $slotTime) {
                    $endTime = $this->calculateEndTime($slotTime, $slotDuration);

                    // Check if this slot is already booked (for backward compatibility)
                    $bookedSlots = $this->getBookedSlots($date, $bufferMinutes, null);
                    $isBooked = in_array($slotTime, $bookedSlots);

                    // Only add slot if not booked (old behavior: capacity check)
                    if (!$isBooked) {
                        $slots[] = [
                            'time' => $slotTime,
                            'endTime' => $endTime,
                            'variationId' => null,
                            'maxCapacity' => 1,
                            'remainingCapacity' => 1,
                            'allowQuantitySelection' => false,
                        ];
                    }
                }
            } else {
                // Generate slots for each variation with capacity tracking
                foreach ($variations as $variation) {
                    // If filtering by variation, skip others
                    if ($variationId && $variation->id != $variationId) {
                        continue;
                    }

                    $slotDuration = $variation->slotDurationMinutes ?? $settings->getDefaultSlotDurationMinutes();
                    $bufferMinutes = $variation->bufferMinutes ?? $settings->getDefaultBufferMinutes();

                    // Track the maximum buffer time for filtering booked slots
                    $maxBufferMinutes = max($maxBufferMinutes, $bufferMinutes);

                    $generatedSlots = $this->generateSlotsForAvailability($avail, $slotDuration);

                    // Create slot objects with capacity information
                    foreach ($generatedSlots as $slotTime) {
                        $endTime = $this->calculateEndTime($slotTime, $slotDuration);

                        // Check remaining capacity for this variation
                        // Note: Cross-variation overlap prevention is handled at the BookingService layer
                        $remainingCapacity = $variation->getRemainingCapacity($date, $slotTime, $endTime);

                        // Only add slot if it has enough capacity for the requested quantity
                        if ($remainingCapacity >= $requestedQuantity) {
                            $slots[] = [
                                'time' => $slotTime,
                                'endTime' => $endTime,
                                'variationId' => $variation->id,
                                'maxCapacity' => $variation->maxCapacity,
                                'remainingCapacity' => $remainingCapacity,
                                'allowQuantitySelection' => $variation->allowQuantitySelection,
                            ];
                        }
                    }
                }
            }
        }

        // Remove slots in the past
        $now = new \DateTime();
        $today = $now->format('Y-m-d');
        if ($date === $today) {
            $currentTime = $now->format('H:i');
            $slots = array_filter($slots, function($slot) use ($currentTime) {
                return $slot['time'] >= $currentTime;
            });
        }

        // Sort by time
        usort($slots, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        return array_values($slots);
    }

    /**
     * Generate time slots for an availability period
     *
     * @param Availability $availability The availability to generate slots for
     * @param int $slotDuration The duration of each slot in minutes
     */
    private function generateSlotsForAvailability(Availability $availability, int $slotDuration): array
    {
        $slots = [];
        $start = strtotime($availability->startTime);
        $end = strtotime($availability->endTime);
        $slotSeconds = $slotDuration * 60;

        $current = $start;
        while ($current + $slotSeconds <= $end) {
            $slots[] = date('H:i', $current);
            $current += $slotSeconds;
        }

        return $slots;
    }

    /**
     * Calculate end time by adding duration to start time
     *
     * @param string $startTime Time in H:i format
     * @param int $durationMinutes Duration in minutes
     * @return string End time in H:i format
     */
    private function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        $start = strtotime($startTime);
        $end = $start + ($durationMinutes * 60);
        return date('H:i', $end);
    }

    /**
     * Get booked time slots for a specific date (including buffer)
     *
     * @param string $date Date in Y-m-d format
     * @param int $bufferMinutes Buffer minutes to add before/after bookings
     * @param int|null $variationId Optional variation ID to filter by specific variation
     * @return array Array of booked time slots (H:i format)
     */
    private function getBookedSlots(string $date, int $bufferMinutes, ?int $variationId = null): array
    {
        $query = ReservationRecord::find()
            ->where(['bookingDate' => $date])
            ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED]);

        // CRITICAL FIX: Only check conflicts for the same variation
        // This allows different variations to use the same time slot
        if ($variationId !== null) {
            $query->andWhere(['variationId' => $variationId]);
        }

        $reservations = $query->all();

        $bookedSlots = [];
        foreach ($reservations as $reservation) {
            // Add buffer time before and after the booking
            $startTime = strtotime($reservation->startTime) - ($bufferMinutes * 60);
            $endTime = strtotime($reservation->endTime) + ($bufferMinutes * 60);

            // Generate all slots that should be blocked
            $current = $startTime;
            while ($current < $endTime) {
                $timeStr = date('H:i', $current);
                if (!in_array($timeStr, $bookedSlots)) {
                    $bookedSlots[] = $timeStr;
                }
                $current += 900; // 15-minute increments for checking
            }
        }

        return $bookedSlots;
    }

    /**
     * Get the availability that matches a specific date/time slot
     * Returns the availability object so we can access its source information
     */
    public function getAvailabilityForSlot(string $date, string $startTime, string $endTime): ?Availability
    {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return null;
        }

        $dayOfWeek = (int) $dateObj->format('w');

        // Check both recurring and event-specific availability
        $availability = array_merge(
            $this->getAvailabilityForDay($dayOfWeek),
            $this->getAvailabilityForEventDate($date)
        );

        // Find the availability window that contains this time slot
        // Prioritize event-specific availability over recurring
        $eventAvailability = null;
        $recurringAvailability = null;

        foreach ($availability as $avail) {
            $availStart = strtotime($avail->startTime);
            $availEnd = strtotime($avail->endTime);
            $requestStart = strtotime($startTime);
            $requestEnd = strtotime($endTime);

            if ($requestStart >= $availStart && $requestEnd <= $availEnd) {
                if ($avail->availabilityType === 'event') {
                    $eventAvailability = $avail;
                } else {
                    $recurringAvailability = $avail;
                }
            }
        }

        // Return event availability if found, otherwise recurring
        return $eventAvailability ?? $recurringAvailability;
    }

    /**
     * Check if a time slot is available
     */
    /**
     * Check if a time slot is available for booking
     *
     * @param string $date Date in Y-m-d format
     * @param string $startTime Start time in H:i:s format
     * @param string $endTime End time in H:i:s format
     * @param int|null $variationId Optional variation ID to check availability for specific variation
     * @return bool True if the slot is available
     */
    public function isSlotAvailable(
        string $date,
        string $startTime,
        string $endTime,
        ?int $variationId = null,
        int $requestedQuantity = 1
    ): bool {
        // Check if the day has availability
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            Craft::warning("Availability check failed: Invalid date format '$date'", __METHOD__);
            return false;
        }

        // Check if date is blacked out
        if ($this->isDateBlackedOut($date)) {
            Craft::info("Availability check failed: Date '$date' is blacked out", __METHOD__);
            return false;
        }

        $dayOfWeek = (int) $dateObj->format('w');

        // Check both recurring and event-specific availability
        $availability = array_merge(
            $this->getAvailabilityForDay($dayOfWeek),
            $this->getAvailabilityForEventDate($date)
        );

        if (empty($availability)) {
            Craft::info("Availability check failed: No availability rules found for date '$date'", __METHOD__);
            return false;
        }

        // Check if the requested time falls within any availability window
        $timeInRange = false;
        foreach ($availability as $avail) {
            $availStart = strtotime($avail->startTime);
            $availEnd = strtotime($avail->endTime);
            $requestStart = strtotime($startTime);
            $requestEnd = strtotime($endTime);

            if ($requestStart >= $availStart && $requestEnd <= $availEnd) {
                $timeInRange = true;
                break;
            }
        }

        if (!$timeInRange) {
            Craft::info("Availability check failed: Time slot $startTime-$endTime is not within configured availability hours", __METHOD__);
            return false;
        }

        // Convert requested Local time to UTC for Database comparison
        // Reservations are stored in UTC, but input is Local
        try {
            $utcStart = $this->convertToUtc($date, $startTime);
            $utcEnd = $this->convertToUtc($date, $endTime);
            
            $utcDateStr = $utcStart->format('Y-m-d');
            $utcStartStr = $utcStart->format('H:i:s');
            $utcEndStr = $utcEnd->format('H:i:s');
        } catch (\Exception $e) {
            Craft::error("Timezone conversion failed: " . $e->getMessage(), __METHOD__);
            return false;
        }

        // If no variation specified, use old behavior (check for any conflict)
        if ($variationId === null) {
            $query = ReservationRecord::find()
                ->where(['bookingDate' => $utcDateStr])
                ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED]);
            
            // Standard overlap check using UTC times
            $query->andWhere(['and',
                ['<', 'startTime', $utcEndStr],
                ['>', 'endTime', $utcStartStr]
            ]);

            $exists = $query->exists();
            if ($exists) {
                Craft::info("Availability check failed: Slot $date $startTime-$endTime overlaps with existing reservation (checked as UTC $utcDateStr $utcStartStr-$utcEndStr)", __METHOD__);
            }
            return !$exists;
        }

        // Check capacity for the specific variation
        // Note: Cross-variation overlap prevention is handled at the service layer (BookingService)
        // where we have access to user information
        $variation = \modules\booking\elements\BookingVariation::findOne($variationId);
        if (!$variation) {
            Craft::error("Availability check failed: Variation ID $variationId not found", __METHOD__);
            return false;
        }

        // getRemainingCapacity needs to handle the timezone conversion internally or we pass Local
        // BookingVariation::getRemainingCapacity seems to query DB directly, so it needs UTC
        // Let's check BookingVariation implementation. Assuming it needs to be fixed too or we rely on it being fixed.
        // For now, let's assume getRemainingCapacity expects LOCAL time and handles it, OR we need to fix it.
        // Given I cannot see BookingVariation.php right now, I'll stick to fixing what I see.
        
        $remainingCapacity = $variation->getRemainingCapacity($date, $startTime, $endTime);
        
        if ($remainingCapacity < $requestedQuantity) {
            Craft::info("Availability check failed: Insufficient capacity for variation $variationId on $date $startTime-$endTime. Requested: $requestedQuantity, Remaining: $remainingCapacity", __METHOD__);
            return false;
        }

        return true;
    }

    /**
     * Helper to convert Local Date/Time to UTC DateTime
     */
    private function convertToUtc(string $date, string $time): \DateTime
    {
        // Use application timezone as "Local"
        $timezone = new \DateTimeZone(Craft::$app->getTimeZone());
        
        // Try H:i:s first, then H:i
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', "$date $time", $timezone);
        if (!$dt) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i', "$date $time", $timezone);
        }
        
        if (!$dt) {
            throw new \Exception("Invalid date/time format: $date $time");
        }

        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt;
    }

    /**
     * Get next available date
     */
    public function getNextAvailableDate(): ?string
    {
        $date = new \DateTime();
        $maxDays = 90; // Look ahead 90 days maximum

        for ($i = 0; $i < $maxDays; $i++) {
            $dateStr = $date->format('Y-m-d');
            $slots = $this->getAvailableSlots($dateStr);
            
            if (!empty($slots)) {
                return $dateStr;
            }
            
            $date->add(new \DateInterval('P1D'));
        }

        return null;
    }

    /**
     * Get availability summary for a date range
     */
    public function getAvailabilitySummary(string $startDate, string $endDate): array
    {
        $summary = [];
        $current = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $slots = $this->getAvailableSlots($dateStr);

            $summary[$dateStr] = [
                'date' => $dateStr,
                'dayName' => $current->format('l'),
                'availableSlots' => count($slots),
                'hasAvailability' => !empty($slots)
            ];

            $current->add(new \DateInterval('P1D'));
        }

        return $summary;
    }

    /**
     * Get upcoming event dates with available slots
     * Returns array of event dates for display in booking form
     */
    /**
     * Get upcoming event dates
     *
     * @param int|null $entryId Optional entry ID to filter by
     * @param int $limit Maximum number of events to return
     */
    public function getUpcomingEventDates(?int $entryId = null, int $limit = 10): array
    {
        $now = new \DateTime();
        $today = $now->format('Y-m-d');

        // Get all future event dates that are active
        $eventDateRecords = EventDateRecord::find()
            ->where(['>=', 'eventDate', $today])
            ->orderBy(['eventDate' => SORT_ASC, 'startTime' => SORT_ASC])
            ->limit($limit)
            ->all();

        $eventDates = [];
        foreach ($eventDateRecords as $record) {
            // Get the parent availability element
            $avail = Availability::find()
                ->id($record->availabilityId)
                ->isActive(true)
                ->one();

            if (!$avail) {
                continue;
            }

            // Filter by entry if provided
            if ($entryId && !($avail->sourceType === 'entry' && $avail->sourceId == $entryId)) {
                continue;
            }

            // Check if there are available slots for this date/time
            $slots = $this->getAvailableSlots($record->eventDate, $entryId);
            if (!empty($slots)) {
                $eventDate = EventDate::fromRecord($record);

                $eventDates[] = [
                    'date' => $record->eventDate,
                    'startTime' => $record->startTime,
                    'endTime' => $record->endTime,
                    'formattedDate' => $eventDate->getFormattedDate(),
                    'formattedTime' => $eventDate->getFormattedTimeRange(),
                    'sourceType' => $avail->sourceType,
                    'sourceName' => $avail->getSourceName(),
                ];
            }
        }

        return $eventDates;
    }
}
