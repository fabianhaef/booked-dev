<?php

namespace fabian\booked\exceptions;

/**
 * Exception thrown when a booking conflict occurs (slot already booked, unavailable, etc.)
 */
class BookingConflictException extends BookingException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Booking Conflict';
    }
}
