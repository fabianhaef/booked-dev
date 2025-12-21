<?php

namespace fabian\booked\models\forms;

use craft\base\Model;
use fabian\booked\elements\Reservation;

/**
 * Booking Form Model
 *
 * Handles validation and sanitization for booking requests
 */
class BookingForm extends Model
{
    public string $userName = '';
    public string $userEmail = '';
    public ?string $userPhone = null;
    public ?string $userTimezone = 'Europe/Zurich';
    public string $bookingDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public ?string $notes = null;
    public ?int $variationId = null;
    public int $quantity = 1;
    public ?string $honeypot = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['userName', 'userEmail', 'bookingDate', 'startTime', 'endTime'], 'required', 'message' => 'Dieses Feld ist erforderlich.'],
            ['userEmail', 'email', 'message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'],
            [['userName', 'userEmail', 'userPhone'], 'string', 'max' => 255],
            [['userName', 'userEmail', 'userPhone', 'notes'], 'filter', 'filter' => function ($value) {
                // Sanitize input: strip tags and special chars
                return $value ? trim(htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8')) : null;
            }],
            ['userEmail', 'filter', 'filter' => 'strtolower'],
            [['bookingDate'], 'date', 'format' => 'php:Y-m-d'],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['variationId'], 'integer'],
            [['quantity'], 'integer', 'min' => 1],
            [['userTimezone'], 'string', 'max' => 50],
            [['honeypot'], 'string'],
        ];
    }

    /**
     * Check if submission is spam
     */
    public function isSpam(): bool
    {
        return !empty($this->honeypot);
    }

    /**
     * Get data formatted for Reservation creation
     */
    public function getReservationData(): array
    {
        return [
            'userName' => $this->userName,
            'userEmail' => $this->userEmail,
            'userPhone' => $this->userPhone,
            'userTimezone' => $this->userTimezone,
            'bookingDate' => $this->bookingDate,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'notes' => $this->notes,
            'variationId' => $this->variationId,
            'quantity' => $this->quantity,
        ];
    }
}

