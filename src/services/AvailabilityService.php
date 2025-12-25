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
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Availability;
use fabian\booked\events\AfterAvailabilityCheckEvent;
use fabian\booked\events\BeforeAvailabilityCheckEvent;
use fabian\booked\records\ReservationRecord;
use fabian\booked\records\ExternalEventRecord;
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
     * Event constants
     */
    const EVENT_BEFORE_AVAILABILITY_CHECK = 'beforeAvailabilityCheck';
    const EVENT_AFTER_AVAILABILITY_CHECK = 'afterAvailabilityCheck';

    /**
     * @var DateTime|null Current date/time for testing purposes (null = use real time)
     */
    protected ?DateTime $currentDateTime = null;

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
        int $requestedQuantity = 1,
        ?string $userTimezone = null
    ): array {
        $startTime = microtime(true);

        // Fire BEFORE_AVAILABILITY_CHECK event
        $beforeCheckEvent = new BeforeAvailabilityCheckEvent([
            'date' => $date,
            'serviceId' => $serviceId,
            'employeeId' => $employeeId,
            'locationId' => $locationId,
            'quantity' => $requestedQuantity,
            'criteria' => [
                'userTimezone' => $userTimezone,
            ],
        ]);
        $this->trigger(self::EVENT_BEFORE_AVAILABILITY_CHECK, $beforeCheckEvent);

        // Check if event was cancelled
        if (!$beforeCheckEvent->isValid) {
            $errorMessage = $beforeCheckEvent->errorMessage ?? 'Availability check was cancelled by event handler';
            Craft::warning("Availability check cancelled by event handler: {$errorMessage}", __METHOD__);

            // Fire AFTER event with failure
            $afterCheckEvent = new AfterAvailabilityCheckEvent([
                'date' => $date,
                'serviceId' => $serviceId,
                'employeeId' => $employeeId,
                'locationId' => $locationId,
                'slots' => [],
                'slotCount' => 0,
                'calculationTime' => microtime(true) - $startTime,
                'fromCache' => false,
            ]);
            $this->trigger(self::EVENT_AFTER_AVAILABILITY_CHECK, $afterCheckEvent);

            return [];
        }

        // Use potentially modified criteria
        $date = $beforeCheckEvent->date;
        $serviceId = $beforeCheckEvent->serviceId;
        $employeeId = $beforeCheckEvent->employeeId;
        $locationId = $beforeCheckEvent->locationId;
        $requestedQuantity = $beforeCheckEvent->quantity;

        Craft::info("Getting available slots for date: $date, employee: $employeeId, location: $locationId, service: $serviceId", __METHOD__);

        $cacheService = Booked::getInstance()->availabilityCache;

        // Check cache first (re-enabled after implementing efficient tag-based invalidation)
        $cached = $cacheService->getCachedAvailability($date, $employeeId, $serviceId);

        if ($cached !== null) {
            Craft::info("Cache HIT: Returning cached slots for $date (employee: " . ($employeeId ?? 'all') . ", service: " . ($serviceId ?? 'all') . ")", __METHOD__);
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

            $finalSlots = array_values($slots);

            // Fire AFTER_AVAILABILITY_CHECK event
            $afterCheckEvent = new AfterAvailabilityCheckEvent([
                'date' => $date,
                'serviceId' => $serviceId,
                'employeeId' => $employeeId,
                'locationId' => $locationId,
                'slots' => $finalSlots,
                'slotCount' => count($finalSlots),
                'calculationTime' => microtime(true) - $startTime,
                'fromCache' => true,
            ]);
            $this->trigger(self::EVENT_AFTER_AVAILABILITY_CHECK, $afterCheckEvent);

            return $afterCheckEvent->slots;
        }

        Craft::info("Cache MISS: Calculating slots for $date", __METHOD__);
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            Craft::warning("Invalid date format: {$date}", __METHOD__);
            return [];
        }

        $dayOfWeek = (int)$dateObj->format('w'); // 0 = Sunday, 6 = Saturday

        // Step 1: Get base working hours (Schedule) for the date
        $schedules = $this->getWorkingHours($dayOfWeek, $employeeId, $locationId, $serviceId);
        Craft::info("Found " . count($schedules) . " schedules for day $dayOfWeek. Employee: $employeeId, Location: $locationId, Service: $serviceId", __METHOD__);
        
        if (empty($schedules) && $serviceId !== null) {
            // Try without serviceId to see if it's the filter
            $schedulesWithoutService = $this->getWorkingHours($dayOfWeek, $employeeId, $locationId);
            if (!empty($schedulesWithoutService)) {
                Craft::warning("Found " . count($schedulesWithoutService) . " schedules when serviceId filter was REMOVED. This suggests Employee/Service mapping is missing.", __METHOD__);
            }
        }

        foreach ($schedules as $s) {
            $sid = isset($s->id) ? $s->id : 'N/A';
            $st = isset($s->startTime) ? $s->startTime : 'N/A';
            $et = isset($s->endTime) ? $s->endTime : 'N/A';
            Craft::info("Schedule ID: {$sid}, Time: {$st}-{$et}", __METHOD__);
        }
        
        // Step 2: Get additional availability elements
        $availabilities = $this->getAvailabilities($employeeId, $locationId, $serviceId);
        $expandedAvailabilities = $this->expandAvailabilities($availabilities, $date);
        Craft::info("Found " . count($expandedAvailabilities) . " expanded availabilities", __METHOD__);
        foreach ($expandedAvailabilities as $ea) {
            Craft::info("Expanded Avail: Employee {$ea['employeeId']}, Time: {$ea['start']}-{$ea['end']}", __METHOD__);
        }

        if (empty($schedules) && empty($expandedAvailabilities)) {
            Craft::info("No schedules or availabilities found for $date", __METHOD__);
            $cacheService->setCachedAvailability($date, [], $employeeId, $serviceId);

            // Fire AFTER_AVAILABILITY_CHECK event
            $afterCheckEvent = new AfterAvailabilityCheckEvent([
                'date' => $date,
                'serviceId' => $serviceId,
                'employeeId' => $employeeId,
                'locationId' => $locationId,
                'slots' => [],
                'slotCount' => 0,
                'calculationTime' => microtime(true) - $startTime,
                'fromCache' => false,
            ]);
            $this->trigger(self::EVENT_AFTER_AVAILABILITY_CHECK, $afterCheckEvent);

            return [];
        }

        // Step 3: Get service details if specified
        $service = null;
        if ($serviceId) {
            $service = $this->getService($serviceId);
            if (!$service || !$service->enabled) {
                return [];
            }
        }

        $duration = $service ? $service->duration : 60; // Default 60 minutes
        if ($duration <= 0) {
            Craft::error("Service $serviceId has invalid duration: $duration", __METHOD__);
            return [];
        }
        $allSlots = [];

        // Group schedules and availabilities by employee
        $schedulesByEmployee = [];
        foreach ($schedules as $schedule) {
            $employees = $schedule->getEmployees();
            if (empty($employees)) {
                // Legacy support
                if ($schedule->employeeId) {
                    $schedulesByEmployee[$schedule->employeeId][] = [
                        'start' => $schedule->startTime,
                        'end' => $schedule->endTime,
                    ];
                    Craft::info("Added legacy schedule for Employee {$schedule->employeeId}", __METHOD__);
                }
                continue;
            }
            
            foreach ($employees as $employee) {
                $schedulesByEmployee[$employee->id][] = [
                    'start' => $schedule->startTime,
                    'end' => $schedule->endTime,
                ];
            }
        }
        foreach ($expandedAvailabilities as $avail) {
            $empId = $avail['employeeId'] ?? null;
            if ($empId) {
                $schedulesByEmployee[$empId][] = [
                    'start' => $avail['start'],
                    'end' => $avail['end'],
                ];
            }
        }

        foreach ($schedulesByEmployee as $empId => $empWindowsRaw) {
            // Step 4: Generate merged time windows for this employee
            $timeWindows = $this->mergeTimeWindows($empWindowsRaw);
            Craft::info("Employee $empId base working hours: " . json_encode($timeWindows), __METHOD__);

            // Step 5: Subtract this employee's bookings with buffers
            $bufferBefore = $service->bufferBefore ?? 0;
            $bufferAfter = $service->bufferAfter ?? 0;

            $bookings = $this->getReservationsForDate($date, $empId);
            Craft::info("Employee $empId has " . count($bookings) . " bookings on $date", __METHOD__);
            foreach ($bookings as $booking) {
                Craft::info("Booking: {$booking->startTime} - {$booking->endTime}, Status: {$booking->status}", __METHOD__);
                // Expanding the blocked window: 
                // A new booking starting at T must have T >= booking.end + bufferBefore
                // and T + duration + bufferAfter <= booking.start
                $blockedStart = $this->minutesToTime(max(0, $this->timeToMinutes($booking->startTime) - $bufferAfter));
                $blockedEnd = $this->minutesToTime($this->timeToMinutes($booking->endTime) + $bufferBefore);
                
                $timeWindows = $this->subtractWindow($timeWindows, $blockedStart, $blockedEnd);
            }
            Craft::info("Employee $empId after bookings: " . json_encode($timeWindows), __METHOD__);

            // Step 6: Apply start/end of day buffers
            if ($bufferBefore > 0 || $bufferAfter > 0) {
                foreach ($timeWindows as $key => $window) {
                    $winStart = $this->timeToMinutes($window['start']);
                    $winEnd = $this->timeToMinutes($window['end']);
                    
                    $adjustedStart = $winStart + $bufferBefore;
                    $adjustedEnd = $winEnd - $bufferAfter;
                    
                    if ($adjustedStart < $adjustedEnd) {
                        $timeWindows[$key]['start'] = $this->minutesToTime($adjustedStart);
                        $timeWindows[$key]['end'] = $this->minutesToTime($adjustedEnd);
                    } else {
                        unset($timeWindows[$key]);
                    }
                }
                $timeWindows = array_values($timeWindows);
                Craft::info("Employee $empId after start/end buffers: " . json_encode($timeWindows), __METHOD__);
            }

            // Step 7: Subtract blackout dates
            $timeWindows = $this->subtractBlackouts($timeWindows, $date, $empId);
            Craft::info("Employee $empId after blackouts: " . json_encode($timeWindows), __METHOD__);

            // Step 7.5: Subtract external calendar events
            $timeWindows = $this->subtractExternalEvents($timeWindows, $date, $empId);
            Craft::info("Employee $empId after external events: " . json_encode($timeWindows), __METHOD__);

            // Step 8: Generate slots for this employee
            $empSlots = $this->generateSlots($timeWindows, $duration, $serviceId, $locationId);
            
            // Add employee info to each slot
            $empElement = Employee::find()->id($empId)->one();
            $empName = $empElement ? $empElement->title : "Unknown";
            $empTimezone = $this->getEmployeeTimezone($empId);
            
            foreach ($empSlots as &$slot) {
                $slot['employeeId'] = $empId;
                $slot['employeeName'] = $empName;
                $slot['timezone'] = $empTimezone;
            }
            
            Craft::info("Employee $empId generated " . count($empSlots) . " slots", __METHOD__);
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

        // Step 11: Shift to user timezone if specified
        if ($userTimezone) {
            $timezoneService = new TimezoneService();
            $allSlots = $this->shiftAllSlots($allSlots, $date, $userTimezone, $timezoneService);
        }

        // Step 12: If no specific employee was requested, deduplicate slots by time
        // (user selected "random employee" so they don't care which employee)
        if ($employeeId === null && count($allSlots) > 0) {
            $allSlots = $this->deduplicateSlotsByTime($allSlots);
            Craft::info("Deduplicated slots to " . count($allSlots) . " unique times", __METHOD__);
        }

        $finalSlots = array_values($allSlots);
        Craft::info("Returning " . count($finalSlots) . " available slots for $date", __METHOD__);

        // Fire AFTER_AVAILABILITY_CHECK event
        $afterCheckEvent = new AfterAvailabilityCheckEvent([
            'date' => $date,
            'serviceId' => $serviceId,
            'employeeId' => $employeeId,
            'locationId' => $locationId,
            'slots' => $finalSlots,
            'slotCount' => count($finalSlots),
            'calculationTime' => microtime(true) - $startTime,
            'fromCache' => false,
        ]);
        $this->trigger(self::EVENT_AFTER_AVAILABILITY_CHECK, $afterCheckEvent);

        return $afterCheckEvent->slots;
    }

    /**
     * Shift all slots to user timezone
     */
    protected function shiftAllSlots(array $slots, string $date, string $userTimezone, TimezoneService $timezoneService): array
    {
        // Group slots by their source timezone (location timezone)
        $slotsByTimezone = [];
        foreach ($slots as $slot) {
            $tz = $this->getSlotTimezone($slot);
            $slotsByTimezone[$tz][] = $slot;
        }

        $allShifted = [];
        foreach ($slotsByTimezone as $sourceTz => $tzSlots) {
            $shifted = $timezoneService->shiftSlots($tzSlots, $date, $sourceTz, $userTimezone);
            $allShifted = array_merge($allShifted, $shifted);
        }

        return $allShifted;
    }

    /**
     * Get the source timezone for a slot
     */
    protected function getSlotTimezone(array $slot): string
    {
        // In a real scenario, we'd look up the employee -> location -> timezone
        // For now, we'll try to get it from the slot or return system default
        return $slot['timezone'] ?? \Craft::$app->getTimezone();
    }

    /**
     * Get employee timezone
     */
    protected function getEmployeeTimezone(int $employeeId): string
    {
        // Default to system timezone
        $timezone = \Craft::$app->getTimezone();

        $employee = Employee::find()->id($employeeId)->one();
        if ($employee && $employee->locationId) {
            $location = Location::find()->id($employee->locationId)->one();
            if ($location && $location->timezone) {
                $timezone = $location->timezone;
            }
        }

        return $timezone;
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
    protected function getAvailabilities(?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        $query = Availability::find()
            ->status('active');

        if ($employeeId !== null) {
            $query->sourceId($employeeId);
        }

        if ($serviceId !== null) {
            $query->serviceId($serviceId);
        }

        $availabilities = $query->all();

        // Filter by location if specified
        if ($locationId !== null) {
            $availabilities = array_filter($availabilities, function($avail) use ($locationId) {
                if ($avail->sourceType === 'employee') {
                    $employee = Employee::findOne($avail->sourceId);
                    return $employee && $employee->locationId === $locationId;
                }
                return true;
            });
        }

        return array_values($availabilities);
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
     * @param int|null $serviceId Optional service ID to filter by
     * @return Schedule[] Array of Schedule elements
     */
    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        $query = Schedule::find()
            ->enabled()
            ->dayOfWeek($dayOfWeek);

        if ($employeeId !== null) {
            $query->employeeId($employeeId);
        }

        if ($serviceId !== null) {
            $query->serviceId($serviceId);
        }

        $schedules = $query->all();

        // Filter by location if specified
        if ($locationId !== null) {
            $schedules = array_filter($schedules, function($schedule) use ($locationId) {
                $employees = $schedule->getEmployees();
                if (empty($employees)) {
                    $employee = $schedule->getEmployee();
                    return $employee && (int)$employee->locationId === (int)$locationId;
                }
                
                foreach ($employees as $employee) {
                    if ((int)$employee->locationId === (int)$locationId) {
                        return true;
                    }
                }
                return false;
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
            ->bookingDate($date)
            ->status(['not', ReservationRecord::STATUS_CANCELLED]);

        if ($employeeId !== null) {
            $query->employeeId($employeeId);
        }

        // REMOVED: serviceId filter. An employee is busy regardless of the service type.
        
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
     * @param int|null $employeeId Optional employee ID
     * @return array Time windows with blackouts subtracted
     */
    protected function subtractBlackouts(array $windows, string $date, ?int $employeeId = null): array
    {
        $blackoutService = Booked::getInstance()->getBlackoutDate();
        
        // Find the locationId if employeeId is set
        $locationId = null;
        if ($employeeId) {
            // Use static cache or similar to avoid N+1 in the loop
            static $employeeLocations = [];
            if (!isset($employeeLocations[$employeeId])) {
                $employee = Employee::find()->id($employeeId)->one();
                $employeeLocations[$employeeId] = $employee ? $employee->locationId : null;
            }
            $locationId = $employeeLocations[$employeeId];
        }

        if ($blackoutService->isDateBlackedOut($date, $employeeId, $locationId)) {
            return [];
        }

        return $windows;
    }

    /**
     * Subtract external calendar events from time windows
     */
    protected function subtractExternalEvents(array $windows, string $date, int $employeeId): array
    {
        $events = ExternalEventRecord::find()
            ->where(['employeeId' => $employeeId])
            ->andWhere(['startDate' => $date])
            ->all();

        foreach ($events as $event) {
            $windows = $this->subtractWindow($windows, $event->startTime, $event->endTime);
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

        // Get minimum advance booking hours from settings
        $settings = Booked::getInstance()->getSettings();
        $minAdvanceHours = $settings->minimumAdvanceBookingHours ?? 0;

        // Calculate cutoff datetime (now + minimum advance hours)
        $cutoffDateTime = clone $now;
        if ($minAdvanceHours > 0) {
            $cutoffDateTime->add(new \DateInterval("PT{$minAdvanceHours}H"));
        }

        $cutoffDate = $cutoffDateTime->format('Y-m-d');
        $cutoffTime = $cutoffDateTime->format('H:i');

        // If requested date is before cutoff date, no slots are available
        if ($date < $cutoffDate) {
            return [];
        }

        // If requested date is after cutoff date, all slots are available
        if ($date > $cutoffDate) {
            return $slots;
        }

        // For cutoff date, only show slots after cutoff time
        return array_filter($slots, function($slot) use ($cutoffTime) {
            return $slot['time'] >= $cutoffTime;
        });
    }

    /**
     * Get service by ID
     */
    protected function getService(int $serviceId): ?Service
    {
        return Service::findOne($serviceId);
    }

    /**
     * Get current DateTime
     * Mockable for testing
     */
    protected function getCurrentDateTime(): DateTime
    {
        return $this->currentDateTime ?: new DateTime();
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
        // First check if requested quantity is valid for this service/variation
        if (!$this->isQuantityAllowed($requestedQuantity, $serviceId)) {
            return false;
        }

        // Then check if slot exists and has available capacity
        if (!$this->hasAvailableCapacity($date, $startTime, $endTime, $employeeId, $serviceId, $requestedQuantity)) {
            return false;
        }

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

    /**
     * Deduplicate slots by time when user doesn't care about specific employee
     * Keeps the first slot for each unique time, removes duplicates
     *
     * @param array $slots
     * @return array
     */
    protected function deduplicateSlotsByTime(array $slots): array
    {
        $uniqueSlots = [];
        $seenTimes = [];

        foreach ($slots as $slot) {
            $timeKey = $slot['time'];

            if (!isset($seenTimes[$timeKey])) {
                $seenTimes[$timeKey] = true;
                // Remove employee-specific info when showing "any employee" slots
                $slot['employeeId'] = null;
                $slot['employeeName'] = 'Beliebig';
                $uniqueSlots[] = $slot;
            }
        }

        return $uniqueSlots;
    }

    /**
     * Find matching availability for a specific slot
     *
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @return Availability|null
     */
    public function getAvailabilityForSlot(string $date, string $startTime, string $endTime): ?Availability
    {
        // This is a simplified version. In Phase 4.1, we might need more complex logic
        // to link a specific slot back to an Availability element if it's an event.

        // For now, return first active availability that might cover this slot
        return Availability::find()
            ->status('active')
            ->one();
    }

    /**
     * Check if requested quantity is allowed for this service
     *
     * @param int $requestedQuantity Quantity requested
     * @param int|null $serviceId Service ID
     * @return bool True if quantity is allowed
     */
    protected function isQuantityAllowed(int $requestedQuantity, ?int $serviceId = null): bool
    {
        // Negative or zero quantity is never allowed
        if ($requestedQuantity <= 0) {
            return false;
        }

        // If no service specified, assume quantity is allowed (backward compatibility)
        if ($serviceId === null) {
            return true;
        }

        // Get service/variation to check maxCapacity
        $service = $this->getService($serviceId);
        if (!$service) {
            return true; // Service not found, allow (fail open for backward compatibility)
        }

        // Check if service allows quantity selection
        if (property_exists($service, 'allowQuantitySelection') && !$service->allowQuantitySelection && $requestedQuantity > 1) {
            return false;
        }

        // Check if requested quantity exceeds max capacity
        if (property_exists($service, 'maxCapacity') && $requestedQuantity > $service->maxCapacity) {
            return false;
        }

        return true;
    }

    /**
     * Check if slot has available capacity for requested quantity
     *
     * @param string $date Date in Y-m-d format
     * @param string $startTime Start time in H:i format
     * @param string $endTime End time in H:i format
     * @param int|null $employeeId Employee ID
     * @param int|null $serviceId Service ID
     * @param int $requestedQuantity Quantity requested
     * @return bool True if capacity is available
     */
    protected function hasAvailableCapacity(
        string $date,
        string $startTime,
        string $endTime,
        ?int $employeeId,
        ?int $serviceId,
        int $requestedQuantity
    ): bool {
        // If no service specified, assume capacity is available (backward compatibility)
        if ($serviceId === null) {
            return true;
        }

        // Get service/variation to check maxCapacity
        $service = $this->getService($serviceId);
        if (!$service || !property_exists($service, 'maxCapacity')) {
            return true; // No capacity limit configured
        }

        $maxCapacity = $service->maxCapacity;

        // Calculate already booked quantity for this slot
        $existingBookedQuantity = $this->getBookedQuantityForSlot($date, $startTime, $endTime, $employeeId, $serviceId);

        // Check if requested quantity + existing bookings exceeds capacity
        $totalQuantity = $requestedQuantity + $existingBookedQuantity;
        if ($totalQuantity > $maxCapacity) {
            return false;
        }

        return true;
    }

    /**
     * Get already booked quantity for a specific slot
     *
     * @param string $date Date in Y-m-d format
     * @param string $startTime Start time in H:i format
     * @param string $endTime End time in H:i format
     * @param int|null $employeeId Employee ID
     * @param int|null $serviceId Service ID
     * @return int Total quantity already booked
     */
    protected function getBookedQuantityForSlot(
        string $date,
        string $startTime,
        string $endTime,
        ?int $employeeId,
        ?int $serviceId
    ): int {
        $query = Reservation::find()
            ->bookingDate($date)
            ->startTime($startTime)
            ->status(['confirmed', 'pending']); // Only count confirmed/pending bookings

        if ($employeeId !== null) {
            $query->employeeId($employeeId);
        }

        if ($serviceId !== null) {
            $query->serviceId($serviceId);
        }

        $reservations = $query->all();

        // Sum up quantities from all matching reservations
        $totalQuantity = 0;
        foreach ($reservations as $reservation) {
            $totalQuantity += $reservation->quantity ?? 1;
        }

        return $totalQuantity;
    }
}
