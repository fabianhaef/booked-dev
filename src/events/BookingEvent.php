<?php

namespace fabian\booked\events;

use craft\events\CancelableEvent;
use fabian\booked\elements\Reservation;

/**
 * Base Booking Event
 *
 * Base class for all booking-related events, providing common properties
 * and functionality for event handlers.
 */
abstract class BookingEvent extends CancelableEvent
{
    /**
     * @var Reservation The reservation element
     */
    public Reservation $reservation;

    /**
     * @var array Additional data that can be passed to event handlers
     */
    public array $data = [];

    /**
     * @var bool Whether this is a new reservation (vs updating existing)
     */
    public bool $isNew = true;
}
