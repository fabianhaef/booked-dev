<?php

namespace modules\booking\records;

use craft\db\ActiveRecord;

/**
 * Availability Active Record
 *
 * @property int $id
 * @property int $dayOfWeek 0 = Sunday, 6 = Saturday
 * @property string $startTime
 * @property string $endTime
 * @property bool $isActive
 * @property string $availabilityType 'recurring' or 'event'
 * @property string|null $description
 * @property string $sourceType
 * @property int|null $sourceId
 * @property string|null $sourceHandle
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class AvailabilityRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_availability}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['sourceType', 'availabilityType'], 'required'],
            [['dayOfWeek', 'sourceId'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 0, 'max' => 6],
            // Accept both H:i and H:i:s formats (HTML5 time input returns H:i:s)
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['isActive'], 'boolean'],
            [['isActive'], 'default', 'value' => true],
            [['availabilityType'], 'in', 'range' => ['recurring', 'event']],
            [['availabilityType'], 'default', 'value' => 'recurring'],
            [['sourceType'], 'in', 'range' => ['entry', 'section']],
            [['sourceType'], 'default', 'value' => 'section'],
            [['sourceHandle'], 'string', 'max' => 255],
            [['description'], 'string'],
            // Conditional validation: dayOfWeek, startTime, endTime required for recurring
            [['dayOfWeek', 'startTime', 'endTime'], 'required', 'when' => function($model) {
                return $model->availabilityType === 'recurring';
            }],
        ];
    }

    /**
     * Get day name from dayOfWeek number
     */
    public function getDayName(): string
    {
        $days = [
            0 => 'Sonntag',
            1 => 'Montag', 
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag'
        ];
        
        return $days[$this->dayOfWeek] ?? 'Unbekannt';
    }

    /**
     * Get event dates relation
     */
    public function getEventDates(): \yii\db\ActiveQuery
    {
        return $this->hasMany(EventDateRecord::class, ['availabilityId' => 'id']);
    }
}
