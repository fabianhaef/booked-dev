<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * BookingSequenceQuery represents a SELECT SQL statement for booking sequences.
 *
 * @method \fabian\booked\elements\BookingSequence[]|array all($db = null)
 * @method \fabian\booked\elements\BookingSequence|array|null one($db = null)
 * @method \fabian\booked\elements\BookingSequence|array|null nth(int $n, ?Connection $db = null)
 */
class BookingSequenceQuery extends ElementQuery
{
    // Public properties for query parameters
    public mixed $userId = null;
    public mixed $customerEmail = null;
    public array|string|null $status = null; // Must match parent ElementQuery type

    /**
     * Filter by user ID
     */
    public function userId(mixed $value): self
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Filter by customer email
     */
    public function customerEmail(mixed $value): self
    {
        $this->customerEmail = $value;
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
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // Join in the booking sequences table
        $this->joinElementTable('booked_booking_sequences');

        // Select the columns
        $this->query->select([
            'booked_booking_sequences.userId',
            'booked_booking_sequences.customerEmail',
            'booked_booking_sequences.customerName',
            'booked_booking_sequences.status',
            'booked_booking_sequences.totalPrice',
        ]);

        // Apply filters
        if ($this->userId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_booking_sequences.userId', $this->userId));
        }

        if ($this->customerEmail !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_booking_sequences.customerEmail', $this->customerEmail));
        }

        if ($this->status !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_booking_sequences.status', $this->status));
        }

        return parent::beforePrepare();
    }
}
