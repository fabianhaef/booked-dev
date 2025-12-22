<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use DateTime;
use DateInterval;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Service;
use fabian\booked\elements\Schedule;
use fabian\booked\elements\Location;
use fabian\booked\Booked;

/**
 * Availability Service
 * 
 * Implements subtractive availability model:
 * Availability(W) = WorkingHours(W) \ (Bookings(W) ∪ Buffers(W) ∪ Exclusions(W))
 */
class AvailabilityService extends Component
{
    /**
     * Get available time slots for a specific date
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $employeeId Optional employee ID to filter by
     * @param int|null $locationId Optional location ID to filter by
     * @param int|null $serviceId Optional service ID to filter by
     * @param int $requestedQuantity Requested quantity (default: 1)
     * @return array Array of available slots
     */
    public function getAvailableSlots(
        string $date,
        ?int $employeeId = null,
        ?int $locationId = null,
        ?int $serviceId = null,
        int $requestedQuantity = 1
    ): array {
        // Check cache first
        $cacheService = Booked::getInstance()->availabilityCache;
        $cached = $cacheService->getCachedAvailability($date, $employeeId, $serviceId);
        
        if ($cached !== null) {
            $slots = $cached;
            // Filter by location if specified
            if ($locationId !== null) {
                $slots = array_filter($slots, function($slot) use ($locationId) {
                    return ($slot['locationId'] ?? null) === $locationId;
                });
            }
            // Filter past slots (since "now" might have changed since caching)
            $slots = $this->filterPastSlots($slots, $date);
            
            // Filter by quantity if specified
            if ($requestedQuantity > 1) {
                $slots = $this->filterByQuantity($slots, $requestedQuantity);
            }
            return array_values($slots);
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            Craft::warning("Invalid date format: {$date}", __METHOD__);
            return [];
        }

        $dayOfWeek = (int)$dateObj->format('w'); // 0 = Sunday, 6 = Saturday

        // Step 1: Get base working hours (Schedule) for the date
        $schedules = $this->getWorkingHours($dayOfWeek, $employeeId, $locationId);
        
        // Step 2: Get additional availability elements
        $availabilities = $this->getAvailabilities($employeeId, $locationId);
        $expandedAvailabilities = $this->expandAvailabilities($availabilities, $date);

        if (empty($schedules) && empty($expandedAvailabilities)) {
            $cacheService->setCachedAvailability($date, [], $employeeId, $serviceId);
            return [];
        }

        // Step 3: Get service details if specified
        $service = null;
        if ($serviceId) {
            $service = Service::findOne($serviceId);
            if (!$service || !$service->enabled) {
                return [];
            }
        }

        $duration = $service ? $service->duration : 60; // Default 60 minutes
        $allSlots = [];

        // Group schedules and availabilities by employee
        $schedulesByEmployee = [];
        foreach ($schedules as $schedule) {
            $schedulesByEmployee[$schedule->employeeId][] = [
                'start' => $schedule->startTime,
                'end' => $schedule->endTime,
            ];
        }
        foreach ($expandedAvailabilities as $avail) {
            $schedulesByEmployee[$avail['employeeId']][] = [
                'start' => $avail['start'],
                'end' => $avail['end'],
            ];
        }

        foreach ($schedulesByEmployee as $empId => $empWindowsRaw) {
            // Step 4: Generate merged time windows for this employee
            $timeWindows = $this->mergeTimeWindows($empWindowsRaw);

            // Step 5: Subtract this employee's bookings
            $timeWindows = $this->subtractBookings($timeWindows, $date, $empId, $serviceId);

            // Step 5: Subtract buffer times (if service specified)
            if ($service) {
                $timeWindows = $this->subtractBuffers($timeWindows, $service);
            }

            // Step 6: Subtract blackout dates
            $timeWindows = $this->subtractBlackouts($timeWindows, $date);

            // Step 7: Generate slots for this employee
            $empSlots = $this->generateSlots($timeWindows, $duration, $serviceId, $locationId);
            
            // Add employee ID to each slot
            foreach ($empSlots as &$slot) {
                $slot['employeeId'] = $empId;
            }
            
            $allSlots = array_merge($allSlots, $empSlots);
        }

        // Step 8: Remove slots in the past
        $allSlots = $this->filterPastSlots($allSlots, $date);

        // Step 9: Cache the raw result (all employees)
        $cacheService->setCachedAvailability($date, $allSlots, $employeeId, $serviceId);

        // Step 10: Filter by requested quantity
        if ($requestedQuantity > 1) {
            $allSlots = $this->filterByQuantity($allSlots, $requestedQuantity);
        }

        return array_values($allSlots);
    }

    /**
     * Filter slots by requested quantity
     * A slot is available if at least $quantity employees are free at that time
     */
    protected function filterByQuantity(array $slots, int $quantity): array
    {
        $slotsByTime = [];
        foreach ($slots as $slot) {
            $slotsByTime[$slot['time']][] = $slot;
        }

        $filtered = [];
        foreach ($slotsByTime as $time => $timeSlots) {
            if (count($timeSlots) >= $quantity) {
                foreach ($timeSlots as $slot) {
                    $filtered[] = $slot;
                }
            }
        }

        return $filtered;
    }

    /**
     * Get availability elements
     */
    protected function getAvailabilities(?int $employeeId = null, ?int $locationId = null): array
    {
        $query = \fabian\booked\elements\Availability::find()
            ->status('active');

        // TODO: Filter by employee/location once relationships are established
        // For now, return all active ones
        return $query->all();
    }

    /**
     * Expand availability elements for a specific date
     */
    protected function expandAvailabilities(array $availabilities, string $date): array
    {
        $expanded = [];
        $dateObj = new DateTime($date);
        $recurrenceService = new RecurrenceService();

        foreach ($availabilities as $avail) {
            $match = false;

            if ($avail->availabilityType === 'event') {
                foreach ($avail->getEventDates() as $eventDate) {
                    if ($eventDate->eventDate === $date) {
                        $expanded[] = [
                            'start' => $eventDate->startTime,
                            'end' => $eventDate->endTime,
                            'employeeId' => $avail->sourceId, // Assuming sourceId is employeeId for now
                        ];
                    }
                }
            } elseif ($avail->availabilityType === 'recurring' && $avail->rrule) {
                // Use dateCreated as start of series if no explicit startDate (TODO: add startDate to element)
                $seriesStart = $avail->dateCreated ?? new DateTime('2000-01-01');
                $occurs = $recurrenceService->occursOn($avail->rrule, $date, $seriesStart);
                if ($occurs) {
                    $expanded[] = [
                        'start' => $avail->startTime,
                        'end' => $avail->endTime,
                        'employeeId' => $avail->sourceId,
                    ];
                }
            }
        }

        return $expanded;
    }

    /**
     * Get working hours (Schedule) for a specific day of week
     * 
     * @param int $dayOfWeek Day of week (0 = Sunday, 6 = Saturday)
     * @param int|null $employeeId Optional employee ID to filter by
     * @param int|null $locationId Optional location ID to filter by
     * @return Schedule[] Array of Schedule elements
     */
    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null): array
    {
        $query = Schedule::find()
            ->enabled()
            ->dayOfWeek($dayOfWeek);

        if ($employeeId !== null) {
            $query->employeeId($employeeId);
        }

        $schedules = $query->all();

        // Filter by location if specified
        if ($locationId !== null) {
            $schedules = array_filter($schedules, function($schedule) use ($locationId) {
                $employee = $schedule->getEmployee();
                return $employee && $employee->locationId === $locationId;
            });
        }

        return array_values($schedules);
    }

    /**
     * Generate time windows from Schedule elements
     * 
     * @param Schedule[] $schedules Array of Schedule elements
     * @return array Array of time windows [['start' => 'H:i', 'end' => 'H:i'], ...]
     */
    protected function generateTimeWindows(array $schedules): array
    {
        $windows = [];

        foreach ($schedules as $schedule) {
            if ($schedule->startTime && $schedule->endTime) {
                $windows[] = [
                    'start' => $schedule->startTime,
                    'end' => $schedule->endTime,
                    'employeeId' => $schedule->employeeId,
                ];
            }
        }

        // Merge overlapping windows
        return $this->mergeTimeWindows($windows);
    }

    /**
     * Merge overlapping time windows
     * 
     * @param array $windows Array of time windows
     * @return array Merged time windows
     */
    protected function mergeTimeWindows(array $windows): array
    {
        if (empty($windows)) {
            return [];
        }

        // Sort by start time
        usort($windows, function($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        $merged = [];
        $current = $windows[0];

        for ($i = 1; $i < count($windows); $i++) {
            $next = $windows[$i];

            // If current window overlaps or is adjacent to next window
            if ($this->timeToMinutes($current['end']) >= $this->timeToMinutes($next['start'])) {
                // Merge windows - use the later end time
                $currentEndMinutes = $this->timeToMinutes($current['end']);
                $nextEndMinutes = $this->timeToMinutes($next['end']);
                $current['end'] = $this->minutesToTime(max($currentEndMinutes, $nextEndMinutes));
            } else {
                // No overlap, add current to merged and move to next
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;
        return $merged;
    }

    /**
     * Subtract existing bookings from time windows
     * 
     * @param array $windows Array of time windows
     * @param string $date Date in Y-m-d format
     * @param int|null $employeeId Optional employee ID
     * @param int|null $serviceId Optional service ID
     * @return array Time windows with bookings subtracted
     */
    protected function subtractBookings(array $windows, string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        $bookings = $this->getReservationsForDate($date, $employeeId, $serviceId);
        
        foreach ($bookings as $booking) {
            $windows = $this->subtractWindow($windows, $booking->startTime, $booking->endTime);
        }

        return $windows;
    }

    /**
     * Get reservations for a specific date and filters
     * 
     * @param string $date
     * @param int|null $employeeId
     * @param int|null $serviceId
     * @return Reservation[]
     */
    protected function getReservationsForDate(string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        $query = Reservation::find()
            ->where(['bookingDate' => $date])
            ->andWhere(['!=', 'status', ReservationRecord::STATUS_CANCELLED]);

        if ($employeeId !== null) {
            $query->andWhere(['employeeId' => $employeeId]);
        }

        if ($serviceId !== null) {
            $query->andWhere(['serviceId' => $serviceId]);
        }

        return $query->all();
    }

    /**
     * Subtract buffer times from time windows
     * 
     * @param array $windows Array of time windows
     * @param Service $service Service element with buffer times
     * @return array Time windows with buffers subtracted
     */
    protected function subtractBuffers(array $windows, $service): array
    {
        $bufferBefore = $service->bufferBefore ?? 0;
        $bufferAfter = $service->bufferAfter ?? 0;

        if ($bufferBefore === 0 && $bufferAfter === 0) {
            return $windows;
        }

        $adjusted = [];

        foreach ($windows as $window) {
            $startMinutes = $this->timeToMinutes($window['start']);
            $endMinutes = $this->timeToMinutes($window['end']);

            // Add buffer before (reduce available start time)
            $adjustedStart = $startMinutes + $bufferBefore;
            // Add buffer after (reduce available end time)
            $adjustedEnd = $endMinutes - $bufferAfter;

            // Only add window if there's still time available
            if ($adjustedStart < $adjustedEnd) {
                $adjusted[] = [
                    'start' => $this->minutesToTime($adjustedStart),
                    'end' => $this->minutesToTime($adjustedEnd),
                    'employeeId' => $window['employeeId'] ?? null,
                ];
            }
        }

        return $adjusted;
    }

    /**
     * Subtract blackout dates from time windows
     * 
     * @param array $windows Array of time windows
     * @param string $date Date in Y-m-d format
     * @return array Time windows with blackouts subtracted
     */
    protected function subtractBlackouts(array $windows, string $date): array
    {
        $blackoutService = Booked::getInstance()->getBlackoutDate();
        
        if ($blackoutService->isDateBlackedOut($date)) {
            return [];
        }

        return $windows;
    }

    /**
     * Generate slots from time windows
     * 
     * @param array $windows Array of time windows
     * @param int $duration Slot duration in minutes
     * @param int|null $serviceId Optional service ID
     * @param int|null $locationId Optional location ID
     * @return array Array of slot objects
     */
    protected function generateSlots(array $windows, int $duration, ?int $serviceId = null, ?int $locationId = null): array
    {
        $slots = [];
        $durationSeconds = $duration * 60;

        foreach ($windows as $window) {
            $start = $this->timeToMinutes($window['start']);
            $end = $this->timeToMinutes($window['end']);

            $current = $start;
            while ($current + $duration <= $end) {
                $slotStart = $this->minutesToTime($current);
                $slotEnd = $this->minutesToTime($current + $duration);

                $slots[] = [
                    'time' => $slotStart,
                    'endTime' => $slotEnd,
                    'serviceId' => $serviceId,
                    'employeeId' => $window['employeeId'] ?? null,
                    'locationId' => $locationId,
                    'duration' => $duration,
                ];

                $current += $duration;
            }
        }

        return $slots;
    }

    /**
     * Filter out slots that don't meet advance booking requirements
     * 
     * @param array $slots Array of slot objects
     * @param string $date Date in Y-m-d format
     * @return array Filtered slots
     */
    protected function filterPastSlots(array $slots, string $date): array
    {
        $now = $this->getCurrentDateTime();
        $today = $now->format('Y-m-d');

        if ($date < $today) {
            return [];
        }

        if ($date > $today) {
            return $slots;
        }

        // For today, only show slots in the future
        // TODO: Respect minimumAdvanceBookingHours from settings
        $currentTime = $now->format('H:i');

        return array_filter($slots, function($slot) use ($currentTime) {
            return $slot['time'] >= $currentTime;
        });
    }

    /**
     * Get current DateTime
     * Mockable for testing
     */
    protected function getCurrentDateTime(): DateTime
    {
        return new DateTime();
    }

    /**
     * Subtract a time range from a set of time windows
     * 
     * @param array $windows Array of time windows
     * @param string $startTime Start time to subtract (H:i)
     * @param string $endTime End time to subtract (H:i)
     * @return array Adjusted time windows
     */
    protected function subtractWindow(array $windows, string $startTime, string $endTime): array
    {
        $subStart = $this->timeToMinutes($startTime);
        $subEnd = $this->timeToMinutes($endTime);
        $adjusted = [];

        foreach ($windows as $window) {
            $winStart = $this->timeToMinutes($window['start']);
            $winEnd = $this->timeToMinutes($window['end']);

            // No overlap
            if ($winEnd <= $subStart || $winStart >= $subEnd) {
                $adjusted[] = $window;
                continue;
            }

            // Subtraction covers the whole window
            if ($winStart >= $subStart && $winEnd <= $subEnd) {
                continue;
            }

            // Subtraction splits the window
            if ($winStart < $subStart && $winEnd > $subEnd) {
                $adjusted[] = array_merge($window, ['end' => $startTime]);
                $adjusted[] = array_merge($window, ['start' => $endTime]);
                continue;
            }

            // Subtraction trims the start
            if ($winStart < $subEnd && $winStart >= $subStart) {
                $adjusted[] = array_merge($window, ['start' => $endTime]);
                continue;
            }

            // Subtraction trims the end
            if ($winEnd > $subStart && $winEnd <= $subEnd) {
                $adjusted[] = array_merge($window, ['end' => $startTime]);
                continue;
            }
        }

        return $adjusted;
    }

    /**
     * Convert time string (H:i) to minutes since midnight
     * 
     * @param string $time Time in H:i format
     * @return int Minutes since midnight
     */
    protected function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int)$parts[0] * 60 + (int)($parts[1] ?? 0);
    }

    /**
     * Convert minutes since midnight to time string (H:i)
     * 
     * @param int $minutes Minutes since midnight
     * @return string Time in H:i format
     */
    protected function minutesToTime(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Check if a time slot is available
     * 
     * @param string $date Date in Y-m-d format
     * @param string $startTime Start time in H:i format
     * @param string $endTime End time in H:i format
     * @param int|null $employeeId Optional employee ID
     * @param int|null $locationId Optional location ID
     * @param int|null $serviceId Optional service ID
     * @return bool True if slot is available
     */
    public function isSlotAvailable(
        string $date,
        string $startTime,
        string $endTime,
        ?int $employeeId = null,
        ?int $locationId = null,
        ?int $serviceId = null,
        int $requestedQuantity = 1
    ): bool {
        $slots = $this->getAvailableSlots($date, $employeeId, $locationId, $serviceId, $requestedQuantity);

        foreach ($slots as $slot) {
            if ($slot['time'] === $startTime && $slot['endTime'] === $endTime) {
                // If specific employee requested, match it
                if ($employeeId !== null && $slot['employeeId'] !== $employeeId) {
                continue;
            }
                return true;
            }
        }

        return false;
    }
}
