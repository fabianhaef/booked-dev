<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Schedule-Employee Junction Record
 *
 * @property int $id
 * @property int $scheduleId
 * @property int $employeeId
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class ScheduleEmployeeRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%booked_schedule_employees}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['scheduleId', 'employeeId'], 'required'],
            [['scheduleId', 'employeeId'], 'integer'],
        ];
    }
}
