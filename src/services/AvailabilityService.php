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
            // Filter by location if specified
            if ($locationId !== null) {
                return array_filter($cached, function($slot) use ($locationId) {
                    return ($slot['locationId'] ?? null) === $locationId;
                });
            }
            return $cached;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            Craft::warning("Invalid date format: {$date}", __METHOD__);
            return [];
        }

        $dayOfWeek = (int)$dateObj->format('w'); // 0 = Sunday, 6 = Saturday

        // Step 1: Get working hours (Schedule) for the date
        $workingHours = $this->getWorkingHours($dayOfWeek, $employeeId, $locationId);
        
        if (empty($workingHours)) {
            // Cache empty result
            $cacheService->setCachedAvailability($date, [], $employeeId, $serviceId);
            return [];
        }

        // Step 2: Get service details if specified
        $service = null;
        if ($serviceId) {
            $service = Service::findOne($serviceId);
            if (!$service || !$service->enabled) {
                return [];
            }
        }

        // Step 3: Generate base time windows from working hours
        $timeWindows = $this->generateTimeWindows($workingHours);

        // Step 4: Subtract existing bookings
        $timeWindows = $this->subtractBookings($timeWindows, $date, $employeeId, $serviceId);

        // Step 5: Subtract buffer times (if service specified)
        if ($service) {
            $timeWindows = $this->subtractBuffers($timeWindows, $service);
        }

        // Step 6: Subtract blackout dates
        $timeWindows = $this->subtractBlackouts($timeWindows, $date);

        // Step 7: Divide by service duration to generate slots
        $duration = $service ? $service->duration : 60; // Default 60 minutes
        $slots = $this->generateSlots($timeWindows, $duration, $serviceId, $locationId);

        // Step 8: Remove slots in the past
        $slots = $this->filterPastSlots($slots, $date);

        // Step 9: Cache the result
        $cacheService->setCachedAvailability($date, $slots, $employeeId, $serviceId);

        return $slots;
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
        // TODO: Get bookings from Reservation element
        // For now, return windows as-is
        // This will be implemented when Reservation element is created
        return $windows;
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
        // TODO: Check blackout dates
        // For now, return windows as-is
        // This will be implemented when BlackoutDate element is created
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
     * Filter out slots that are in the past
     * 
     * @param array $slots Array of slot objects
     * @param string $date Date in Y-m-d format
     * @return array Filtered slots
     */
    protected function filterPastSlots(array $slots, string $date): array
    {
        $now = new DateTime();
        $today = $now->format('Y-m-d');

        if ($date !== $today) {
            // Not today, return all slots
            return $slots;
        }

        $currentTime = $now->format('H:i');

        return array_filter($slots, function($slot) use ($currentTime) {
            return $slot['time'] >= $currentTime;
        });
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
        ?int $serviceId = null
    ): bool {
        $slots = $this->getAvailableSlots($date, $employeeId, $locationId, $serviceId);

        foreach ($slots as $slot) {
            if ($slot['time'] === $startTime && $slot['endTime'] === $endTime) {
                return true;
            }
        }

        return false;
    }
}
