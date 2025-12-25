<?php

namespace fabian\booked\events;

use yii\base\Event;

/**
 * After Availability Check Event
 *
 * Triggered after availability has been calculated for a date/time/service.
 * You can modify the available slots before they're returned.
 *
 * Example use cases:
 * - Filtering slots based on custom business logic
 * - Adding metadata to slots (pricing, promotions)
 * - Limiting slots for non-premium users
 * - Adding dynamic pricing tiers
 *
 * @example
 * ```php
 * use fabian\booked\services\AvailabilityService;
 * use fabian\booked\events\AfterAvailabilityCheckEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     AvailabilityService::class,
 *     AvailabilityService::EVENT_AFTER_AVAILABILITY_CHECK,
 *     function(AfterAvailabilityCheckEvent $event) {
 *         // Add dynamic pricing to peak hours
 *         foreach ($event->slots as &$slot) {
 *             $hour = (int)substr($slot['time'], 0, 2);
 *             if ($hour >= 17 && $hour <= 19) {
 *                 $slot['surcharge'] = 1.5; // 50% peak hour surcharge
 *                 $slot['isPeakHour'] = true;
 *             }
 *         }
 *     }
 * );
 * ```
 */
class AfterAvailabilityCheckEvent extends Event
{
    /**
     * @var string The date that was checked
     */
    public string $date;

    /**
     * @var int|null The service ID
     */
    public ?int $serviceId = null;

    /**
     * @var int|null The employee ID
     */
    public ?int $employeeId = null;

    /**
     * @var int|null The location ID
     */
    public ?int $locationId = null;

    /**
     * @var array The available slots
     * Each slot is an array with keys: time, endTime, employeeId, capacity, etc.
     * This can be modified before being returned
     */
    public array $slots = [];

    /**
     * @var int Number of slots found
     */
    public int $slotCount = 0;

    /**
     * @var float Calculation time in seconds
     */
    public float $calculationTime = 0.0;

    /**
     * @var bool Whether the result was cached
     */
    public bool $fromCache = false;
}
