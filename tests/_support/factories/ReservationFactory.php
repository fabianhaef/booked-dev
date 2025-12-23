<?php

namespace fabian\booked\tests\_support\factories;

use fabian\booked\elements\Reservation;
use DateTime;

/**
 * Factory for creating test Reservation elements
 */
class ReservationFactory
{
    private array $attributes = [];

    public static function create(array $attributes = []): Reservation
    {
        return (new self())->make($attributes);
    }

    public function withService(int $serviceId): self
    {
        $this->attributes['serviceId'] = $serviceId;
        return $this;
    }

    public function withEmployee(int $employeeId): self
    {
        $this->attributes['employeeId'] = $employeeId;
        return $this;
    }

    public function withLocation(int $locationId): self
    {
        $this->attributes['locationId'] = $locationId;
        return $this;
    }

    public function withCustomer(string $name, string $email, ?string $phone = null): self
    {
        $this->attributes['customerName'] = $name;
        $this->attributes['customerEmail'] = $email;
        if ($phone) {
            $this->attributes['customerPhone'] = $phone;
        }
        return $this;
    }

    public function withDateTime(string $date, string $startTime, string $endTime): self
    {
        $this->attributes['bookingDate'] = $date;
        $this->attributes['startTime'] = $startTime;
        $this->attributes['endTime'] = $endTime;
        return $this;
    }

    public function withStatus(string $status): self
    {
        $this->attributes['status'] = $status;
        return $this;
    }

    public function withQuantity(int $quantity): self
    {
        $this->attributes['quantity'] = $quantity;
        return $this;
    }

    public function confirmed(): self
    {
        $this->attributes['status'] = 'confirmed';
        return $this;
    }

    public function pending(): self
    {
        $this->attributes['status'] = 'pending';
        return $this;
    }

    public function cancelled(): self
    {
        $this->attributes['status'] = 'cancelled';
        return $this;
    }

    public function make(array $overrides = []): Reservation
    {
        $tomorrow = (new DateTime('+1 day'))->format('Y-m-d');

        $defaults = [
            'serviceId' => 1,
            'employeeId' => 1,
            'locationId' => null,
            'customerName' => 'Test Customer',
            'customerEmail' => 'customer@example.com',
            'customerPhone' => null,
            'bookingDate' => $tomorrow,
            'startTime' => '10:00',
            'endTime' => '11:00',
            'status' => 'confirmed',
            'quantity' => 1,
            'confirmationToken' => bin2hex(random_bytes(16)),
        ];

        $attributes = array_merge($defaults, $this->attributes, $overrides);

        $reservation = new class extends Reservation {
            public function __construct() {}

            public function getService(): ?\fabian\booked\elements\Service {
                return null;
            }

            public function getEmployee(): ?\fabian\booked\elements\Employee {
                return null;
            }

            public function getLocation(): ?\fabian\booked\elements\Location {
                return null;
            }

            public function getFieldLayout(): ?\craft\models\FieldLayout {
                return null;
            }
        };

        foreach ($attributes as $key => $value) {
            $reservation->$key = $value;
        }

        $reservation->id = rand(1000, 9999);

        return $reservation;
    }
}
