<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * SoftLockRecord
 *
 * @property int $id
 * @property string $token
 * @property int $serviceId
 * @property int|null $employeeId
 * @property int|null $locationId
 * @property string $date
 * @property string $startTime
 * @property string $endTime
 * @property string $expiresAt
 */
class SoftLockRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_soft_locks}}';
    }
}

