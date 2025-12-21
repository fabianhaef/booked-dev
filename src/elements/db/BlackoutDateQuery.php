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
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // Join the bookings_blackout_dates table
        $this->joinElementTable('bookings_blackout_dates');

        // Select custom columns
        $this->query->select([
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

        return parent::beforePrepare();
    }
}
