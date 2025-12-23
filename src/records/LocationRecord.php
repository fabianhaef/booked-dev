<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Location Active Record
 *
 * @property int $id
 * @property string|null $timezone Location timezone
 * @property string|null $contactInfo Contact information
 * @property string|null $addressLine1
 * @property string|null $addressLine2
 * @property string|null $locality
 * @property string|null $administrativeArea
 * @property string|null $postalCode
 * @property string|null $countryCode
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class LocationRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_locations}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['timezone', 'contactInfo', 'addressLine1', 'addressLine2', 'locality', 'administrativeArea', 'postalCode', 'countryCode'], 'string'],
        ];
    }
}
