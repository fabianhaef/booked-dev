<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Employee Active Record
 *
 * @property int $id
 * @property int|null $userId Foreign key to User element
 * @property int|null $locationId Foreign key to Location element
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class EmployeeRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_employees}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['userId', 'locationId'], 'integer'],
        ];
    }
}

