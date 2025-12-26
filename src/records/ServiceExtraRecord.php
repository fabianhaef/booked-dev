<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * ServiceExtra Record
 *
 * Represents a service extra/add-on that can be selected during booking.
 * Examples: "Extended Session (+30 min)", "Premium Products", "Refreshments", etc.
 *
 * Note: This is now a proper Element. The 'id' column is a foreign key to elements.id.
 * Title is stored in the content table, enabled status in the elements table.
 *
 * @property int $id Foreign key to elements.id
 * @property string|null $description
 * @property float $price
 * @property int $duration Additional duration in minutes
 * @property int $maxQuantity Maximum quantity per booking
 * @property bool $isRequired Whether this extra is required
 * @property int $sortOrder Display order (deprecated, kept for backward compatibility)
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ServiceExtraRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_service_extras}}';
    }

    /**
     * Get services that offer this extra
     */
    public function getServices(): ActiveQueryInterface
    {
        return $this->hasMany(ServiceExtraServiceRecord::class, ['extraId' => 'id']);
    }

    /**
     * Get reservation extras (bookings that selected this extra)
     */
    public function getReservationExtras(): ActiveQueryInterface
    {
        return $this->hasMany(ReservationExtraRecord::class, ['extraId' => 'id']);
    }
}
