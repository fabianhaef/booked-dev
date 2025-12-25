<?php

namespace fabian\booked\events;

use yii\base\Event;
use fabian\booked\elements\Reservation;

/**
 * After Calendar Sync Event
 *
 * Triggered after a reservation has been synced to an external calendar.
 *
 * Example use cases:
 * - Logging sync operations
 * - Tracking sync success/failure rates
 * - Sending notifications on sync failure
 * - Storing external event IDs
 *
 * @example
 * ```php
 * use fabian\booked\services\CalendarSyncService;
 * use fabian\booked\events\AfterCalendarSyncEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     CalendarSyncService::class,
 *     CalendarSyncService::EVENT_AFTER_CALENDAR_SYNC,
 *     function(AfterCalendarSyncEvent $event) {
 *         // Log failed syncs for monitoring
 *         if (!$event->success) {
 *             \Craft::error(
 *                 "Calendar sync failed: {$event->errorMessage}",
 *                 'booking-calendar-sync'
 *             );
 *
 *             // Send alert to admin
 *             AdminNotifier::alert([
 *                 'type' => 'calendar_sync_failure',
 *                 'reservation_id' => $event->reservation->id,
 *                 'error' => $event->errorMessage,
 *             ]);
 *         }
 *     }
 * );
 * ```
 */
class AfterCalendarSyncEvent extends Event
{
    /**
     * @var Reservation The reservation that was synced
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
     * @var bool Whether the sync was successful
     */
    public bool $success = true;

    /**
     * @var string|null Error message if sync failed
     */
    public ?string $errorMessage = null;

    /**
     * @var string|null The external calendar event ID
     */
    public ?string $externalEventId = null;

    /**
     * @var array Response data from the calendar API
     */
    public array $response = [];

    /**
     * @var float Sync duration in seconds
     */
    public float $duration = 0.0;
}
