<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Schedule Active Record
 *
 * @property int $id
 * @property int|null $employeeId Foreign key to Employee element
 * @property int|null $dayOfWeek Day of week (0 = Sunday, 6 = Saturday)
 * @property string|null $startTime Start time (H:i format)
 * @property string|null $endTime End time (H:i format)
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ScheduleRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_schedules}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['employeeId', 'dayOfWeek'], 'required'],
            [['employeeId', 'dayOfWeek'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 0, 'max' => 6],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
        ];
    }
}

