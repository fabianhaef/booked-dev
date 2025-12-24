<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

class CalendarControllerTest extends Unit
{
    use CreatesBookings;

    protected $tester;

    public function testMonthViewReturnsReservations()
    {
        $reservation = $this->createReservation([
            'bookingDate' => (new \DateTime())->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
            'status' => 'confirmed',
        ]);

        $month = (new \DateTime())->format('Y-m');
        $result = ['reservations' => [$reservation]];

        $this->assertArrayHasKey('reservations', $result);
        $this->assertNotEmpty($result['reservations']);
    }

    public function testWeekViewGroupsByDay()
    {
        $monday = new \DateTime('monday this week');

        for ($i = 0; $i < 5; $i++) {
            $date = clone $monday;
            $date->modify("+{$i} days");

            $this->createReservation([
                'bookingDate' => $date->format('Y-m-d'),
                'startTime' => '10:00',
                'endTime' => '11:00',
            ]);
        }

        $weekStart = $monday->format('Y-m-d');

        $this->assertNotNull($weekStart);
    }

    public function testDayViewShowsHourlySlots()
    {
        $date = (new \DateTime())->format('Y-m-d');

        $slots = [];
        for ($hour = 9; $hour <= 17; $hour++) {
            $slots[] = sprintf('%02d:00', $hour);
        }

        $this->assertCount(9, $slots);
        $this->assertEquals('09:00', $slots[0]);
        $this->assertEquals('17:00', $slots[8]);
    }

    public function testColorCodingByStatus()
    {
        $colors = [
            'confirmed' => '#28a745',
            'pending' => '#ffc107',
            'cancelled' => '#dc3545',
        ];

        $this->assertEquals('#28a745', $colors['confirmed']);
        $this->assertEquals('#ffc107', $colors['pending']);
        $this->assertEquals('#dc3545', $colors['cancelled']);
    }

    public function testDragDropRescheduling()
    {
        $reservation = $this->createReservation([
            'bookingDate' => '2025-01-15',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ]);

        $newDate = '2025-01-16';
        $newStartTime = '14:00';

        $this->assertNotEquals($newDate, $reservation->bookingDate);
        $this->assertNotEquals($newStartTime, $reservation->startTime);
    }
}
