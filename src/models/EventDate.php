<?php

namespace fabian\booked\models;

use craft\base\Model;
use fabian\booked\records\EventDateRecord;

/**
 * EventDate Model
 * Represents a single date/time occurrence for an event-type availability
 */
class EventDate extends Model
{
    public ?int $id = null;
    public ?int $availabilityId = null;
    public string $eventDate = '';
    public string $startTime = '';
    public string $endTime = '';

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
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'availabilityId' => 'Availability ID',
            'eventDate' => 'Event Date',
            'startTime' => 'Start Time',
            'endTime' => 'End Time',
        ];
    }

    /**
     * Validate that end time is after start time
     */
    public function validateTimeRange(): void
    {
        if ($this->startTime && $this->endTime) {
            $start = strtotime($this->startTime);
            $end = strtotime($this->endTime);

            if ($end <= $start) {
                $this->addError('endTime', 'End time must be after start time.');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        $this->validateTimeRange();
        return parent::beforeValidate();
    }

    /**
     * Save event date to database
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->id) {
            $record = EventDateRecord::findOne($this->id);
        } else {
            $record = new EventDateRecord();
        }

        if (!$record) {
            return false;
        }

        $record->availabilityId = $this->availabilityId;
        $record->eventDate = $this->eventDate;
        $record->startTime = $this->startTime;
        $record->endTime = $this->endTime;

        if ($record->save()) {
            $this->id = $record->id;
            return true;
        }

        $this->addErrors($record->getErrors());
        return false;
    }

    /**
     * Delete event date from database
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        $record = EventDateRecord::findOne($this->id);
        if ($record) {
            return $record->delete() !== false;
        }

        return false;
    }

    /**
     * Load event date from record
     */
    public static function fromRecord(EventDateRecord $record): self
    {
        $model = new self();
        $model->id = $record->id;
        $model->availabilityId = $record->availabilityId;
        $model->eventDate = $record->eventDate;
        $model->startTime = $record->startTime;
        $model->endTime = $record->endTime;

        return $model;
    }

    /**
     * Get formatted time range
     */
    public function getFormattedTimeRange(): string
    {
        if (empty($this->startTime) || empty($this->endTime)) {
            return '';
        }

        // Try to parse with seconds first (H:i:s), then without (H:i)
        $start = \DateTime::createFromFormat('H:i:s', $this->startTime)
            ?: \DateTime::createFromFormat('H:i', $this->startTime);
        $end = \DateTime::createFromFormat('H:i:s', $this->endTime)
            ?: \DateTime::createFromFormat('H:i', $this->endTime);

        if (!$start || !$end) {
            return $this->startTime . ' - ' . $this->endTime;
        }

        return $start->format('H:i') . ' - ' . $end->format('H:i');
    }

    /**
     * Get formatted date
     */
    public function getFormattedDate(): string
    {
        if (empty($this->eventDate)) {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $this->eventDate);
        if (!$date) {
            return $this->eventDate;
        }

        return $date->format('d.m.Y');
    }
}
