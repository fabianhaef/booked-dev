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
}

