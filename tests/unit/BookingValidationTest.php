<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\elements\Reservation;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

class BookingValidationTest extends Unit
{
    use CreatesBookings;

    protected $tester;

    public function testPastDateRejection()
    {
        $reservation = new Reservation();
        $reservation->bookingDate = (new \DateTime('-1 day'))->format('Y-m-d');
        $reservation->startTime = '10:00';
        $reservation->endTime = '11:00';

        $reservation->validate();

        $this->assertTrue($reservation->hasErrors('bookingDate'));
    }

    public function testInvalidTimeRange()
    {
        $reservation = new Reservation();
        $reservation->bookingDate = (new \DateTime('+7 days'))->format('Y-m-d');
        $reservation->startTime = '15:00';
        $reservation->endTime = '14:00'; // End before start

        $reservation->validate();

        $this->assertTrue($reservation->hasErrors('endTime'));
    }

    public function testEmailFormatValidation()
    {
        $reservation = new Reservation();
        $reservation->customerEmail = 'invalid-email';

        $reservation->validate();

        $this->assertTrue($reservation->hasErrors('customerEmail'));
    }

    public function testPhoneNumberValidation()
    {
        $reservation = new Reservation();

        // Invalid phone
        $reservation->customerPhone = 'abc123';
        $reservation->validate();
        $this->assertTrue($reservation->hasErrors('customerPhone'));

        // Valid international
        $reservation->customerPhone = '+1-555-123-4567';
        $reservation->validate();
        $this->assertFalse($reservation->hasErrors('customerPhone'));
    }

    public function testBookingWindowRestriction()
    {
        $service = $this->createService([
            'title' => 'Consultation',
            'minimumNotice' => 24, // 24 hours
        ]);

        $reservation = new Reservation();
        $reservation->serviceId = $service->id;
        $reservation->bookingDate = (new \DateTime('+12 hours'))->format('Y-m-d');
        $reservation->startTime = '10:00';

        $reservation->validate();

        $this->assertTrue($reservation->hasErrors('bookingDate'));
    }

    public function testMaximumAdvanceBooking()
    {
        $service = $this->createService([
            'title' => 'Consultation',
            'maximumAdvance' => 90, // 90 days
        ]);

        $reservation = new Reservation();
        $reservation->serviceId = $service->id;
        $reservation->bookingDate = (new \DateTime('+100 days'))->format('Y-m-d');
        $reservation->startTime = '10:00';

        $reservation->validate();

        $this->assertTrue($reservation->hasErrors('bookingDate'));
    }
}
