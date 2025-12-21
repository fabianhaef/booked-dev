<?php

namespace fabian\booked\exceptions;

use yii\base\Exception;

/**
 * Base exception for booking module
 */
class BookingException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Booking Exception';
    }
}
