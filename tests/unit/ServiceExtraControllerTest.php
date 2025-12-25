<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\Booked;
use fabian\booked\controllers\ServiceExtraController;
use fabian\booked\models\ServiceExtra;
use UnitTester;
use Craft;
use yii\web\NotFoundHttpException;

/**
 * ServiceExtraController Tests
 *
 * Tests Control Panel operations for managing service extras
 */
class ServiceExtraControllerTest extends Unit
{
    protected UnitTester $tester;
    private ServiceExtraController $controller;
    private array $testExtras = [];

    protected function _before()
    {
        $this->controller = new ServiceExtraController('service-extra', Booked::getInstance());
    }

    protected function _after()
    {
        $this->cleanupTestExtras();
    }

    /**
     * Test creating a new extra via controller
     */
    public function testActionSaveCreatesNewExtra()
    {
        // Mock request data
        Craft::$app->request->setBodyParams([
            'name' => 'Controller Test Extra',
            'description' => 'Created via controller test',
            'price' => 45.00,
            'duration' => 20,
            'maxQuantity' => 3,
            'isRequired' => true,
            'sortOrder' => 5,
            'enabled' => true,
            'services' => [],
        ]);

        // This would normally be called via HTTP request
        // We can't directly test the action without full HTTP context,
        // but we can test the underlying service logic
        $extra = new ServiceExtra();
        $extra->name = 'Controller Test Extra';
        $extra->description = 'Created via controller test';
        $extra->price = 45.00;
        $extra->duration = 20;
        $extra->maxQuantity = 3;
        $extra->isRequired = true;
        $extra->sortOrder = 5;
        $extra->enabled = true;

        $result = Booked::getInstance()->serviceExtra->saveExtra($extra);

        $this->assertTrue($result);
        $this->assertNotNull($extra->id);
        $this->testExtras[] = $extra;
    }

    /**
     * Test updating an existing extra
     */
    public function testActionSaveUpdatesExistingExtra()
    {
        // Create initial extra
        $extra = $this->createTestExtra('Original Name', 10.00);
        $originalId = $extra->id;

        // Update via service (simulating controller action)
        $extra->name = 'Updated Name';
        $extra->price = 20.00;
        $result = Booked::getInstance()->serviceExtra->saveExtra($extra);

        $this->assertTrue($result);
        $this->assertEquals($originalId, $extra->id);

        // Verify update
        $updated = Booked::getInstance()->serviceExtra->getExtraById($extra->id);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals(20.00, $updated->price);
    }

    /**
     * Test deleting an extra
     */
    public function testActionDeleteRemovesExtra()
    {
        $extra = $this->createTestExtra('To Delete', 15.00);
        $id = $extra->id;

        $result = Booked::getInstance()->serviceExtra->deleteExtra($id);

        $this->assertTrue($result);

        $retrieved = Booked::getInstance()->serviceExtra->getExtraById($id);
        $this->assertNull($retrieved);
    }

    /**
     * Test validation fails with missing required fields
     */
    public function testValidationFailsWithMissingName()
    {
        $extra = new ServiceExtra();
        $extra->name = ''; // Missing required name
        $extra->price = 10.00;

        $isValid = $extra->validate();

        $this->assertFalse($isValid);
        $this->assertArrayHasKey('name', $extra->getErrors());
    }

    /**
     * Test validation fails with negative price
     */
    public function testValidationFailsWithNegativePrice()
    {
        $extra = new ServiceExtra();
        $extra->name = 'Test Extra';
        $extra->price = -10.00; // Invalid negative price

        $isValid = $extra->validate();

        $this->assertFalse($isValid);
        $this->assertArrayHasKey('price', $extra->getErrors());
    }

    /**
     * Test validation fails with invalid maxQuantity
     */
    public function testValidationFailsWithInvalidMaxQuantity()
    {
        $extra = new ServiceExtra();
        $extra->name = 'Test Extra';
        $extra->price = 10.00;
        $extra->maxQuantity = 0; // Must be at least 1

        $isValid = $extra->validate();

        $this->assertFalse($isValid);
        $this->assertArrayHasKey('maxQuantity', $extra->getErrors());
    }

    /**
     * Test service assignment via controller
     */
    public function testServiceAssignment()
    {
        $extra = $this->createTestExtra('Test Extra', 10.00);
        $service = $this->createTestService();

        // Simulate controller assigning extra to service
        $result = Booked::getInstance()->serviceExtra->assignExtraToService($extra->id, $service->id);

        $this->assertTrue($result);

        // Verify assignment
        $serviceExtras = Booked::getInstance()->serviceExtra->getExtrasForService($service->id);
        $this->assertCount(1, $serviceExtras);
        $this->assertEquals($extra->id, $serviceExtras[0]->id);
    }

    /**
     * Test bulk service assignment replacement
     */
    public function testSetExtrasForServiceReplacesAll()
    {
        $service = $this->createTestService();
        $extra1 = $this->createTestExtra('Extra 1', 10.00);
        $extra2 = $this->createTestExtra('Extra 2', 20.00);
        $extra3 = $this->createTestExtra('Extra 3', 30.00);

        // Initial assignment
        Booked::getInstance()->serviceExtra->setExtrasForService($service->id, [
            $extra1->id,
            $extra2->id,
        ]);

        $extras = Booked::getInstance()->serviceExtra->getExtrasForService($service->id);
        $this->assertCount(2, $extras);

        // Replace with different set
        Booked::getInstance()->serviceExtra->setExtrasForService($service->id, [
            $extra3->id,
        ]);

        $extras = Booked::getInstance()->serviceExtra->getExtrasForService($service->id);
        $this->assertCount(1, $extras);
        $this->assertEquals($extra3->id, $extras[0]->id);
    }

    /**
     * Test AJAX endpoint for getting service extras
     */
    public function testGetForServiceReturnsCorrectExtras()
    {
        $service = $this->createTestService();
        $extra1 = $this->createTestExtra('AJAX Extra 1', 15.00);
        $extra2 = $this->createTestExtra('AJAX Extra 2', 25.00);

        Booked::getInstance()->serviceExtra->assignExtraToService($extra1->id, $service->id);
        Booked::getInstance()->serviceExtra->assignExtraToService($extra2->id, $service->id);

        $extras = Booked::getInstance()->serviceExtra->getExtrasForService($service->id);

        $this->assertCount(2, $extras);

        // Verify data structure (as would be returned by controller)
        foreach ($extras as $extra) {
            $this->assertObjectHasProperty('id', $extra);
            $this->assertObjectHasProperty('name', $extra);
            $this->assertObjectHasProperty('price', $extra);
            $this->assertObjectHasProperty('duration', $extra);
            $this->assertObjectHasProperty('maxQuantity', $extra);
            $this->assertObjectHasProperty('isRequired', $extra);
        }
    }

    /**
     * Test reordering extras
     */
    public function testReorderingExtras()
    {
        $extra1 = $this->createTestExtra('Extra A', 10.00);
        $extra1->sortOrder = 1;
        Booked::getInstance()->serviceExtra->saveExtra($extra1);

        $extra2 = $this->createTestExtra('Extra B', 20.00);
        $extra2->sortOrder = 2;
        Booked::getInstance()->serviceExtra->saveExtra($extra2);

        $extra3 = $this->createTestExtra('Extra C', 30.00);
        $extra3->sortOrder = 3;
        Booked::getInstance()->serviceExtra->saveExtra($extra3);

        // Change order
        $extra1->sortOrder = 3;
        $extra3->sortOrder = 1;

        Booked::getInstance()->serviceExtra->saveExtra($extra1);
        Booked::getInstance()->serviceExtra->saveExtra($extra3);

        // Verify new order
        $allExtras = Booked::getInstance()->serviceExtra->getAllExtras();

        // Find our test extras
        $testExtraIds = [$extra1->id, $extra2->id, $extra3->id];
        $orderedTestExtras = array_filter($allExtras, function($e) use ($testExtraIds) {
            return in_array($e->id, $testExtraIds);
        });

        usort($orderedTestExtras, function($a, $b) {
            return $a->sortOrder <=> $b->sortOrder;
        });

        // Extra C should be first, B second, A third
        $this->assertEquals($extra3->id, $orderedTestExtras[0]->id);
        $this->assertEquals($extra2->id, $orderedTestExtras[1]->id);
        $this->assertEquals($extra1->id, $orderedTestExtras[2]->id);
    }

    /**
     * Test enabled/disabled filtering
     */
    public function testEnabledDisabledFiltering()
    {
        $enabledExtra = $this->createTestExtra('Enabled Extra', 10.00);
        $enabledExtra->enabled = true;
        Booked::getInstance()->serviceExtra->saveExtra($enabledExtra);

        $disabledExtra = $this->createTestExtra('Disabled Extra', 20.00);
        $disabledExtra->enabled = false;
        Booked::getInstance()->serviceExtra->saveExtra($disabledExtra);

        // Get only enabled
        $enabledExtras = Booked::getInstance()->serviceExtra->getAllExtras(true);
        $enabledIds = array_map(fn($e) => $e->id, $enabledExtras);
        $this->assertContains($enabledExtra->id, $enabledIds);
        $this->assertNotContains($disabledExtra->id, $enabledIds);

        // Get all including disabled
        $allExtras = Booked::getInstance()->serviceExtra->getAllExtras(false);
        $allIds = array_map(fn($e) => $e->id, $allExtras);
        $this->assertContains($enabledExtra->id, $allIds);
        $this->assertContains($disabledExtra->id, $allIds);
    }

    // ========== Helper Methods ==========

    private function createTestExtra(string $name, float $price): ServiceExtra
    {
        $extra = new ServiceExtra();
        $extra->name = $name;
        $extra->description = "Test: $name";
        $extra->price = $price;
        $extra->duration = 0;
        $extra->maxQuantity = 1;
        $extra->isRequired = false;
        $extra->sortOrder = 0;
        $extra->enabled = true;

        Booked::getInstance()->serviceExtra->saveExtra($extra);
        $this->testExtras[] = $extra;

        return $extra;
    }

    private function createTestService(): \fabian\booked\elements\Service
    {
        $service = new \fabian\booked\elements\Service();
        $service->title = 'Controller Test Service ' . uniqid();
        $service->duration = 60;
        $service->price = 100.00;
        $service->enabled = true;

        Craft::$app->elements->saveElement($service);

        return $service;
    }

    private function cleanupTestExtras(): void
    {
        foreach ($this->testExtras as $extra) {
            if ($extra && $extra->id) {
                Booked::getInstance()->serviceExtra->deleteExtra($extra->id);
            }
        }
    }
}
