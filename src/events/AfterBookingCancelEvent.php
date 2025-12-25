<?php

namespace fabian\booked\events;

/**
 * After Booking Cancel Event
 *
 * Triggered after a booking has been cancelled.
 *
 * Example use cases:
 * - Updating inventory/availability
 * - Processing refunds
 * - Sending cancellation confirmations
 * - Updating analytics
 *
 * @example
 * ```php
 * use fabian\booked\services\BookingService;
 * use fabian\booked\events\AfterBookingCancelEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     BookingService::class,
 *     BookingService::EVENT_AFTER_BOOKING_CANCEL,
 *     function(AfterBookingCancelEvent $event) {
 *         // Process refund if booking was paid
 *         if ($event->wasPaid && $event->shouldRefund) {
 *             RefundProcessor::process($event->reservation);
 *         }
 *     }
 * );
 * ```
 */
class AfterBookingCancelEvent extends BookingEvent
{
    /**
     * @var bool Whether the booking was paid
     */
    public bool $wasPaid = false;

    /**
     * @var bool Whether a refund should be processed
     */
    public bool $shouldRefund = false;

    /**
     * @var string|null Cancellation reason
     */
    public ?string $cancellationReason = null;
}
