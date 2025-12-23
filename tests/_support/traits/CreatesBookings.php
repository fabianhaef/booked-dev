<?php

namespace fabian\booked\tests\_support\traits;

use fabian\booked\tests\_support\factories\ServiceFactory;
use fabian\booked\tests\_support\factories\EmployeeFactory;
use fabian\booked\tests\_support\factories\LocationFactory;
use fabian\booked\tests\_support\factories\ReservationFactory;

/**
 * Trait for tests that need to create bookings and related entities
 */
trait CreatesBookings
{
    protected function createService(array $attributes = [])
    {
        return ServiceFactory::create($attributes);
    }

    protected function createEmployee(array $attributes = [])
    {
        return EmployeeFactory::create($attributes);
    }

    protected function createLocation(array $attributes = [])
    {
        return LocationFactory::create($attributes);
    }

    protected function createReservation(array $attributes = [])
    {
        return ReservationFactory::create($attributes);
    }

    protected function createBookingScenario(): array
    {
        $location = $this->createLocation([
            'title' => 'Main Office',
            'timezone' => 'Europe/Zurich',
        ]);

        $service = $this->createService([
            'title' => 'Consultation',
            'duration' => 60,
            'bufferBefore' => 15,
            'bufferAfter' => 15,
        ]);

        $employee = $this->createEmployee([
            'title' => 'Dr. Smith',
            'locationId' => $location->id,
        ]);

        return [
            'location' => $location,
            'service' => $service,
            'employee' => $employee,
        ];
    }
}
