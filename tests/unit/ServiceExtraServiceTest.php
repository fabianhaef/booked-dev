<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\Booked;
use fabian\booked\models\ServiceExtra;
use fabian\booked\services\ServiceExtraService;
use UnitTester;
use Craft;

/**
 * ServiceExtraService Unit Tests
 *
 * Tests CRUD operations and business logic for service extras
 */
class ServiceExtraServiceTest extends Unit
{
    protected UnitTester $tester;
    private ServiceExtraService $service;

    protected function _before()
    {
        $this->service = Booked::getInstance()->serviceExtra;
    }

    protected function _after()
    {
        // Clean up test data
        $this->cleanupTestExtras();
    }

    /**
     * Test creating a service extra
     */
    public function testCreateServiceExtra()
    {
        $extra = new ServiceExtra();
        $extra->name = 'Test Extra';
        $extra->description = 'Test Description';
        $extra->price = 25.50;
        $extra->duration = 30;
        $extra->maxQuantity = 2;
        $extra->isRequired = false;
        $extra->sortOrder = 10;
        $extra->enabled = true;

        $result = $this->service->saveExtra($extra);

        $this->assertTrue($result, 'Service extra should be saved successfully');
        $this->assertNotNull($extra->id, 'Service extra should have an ID after saving');
        $this->assertEquals('Test Extra', $extra->name);
        $this->assertEquals(25.50, $extra->price);
        $this->assertEquals(30, $extra->duration);
    }

    /**
     * Test updating a service extra
     */
    public function testUpdateServiceExtra()
    {
        // Create initial extra
        $extra = $this->createTestExtra('Original Name', 10.00);
        $originalId = $extra->id;

        // Update it
        $extra->name = 'Updated Name';
        $extra->price = 20.00;
        $result = $this->service->saveExtra($extra);

        $this->assertTrue($result, 'Service extra update should succeed');
        $this->assertEquals($originalId, $extra->id, 'ID should remain the same');

        // Verify changes persisted
        $retrieved = $this->service->getExtraById($extra->id);
        $this->assertEquals('Updated Name', $retrieved->name);
        $this->assertEquals(20.00, $retrieved->price);
    }

    /**
     * Test deleting a service extra
     */
    public function testDeleteServiceExtra()
    {
        $extra = $this->createTestExtra('To Delete', 15.00);
        $id = $extra->id;

        $result = $this->service->deleteExtra($id);

        $this->assertTrue($result, 'Delete should succeed');

        $retrieved = $this->service->getExtraById($id);
        $this->assertNull($retrieved, 'Deleted extra should not be retrievable');
    }

    /**
     * Test retrieving all extras
     */
    public function testGetAllExtras()
    {
        // Create test extras
        $this->createTestExtra('Extra 1', 10.00, true);
        $this->createTestExtra('Extra 2', 20.00, true);
        $this->createTestExtra('Extra 3 Disabled', 30.00, false);

        // Get all enabled extras
        $enabledExtras = $this->service->getAllExtras(true);
        $this->assertGreaterThanOrEqual(2, count($enabledExtras), 'Should have at least 2 enabled extras');

        // Get all extras including disabled
        $allExtras = $this->service->getAllExtras(false);
        $this->assertGreaterThanOrEqual(3, count($allExtras), 'Should have at least 3 total extras');
    }

    /**
     * Test calculating extras price
     */
    public function testCalculateExtrasPrice()
    {
        $extra1 = $this->createTestExtra('Extra 1', 10.00);
        $extra2 = $this->createTestExtra('Extra 2', 25.50);

        $selectedExtras = [
            $extra1->id => 2, // 2x $10 = $20
            $extra2->id => 1, // 1x $25.50 = $25.50
        ];

        $totalPrice = $this->service->calculateExtrasPrice($selectedExtras);

        $this->assertEquals(45.50, $totalPrice, 'Total price should be $45.50');
    }

    /**
     * Test calculating extras duration
     */
    public function testCalculateExtrasDuration()
    {
        $extra1 = $this->createTestExtra('Extra 1', 10.00);
        $extra1->duration = 15;
        $this->service->saveExtra($extra1);

        $extra2 = $this->createTestExtra('Extra 2', 20.00);
        $extra2->duration = 30;
        $this->service->saveExtra($extra2);

        $selectedExtras = [
            $extra1->id => 2, // 2x 15min = 30min
            $extra2->id => 1, // 1x 30min = 30min
        ];

        $totalDuration = $this->service->calculateExtrasDuration($selectedExtras);

        $this->assertEquals(60, $totalDuration, 'Total duration should be 60 minutes');
    }

    /**
     * Test validating required extras
     */
    public function testValidateRequiredExtras()
    {
        $service = $this->createTestService();

        // Create required and optional extras
        $requiredExtra = $this->createTestExtra('Required Extra', 10.00);
        $requiredExtra->isRequired = true;
        $this->service->saveExtra($requiredExtra);

        $optionalExtra = $this->createTestExtra('Optional Extra', 15.00);
        $optionalExtra->isRequired = false;
        $this->service->saveExtra($optionalExtra);

        // Assign both to service
        $this->service->assignExtraToService($requiredExtra->id, $service->id);
        $this->service->assignExtraToService($optionalExtra->id, $service->id);

        // Test with missing required extra
        $selectedExtras = [$optionalExtra->id => 1];
        $missingRequired = $this->service->validateRequiredExtras($service->id, $selectedExtras);

        $this->assertCount(1, $missingRequired, 'Should detect 1 missing required extra');
        $this->assertContains($requiredExtra->name, $missingRequired);

        // Test with all required extras
        $selectedExtras = [
            $requiredExtra->id => 1,
            $optionalExtra->id => 1,
        ];
        $missingRequired = $this->service->validateRequiredExtras($service->id, $selectedExtras);

        $this->assertEmpty($missingRequired, 'Should not have missing required extras');
    }

    /**
     * Test assigning extras to service
     */
    public function testAssignExtraToService()
    {
        $service = $this->createTestService();
        $extra = $this->createTestExtra('Test Extra', 10.00);

        $result = $this->service->assignExtraToService($extra->id, $service->id);

        $this->assertTrue($result, 'Assignment should succeed');

        // Verify assignment
        $serviceExtras = $this->service->getExtrasForService($service->id);
        $this->assertCount(1, $serviceExtras);
        $this->assertEquals($extra->id, $serviceExtras[0]->id);
    }

    /**
     * Test removing extra from service
     */
    public function testRemoveExtraFromService()
    {
        $service = $this->createTestService();
        $extra = $this->createTestExtra('Test Extra', 10.00);

        // Assign first
        $this->service->assignExtraToService($extra->id, $service->id);

        // Then remove
        $result = $this->service->removeExtraFromService($extra->id, $service->id);

        $this->assertTrue($result, 'Removal should succeed');

        // Verify removal
        $serviceExtras = $this->service->getExtrasForService($service->id);
        $this->assertEmpty($serviceExtras, 'Service should have no extras');
    }

    /**
     * Test setting extras for service (replaces all)
     */
    public function testSetExtrasForService()
    {
        $service = $this->createTestService();
        $extra1 = $this->createTestExtra('Extra 1', 10.00);
        $extra2 = $this->createTestExtra('Extra 2', 20.00);
        $extra3 = $this->createTestExtra('Extra 3', 30.00);

        // Set initial extras
        $result = $this->service->setExtrasForService($service->id, [
            $extra1->id,
            $extra2->id,
        ]);

        $this->assertTrue($result, 'Setting extras should succeed');

        $serviceExtras = $this->service->getExtrasForService($service->id);
        $this->assertCount(2, $serviceExtras);

        // Replace with different set
        $result = $this->service->setExtrasForService($service->id, [
            $extra3->id,
        ]);

        $this->assertTrue($result, 'Replacing extras should succeed');

        $serviceExtras = $this->service->getExtrasForService($service->id);
        $this->assertCount(1, $serviceExtras);
        $this->assertEquals($extra3->id, $serviceExtras[0]->id);
    }

    /**
     * Test extras for reservation
     */
    public function testSaveExtrasForReservation()
    {
        $reservation = $this->createTestReservation();
        $extra1 = $this->createTestExtra('Extra 1', 10.00);
        $extra2 = $this->createTestExtra('Extra 2', 20.00);

        $selectedExtras = [
            $extra1->id => 2,
            $extra2->id => 1,
        ];

        $result = $this->service->saveExtrasForReservation($reservation->id, $selectedExtras);

        $this->assertTrue($result, 'Saving reservation extras should succeed');

        // Verify saved
        $reservationExtras = $this->service->getExtrasForReservation($reservation->id);
        $this->assertCount(2, $reservationExtras);
    }

    /**
     * Test getting total extras price for reservation
     */
    public function testGetTotalExtrasPriceForReservation()
    {
        $reservation = $this->createTestReservation();
        $extra1 = $this->createTestExtra('Extra 1', 10.00);
        $extra2 = $this->createTestExtra('Extra 2', 25.50);

        $selectedExtras = [
            $extra1->id => 2, // 2x $10 = $20
            $extra2->id => 1, // 1x $25.50 = $25.50
        ];

        $this->service->saveExtrasForReservation($reservation->id, $selectedExtras);

        $totalPrice = $this->service->getTotalExtrasPrice($reservation->id);

        $this->assertEquals(45.50, $totalPrice);
    }

    /**
     * Test extras summary generation
     */
    public function testGetExtrasSummary()
    {
        $reservation = $this->createTestReservation();
        $extra1 = $this->createTestExtra('Hot Stone Treatment', 25.00);
        $extra2 = $this->createTestExtra('Extended Time', 30.00);

        $selectedExtras = [
            $extra1->id => 1,
            $extra2->id => 2,
        ];

        $this->service->saveExtrasForReservation($reservation->id, $selectedExtras);

        $summary = $this->service->getExtrasSummary($reservation->id);

        $this->assertStringContainsString('Hot Stone Treatment', $summary);
        $this->assertStringContainsString('Extended Time', $summary);
        $this->assertStringContainsString('2x', $summary);
    }

    /**
     * Test quantity validation
     */
    public function testQuantityValidation()
    {
        $extra = $this->createTestExtra('Limited Extra', 10.00);
        $extra->maxQuantity = 3;
        $this->service->saveExtra($extra);

        // Test valid quantities
        $this->assertTrue($extra->isValidQuantity(1));
        $this->assertTrue($extra->isValidQuantity(3));

        // Test invalid quantities
        $this->assertFalse($extra->isValidQuantity(0));
        $this->assertFalse($extra->isValidQuantity(4));
        $this->assertFalse($extra->isValidQuantity(-1));
    }

    /**
     * Test total price calculation with quantity
     */
    public function testGetTotalPriceWithQuantity()
    {
        $extra = new ServiceExtra();
        $extra->price = 15.00;
        $extra->maxQuantity = 5;

        $this->assertEquals(15.00, $extra->getTotalPrice(1));
        $this->assertEquals(30.00, $extra->getTotalPrice(2));
        $this->assertEquals(75.00, $extra->getTotalPrice(5));

        // Should cap at maxQuantity
        $this->assertEquals(75.00, $extra->getTotalPrice(10));
    }

    /**
     * Test total duration calculation with quantity
     */
    public function testGetTotalDurationWithQuantity()
    {
        $extra = new ServiceExtra();
        $extra->duration = 15;
        $extra->maxQuantity = 3;

        $this->assertEquals(15, $extra->getTotalDuration(1));
        $this->assertEquals(30, $extra->getTotalDuration(2));
        $this->assertEquals(45, $extra->getTotalDuration(3));

        // Should cap at maxQuantity
        $this->assertEquals(45, $extra->getTotalDuration(5));
    }

    // ========== Helper Methods ==========

    private function createTestExtra(string $name, float $price, bool $enabled = true): ServiceExtra
    {
        $extra = new ServiceExtra();
        $extra->name = $name;
        $extra->description = "Test description for $name";
        $extra->price = $price;
        $extra->duration = 0;
        $extra->maxQuantity = 1;
        $extra->isRequired = false;
        $extra->sortOrder = 0;
        $extra->enabled = $enabled;

        $this->service->saveExtra($extra);

        return $extra;
    }

    private function createTestService(): \fabian\booked\elements\Service
    {
        $service = new \fabian\booked\elements\Service();
        $service->title = 'Test Service ' . uniqid();
        $service->duration = 60;
        $service->price = 100.00;
        $service->enabled = true;

        Craft::$app->elements->saveElement($service);

        return $service;
    }

    private function createTestReservation(): \fabian\booked\elements\Reservation
    {
        $reservation = new \fabian\booked\elements\Reservation();
        $reservation->userName = 'Test User';
        $reservation->userEmail = 'test@example.com';
        $reservation->bookingDate = '2025-12-26';
        $reservation->startTime = '10:00';
        $reservation->endTime = '11:00';
        $reservation->status = 'confirmed';

        Craft::$app->elements->saveElement($reservation);

        return $reservation;
    }

    private function cleanupTestExtras(): void
    {
        // Delete all test extras created during tests
        $allExtras = $this->service->getAllExtras(false);
        foreach ($allExtras as $extra) {
            if (strpos($extra->name, 'Test') === 0 || strpos($extra->description, 'Test') === 0) {
                $this->service->deleteExtra($extra->id);
            }
        }
    }
}
