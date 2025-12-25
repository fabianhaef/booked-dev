<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * ReservationExtra Record
 *
 * Tracks which extras were selected for a specific reservation/booking.
 * Stores quantity and the price at time of booking (for historical accuracy).
 *
 * @property int $id
 * @property int $reservationId
 * @property int $extraId
 * @property int $quantity Number of this extra selected
 * @property float $price Price at time of booking (historical)
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ReservationExtraRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_reservation_extras}}';
    }

    /**
     * Get the reservation/booking
     */
    public function getReservation(): ActiveQueryInterface
    {
        return $this->hasOne(\craft\records\Element::class, ['id' => 'reservationId']);
    }

    /**
     * Get the service extra
     */
    public function getExtra(): ActiveQueryInterface
    {
        return $this->hasOne(ServiceExtraRecord::class, ['id' => 'extraId']);
    }

    /**
     * Calculate total price for this extra
     */
    public function getTotalPrice(): float
    {
        return $this->price * $this->quantity;
    }
}
