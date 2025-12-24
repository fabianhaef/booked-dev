<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use craft\commerce\base\PurchasableInterface as Purchasable;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use UnitTester;
use Craft;

class CommerceIntegrationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _before()
    {
        parent::_before();
        
        // Mock Craft::$app
        $app = new class {
            public $sites;
            public $fields;
            public $elements;
            public $view;
            public $projectConfig;
            public $plugins;
            public $db;
            public $cache;
            public $request;
            public function getTimeZone() { return 'UTC'; }
            public function getIsInstalled() { return true; }
            public function getIsUpdating() { return false; }
            public function get($id) { return $this->$id ?? null; }
        };
        $app->fields = \Codeception\Stub::makeEmpty(\craft\services\Fields::class);
        $app->elements = \Codeception\Stub::makeEmpty(\craft\services\Elements::class);
        $app->view = \Codeception\Stub::makeEmpty(\craft\web\View::class);
        $app->projectConfig = \Codeception\Stub::makeEmpty(\craft\services\ProjectConfig::class);
        $app->cache = new class {
            public function get($key) { return null; }
            public function set($key, $val, $ttl = null) { return true; }
            public function delete($key) { return true; }
            public function flush() { return true; }
        };
        $app->request = \Codeception\Stub::makeEmpty(\craft\web\Request::class);
        $app->db = new class {
            public function getIsMysql() { return true; }
            public function getTablePrefix() { return ''; }
            public function getSchema() { return new class { public function getTableSchema($name) { return null; } }; }
        };
        $app->plugins = new class {
            public function isPluginEnabled($handle) { return false; }
            public function getPlugin($handle) { return null; }
            public function getEnabledPluginBehaviors($element) { return []; }
        };
        $app->sites = new class {
            public function getCurrentSite() {
                return new class { public int $id = 1; };
            }
        };
        Craft::$app = $app;
    }

    public function testReservationImplementsPurchasable()
    {
        $reservation = new Reservation();
        $this->assertInstanceOf(Purchasable::class, $reservation);
    }

    public function testReservationPurchasableMethods()
    {
        $reservation = new Reservation();
        $reservation->id = 123;
        $reservation->userName = 'Test User';
        $reservation->bookingDate = '2025-12-25';
        $reservation->startTime = '10:00';

        $this->assertEquals(123, $reservation->id);
        $this->assertEquals('BOOKING-123', $reservation->getSku());
        $this->assertStringContainsString('Booking for', $reservation->getDescription());
        $this->assertTrue($reservation->hasFreeShipping());
        $this->assertFalse($reservation->getIsShippable());
    }

    /**
     * Test that hasInventory() is static and returns false
     * This was causing: "Non-static method cannot be called statically"
     */
    public function testHasInventoryIsStatic()
    {
        // Should be callable statically without error
        $hasInventory = Reservation::hasInventory();

        $this->assertIsBool($hasInventory);
        $this->assertFalse($hasInventory, 'Bookings should not use inventory tracking');
    }

    /**
     * Test that getSales() method exists and returns array
     * This was causing: "Call to undefined method getSales()"
     */
    public function testGetSalesMethodExists()
    {
        $reservation = new Reservation();

        $this->assertTrue(method_exists($reservation, 'getSales'));

        $sales = $reservation->getSales();
        $this->assertIsArray($sales);
    }

    /**
     * Test that populateLineItem doesn't set read-only properties
     * This was causing: "Setting read-only property: saleAmount"
     */
    public function testPopulateLineItemDoesNotSetReadOnlyProperties()
    {
        $reservation = new Reservation();
        $reservation->id = 456;
        $reservation->bookingDate = '2025-12-26';
        $reservation->startTime = '14:00';
        $reservation->endTime = '15:00';

        $lineItem = new class {
            public $price;
            public $sku;
            public $description;
            // saleAmount should NOT be set - it's read-only
        };

        // This should not attempt to set $lineItem->saleAmount
        $reservation->populateLineItem($lineItem);

        $this->assertIsFloat($lineItem->price);
        $this->assertEquals('BOOKING-456', $lineItem->sku);
        $this->assertIsString($lineItem->description);

        // Verify saleAmount was NOT set
        $this->assertObjectNotHasProperty('saleAmount', $lineItem);
    }

    /**
     * Test that getQueueService returns QueueInterface
     * This was causing: "Return value must be of type Service, Queue returned"
     */
    public function testQueueServiceTypeHint()
    {
        // This test verifies the method signature is correct
        // The actual implementation check would require access to BookingService

        $reflection = new \ReflectionClass(\fabian\booked\services\BookingService::class);
        $method = $reflection->getMethod('getQueueService');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('craft\\queue\\QueueInterface', $returnType->getName());
    }

    /**
     * Test all required PurchasableInterface methods exist
     */
    public function testAllRequiredPurchasableMethodsExist()
    {
        $reservation = new Reservation();

        $requiredMethods = [
            'getPrice',
            'getSku',
            'getDescription',
            'getTaxCategory',
            'getShippingCategory',
            'getIsAvailable',
            'populateLineItem',
            'getSnapshot',
            'afterOrderComplete',
            'hasFreeShipping',
            'getIsPromotable',
            'getPromotionRelationSource',
            'getSales',
            'getStore',
            'getStoreId',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($reservation, $method),
                "Method {$method} should exist on Reservation"
            );
        }

        // hasInventory should be static
        $this->assertTrue(
            method_exists(Reservation::class, 'hasInventory'),
            'Static method hasInventory should exist'
        );
    }
}

