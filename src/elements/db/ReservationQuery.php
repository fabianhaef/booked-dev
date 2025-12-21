<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * ReservationQuery defines the condition builder for Reservation elements
 *
 * @method \fabian\booked\elements\Reservation[]|array all($db = null)
 * @method \fabian\booked\elements\Reservation|array|null one($db = null)
 * @method \fabian\booked\elements\Reservation|array|null nth(int $n, ?Connection $db = null)
 */
class ReservationQuery extends ElementQuery
{
    public ?string $userName = null;
    public ?string $userEmail = null;
    public ?string $userPhone = null;
    public ?string $bookingDate = null;
    public ?string $startTime = null;
    public ?string $endTime = null;
    public array|string|null $status = null;
    public ?int $variationId = null;
    public ?string $sourceType = null;
    public ?int $sourceId = null;

    /**
     * Filter by user name
     */
    public function userName(?string $value): static
    {
        $this->userName = $value;
        return $this;
    }

    /**
     * Filter by user email
     */
    public function userEmail(?string $value): static
    {
        $this->userEmail = $value;
        return $this;
    }

    /**
     * Filter by booking date
     */
    public function bookingDate(?string $value): static
    {
        $this->bookingDate = $value;
        return $this;
    }

    /**
     * Filter by start time
     */
    public function startTime(?string $value): static
    {
        $this->startTime = $value;
        return $this;
    }

    /**
     * Filter by end time
     */
    public function endTime(?string $value): static
    {
        $this->endTime = $value;
        return $this;
    }

    /**
     * Filter by status
     */
    public function status(array|string|null $value): static
    {
        $this->status = $value;
        return $this;
    }

    /**
     * Filter by variation ID
     */
    public function variationId(?int $value): static
    {
        $this->variationId = $value;
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
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // Join the bookings_reservations table
        $this->joinElementTable('bookings_reservations');

        // Select custom columns
        $this->query->select([
            'bookings_reservations.userName',
            'bookings_reservations.userEmail',
            'bookings_reservations.userPhone',
            'bookings_reservations.userTimezone',
            'bookings_reservations.bookingDate',
            'bookings_reservations.startTime',
            'bookings_reservations.endTime',
            'bookings_reservations.status',
            'bookings_reservations.notes',
            'bookings_reservations.notificationSent',
            'bookings_reservations.confirmationToken',
            'bookings_reservations.sourceType',
            'bookings_reservations.sourceId',
            'bookings_reservations.sourceHandle',
            'bookings_reservations.variationId',
            'bookings_reservations.quantity',
        ]);

        // Apply filters
        if ($this->userName) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.userName', $this->userName));
        }

        if ($this->userEmail) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.userEmail', $this->userEmail));
        }

        if ($this->bookingDate) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.bookingDate', $this->bookingDate));
        }

        if ($this->startTime) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.startTime', $this->startTime));
        }

        if ($this->endTime) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.endTime', $this->endTime));
        }

        if ($this->status) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.status', $this->status));
        }

        if ($this->variationId) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.variationId', $this->variationId));
        }

        if ($this->sourceType) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.sourceType', $this->sourceType));
        }

        if ($this->sourceId) {
            $this->subQuery->andWhere(Db::parseParam('bookings_reservations.sourceId', $this->sourceId));
        }

        return parent::beforePrepare();
    }
}
