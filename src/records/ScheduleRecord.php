<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Schedule Active Record
 *
 * @property int $id
 * @property string|null $title Schedule title
 * @property int|null $serviceId FK to Service element
 * @property int|null $employeeId FK to Employee element
 * @property int|null $locationId FK to Location element
 * @property int|null $dayOfWeek Day of week (1-7, Monday=1) - DEPRECATED
 * @property string|null $daysOfWeek JSON array of days
 * @property string|null $startTime Start time (H:i format)
 * @property string|null $endTime End time (H:i format)
 * @property int $capacity Number of people per booking slot
 * @property int $simultaneousSlots Number of parallel resources
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
            [['daysOfWeek'], 'required'],
            [['dayOfWeek'], 'integer', 'min' => 1, 'max' => 7],
            [['serviceId', 'employeeId', 'locationId'], 'integer'],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['title'], 'string', 'max' => 255],
            [['daysOfWeek'], 'string'],
            [['capacity', 'simultaneousSlots'], 'integer', 'min' => 1],
            [['capacity', 'simultaneousSlots'], 'default', 'value' => 1],
        ];
    }
}

