<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Schedule Active Record
 *
 * @property int $id
 * @property string|null $title Schedule title
 * @property int|null $employeeId Foreign key to Employee element (deprecated)
 * @property int|null $dayOfWeek Day of week (0 = Sunday, 6 = Saturday) - DEPRECATED
 * @property string|null $daysOfWeek JSON array of days
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
            [['daysOfWeek'], 'required'],
            [['employeeId', 'dayOfWeek'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 1, 'max' => 7], // New format: 1=Monday, 7=Sunday
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['title'], 'string', 'max' => 255],
            [['daysOfWeek'], 'string'], // JSON stored as string
        ];
    }
}

