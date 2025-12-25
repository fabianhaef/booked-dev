<?php

namespace fabian\booked\events;

/**
 * After Booking Save Event
 *
 * Triggered after a booking has been successfully saved to the database.
 * This is NOT cancelable - the booking has already been saved.
 *
 * Example use cases:
 * - Sending notifications to external systems
 * - Updating analytics/metrics
 * - Triggering webhooks
 * - Syncing with CRM systems
 * - Sending custom notifications
 * - Creating related records
 *
 * @example
 * ```php
 * use fabian\booked\services\BookingService;
 * use fabian\booked\events\AfterBookingSaveEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     BookingService::class,
 *     BookingService::EVENT_AFTER_BOOKING_SAVE,
 *     function(AfterBookingSaveEvent $event) {
 *         // Send Slack notification for high-value bookings
 *         $service = $event->reservation->getService();
 *         if ($service && $service->price >= 500) {
 *             $slack = new SlackNotifier();
 *             $slack->notify([
 *                 'text' => "💰 High-value booking: {$service->title} - ${service->price}",
 *                 'customer' => $event->reservation->userName,
 *                 'date' => $event->reservation->bookingDate,
 *             ]);
 *         }
 *     }
 * );
 * ```
 */
class AfterBookingSaveEvent extends BookingEvent
{
    /**
     * @var bool Whether the save operation was successful
     */
    public bool $success = true;

    /**
     * @var array Validation errors if save failed
     */
    public array $errors = [];
}
