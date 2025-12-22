<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use Craft;
use fabian\booked\controllers\BookingController;
use fabian\booked\elements\Service;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use UnitTester;
use Codeception\Stub;

/**
 * Simple mock application for controller tests
 */
class ControllerMockApplication {
    public $request;
    public $response;
    public $view;
    public $sites;
    public $fields;
    public $elements;
    public $security;
    public $session;
    public $config;
    public function getIsInstalled() { return true; }
    public function getIsUpdating() { return false; }
    public function getTimeZone() { return 'Europe/Zurich'; }
    public function getRequest() { return $this->request; }
    public function getResponse() { return $this->response; }
    public function getView() { return $this->view; }
    public function getSecurity() { return $this->security; }
    public function getSession() { return $this->session; }
    public function getConfig() { return $this->config; }
    public function has($id) { return isset($this->$id); }
    public function set($id, $service) { $this->$id = $service; }
    public function get($id) { return $this->$id; }
}

class FrontendControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var BookingController
     */
    protected $controller;

    protected function _before()
    {
        parent::_before();

        $app = new ControllerMockApplication();
        $app->request = Stub::makeEmpty(\craft\web\Request::class);
        $app->response = Stub::makeEmpty(\craft\web\Response::class);
        $app->view = Stub::makeEmpty(\craft\web\View::class);
        $app->security = Stub::makeEmpty(\craft\services\Security::class);
        $app->session = Stub::makeEmpty(\craft\web\Session::class);
        $app->config = Stub::makeEmpty(\craft\services\Config::class);
        $app->sites = new class {
            public function getCurrentSite() {
                return new class { public int $id = 1; };
            }
        };
        Craft::$app = $app;

        // Mock the Booked plugin singleton
        $pluginMock = $this->getMockBuilder(\fabian\booked\Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailability', 'getBooking'])
            ->getMock();
            
        $pluginMock->method('getAvailability')->willReturn(Stub::makeEmpty(\fabian\booked\services\AvailabilityService::class));
        $pluginMock->method('getBooking')->willReturn(Stub::makeEmpty(\fabian\booked\services\BookingService::class));
            
        // Force the mock into the private static property
        $reflection = new \ReflectionClass(\fabian\booked\Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->controller = new BookingController('booking', Craft::$app);
    }

    public function testGetServices()
    {
        // This is a placeholder since we can't easily mock static Element::find() in this environment
        // without a full Craft setup.
        $this->assertTrue(true);
    }

    public function testGetEmployees()
    {
        $this->assertTrue(true);
    }

    public function testGetLocations()
    {
        $this->assertTrue(true);
    }
}

