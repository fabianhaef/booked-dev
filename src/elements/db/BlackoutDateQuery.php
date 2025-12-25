<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * BlackoutDateQuery defines the condition builder for BlackoutDate elements
 *
 * @method \fabian\booked\elements\BlackoutDate[]|array all($db = null)
 * @method \fabian\booked\elements\BlackoutDate|array|null one($db = null)
 * @method \fabian\booked\elements\BlackoutDate|array|null nth(int $n, ?Connection $db = null)
 */
class BlackoutDateQuery extends ElementQuery
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?bool $isActive = null;
    public array|int|null $locationId = null;
    public array|int|null $employeeId = null;

    /**
     * Filter by start date
     */
    public function startDate(?string $value): static
    {
        $this->startDate = $value;
        return $this;
    }

    /**
     * Filter by end date
     */
    public function endDate(?string $value): static
    {
        $this->endDate = $value;
        return $this;
    }

    /**
     * Filter by active status
     */
    public function isActive(?bool $value): static
    {
        $this->isActive = $value;
        return $this;
    }

    /**
     * Filter by location ID(s)
     *
     * @param int|array|null $value Single ID, array of IDs, or null
     */
    public function locationId(array|int|null $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    /**
     * Filter by employee ID(s)
     *
     * @param int|array|null $value Single ID, array of IDs, or null
     */
    public function employeeId(array|int|null $value): static
    {
        $this->employeeId = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // Join the bookings_blackout_dates table
        $this->joinElementTable('bookings_blackout_dates');

        // Select only the columns that exist in the table
        $this->query->addSelect([
            'bookings_blackout_dates.startDate',
            'bookings_blackout_dates.endDate',
            'bookings_blackout_dates.isActive',
        ]);

        // Apply filters
        if ($this->startDate) {
            $this->subQuery->andWhere(Db::parseParam('bookings_blackout_dates.startDate', $this->startDate));
        }

        if ($this->endDate) {
            $this->subQuery->andWhere(Db::parseParam('bookings_blackout_dates.endDate', $this->endDate));
        }

        if ($this->isActive !== null) {
            $this->subQuery->andWhere(Db::parseParam('bookings_blackout_dates.isActive', $this->isActive));
        }

        // Filter by location using junction table
        if ($this->locationId !== null) {
            $locationIds = is_array($this->locationId) ? $this->locationId : [$this->locationId];

            $this->subQuery->andWhere([
                'in',
                'elements.id',
                (new \craft\db\Query())
                    ->select(['blackoutDateId'])
                    ->from('{{%bookings_blackout_dates_locations}}')
                    ->where(['in', 'locationId', $locationIds])
            ]);
        }

        // Filter by employee using junction table
        if ($this->employeeId !== null) {
            $employeeIds = is_array($this->employeeId) ? $this->employeeId : [$this->employeeId];

            $this->subQuery->andWhere([
                'in',
                'elements.id',
                (new \craft\db\Query())
                    ->select(['blackoutDateId'])
                    ->from('{{%bookings_blackout_dates_employees}}')
                    ->where(['in', 'employeeId', $employeeIds])
            ]);
        }

        return parent::beforePrepare();
    }
}
