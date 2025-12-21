<?php

namespace modules\booking\records;

use craft\db\ActiveRecord;

/**
 * BookingVariation Record
 *
 * @property int $id
 * @property string|null $description
 * @property int|null $slotDurationMinutes
 * @property int|null $bufferMinutes
 * @property int $maxCapacity
 * @property bool $allowQuantitySelection
 * @property bool $isActive
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class BookingVariationRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_variations}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['description'], 'string'],
            [['slotDurationMinutes', 'bufferMinutes', 'maxCapacity'], 'integer'],
            [['maxCapacity'], 'required'],
            [['maxCapacity'], 'default', 'value' => 1],
            [['isActive', 'allowQuantitySelection'], 'boolean'],
            [['allowQuantitySelection'], 'default', 'value' => false],
        ];
    }
}
