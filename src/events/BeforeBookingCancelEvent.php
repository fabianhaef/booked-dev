<?php

namespace fabian\booked\events;

/**
 * Before Booking Cancel Event
 *
 * Triggered before a booking is cancelled.
 * This is a cancelable event - setting $isValid to false will prevent the cancellation.
 *
 * Example use cases:
 * - Enforcing custom cancellation policies
 * - Checking cancellation deadlines
 * - Requiring approval for cancellations
 * - Preventing cancellation of paid bookings
 *
 * @example
 * ```php
 * use fabian\booked\services\BookingService;
 * use fabian\booked\events\BeforeBookingCancelEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     BookingService::class,
 *     BookingService::EVENT_BEFORE_BOOKING_CANCEL,
 *     function(BeforeBookingCancelEvent $event) {
 *         // Prevent cancellation within 24 hours of appointment
 *         $bookingDateTime = new \DateTime($event->reservation->bookingDate . ' ' . $event->reservation->startTime);
 *         $hoursUntil = ($bookingDateTime->getTimestamp() - time()) / 3600;
 *
 *         if ($hoursUntil < 24) {
 *             $event->isValid = false;
 *             $event->data['errorMessage'] = 'Cannot cancel within 24 hours of appointment';
 *         }
 *     }
 * );
 * ```
 */
class BeforeBookingCancelEvent extends BookingEvent
{
    /**
     * @var string|null Reason for cancellation
     */
    public ?string $cancellationReason = null;

    /**
     * @var string|null User or system that initiated the cancellation
     */
    public ?string $cancelledBy = null;

    /**
     * @var bool Whether to send cancellation notification
     */
    public bool $sendNotification = true;
}
