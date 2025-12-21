<?php

namespace modules\booking\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * AvailabilityQuery defines the condition builder for Availability elements
 *
 * @method \modules\booking\elements\Availability[]|array all($db = null)
 * @method \modules\booking\elements\Availability|array|null one($db = null)
 * @method \modules\booking\elements\Availability|array|null nth(int $n, ?Connection $db = null)
 */
class AvailabilityQuery extends ElementQuery
{
    public ?int $dayOfWeek = null;
    public ?string $availabilityType = null;
    public ?string $sourceType = null;
    public ?int $sourceId = null;
    public ?bool $isActive = null;

    /**
     * Filter by day of week
     */
    public function dayOfWeek(?int $value): static
    {
        $this->dayOfWeek = $value;
        return $this;
    }

    /**
     * Filter by availability type
     */
    public function availabilityType(?string $value): static
    {
        $this->availabilityType = $value;
        return $this;
    }

    /**
     * Filter by source type
     */
    public function sourceType(?string $value): static
    {
        $this->sourceType = $value;
        return $this;
    }

    /**
     * Filter by source ID
     */
    public function sourceId(?int $value): static
    {
        $this->sourceId = $value;
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
        // Join the bookings_availability table
        $this->joinElementTable('bookings_availability');

        // Select custom columns
        $this->query->select([
            'bookings_availability.dayOfWeek',
            'bookings_availability.startTime',
            'bookings_availability.endTime',
            'bookings_availability.isActive',
            'bookings_availability.availabilityType',
            'bookings_availability.description',
            'bookings_availability.sourceType',
            'bookings_availability.sourceId',
            'bookings_availability.sourceHandle',
        ]);

        // Apply filters
        if ($this->dayOfWeek !== null) {
            $this->subQuery->andWhere(Db::parseParam('bookings_availability.dayOfWeek', $this->dayOfWeek));
        }

        if ($this->availabilityType) {
            $this->subQuery->andWhere(Db::parseParam('bookings_availability.availabilityType', $this->availabilityType));
        }

        if ($this->sourceType) {
            $this->subQuery->andWhere(Db::parseParam('bookings_availability.sourceType', $this->sourceType));
        }

        if ($this->sourceId) {
            $this->subQuery->andWhere(Db::parseParam('bookings_availability.sourceId', $this->sourceId));
        }

        if ($this->isActive !== null) {
            $this->subQuery->andWhere(Db::parseParam('bookings_availability.isActive', $this->isActive));
        }

        return parent::beforePrepare();
    }
}
