<?php

namespace fabian\booked\tests\_support\Helper;

use Codeception\Module;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Service;
use fabian\booked\elements\Location;
use fabian\booked\elements\Reservation;

/**
 * Integration Test Helper
 *
 * Provides utilities for integration testing with full Craft CMS
 */
class Integration extends Module
{
    /**
     * Create a test employee
     *
     * @param array $attributes Employee attributes
     * @return Employee
     */
    public function createEmployee(array $attributes = []): Employee
    {
        $employee = new Employee();
        $employee->title = $attributes['title'] ?? 'Test Employee';
        $employee->email = $attributes['email'] ?? 'employee@test.com';
        $employee->locationId = $attributes['locationId'] ?? null;

        if (isset($attributes['serviceIds'])) {
            $employee->serviceIds = $attributes['serviceIds'];
        }

        \Craft::$app->elements->saveElement($employee);

        return $employee;
    }

    /**
     * Create a test service
     *
     * @param array $attributes Service attributes
     * @return Service
     */
    public function createService(array $attributes = []): Service
    {
        $service = new Service();
        $service->title = $attributes['title'] ?? 'Test Service';
        $service->duration = $attributes['duration'] ?? 60;
        $service->bufferBefore = $attributes['bufferBefore'] ?? 0;
        $service->bufferAfter = $attributes['bufferAfter'] ?? 0;
        $service->maxCapacity = $attributes['maxCapacity'] ?? 1;
        $service->isActive = $attributes['isActive'] ?? true;

        \Craft::$app->elements->saveElement($service);

        return $service;
    }

    /**
     * Create a test location
     *
     * @param array $attributes Location attributes
     * @return Location
     */
    public function createLocation(array $attributes = []): Location
    {
        $location = new Location();
        $location->title = $attributes['title'] ?? 'Test Location';
        $location->timezone = $attributes['timezone'] ?? 'UTC';
        $location->isActive = $attributes['isActive'] ?? true;

        \Craft::$app->elements->saveElement($location);

        return $location;
    }

    /**
     * Create a test reservation
     *
     * @param array $attributes Reservation attributes
     * @return Reservation
     */
    public function createReservation(array $attributes = []): Reservation
    {
        $reservation = new Reservation();
        $reservation->serviceId = $attributes['serviceId'] ?? 1;
        $reservation->employeeId = $attributes['employeeId'] ?? 1;
        $reservation->customerName = $attributes['customerName'] ?? 'Test Customer';
        $reservation->customerEmail = $attributes['customerEmail'] ?? 'customer@test.com';
        $reservation->bookingDate = $attributes['bookingDate'] ?? date('Y-m-d', strtotime('+1 day'));
        $reservation->startTime = $attributes['startTime'] ?? '10:00';
        $reservation->endTime = $attributes['endTime'] ?? '11:00';
        $reservation->status = $attributes['status'] ?? 'confirmed';

        \Craft::$app->elements->saveElement($reservation);

        return $reservation;
    }

    /**
     * Clean up all test data
     */
    public function cleanupTestData()
    {
        // This will be handled by Craft's transaction rollback in tests
        // But can be used for manual cleanup if needed
    }
}
