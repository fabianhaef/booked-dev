<?php

namespace fabian\booked\events;

use craft\events\CancelableEvent;
use fabian\booked\elements\Reservation;

/**
 * Before Calendar Sync Event
 *
 * Triggered before syncing a reservation to an external calendar (Google, Outlook).
 * This is a cancelable event - setting $isValid to false will prevent the sync.
 *
 * Example use cases:
 * - Preventing sync for certain booking types
 * - Modifying event data before sync
 * - Adding custom calendar properties
 * - Conditional sync based on service or employee
 *
 * @example
 * ```php
 * use fabian\booked\services\CalendarSyncService;
 * use fabian\booked\events\BeforeCalendarSyncEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     CalendarSyncService::class,
 *     CalendarSyncService::EVENT_BEFORE_CALENDAR_SYNC,
 *     function(BeforeCalendarSyncEvent $event) {
 *         // Don't sync internal training sessions
 *         if ($event->reservation->getService()->handle === 'internal-training') {
 *             $event->isValid = false;
 *         }
 *
 *         // Add custom color for VIP customers
 *         if (str_contains($event->reservation->userEmail, '@vip.')) {
 *             $event->eventData['colorId'] = '11'; // Red in Google Calendar
 *         }
 *     }
 * );
 * ```
 */
class BeforeCalendarSyncEvent extends CancelableEvent
{
    /**
     * @var Reservation The reservation being synced
     */
    public Reservation $reservation;

    /**
     * @var string The calendar provider ('google', 'outlook')
     */
    public string $provider;

    /**
     * @var string The sync action ('create', 'update', 'delete')
     */
    public string $action;

    /**
     * @var array The event data that will be sent to the calendar API
     * This can be modified before sync
     */
    public array $eventData = [];

    /**
     * @var int|null The employee ID for which calendar to sync to
     */
    public ?int $employeeId = null;
}
