<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Reservation Active Record
 *
 * @property int $id
 * @property string $userName
 * @property string $userEmail
 * @property string|null $userPhone
 * @property string|null $userTimezone
 * @property string $bookingDate
 * @property string $startTime
 * @property string $endTime
 * @property string $status
 * @property string|null $notes
 * @property bool $notificationSent
 * @property string $confirmationToken
 * @property string|null $sourceType
 * @property int|null $sourceId
 * @property string|null $sourceHandle
 * @property int|null $variationId
 * @property int|null $employeeId
 * @property int|null $locationId
 * @property int|null $serviceId
 * @property int $quantity
 * @property string|null $virtualMeetingUrl
 * @property string|null $virtualMeetingProvider
 * @property string|null $virtualMeetingId
 * @property bool $notificationSent
 * @property bool $emailReminder24hSent
 * @property bool $emailReminder1hSent
 * @property bool $smsReminder24hSent
 * @property bool $smsReminder1hSent
 * @property int|null $sequenceId
 * @property int $sequenceOrder
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ReservationRecord extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_reservations}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['userName', 'userEmail', 'bookingDate', 'startTime', 'endTime', 'confirmationToken'], 'required'],
            [['userEmail'], 'email'],
            [['userName', 'userEmail', 'userPhone'], 'string', 'max' => 255],
            [['userTimezone'], 'string', 'max' => 50],
            [['bookingDate'], 'date', 'format' => 'php:Y-m-d'],
            // Accept both H:i and H:i:s formats (database stores with seconds)
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED]],
            [['notes', 'virtualMeetingUrl', 'virtualMeetingProvider', 'virtualMeetingId'], 'string'],
            [['notificationSent', 'emailReminder24hSent', 'emailReminder1hSent', 'smsReminder24hSent', 'smsReminder1hSent'], 'boolean'],
            [['confirmationToken'], 'string', 'max' => 64],
            [['confirmationToken'], 'unique'],
            [['status'], 'default', 'value' => self::STATUS_CONFIRMED],
            [['notificationSent'], 'default', 'value' => false],
            [['sourceType'], 'in', 'range' => ['entry', 'section']],
            [['sourceId', 'variationId', 'employeeId', 'locationId', 'serviceId', 'quantity', 'sequenceId', 'sequenceOrder'], 'integer'],
            [['quantity'], 'default', 'value' => 1],
            [['sequenceOrder'], 'default', 'value' => 0],
            [['sourceHandle'], 'string', 'max' => 255],
        ];
    }

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Ausstehend',
            self::STATUS_CONFIRMED => 'Bestätigt',
            self::STATUS_CANCELLED => 'Storniert',
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $statuses = self::getStatuses();
        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Get formatted booking datetime
     */
    public function getFormattedDateTime(): string
    {
        if (empty($this->bookingDate) || empty($this->startTime) || empty($this->endTime)) {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $this->bookingDate);

        // Try to parse with seconds first (H:i:s), then without (H:i)
        $startTime = \DateTime::createFromFormat('H:i:s', $this->startTime)
            ?: \DateTime::createFromFormat('H:i', $this->startTime);
        $endTime = \DateTime::createFromFormat('H:i:s', $this->endTime)
            ?: \DateTime::createFromFormat('H:i', $this->endTime);

        if (!$date || !$startTime || !$endTime) {
            return $this->bookingDate . ' von ' . $this->startTime . ' bis ' . $this->endTime;
        }

        // Use IntlDateFormatter for proper German formatting
        // Use user's timezone if available, otherwise default to Europe/Zurich
        $timezone = $this->userTimezone ?: 'Europe/Zurich';
        $formatter = new \IntlDateFormatter(
            'de_CH', // Swiss German locale
            \IntlDateFormatter::FULL, // Full date format
            \IntlDateFormatter::NONE, // No time
            $timezone
        );

        $formattedDate = $formatter->format($date);
        
        // Format times in 24-hour format (German style)
        return $formattedDate . ' von ' .
               $startTime->format('H:i') . ' bis ' .
               $endTime->format('H:i') . ' Uhr';
    }

    /**
     * Check if reservation can be cancelled
     */
    public function canBeCancelled(): bool
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }
        
        // Allow cancellation up to 24 hours before the appointment
        $bookingDateTime = strtotime($this->bookingDate . ' ' . $this->startTime);
        $cutoffTime = time() + (24 * 60 * 60); // 24 hours from now
        
        return $bookingDateTime > $cutoffTime;
    }

    /**
     * Find reservation by confirmation token
     */
    public static function findByToken(string $token): ?self
    {
        return self::findOne(['confirmationToken' => $token]);
    }

    /**
     * Generate a unique confirmation token
     */
    public static function generateConfirmationToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32)); // 64 character hex string
        } while (self::findOne(['confirmationToken' => $token])); // Ensure uniqueness
        
        return $token;
    }
}
