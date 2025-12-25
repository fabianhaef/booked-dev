<?php

namespace fabian\booked\tests\integration;

use Codeception\Test\Unit;
use fabian\booked\Booked;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\models\ServiceExtra;
use fabian\booked\services\BookingService;
use UnitTester;
use Craft;

/**
 * Booking with Service Extras Integration Tests
 *
 * Tests the complete booking flow with service extras
 */
class BookingWithExtrasTest extends Unit
{
    protected UnitTester $tester;
    private BookingService $bookingService;
    private Service $testService;
    private array $testExtras = [];

    protected function _before()
    {
        $this->bookingService = Booked::getInstance()->booking;
        $this->setupTestData();
    }

    protected function _after()
    {
        $this->cleanupTestData();
    }

    /**
     * Test creating a booking with extras
     */
    public function testCreateBookingWithExtras()
    {
        $extra1 = $this->testExtras[0];
        $extra2 = $this->testExtras[1];

        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'John Doe',
            'userEmail' => 'john@example.com',
            'bookingDate' => '2025-12-26',
            'startTime' => '10:00',
            'extras' => [
                $extra1->id => 1,
                $extra2->id => 2,
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertNotNull($reservation->id);

        // Verify extras were saved
        $savedExtras = $reservation->getExtras();
        $this->assertCount(2, $savedExtras);

        // Verify total price includes extras
        $expectedExtrasPrice = ($extra1->price * 1) + ($extra2->price * 2);
        $this->assertEquals($expectedExtrasPrice, $reservation->getExtrasPrice());

        // Verify total duration includes extras
        $expectedDuration = $this->testService->duration + ($extra1->duration * 1) + ($extra2->duration * 2);
        $this->assertEquals($expectedDuration, $reservation->getTotalDuration());
    }

    /**
     * Test creating booking with required extras missing
     */
    public function testCreateBookingWithMissingRequiredExtras()
    {
        // Create a required extra
        $requiredExtra = $this->createExtra('Required Deposit', 50.00, 0, true);
        Booked::getInstance()->serviceExtra->assignExtraToService($requiredExtra->id, $this->testService->id);

        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Jane Doe',
            'userEmail' => 'jane@example.com',
            'bookingDate' => '2025-12-27',
            'startTime' => '14:00',
            'extras' => [], // Missing required extra
        ];

        $this->expectException(\fabian\booked\exceptions\BookingValidationException::class);
        $this->expectExceptionMessageMatches('/Required Deposit/i');

        $this->bookingService->createReservation($bookingData);
    }

    /**
     * Test creating booking with quantity validation
     */
    public function testBookingWithExtraQuantityValidation()
    {
        $extra = $this->testExtras[0];
        $extra->maxQuantity = 2;
        Booked::getInstance()->serviceExtra->saveExtra($extra);

        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Test User',
            'userEmail' => 'test@example.com',
            'bookingDate' => '2025-12-28',
            'startTime' => '09:00',
            'extras' => [
                $extra->id => 5, // Exceeds max quantity
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);

        // Should succeed but cap quantity at max
        $savedExtras = $reservation->getExtras();
        $this->assertCount(1, $savedExtras);
        $this->assertLessThanOrEqual(2, $savedExtras[0]['quantity']);
    }

    /**
     * Test end time calculation with extras duration
     */
    public function testEndTimeCalculationWithExtrasDuration()
    {
        $extra = $this->testExtras[0]; // Has 30 min duration
        $extra->duration = 30;
        Booked::getInstance()->serviceExtra->saveExtra($extra);

        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Time Test',
            'userEmail' => 'time@example.com',
            'bookingDate' => '2025-12-29',
            'startTime' => '10:00',
            'extras' => [
                $extra->id => 2, // 2x 30min = 60min extra
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);

        // Base service: 60min, Extra: 60min, Total: 120min (2 hours)
        // Start: 10:00, End should be: 12:00
        $this->assertEquals('12:00', $reservation->endTime);
    }

    /**
     * Test updating reservation with different extras
     */
    public function testUpdateReservationExtras()
    {
        // Create initial reservation with one extra
        $extra1 = $this->testExtras[0];
        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Update Test',
            'userEmail' => 'update@example.com',
            'bookingDate' => '2025-12-30',
            'startTime' => '11:00',
            'extras' => [
                $extra1->id => 1,
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);
        $originalId = $reservation->id;

        // Update with different extras
        $extra2 = $this->testExtras[1];
        $newExtras = [
            $extra2->id => 2,
        ];

        Booked::getInstance()->serviceExtra->saveExtrasForReservation($reservation->id, $newExtras);

        // Reload and verify
        $updated = Reservation::find()->id($originalId)->one();
        $savedExtras = $updated->getExtras();

        $this->assertCount(1, $savedExtras);
        $this->assertEquals($extra2->id, $savedExtras[0]['extra']->id);
        $this->assertEquals(2, $savedExtras[0]['quantity']);
    }

    /**
     * Test reservation total price calculation
     */
    public function testReservationTotalPriceWithExtras()
    {
        $extra1 = $this->testExtras[0]; // $25
        $extra2 = $this->testExtras[1]; // $35

        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Price Test',
            'userEmail' => 'price@example.com',
            'bookingDate' => '2025-12-31',
            'startTime' => '15:00',
            'extras' => [
                $extra1->id => 1, // 1x $25 = $25
                $extra2->id => 2, // 2x $35 = $70
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);

        $servicePrice = $this->testService->price; // $100
        $extrasPrice = 25 + 70; // $95
        $expectedTotal = $servicePrice + $extrasPrice; // $195

        $this->assertEquals($extrasPrice, $reservation->getExtrasPrice());
        $this->assertEquals($expectedTotal, $reservation->getTotalPrice());
    }

    /**
     * Test that extras price is stored at time of booking
     */
    public function testExtrasPriceStoredAtBookingTime()
    {
        $extra = $this->testExtras[0];
        $originalPrice = $extra->price;

        // Create booking
        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Price Lock Test',
            'userEmail' => 'pricelock@example.com',
            'bookingDate' => '2026-01-01',
            'startTime' => '10:00',
            'extras' => [
                $extra->id => 1,
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);

        // Change extra price
        $extra->price = $originalPrice + 50.00;
        Booked::getInstance()->serviceExtra->saveExtra($extra);

        // Reload reservation and check that price didn't change
        $reloaded = Reservation::find()->id($reservation->id)->one();
        $savedExtras = $reloaded->getExtras();

        $this->assertEquals($originalPrice, $savedExtras[0]['price']);
        $this->assertNotEquals($extra->price, $savedExtras[0]['price']);
    }

    /**
     * Test hasExtras() method
     */
    public function testHasExtrasMethod()
    {
        // Create booking without extras
        $bookingData1 = [
            'serviceId' => $this->testService->id,
            'userName' => 'No Extras',
            'userEmail' => 'noextras@example.com',
            'bookingDate' => '2026-01-02',
            'startTime' => '09:00',
        ];

        $reservation1 = $this->bookingService->createReservation($bookingData1);
        $this->assertFalse($reservation1->hasExtras());

        // Create booking with extras
        $bookingData2 = [
            'serviceId' => $this->testService->id,
            'userName' => 'With Extras',
            'userEmail' => 'withextras@example.com',
            'bookingDate' => '2026-01-03',
            'startTime' => '10:00',
            'extras' => [
                $this->testExtras[0]->id => 1,
            ],
        ];

        $reservation2 = $this->bookingService->createReservation($bookingData2);
        $this->assertTrue($reservation2->hasExtras());
    }

    /**
     * Test extras summary formatting
     */
    public function testExtrasSummaryFormatting()
    {
        $extra1 = $this->testExtras[0];
        $extra2 = $this->testExtras[1];

        $bookingData = [
            'serviceId' => $this->testService->id,
            'userName' => 'Summary Test',
            'userEmail' => 'summary@example.com',
            'bookingDate' => '2026-01-04',
            'startTime' => '11:00',
            'extras' => [
                $extra1->id => 1,
                $extra2->id => 3,
            ],
        ];

        $reservation = $this->bookingService->createReservation($bookingData);
        $summary = $reservation->getExtrasSummary();

        $this->assertNotEmpty($summary);
        $this->assertStringContainsString($extra1->name, $summary);
        $this->assertStringContainsString($extra2->name, $summary);
        $this->assertStringContainsString('3x', $summary); // Quantity indicator
    }

    // ========== Helper Methods ==========

    private function setupTestData(): void
    {
        // Create test service
        $this->testService = new Service();
        $this->testService->title = 'Integration Test Service';
        $this->testService->duration = 60;
        $this->testService->price = 100.00;
        $this->testService->enabled = true;
        Craft::$app->elements->saveElement($this->testService);

        // Create test extras
        $this->testExtras[] = $this->createExtra('Hot Stone Treatment', 25.00, 30);
        $this->testExtras[] = $this->createExtra('Aromatherapy', 35.00, 15);
        $this->testExtras[] = $this->createExtra('Extended Time', 40.00, 60);

        // Assign extras to service
        foreach ($this->testExtras as $extra) {
            Booked::getInstance()->serviceExtra->assignExtraToService($extra->id, $this->testService->id);
        }
    }

    private function createExtra(string $name, float $price, int $duration, bool $required = false): ServiceExtra
    {
        $extra = new ServiceExtra();
        $extra->name = $name;
        $extra->description = "Test extra: $name";
        $extra->price = $price;
        $extra->duration = $duration;
        $extra->maxQuantity = 5;
        $extra->isRequired = $required;
        $extra->sortOrder = 0;
        $extra->enabled = true;

        Booked::getInstance()->serviceExtra->saveExtra($extra);

        return $extra;
    }

    private function cleanupTestData(): void
    {
        // Delete test service
        if ($this->testService && $this->testService->id) {
            Craft::$app->elements->deleteElement($this->testService);
        }

        // Delete test extras
        foreach ($this->testExtras as $extra) {
            if ($extra && $extra->id) {
                Booked::getInstance()->serviceExtra->deleteExtra($extra->id);
            }
        }

        // Delete test reservations
        $reservations = Reservation::find()
            ->userEmail(['john@example.com', 'jane@example.com', 'test@example.com',
                        'time@example.com', 'update@example.com', 'price@example.com',
                        'pricelock@example.com', 'noextras@example.com', 'withextras@example.com',
                        'summary@example.com'])
            ->all();

        foreach ($reservations as $reservation) {
            Craft::$app->elements->deleteElement($reservation);
        }
    }
}
