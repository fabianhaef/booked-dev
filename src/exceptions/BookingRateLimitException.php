<?php

namespace modules\booking\exceptions;

/**
 * Exception thrown when rate limit is exceeded
 */
class BookingRateLimitException extends BookingException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Booking Rate Limit Exceeded';
    }
}
