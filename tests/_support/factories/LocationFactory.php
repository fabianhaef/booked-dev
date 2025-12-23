<?php

namespace fabian\booked\tests\_support\factories;

use fabian\booked\elements\Location;

/**
 * Factory for creating test Location elements
 */
class LocationFactory
{
    private array $attributes = [];

    public static function create(array $attributes = []): Location
    {
        return (new self())->make($attributes);
    }

    public function withTitle(string $title): self
    {
        $this->attributes['title'] = $title;
        return $this;
    }

    public function withTimezone(string $timezone): self
    {
        $this->attributes['timezone'] = $timezone;
        return $this;
    }

    public function withAddress(string $street, string $city, string $postalCode, string $country): self
    {
        $this->attributes['addressStreet'] = $street;
        $this->attributes['addressCity'] = $city;
        $this->attributes['addressPostalCode'] = $postalCode;
        $this->attributes['addressCountry'] = $country;
        return $this;
    }

    public function make(array $overrides = []): Location
    {
        $defaults = [
            'title' => 'Test Location',
            'timezone' => 'Europe/Zurich',
            'addressStreet' => '123 Test Street',
            'addressCity' => 'Zurich',
            'addressPostalCode' => '8001',
            'addressCountry' => 'CH',
        ];

        $attributes = array_merge($defaults, $this->attributes, $overrides);

        $location = new class extends Location {
            public function __construct() {}
        };

        foreach ($attributes as $key => $value) {
            $location->$key = $value;
        }

        $location->id = rand(1000, 9999);

        return $location;
    }
}
