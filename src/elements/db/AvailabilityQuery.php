<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * AvailabilityQuery defines the condition builder for Availability elements
 *
 * @method \fabian\booked\elements\Availability[]|array all($db = null)
 * @method \fabian\booked\elements\Availability|array|null one($db = null)
 * @method \fabian\booked\elements\Availability|array|null nth(int $n, ?Connection $db = null)
 */
class AvailabilityQuery extends ElementQuery
{
    public ?int $dayOfWeek = null;
    public ?string $availabilityType = null;
    public ?string $sourceType = null;
    public ?int $sourceId = null;
    public ?bool $isActive = null;
    public $serviceId = null;

    /**
     * Filter by day of week
     */
    public function dayOfWeek(?int $value): static
    {
        $this->dayOfWeek = $value;
        return $this;
    }

    /**
     * Filter by service ID
     */
    public function serviceId($value): static
    {
        $this->serviceId = $value;
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

        $this->query->addSelect([
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

        if ($this->serviceId !== null) {
            $this->subQuery->innerJoin('{{%booked_employees_services}} booked_employees_services', '[[booked_employees_services.employeeId]] = [[bookings_availability.sourceId]]');
            $this->subQuery->andWhere(Db::parseParam('booked_employees_services.serviceId', $this->serviceId));
        }

        return parent::beforePrepare();
    }
}
