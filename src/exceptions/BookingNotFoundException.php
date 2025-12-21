<?php

namespace fabian\booked\exceptions;

/**
 * Exception thrown when a booking/reservation is not found
 */
class BookingNotFoundException extends BookingException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Booking Not Found';
    }
}
