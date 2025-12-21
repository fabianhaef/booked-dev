<?php

namespace fabian\booked\models;

use Craft;
use craft\base\Model;
use fabian\booked\records\BlackoutDateRecord;

/**
 * Blackout Date Model
 */
class BlackoutDate extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $startDate = '';
    public string $endDate = '';
    public ?string $reason = null;
    public bool $isActive = true;
    public ?string $dateCreated = null;
    public ?string $dateUpdated = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name', 'startDate', 'endDate'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d'],
            [['reason'], 'string'],
            [['isActive'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'name' => 'Name',
            'startDate' => 'Start Date',
            'endDate' => 'End Date',
            'reason' => 'Reason',
            'isActive' => 'Active',
        ];
    }

    /**
     * Validate that end date is after or equal to start date
     */
    public function validateDateRange(): void
    {
        if ($this->startDate && $this->endDate) {
            $start = strtotime($this->startDate);
            $end = strtotime($this->endDate);

            if ($end < $start) {
                $this->addError('endDate', 'End date must be after or equal to start date.');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        $this->validateDateRange();
        return parent::beforeValidate();
    }

    /**
     * Save blackout date to database
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->id) {
            $record = BlackoutDateRecord::findOne($this->id);
        } else {
            $record = new BlackoutDateRecord();
        }

        if (!$record) {
            return false;
        }

        $record->name = $this->name;
        $record->startDate = $this->startDate;
        $record->endDate = $this->endDate;
        $record->reason = $this->reason;
        $record->isActive = $this->isActive;

        if ($record->save()) {
            $this->id = $record->id;
            return true;
        }

        $this->addErrors($record->getErrors());
        return false;
    }

    /**
     * Delete blackout date from database
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        $record = BlackoutDateRecord::findOne($this->id);
        if ($record) {
            return $record->delete() !== false;
        }

        return false;
    }

    /**
     * Load blackout date from record
     */
    public static function fromRecord(BlackoutDateRecord $record): self
    {
        $model = new self();
        $model->id = $record->id;
        $model->name = $record->name;
        $model->startDate = $record->startDate;
        $model->endDate = $record->endDate;
        $model->reason = $record->reason;
        $model->isActive = $record->isActive;
        $model->dateCreated = $record->dateCreated;
        $model->dateUpdated = $record->dateUpdated;

        return $model;
    }

    /**
     * Get formatted date range
     */
    public function getFormattedDateRange(): string
    {
        if (empty($this->startDate) || empty($this->endDate)) {
            return '';
        }

        $start = \DateTime::createFromFormat('Y-m-d', $this->startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $this->endDate);

        if (!$start || !$end) {
            return $this->startDate . ' - ' . $this->endDate;
        }

        $startFormatted = $start->format('d.m.Y');
        $endFormatted = $end->format('d.m.Y');

        // If same date, show only once
        if ($this->startDate === $this->endDate) {
            return $startFormatted;
        }

        return $startFormatted . ' - ' . $endFormatted;
    }

    /**
     * Check if a specific date falls within this blackout period
     */
    public function containsDate(string $date): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $checkDate = strtotime($date);
        $start = strtotime($this->startDate);
        $end = strtotime($this->endDate);

        return $checkDate >= $start && $checkDate <= $end;
    }

    /**
     * Get duration in days
     */
    public function getDurationDays(): int
    {
        $start = strtotime($this->startDate);
        $end = strtotime($this->endDate);

        return (int) (($end - $start) / 86400) + 1; // +1 to include both start and end days
    }

    /**
     * Check if blackout date is currently active (date-wise)
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $today = date('Y-m-d');
        return $this->containsDate($today);
    }

    /**
     * Check if blackout date is in the future
     */
    public function isFuture(): bool
    {
        $today = date('Y-m-d');
        return $this->startDate > $today;
    }

    /**
     * Check if blackout date is in the past
     */
    public function isPast(): bool
    {
        $today = date('Y-m-d');
        return $this->endDate < $today;
    }
}
