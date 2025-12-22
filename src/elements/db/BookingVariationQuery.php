<?php

namespace fabian\booked\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * BookingVariationQuery represents a SELECT SQL statement for booking variations.
 */
class BookingVariationQuery extends ElementQuery
{
    public ?bool $isActive = null;

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
        $this->joinElementTable('bookings_variations');

        $this->query->addSelect([
            'bookings_variations.description',
            'bookings_variations.slotDurationMinutes',
            'bookings_variations.bufferMinutes',
            'bookings_variations.maxCapacity',
            'bookings_variations.allowQuantitySelection',
            'bookings_variations.isActive',
        ]);

        // Apply custom filters
        if ($this->isActive !== null) {
            $this->subQuery->andWhere(Db::parseParam('bookings_variations.isActive', $this->isActive));
        }

        return parent::beforePrepare();
    }
}
