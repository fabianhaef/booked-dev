<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\ReservationQuery;
use fabian\booked\records\ReservationRecord;

/**
 * Reservation Element
 *
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
 * @property int $quantity
 */
class Reservation extends Element
{
    public string $userName = '';
    public string $userEmail = '';
    public ?string $userPhone = null;
    public ?string $userTimezone = null;
    public string $bookingDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public string $status = ReservationRecord::STATUS_CONFIRMED;
    public ?string $notes = null;
    public bool $notificationSent = false;
    public string $confirmationToken = '';
    public ?string $sourceType = null;
    public ?int $sourceId = null;
    public ?string $sourceHandle = null;
    public ?int $variationId = null;
    public int $quantity = 1;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        // Default to system timezone if not set
        if ($this->userTimezone === null) {
            $this->userTimezone = Craft::$app->getTimeZone();
        }
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Buchung';
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return 'Buchung';
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return 'Buchungen';
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return 'buchungen';
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'reservation';
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(?string $source = null): array
    {
        return [
            Delete::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            'confirmed' => 'green',
            'pending' => 'orange',
            'cancelled' => null,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        // Map the database status to element status
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new ReservationQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => 'Alle Buchungen',
                'defaultSort' => ['bookingDate', 'desc'],
                'type' => 'native',
            ],
            [
                'heading' => 'Status',
            ],
            [
                'key' => 'confirmed',
                'label' => 'Bestätigt',
                'criteria' => ['status' => ReservationRecord::STATUS_CONFIRMED],
                'defaultSort' => ['bookingDate', 'desc'],
                'type' => 'native',
            ],
            [
                'key' => 'pending',
                'label' => 'Ausstehend',
                'criteria' => ['status' => ReservationRecord::STATUS_PENDING],
                'defaultSort' => ['bookingDate', 'desc'],
                'type' => 'native',
            ],
            [
                'key' => 'cancelled',
                'label' => 'Storniert',
                'criteria' => ['status' => ReservationRecord::STATUS_CANCELLED],
                'defaultSort' => ['bookingDate', 'desc'],
                'type' => 'native',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'id' => ['label' => 'ID'],
            'userName' => ['label' => 'Name'],
            'userEmail' => ['label' => 'E-Mail'],
            'sourceName' => ['label' => 'Gebucht'],
            'bookingDate' => ['label' => 'Datum & Uhrzeit'],
            'quantity' => ['label' => 'Plätze'],
            'duration' => ['label' => 'Dauer'],
            'dateCreated' => ['label' => 'Erstellt'],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['id', 'userName', 'userEmail', 'sourceName', 'bookingDate', 'quantity', 'duration', 'dateCreated'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => 'Name',
                'orderBy' => 'bookings_reservations.userName',
                'attribute' => 'userName',
            ],
            [
                'label' => 'E-Mail',
                'orderBy' => 'bookings_reservations.userEmail',
                'attribute' => 'userEmail',
            ],
            [
                'label' => 'Buchungsdatum',
                'orderBy' => 'bookings_reservations.bookingDate',
                'attribute' => 'bookingDate',
            ],
            [
                'label' => 'Erstellt',
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'id':
                // Make ID clickable to edit the reservation
                if ($this->getCpEditUrl()) {
                    return Html::a('#' . $this->id, $this->getCpEditUrl());
                }
                return '#' . $this->id;

            case 'userName':
                return Html::encode($this->userName);

            case 'userEmail':
                return Html::encode($this->userEmail);

            case 'sourceName':
                $sourceName = $this->getSourceName();
                if ($sourceName) {
                    $sourceTypeLabel = $this->sourceType == 'entry' ? 'Eintrag' : 'Sektion';
                    return Html::tag('div', Html::tag('strong', Html::encode($sourceName)) . Html::tag('br') .
                        Html::tag('span', Html::encode($sourceTypeLabel), ['class' => 'light', 'style' => 'font-size: 11px;']));
                }
                return Html::tag('span', 'Allgemein', ['class' => 'light']);

            case 'bookingDate':
                return Html::tag('div',
                    Html::tag('strong', Craft::$app->formatter->asDate($this->bookingDate, 'short')) .
                    Html::tag('br') .
                    Html::tag('span', $this->startTime . ' - ' . $this->endTime, ['class' => 'light', 'style' => 'font-size: 11px;'])
                );

            case 'quantity':
                $qty = $this->quantity ?? 1;
                if ($qty > 1) {
                    return Html::tag('span', $qty . 'x', ['class' => 'badge', 'style' => 'background-color: #0d78f2; color: white;']);
                }
                return Html::tag('span', $qty, ['class' => 'light']);

            case 'duration':
                return Html::tag('span', $this->getDurationMinutes() . ' Min.', ['class' => 'light']);
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booking/bookings/edit/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function getSection(): ?string
    {
        // Reservations don't belong to sections
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getType(): ?string
    {
        // Reservations don't have types
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canView(\craft\elements\User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canSave(\craft\elements\User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDelete(?\craft\elements\User $user = null): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['userName', 'userEmail', 'bookingDate', 'startTime', 'endTime'], 'required'],
            [['userEmail'], 'email'],
            [['userName', 'userEmail', 'userPhone'], 'string', 'max' => 255],
            [['userTimezone'], 'string', 'max' => 50],
            [['bookingDate'], 'date', 'format' => 'php:Y-m-d'],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['status'], 'in', 'range' => [
                ReservationRecord::STATUS_PENDING,
                ReservationRecord::STATUS_CONFIRMED,
                ReservationRecord::STATUS_CANCELLED
            ]],
            [['notes'], 'string'],
            [['notificationSent'], 'boolean'],
            [['confirmationToken'], 'string', 'max' => 64],
            [['quantity'], 'integer', 'min' => 1],
            [['quantity'], 'required'],
            [['quantity'], 'default', 'value' => 1],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $this->validateTimeRange();
        $this->validateBookingDate();
        $this->validateQuantity();

        return true;
    }

    /**
     * Validate that end time is after start time
     */
    protected function validateTimeRange(): void
    {
        if ($this->startTime && $this->endTime) {
            $start = strtotime($this->startTime);
            $end = strtotime($this->endTime);

            if ($end <= $start) {
                $this->addError('endTime', 'Die Endzeit muss nach der Startzeit liegen.');
            }
        }
    }

    /**
     * Validate that booking date is not in the past and meets minimum advance booking requirement
     */
    protected function validateBookingDate(): void
    {
        if (!$this->id && $this->bookingDate && $this->startTime) {
            $bookingDateTime = strtotime($this->bookingDate . ' ' . $this->startTime);
            $now = time();

            // Check if booking is in the past
            if ($bookingDateTime < $now) {
                $this->addError('bookingDate', 'Termine in der Vergangenheit können nicht gebucht werden.');
                return;
            }

            // Check minimum advance booking time
            $settings = \fabian\booked\models\Settings::loadSettings();
            $minimumAdvanceHours = $settings->minimumAdvanceBookingHours ?? 2;

            // If set to 0, allow immediate bookings
            if ($minimumAdvanceHours > 0) {
                $minimumBookingTime = $now + ($minimumAdvanceHours * 60 * 60);

                if ($bookingDateTime < $minimumBookingTime) {
                    $hoursText = $minimumAdvanceHours === 1 ? 'Stunde' : 'Stunden';
                    $this->addError('bookingDate', "Buchungen müssen mindestens {$minimumAdvanceHours} {$hoursText} im Voraus vorgenommen werden.");
                }
            }
        }
    }

    /**
     * Validate that quantity doesn't exceed remaining capacity
     */
    protected function validateQuantity(): void
    {
        // Skip validation if required fields are missing
        if (!$this->variationId || !$this->bookingDate || !$this->startTime || !$this->endTime) {
            return;
        }

        // Get the variation
        $variation = BookingVariation::findOne($this->variationId);
        if (!$variation) {
            $this->addError('variationId', 'Ungültige Variante ausgewählt.');
            return;
        }

        // Calculate remaining capacity (excluding this reservation if updating)
        $remaining = $variation->getRemainingCapacity(
            $this->bookingDate,
            $this->startTime,
            $this->endTime,
            $this->id // Exclude current reservation when updating
        );

        // Check if requested quantity exceeds available capacity
        if ($this->quantity > $remaining) {
            if ($remaining === 0) {
                $this->addError('quantity', 'Dieser Zeitslot ist vollständig ausgebucht.');
            } else {
                $suffix = $remaining === 1 ? 'Platz' : 'Plätze';
                $this->addError('quantity', "Nur noch {$remaining} {$suffix} für diesen Zeitslot verfügbar.");
            }
        }
    }

    /**
     * Store times internally for display/manipulation
     * These are kept in the user's original timezone until save
     */
    // Removed internal state flags as we now handle conversion in afterSave/afterFind strictly

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        if (!parent::beforeSave($isNew)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     * 
     * Times are stored in Europe/Zurich timezone (same as Availability).
     * No conversion needed since all users are in Switzerland.
     */
    public function afterFind(): void
    {
        parent::afterFind();
        // No timezone conversion - times stored and displayed in Europe/Zurich
    }

    /**
     * @inheritdoc
     * 
     * Times are stored in Europe/Zurich timezone (same as Availability).
     * No conversion needed since all users are in Switzerland.
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ReservationRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid reservation ID: ' . $this->id);
            }
        } else {
            $record = new ReservationRecord();
            $record->id = (int)$this->id;

            // Generate confirmation token for new reservations
            if (empty($this->confirmationToken)) {
                $this->confirmationToken = ReservationRecord::generateConfirmationToken();
            }
        }

        $record->userName = $this->userName;
        $record->userEmail = $this->userEmail;
        $record->userPhone = $this->userPhone;
        $record->userTimezone = $this->userTimezone ?? 'Europe/Zurich';
        
        // Store times directly in Europe/Zurich format (no conversion)
        $record->bookingDate = $this->bookingDate;
        $record->startTime = $this->startTime;
        $record->endTime = $this->endTime;
        
        $record->status = $this->status;
        $record->notes = $this->notes;
        $record->notificationSent = $this->notificationSent;
        $record->confirmationToken = $this->confirmationToken;
        $record->sourceType = $this->sourceType;
        $record->sourceId = $this->sourceId;
        $record->sourceHandle = $this->sourceHandle;
        $record->variationId = $this->variationId;
        $record->quantity = $this->quantity;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // Delete the reservation record
        $record = ReservationRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    // === Business Logic Methods ===

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return ReservationRecord::getStatuses();
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
     * Cancel reservation
     */
    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->status = ReservationRecord::STATUS_CANCELLED;
        return Craft::$app->elements->saveElement($this);
    }

    /**
     * Get source name (entry title or section name)
     */
    public function getSourceName(): ?string
    {
        if (!$this->sourceType) {
            return null;
        }

        if ($this->sourceType === 'entry' && $this->sourceId) {
            $entry = Craft::$app->entries->getEntryById($this->sourceId);
            return $entry ? $entry->title : 'Entry #' . $this->sourceId;
        }

        if ($this->sourceType === 'section' && $this->sourceHandle) {
            $section = Craft::$app->sections->getSectionByHandle($this->sourceHandle);
            return $section ? $section->name : $this->sourceHandle;
        }

        if ($this->sourceType === 'section' && $this->sourceId) {
            $section = Craft::$app->sections->getSectionById($this->sourceId);
            return $section ? $section->name : 'Section #' . $this->sourceId;
        }

        return null;
    }

    /**
     * Get formatted booking datetime
     *
     * Returns formatted date and time in Europe/Zurich timezone.
     * Times are stored and displayed in local Switzerland time.
     */
    public function getFormattedDateTime(): string
    {
        if (empty($this->bookingDate) || empty($this->startTime) || empty($this->endTime)) {
            return '';
        }

        // Times are stored in Europe/Zurich timezone
        $date = \DateTime::createFromFormat('Y-m-d', $this->bookingDate);
        $startTime = \DateTime::createFromFormat('H:i:s', $this->startTime) ?: \DateTime::createFromFormat('H:i', $this->startTime);
        $endTime = \DateTime::createFromFormat('H:i:s', $this->endTime) ?: \DateTime::createFromFormat('H:i', $this->endTime);

        if (!$date || !$startTime || !$endTime) {
            return $this->bookingDate . ' von ' . $this->startTime . ' bis ' . $this->endTime;
        }

        $timezone = $this->userTimezone ?: 'Europe/Zurich';
        $formatter = new \IntlDateFormatter(
            'de_CH',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            $timezone
        );

        $formattedDate = $formatter->format($date);

        return $formattedDate . ' von ' .
            $startTime->format('H:i') . ' bis ' .
            $endTime->format('H:i') . ' Uhr';
    }

    /**
     * Check if reservation can be cancelled
     *
     * Enforces cancellation policy: bookings can only be cancelled
     * if they are more than X hours in the future (configurable via settings)
     *
     * @return bool True if the booking can be cancelled
     */
    public function canBeCancelled(): bool
    {
        // Already cancelled bookings cannot be cancelled again
        if ($this->status === ReservationRecord::STATUS_CANCELLED) {
            return false;
        }

        // Get cancellation policy from settings
        $settings = \fabian\booked\models\Settings::loadSettings();
        $hoursBeforeCancellation = $settings->cancellationPolicyHours ?? 24;

        // If policy is set to 0, allow cancellation any time
        if ($hoursBeforeCancellation === 0) {
            return true;
        }

        // Calculate booking date/time and cutoff time
        // Times are stored in Europe/Zurich, strtotime() interprets in server timezone
        $bookingDateTime = strtotime($this->bookingDate . ' ' . $this->startTime);
        $cutoffTime = time() + ($hoursBeforeCancellation * 60 * 60);

        // Can cancel if booking is more than X hours in the future
        return $bookingDateTime > $cutoffTime;
    }

    /**
     * Get booking duration in minutes
     */
    public function getDurationMinutes(): int
    {
        $start = strtotime($this->startTime);
        $end = strtotime($this->endTime);

        return ($end - $start) / 60;
    }

    /**
     * Check if this reservation conflicts with another
     */
    public function conflictsWith(Reservation $other): bool
    {
        if ($this->bookingDate !== $other->bookingDate) {
            return false;
        }

        $thisStart = strtotime($this->startTime);
        $thisEnd = strtotime($this->endTime);
        $otherStart = strtotime($other->startTime);
        $otherEnd = strtotime($other->endTime);

        return !($thisEnd <= $otherStart || $thisStart >= $otherEnd);
    }

    /**
     * Get the management URL for this booking
     */
    public function getManagementUrl(): string
    {
        $baseUrl = rtrim(Craft::$app->sites->getCurrentSite()->baseUrl, '/');
        return $baseUrl . '/booking/manage/' . $this->confirmationToken;
    }

    /**
     * Get the cancel URL for this booking
     */
    public function getCancelUrl(): string
    {
        $baseUrl = rtrim(Craft::$app->sites->getCurrentSite()->baseUrl, '/');
        return $baseUrl . '/booking/cancel/' . $this->confirmationToken;
    }

    /**
     * Get DateTime object for booking in Europe/Zurich timezone
     *
     * Times are stored in Europe/Zurich timezone directly.
     */
    public function getBookingDateTime(): ?\DateTime
    {
        if (empty($this->bookingDate) || empty($this->startTime)) {
            return null;
        }

        try {
            // Times are stored in Europe/Zurich timezone
            $dateTimeString = $this->bookingDate . ' ' . $this->startTime;
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTimeString, new \DateTimeZone('Europe/Zurich'));
            if (!$dateTime) {
                $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $dateTimeString, new \DateTimeZone('Europe/Zurich'));
            }

            return $dateTime;

        } catch (\Exception $e) {
            \Craft::error('Failed to create DateTime: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Find reservation by confirmation token
     */
    public static function findByToken(string $token): ?self
    {
        return self::find()
            ->where(['confirmationToken' => $token])
            ->one();
    }
}
