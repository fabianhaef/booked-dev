<?php

namespace fabian\booked\events;

use craft\events\CancelableEvent;

/**
 * Before Availability Check Event
 *
 * Triggered before checking availability for a date/time/service.
 * This is a cancelable event - you can modify criteria or prevent the check.
 *
 * Example use cases:
 * - Implementing custom availability rules
 * - Filtering available slots based on user roles
 * - Dynamic pricing based on demand
 * - Seasonal availability modifications
 *
 * @example
 * ```php
 * use fabian\booked\services\AvailabilityService;
 * use fabian\booked\events\BeforeAvailabilityCheckEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     AvailabilityService::class,
 *     AvailabilityService::EVENT_BEFORE_AVAILABILITY_CHECK,
 *     function(BeforeAvailabilityCheckEvent $event) {
 *         // Early bird discount: only show early morning slots to premium members
 *         $currentUser = \Craft::$app->user->identity;
 *         if (!$currentUser || !$currentUser->isPremium()) {
 *             $event->criteria['excludeEarlySlots'] = true;
 *         }
 *     }
 * );
 * ```
 */
class BeforeAvailabilityCheckEvent extends CancelableEvent
{
    /**
     * @var string The date being checked (Y-m-d format)
     */
    public string $date;

    /**
     * @var int|null The service ID (null for all services)
     */
    public ?int $serviceId = null;

    /**
     * @var int|null The employee ID (null for any employee)
     */
    public ?int $employeeId = null;

    /**
     * @var int|null The location ID (null for any location)
     */
    public ?int $locationId = null;

    /**
     * @var int Requested quantity/capacity
     */
    public int $quantity = 1;

    /**
     * @var array Additional criteria or filters
     */
    public array $criteria = [];
}
