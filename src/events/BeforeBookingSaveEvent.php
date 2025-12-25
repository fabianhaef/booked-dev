<?php

namespace fabian\booked\events;

/**
 * Before Booking Save Event
 *
 * Triggered before a booking (reservation) is saved to the database.
 * This is a cancelable event - setting $isValid to false will prevent the save.
 *
 * Example use cases:
 * - Custom validation logic
 * - Integration with external systems before saving
 * - Modifying reservation data before save
 * - Preventing bookings based on custom business rules
 * - Logging booking attempts
 *
 * @example
 * ```php
 * use fabian\booked\services\BookingService;
 * use fabian\booked\events\BeforeBookingSaveEvent;
 * use yii\base\Event;
 *
 * Event::on(
 *     BookingService::class,
 *     BookingService::EVENT_BEFORE_BOOKING_SAVE,
 *     function(BeforeBookingSaveEvent $event) {
 *         // Prevent VIP customers from booking on Sundays
 *         if ($event->reservation->userEmail === 'vip@example.com') {
 *             $date = new \DateTime($event->reservation->bookingDate);
 *             if ($date->format('N') == 7) { // Sunday
 *                 $event->isValid = false;
 *                 $event->data['errorMessage'] = 'VIP customers cannot book on Sundays';
 *             }
 *         }
 *     }
 * );
 * ```
 */
class BeforeBookingSaveEvent extends BookingEvent
{
    /**
     * @var array The raw booking data submitted (before processing)
     */
    public array $bookingData = [];

    /**
     * @var string|null The source of the booking (e.g., 'web', 'api', 'admin')
     */
    public ?string $source = null;
}
