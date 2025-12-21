<?php

namespace fabian\booked\exceptions;

/**
 * Exception thrown when booking validation fails
 */
class BookingValidationException extends BookingException
{
    private array $validationErrors = [];

    /**
     * @param string $message
     * @param array $validationErrors
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = '', array $validationErrors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
    }

    /**
     * Get validation errors
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Booking Validation Error';
    }
}
