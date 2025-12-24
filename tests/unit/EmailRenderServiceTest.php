<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\EmailRenderService;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use fabian\booked\models\Settings;
use UnitTester;
use Craft;

/**
 * Tests for EmailRenderService
 * Ensures centralized email rendering works correctly after refactoring
 */
class EmailRenderServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var EmailRenderService
     */
    protected $service;

    /**
     * @var Reservation
     */
    protected $reservation;

    /**
     * @var Settings
     */
    protected $settings;

    protected function _before()
    {
        parent::_before();

        // Mock Craft::$app
        $this->mockCraftApp();

        // Create service instance
        $this->service = new EmailRenderService();

        // Create test reservation
        $this->reservation = $this->createTestReservation();

        // Create test settings
        $this->settings = $this->createTestSettings();
    }

    /**
     * Test that confirmation email renders without errors
     */
    public function testRenderConfirmationEmail()
    {
        $html = $this->service->renderConfirmationEmail($this->reservation, $this->settings);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Test User', $html, 'Should contain customer name');
        $this->assertStringContainsString('test@example.com', $html, 'Should contain customer email');
    }

    /**
     * Test that status change email renders correctly
     */
    public function testRenderStatusChangeEmail()
    {
        $oldStatus = 'pending';
        $html = $this->service->renderStatusChangeEmail($this->reservation, $oldStatus, $this->settings);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Test User', $html);
    }

    /**
     * Test that cancellation email renders correctly
     */
    public function testRenderCancellationEmail()
    {
        $html = $this->service->renderCancellationEmail($this->reservation, $this->settings);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Test User', $html);
    }

    /**
     * Test that reminder email renders correctly
     */
    public function testRenderReminderEmail()
    {
        $html = $this->service->renderReminderEmail($this->reservation, $this->settings, 24);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Test User', $html);
    }

    /**
     * Test that owner notification email renders correctly
     */
    public function testRenderOwnerNotificationEmail()
    {
        $html = $this->service->renderOwnerNotificationEmail($this->reservation, $this->settings);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Test User', $html);
    }

    /**
     * Test that confirmation email uses custom template body if configured
     */
    public function testConfirmationEmailUsesCustomTemplate()
    {
        $this->settings->bookingConfirmationBody = 'Custom template: {{ reservation.userName }}';

        $html = $this->service->renderConfirmationEmail($this->reservation, $this->settings);

        $this->assertStringContainsString('Custom template:', $html);
        $this->assertStringContainsString('Test User', $html);
    }

    /**
     * Test that all emails handle null service gracefully
     */
    public function testEmailsHandleNullService()
    {
        // Create reservation without service
        $reservation = new Reservation();
        $reservation->id = 999;
        $reservation->userName = 'Test User';
        $reservation->userEmail = 'test@example.com';
        $reservation->bookingDate = '2025-12-26';
        $reservation->startTime = '10:00';
        $reservation->endTime = '11:00';
        $reservation->status = 'confirmed';

        // Should not throw exceptions
        $html = $this->service->renderConfirmationEmail($reservation, $this->settings);
        $this->assertIsString($html);

        $html = $this->service->renderStatusChangeEmail($reservation, 'pending', $this->settings);
        $this->assertIsString($html);

        $html = $this->service->renderCancellationEmail($reservation, $this->settings);
        $this->assertIsString($html);
    }

    /**
     * Test that reminder email handles different hour values
     */
    public function testReminderEmailHandlesDifferentHours()
    {
        // Test 24 hours before
        $html24 = $this->service->renderReminderEmail($this->reservation, $this->settings, 24);
        $this->assertIsString($html24);

        // Test 1 hour before
        $html1 = $this->service->renderReminderEmail($this->reservation, $this->settings, 1);
        $this->assertIsString($html1);

        // Content should be different based on hours
        // (Implementation detail - template should differentiate)
        $this->assertNotEquals($html24, $html1, 'Different hour values should produce different output');
    }

    /**
     * Create test reservation
     */
    private function createTestReservation(): Reservation
    {
        $reservation = new Reservation();
        $reservation->id = 123;
        $reservation->userName = 'Test User';
        $reservation->userEmail = 'test@example.com';
        $reservation->userPhone = '+1234567890';
        $reservation->bookingDate = '2025-12-26';
        $reservation->startTime = '10:00';
        $reservation->endTime = '11:00';
        $reservation->status = 'confirmed';
        $reservation->notes = 'Test notes';
        $reservation->confirmationToken = 'test-token-123';

        return $reservation;
    }

    /**
     * Create test settings
     */
    private function createTestSettings(): Settings
    {
        $settings = new Settings();
        $settings->ownerName = 'Test Owner';
        $settings->ownerEmail = 'owner@example.com';
        $settings->bookingConfirmationSubject = 'Booking Confirmed';
        $settings->ownerNotificationSubject = 'New Booking';

        return $settings;
    }

    /**
     * Mock Craft application
     */
    private function mockCraftApp()
    {
        // Always reset to ensure clean state
        $app = new \stdClass();

        // Mock View service
        $app->view = new class {
            public function renderTemplate(string $template, array $variables = []): string
            {
                // Simple mock: return template name with customer name
                $userName = $variables['reservation']->userName ?? 'Unknown';
                $userEmail = $variables['reservation']->userEmail ?? '';
                return "Template: {$template} - User: {$userName} - Email: {$userEmail}";
            }

            public function renderString(string $template, array $variables = []): string
            {
                // Simple mock: replace {{ reservation.userName }} with actual value
                $userName = $variables['reservation']->userName ?? 'Unknown';
                return str_replace('{{ reservation.userName }}', $userName, $template);
            }
        };

        // Mock Sites service
        $app->sites = new class {
            public function getCurrentSite()
            {
                return new class {
                    public $name = 'Test Site';
                };
            }
        };

        // Mock Project Config
        $app->projectConfig = new class {
            public function get(string $key)
            {
                if ($key === 'email.fromEmail') {
                    return 'noreply@example.com';
                }
                if ($key === 'email.fromName') {
                    return 'Test System';
                }
                return null;
            }
        };

        $app->getTimeZone = function() {
            return 'UTC';
        };

        Craft::$app = $app;
    }
}
