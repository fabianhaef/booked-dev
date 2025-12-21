<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * EventDate Active Record
 * Stores individual event date/time occurrences for event-type availability
 *
 * @property int $id
 * @property int $availabilityId
 * @property string $eventDate
 * @property string $startTime
 * @property string $endTime
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class EventDateRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_event_dates}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['availabilityId', 'eventDate', 'startTime', 'endTime'], 'required'],
            [['availabilityId'], 'integer'],
            [['eventDate'], 'date', 'format' => 'php:Y-m-d'],
            // Accept both H:i and H:i:s formats (HTML5 time input returns H:i:s)
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
        ];
    }

    /**
     * Get the parent availability record
     */
    public function getAvailability(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AvailabilityRecord::class, ['id' => 'availabilityId']);
    }

}
