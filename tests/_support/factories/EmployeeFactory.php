<?php

namespace fabian\booked\tests\_support\factories;

use fabian\booked\elements\Employee;

/**
 * Factory for creating test Employee elements
 */
class EmployeeFactory
{
    private array $attributes = [];

    public static function create(array $attributes = []): Employee
    {
        return (new self())->make($attributes);
    }

    public function withTitle(string $title): self
    {
        $this->attributes['title'] = $title;
        return $this;
    }

    public function withUser(int $userId): self
    {
        $this->attributes['userId'] = $userId;
        return $this;
    }

    public function withLocation(int $locationId): self
    {
        $this->attributes['locationId'] = $locationId;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->attributes['email'] = $email;
        return $this;
    }

    public function make(array $overrides = []): Employee
    {
        $defaults = [
            'title' => 'Test Employee',
            'userId' => null,
            'locationId' => null,
            'email' => 'employee@example.com',
        ];

        $attributes = array_merge($defaults, $this->attributes, $overrides);

        // Create a mock employee
        $employee = new class extends Employee {
            public function __construct() {
                // Skip parent constructor
            }

            public function getLocation(): ?\fabian\booked\elements\Location {
                return null;
            }
        };

        foreach ($attributes as $key => $value) {
            $employee->$key = $value;
        }

        $employee->id = rand(1000, 9999);

        return $employee;
    }
}
