<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Blackout Date Active Record
 *
 * @property int $id
 * @property int|null $locationId
 * @property int|null $employeeId
 * @property string $startDate
 * @property string $endDate
 * @property string|null $reason
 * @property bool $isActive
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class BlackoutDateRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_blackout_dates}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['startDate', 'endDate'], 'required'],
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d'],
            [['reason'], 'string'],
            [['locationId', 'employeeId'], 'integer'],
            [['isActive'], 'boolean'],
            [['isActive'], 'default', 'value' => true],
        ];
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
}
