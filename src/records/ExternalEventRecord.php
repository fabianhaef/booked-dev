<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * ExternalEventRecord
 *
 * @property int $id
 * @property int $employeeId
 * @property string $provider
 * @property string $externalId
 * @property string|null $summary
 * @property string $startDate
 * @property string $startTime
 * @property string $endDate
 * @property string $endTime
 */
class ExternalEventRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_external_events}}';
    }
}

