<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\BookingSequenceQuery;
use fabian\booked\records\BookingSequenceRecord;

/**
 * BookingSequence Element
 *
 * Represents a collection of reservations booked back-to-back
 *
 * @property int|null $userId
 * @property string $customerEmail
 * @property string $customerName
 * @property string $status
 * @property float $totalPrice
 * @property Reservation[] $items
 * @property User|null $user
 */
class BookingSequence extends Element
{
    public ?int $userId = null;
    public string $customerEmail = '';
    public string $customerName = '';
    public string $status = BookingSequenceRecord::STATUS_PENDING;
    public float $totalPrice = 0.0;

    private ?array $_items = null;
    private ?User $_user = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Booking Sequence');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Booking Sequences');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'bookingSequence';
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
            BookingSequenceRecord::STATUS_PENDING => ['label' => Craft::t('booked', 'Pending'), 'color' => 'orange'],
            BookingSequenceRecord::STATUS_CONFIRMED => ['label' => Craft::t('booked', 'Confirmed'), 'color' => 'green'],
            BookingSequenceRecord::STATUS_CANCELLED => ['label' => Craft::t('booked', 'Cancelled'), 'color' => 'red'],
            BookingSequenceRecord::STATUS_COMPLETED => ['label' => Craft::t('booked', 'Completed'), 'color' => 'blue'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        return $this->status;
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
    public static function find(): ElementQueryInterface
    {
        return new BookingSequenceQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Sequences'),
            ],
            [
                'key' => 'pending',
                'label' => Craft::t('booked', 'Pending'),
                'criteria' => ['status' => BookingSequenceRecord::STATUS_PENDING],
            ],
            [
                'key' => 'confirmed',
                'label' => Craft::t('booked', 'Confirmed'),
                'criteria' => ['status' => BookingSequenceRecord::STATUS_CONFIRMED],
            ],
            [
                'key' => 'cancelled',
                'label' => Craft::t('booked', 'Cancelled'),
                'criteria' => ['status' => BookingSequenceRecord::STATUS_CANCELLED],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'customerName' => ['label' => Craft::t('booked', 'Customer Name')],
            'customerEmail' => ['label' => Craft::t('booked', 'Email')],
            'itemCount' => ['label' => Craft::t('booked', 'Services')],
            'totalDuration' => ['label' => Craft::t('booked', 'Duration')],
            'totalPrice' => ['label' => Craft::t('booked', 'Total Price')],
            'status' => ['label' => Craft::t('booked', 'Status')],
            'dateCreated' => ['label' => Craft::t('booked', 'Date Created')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['customerName', 'customerEmail', 'itemCount', 'totalPrice', 'status', 'dateCreated'];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'itemCount':
                return (string) count($this->getItems());

            case 'totalDuration':
                return $this->getTotalDuration() . ' min';

            case 'totalPrice':
                return Craft::$app->formatter->asCurrency($this->totalPrice, 'CHF');
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * Get all reservations in this sequence
     *
     * @return Reservation[]
     */
    public function getItems(): array
    {
        if ($this->_items === null) {
            $this->_items = Reservation::find()
                ->andWhere(['sequenceId' => $this->id])
                ->orderBy(['sequenceOrder' => SORT_ASC])
                ->all();
        }
        return $this->_items;
    }

    /**
     * Get the user associated with this sequence
     */
    public function getUser(): ?User
    {
        if ($this->_user === null && $this->userId) {
            $this->_user = User::find()->id($this->userId)->one();
        }
        return $this->_user;
    }

    /**
     * Calculate total duration of sequence
     *
     * @return int Duration in minutes
     */
    public function getTotalDuration(): int
    {
        $duration = 0;
        foreach ($this->getItems() as $item) {
            $service = $item->getService();
            if ($service) {
                $duration += $service->duration;
                if ($service->bufferAfter) {
                    $duration += $service->bufferAfter;
                }
            }
        }
        return $duration;
    }

    /**
     * Get first reservation in sequence
     */
    public function getFirstReservation(): ?Reservation
    {
        $items = $this->getItems();
        return $items[0] ?? null;
    }

    /**
     * Get last reservation in sequence
     */
    public function getLastReservation(): ?Reservation
    {
        $items = $this->getItems();
        return end($items) ?: null;
    }

    /**
     * Cancel entire sequence
     */
    public function cancel(): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Cancel all reservations
            foreach ($this->getItems() as $item) {
                $item->status = ReservationRecord::STATUS_CANCELLED;
                if (!Craft::$app->elements->saveElement($item)) {
                    throw new \Exception('Failed to cancel reservation: ' . implode(', ', $item->getErrorSummary(true)));
                }
            }

            // Update sequence status
            $this->status = BookingSequenceRecord::STATUS_CANCELLED;
            if (!Craft::$app->elements->saveElement($this)) {
                throw new \Exception('Failed to cancel sequence: ' . implode(', ', $this->getErrorSummary(true)));
            }

            $transaction->commit();
            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            Craft::error('Failed to cancel booking sequence: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = BookingSequenceRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid booking sequence ID: ' . $this->id);
            }
        } else {
            $record = new BookingSequenceRecord();
            $record->id = (int) $this->id;
        }

        $record->userId = $this->userId;
        $record->customerEmail = $this->customerEmail;
        $record->customerName = $this->customerName;
        $record->status = $this->status;
        $record->totalPrice = $this->totalPrice;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        // Delete all associated reservations
        foreach ($this->getItems() as $item) {
            Craft::$app->elements->deleteElement($item);
        }

        return parent::beforeDelete();
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booked/sequences/' . $this->id);
    }
}
