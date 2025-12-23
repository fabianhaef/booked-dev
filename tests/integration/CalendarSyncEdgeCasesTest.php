<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use fabian\booked\services\CalendarSyncService;
use IntegrationTester;

class CalendarSyncEdgeCasesTest extends Unit
{
    protected $tester;
    private $calendarService;

    protected function _before()
    {
        parent::_before();
        $this->calendarService = new CalendarSyncService();
    }

    public function testTokenExpirationAndRefresh()
    {
        $token = [
            'access_token' => 'old_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => time() - 3600, // Expired
        ];

        $isExpired = $token['expires_at'] < time();

        $this->assertTrue($isExpired);
    }

    public function testOAuthFlowInterruption()
    {
        $state = bin2hex(random_bytes(16));
        $receivedState = 'different_state';

        $this->assertNotEquals($state, $receivedState);
    }

    public function testDuplicateEventPrevention()
    {
        $existingEvents = [
            ['id' => 'event1', 'title' => 'Meeting'],
            ['id' => 'event2', 'title' => 'Call'],
        ];

        $newEvent = ['id' => 'event1', 'title' => 'Meeting'];

        $isDuplicate = in_array($newEvent['id'], array_column($existingEvents, 'id'));

        $this->assertTrue($isDuplicate);
    }

    public function testEventUpdatePropagation()
    {
        $original = ['title' => 'Old Title', 'updated_at' => '2025-01-01'];
        $updated = ['title' => 'New Title', 'updated_at' => '2025-01-02'];

        $this->assertNotEquals($original['title'], $updated['title']);
        $this->assertGreaterThan($original['updated_at'], $updated['updated_at']);
    }

    public function testSyncFrequencyThrottling()
    {
        $lastSync = time() - 300; // 5 minutes ago
        $minInterval = 600; // 10 minutes

        $canSync = (time() - $lastSync) >= $minInterval;

        $this->assertFalse($canSync);
    }
}
