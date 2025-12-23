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
    public ?int $locationId = null;
    public ?int $employeeId = null;

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
     * Filter by location ID
     */
    public function locationId(?int $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    /**
     * Filter by employee ID
     */
    public function employeeId(?int $value): static
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

        $this->query->addSelect([
            'bookings_blackout_dates.startDate',
            'bookings_blackout_dates.endDate',
            'bookings_blackout_dates.isActive',
            'bookings_blackout_dates.locationId',
            'bookings_blackout_dates.employeeId',
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

        if ($this->locationId !== null) {
            $this->subQuery->andWhere(Db::parseParam('bookings_blackout_dates.locationId', $this->locationId));
        }

        if ($this->employeeId !== null) {
            $this->subQuery->andWhere(Db::parseParam('bookings_blackout_dates.employeeId', $this->employeeId));
        }

        return parent::beforePrepare();
    }
}
