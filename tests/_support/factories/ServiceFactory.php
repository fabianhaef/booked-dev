<?php

namespace fabian\booked\tests\_support\factories;

use fabian\booked\elements\Service;
use fabian\booked\records\ServiceRecord;

/**
 * Factory for creating test Service elements
 */
class ServiceFactory
{
    private array $attributes = [];

    public static function create(array $attributes = []): Service
    {
        return (new self())->make($attributes);
    }

    public function withTitle(string $title): self
    {
        $this->attributes['title'] = $title;
        return $this;
    }

    public function withDuration(int $minutes): self
    {
        $this->attributes['duration'] = $minutes;
        return $this;
    }

    public function withPrice(float $price): self
    {
        $this->attributes['price'] = $price;
        return $this;
    }

    public function withBuffers(int $before = 0, int $after = 0): self
    {
        $this->attributes['bufferBefore'] = $before;
        $this->attributes['bufferAfter'] = $after;
        return $this;
    }

    public function enabled(bool $enabled = true): self
    {
        $this->attributes['enabled'] = $enabled;
        return $this;
    }

    public function make(array $overrides = []): Service
    {
        $defaults = [
            'title' => 'Test Service',
            'duration' => 60,
            'bufferBefore' => 0,
            'bufferAfter' => 0,
            'price' => 100.00,
            'enabled' => true,
        ];

        $attributes = array_merge($defaults, $this->attributes, $overrides);

        // Create a mock service that doesn't require database
        $service = new class extends Service {
            public function __construct() {
                // Skip parent constructor to avoid DB dependencies
            }
        };

        foreach ($attributes as $key => $value) {
            $service->$key = $value;
        }

        // Set a mock ID
        $service->id = rand(1000, 9999);

        return $service;
    }
}
