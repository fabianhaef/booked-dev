<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\ReminderService;
use fabian\booked\elements\Reservation;
use fabian\booked\records\ReservationRecord;
use UnitTester;
use Craft;

/**
 * Testable version of ReminderService
 */
class TestableReminderService extends ReminderService
{
    public array $sentEmails = [];
    public array $sentSms = [];

    protected function sendEmailReminder(Reservation $reservation, string $type): bool
    {
        $this->sentEmails[] = ['id' => $reservation->id, 'type' => $type];
        return true;
    }

    protected function sendSmsReminder(Reservation $reservation, string $type): bool
    {
        $this->sentSms[] = ['id' => $reservation->id, 'type' => $type];
        return true;
    }

    protected function saveReservation(Reservation $reservation): bool
    {
        return true;
    }
}

class ReminderServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableReminderService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        
        // Mock Booked plugin instance
        $settings = new \fabian\booked\models\Settings();
        $settings->emailRemindersEnabled = true;
        $settings->emailReminderHoursBefore = 24;
        $settings->emailReminderOneHourBefore = true;
        
        $mockBooked = $this->getMockBuilder(\fabian\booked\Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSettings'])
            ->getMock();
        $mockBooked->method('getSettings')->willReturn($settings);
        
        // Use reflection to set the private static instance
        $reflection = new \ReflectionClass(\fabian\booked\Booked::class);
        $instanceProperty = $reflection->getProperty('plugin');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $mockBooked);

        $this->service = new TestableReminderService();
    }

    public function testSendReminders()
    {
        // Mock reservations
        $res24h = $this->getMockBuilder(Reservation::class)
            ->disableOriginalConstructor()
            ->getMock();
        $res24h->id = 1;
        $res24h->emailReminder24hSent = false;
        $res24h->bookingDate = date('Y-m-d', strtotime('+24 hours'));
        $res24h->startTime = date('H:i', strtotime('+24 hours'));

        $res1h = $this->getMockBuilder(Reservation::class)
            ->disableOriginalConstructor()
            ->getMock();
        $res1h->id = 2;
        $res1h->emailReminder1hSent = false;
        $res1h->bookingDate = date('Y-m-d', strtotime('+1 hour'));
        $res1h->startTime = date('H:i', strtotime('+1 hour'));

        // Mock the query
        $mockQuery = $this->getMockBuilder(\fabian\booked\elements\db\ReservationQuery::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // This is tricky to mock perfectly without a DB, so we'll mock the internal findReminders method
        $this->service = $this->getMockBuilder(TestableReminderService::class)
            ->onlyMethods(['getPendingReminders'])
            ->getMock();
        
        $this->service->method('getPendingReminders')
            ->willReturn([$res24h, $res1h]);

        $count = $this->service->sendReminders();
        
        $this->assertEquals(2, $count);
    }
}

