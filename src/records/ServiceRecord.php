<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Service Active Record
 *
 * @property int $id
 * @property int|null $duration Duration in minutes
 * @property int|null $bufferBefore Buffer time before service in minutes
 * @property int|null $bufferAfter Buffer time after service in minutes
 * @property float|null $price Service price
 * @property string|null $virtualMeetingProvider Virtual meeting provider
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ServiceRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_services}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['duration', 'bufferBefore', 'bufferAfter'], 'integer', 'min' => 0],
            [['price'], 'number', 'min' => 0],
        ];
    }
}

