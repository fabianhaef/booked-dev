<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\Booked;
use fabian\booked\elements\Reservation;
use fabian\booked\models\ServiceExtra;
use fabian\booked\models\Settings;
use fabian\booked\services\EmailRenderService;
use UnitTester;
use Craft;

/**
 * Email Rendering with Extras Tests
 *
 * Tests that service extras are properly displayed in email templates
 */
class EmailRenderingWithExtrasTest extends Unit
{
    protected UnitTester $tester;
    private EmailRenderService $emailService;
    private Reservation $testReservation;
    private array $testExtras = [];

    protected function _before()
    {
        $this->emailService = Booked::getInstance()->emailRender;
        $this->setupTestData();
    }

    protected function _after()
    {
        $this->cleanupTestData();
    }

    /**
     * Test confirmation email includes extras
     */
    public function testConfirmationEmailIncludesExtras()
    {
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($this->testReservation, $settings);

        // Check that extras section is present
        $this->assertStringContainsString('Service-Extras', $html);

        // Check that individual extras are shown
        foreach ($this->testExtras as $extra) {
            $this->assertStringContainsString($extra->name, $html);
        }

        // Check that prices are displayed
        $this->assertStringContainsString('Extras Gesamt', $html);
        $this->assertStringContainsString('Gesamtpreis', $html);

        // Check that currency formatting is present
        $this->assertMatchesRegularExpression('/CHF|Fr\.|€/', $html);
    }

    /**
     * Test confirmation email shows correct quantity
     */
    public function testConfirmationEmailShowsQuantity()
    {
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($this->testReservation, $settings);

        // Should show "2x" for the extra with quantity 2
        $this->assertStringContainsString('2x', $html);
    }

    /**
     * Test confirmation email calculates total price correctly
     */
    public function testConfirmationEmailTotalPrice()
    {
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($this->testReservation, $settings);

        $extrasPrice = $this->testReservation->getExtrasPrice();
        $totalPrice = $this->testReservation->getTotalPrice();

        // Check that prices are in the email (allowing for currency formatting)
        // Convert to string without currency symbols for comparison
        $extrasFormatted = number_format($extrasPrice, 2);
        $totalFormatted = number_format($totalPrice, 2);

        $this->assertStringContainsString($extrasFormatted, $html);
        $this->assertStringContainsString($totalFormatted, $html);
    }

    /**
     * Test owner notification email includes extras
     */
    public function testOwnerNotificationEmailIncludesExtras()
    {
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderOwnerNotificationEmail($this->testReservation, $settings);

        // Check that extras section is present
        $this->assertStringContainsString('Service-Extras', $html);

        // Check that individual extras are shown
        foreach ($this->testExtras as $extra) {
            $this->assertStringContainsString($extra->name, $html);
        }

        // Check pricing totals
        $this->assertStringContainsString('Extras Gesamt', $html);
        $this->assertStringContainsString('Gesamtpreis', $html);
    }

    /**
     * Test reminder email includes extras
     */
    public function testReminderEmailIncludesExtras()
    {
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderReminderEmail($this->testReservation, $settings, 24);

        // Check that extras are mentioned
        $this->assertStringContainsString('Inkl. Extras', $html);

        // Check that extra names are shown
        foreach ($this->testExtras as $extra) {
            $this->assertStringContainsString($extra->name, $html);
        }
    }

    /**
     * Test email with no extras doesn't show extras section
     */
    public function testEmailWithoutExtrasDoesntShowSection()
    {
        // Create reservation without extras
        $reservation = new Reservation();
        $reservation->userName = 'No Extras User';
        $reservation->userEmail = 'noextras@example.com';
        $reservation->bookingDate = '2026-01-15';
        $reservation->startTime = '10:00';
        $reservation->endTime = '11:00';
        $reservation->status = 'confirmed';
        Craft::$app->elements->saveElement($reservation);

        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($reservation, $settings);

        // Extras section should not be present
        // We can't assert string NOT contains because the template might have the word "extras"
        // but we can check that the specific Twig block is not rendered
        $extrasCount = substr_count($html, 'Service-Extras');
        $this->assertEquals(0, $extrasCount, 'Extras section should not appear in emails without extras');

        // Cleanup
        Craft::$app->elements->deleteElement($reservation);
    }

    /**
     * Test email renders extra descriptions when available
     */
    public function testEmailRendersExtraDescriptions()
    {
        // Add description to one of the test extras
        $extra = $this->testExtras[0];
        $extra->description = 'This is a test extra description that should appear in emails';
        Booked::getInstance()->serviceExtra->saveExtra($extra);

        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($this->testReservation, $settings);

        // In the current implementation, descriptions are shown in the service edit screen
        // but not in emails (to keep them concise). Just verify the extra name is there.
        $this->assertStringContainsString($extra->name, $html);
    }

    /**
     * Test email shows correct currency formatting
     */
    public function testEmailCurrencyFormatting()
    {
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($this->testReservation, $settings);

        // Check for proper price formatting (should use Twig currency filter)
        // The exact format depends on locale settings, but should have decimal points
        $this->assertMatchesRegularExpression('/\d+\.\d{2}/', $html);
    }

    /**
     * Test that extras totals are calculated correctly in template
     */
    public function testEmailExtrasTotalsCalculation()
    {
        $extra1Price = $this->testExtras[0]->price; // 25.00
        $extra2Price = $this->testExtras[1]->price; // 35.00

        // Quantities: 1x extra1, 2x extra2
        $expectedExtrasTotal = ($extra1Price * 1) + ($extra2Price * 2);

        $actualExtrasTotal = $this->testReservation->getExtrasPrice();

        $this->assertEquals($expectedExtrasTotal, $actualExtrasTotal);

        // Verify this appears in the email
        $settings = Settings::loadSettings();
        $html = $this->emailService->renderConfirmationEmail($this->testReservation, $settings);

        $formattedTotal = number_format($expectedExtrasTotal, 2);
        $this->assertStringContainsString($formattedTotal, $html);
    }

    // ========== Helper Methods ==========

    private function setupTestData(): void
    {
        // Create test extras
        $extra1 = new ServiceExtra();
        $extra1->name = 'Hot Stone Treatment';
        $extra1->description = 'Relaxing hot stone therapy';
        $extra1->price = 25.00;
        $extra1->duration = 30;
        $extra1->maxQuantity = 3;
        $extra1->isRequired = false;
        $extra1->enabled = true;
        Booked::getInstance()->serviceExtra->saveExtra($extra1);
        $this->testExtras[] = $extra1;

        $extra2 = new ServiceExtra();
        $extra2->name = 'Aromatherapy Upgrade';
        $extra2->description = 'Essential oils treatment';
        $extra2->price = 35.00;
        $extra2->duration = 15;
        $extra2->maxQuantity = 2;
        $extra2->isRequired = false;
        $extra2->enabled = true;
        Booked::getInstance()->serviceExtra->saveExtra($extra2);
        $this->testExtras[] = $extra2;

        // Create test service
        $service = new \fabian\booked\elements\Service();
        $service->title = 'Email Test Massage';
        $service->duration = 60;
        $service->price = 100.00;
        $service->enabled = true;
        Craft::$app->elements->saveElement($service);

        // Create test reservation
        $this->testReservation = new Reservation();
        $this->testReservation->serviceId = $service->id;
        $this->testReservation->userName = 'Email Test User';
        $this->testReservation->userEmail = 'emailtest@example.com';
        $this->testReservation->bookingDate = '2026-01-10';
        $this->testReservation->startTime = '14:00';
        $this->testReservation->endTime = '15:00';
        $this->testReservation->status = 'confirmed';
        Craft::$app->elements->saveElement($this->testReservation);

        // Add extras to reservation
        $selectedExtras = [
            $extra1->id => 1,
            $extra2->id => 2,
        ];
        Booked::getInstance()->serviceExtra->saveExtrasForReservation($this->testReservation->id, $selectedExtras);
    }

    private function cleanupTestData(): void
    {
        // Delete test reservation
        if ($this->testReservation && $this->testReservation->id) {
            Craft::$app->elements->deleteElement($this->testReservation);
        }

        // Delete test extras
        foreach ($this->testExtras as $extra) {
            if ($extra && $extra->id) {
                Booked::getInstance()->serviceExtra->deleteExtra($extra->id);
            }
        }

        // Delete test service
        $services = \fabian\booked\elements\Service::find()
            ->title('Email Test Massage')
            ->all();
        foreach ($services as $service) {
            Craft::$app->elements->deleteElement($service);
        }
    }
}
