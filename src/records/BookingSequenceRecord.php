<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * BookingSequence Record
 *
 * @property int $id
 * @property int|null $userId
 * @property string $customerEmail
 * @property string $customerName
 * @property string $status
 * @property float $totalPrice
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 * @property User|null $user
 * @property ReservationRecord[] $reservations
 */
class BookingSequenceRecord extends ActiveRecord
{
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_booking_sequences}}';
    }

    /**
     * Returns the sequence's user.
     *
     * @return ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * Returns the sequence's reservations.
     *
     * @return ActiveQueryInterface
     */
    public function getReservations(): ActiveQueryInterface
    {
        return $this->hasMany(ReservationRecord::class, ['sequenceId' => 'id'])
            ->orderBy(['sequenceOrder' => SORT_ASC]);
    }
}
