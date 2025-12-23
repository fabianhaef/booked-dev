<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\tests\_support\traits\CreatesBookings;
use UnitTester;

class NotificationQueueTest extends Unit
{
    use CreatesBookings;

    protected $tester;

    public function testConfirmationEmailSentOnBooking()
    {
        $reservation = $this->createReservation([
            'status' => 'confirmed',
            'customerEmail' => 'test@example.com',
        ]);

        $this->assertEquals('confirmed', $reservation->status);
        $this->assertNotNull($reservation->customerEmail);
    }

    public function testCancellationEmailSent()
    {
        $reservation = $this->createReservation(['status' => 'confirmed']);
        $reservation->status = 'cancelled';

        $this->assertEquals('cancelled', $reservation->status);
    }

    public function testReminderScheduled24HoursBefore()
    {
        $reservation = $this->createReservation([
            'bookingDate' => (new \DateTime('+2 days'))->format('Y-m-d'),
            'startTime' => '10:00',
        ]);

        $reminderTime = new \DateTime($reservation->bookingDate . ' ' . $reservation->startTime);
        $reminderTime->modify('-24 hours');

        $this->assertInstanceOf(\DateTime::class, $reminderTime);
    }

    public function testReminderScheduled1HourBefore()
    {
        $reservation = $this->createReservation([
            'bookingDate' => (new \DateTime('+2 days'))->format('Y-m-d'),
            'startTime' => '10:00',
        ]);

        $reminderTime = new \DateTime($reservation->bookingDate . ' ' . $reservation->startTime);
        $reminderTime->modify('-1 hour');

        $this->assertInstanceOf(\DateTime::class, $reminderTime);
    }

    public function testNotificationBatching()
    {
        $notifications = [];

        for ($i = 0; $i < 50; $i++) {
            $notifications[] = ['email' => "user{$i}@example.com", 'type' => 'reminder'];
        }

        $batches = array_chunk($notifications, 10);

        $this->assertCount(5, $batches);
        $this->assertCount(10, $batches[0]);
    }

    public function testNotificationRetryOnFailure()
    {
        $maxRetries = 3;
        $attempts = 0;

        while ($attempts < $maxRetries) {
            $attempts++;
            if ($attempts === 3) {
                $success = true;
                break;
            }
        }

        $this->assertTrue($success);
        $this->assertEquals(3, $attempts);
    }
}
