<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\models\FieldLayout;
use craft\base\Field;
use fabian\booked\elements\Reservation;
use UnitTester;
use Codeception\Stub;

/**
 * Simple mock application to avoid Codeception Stub issues with Craft's Application class
 */
class MockApplication {
    public $fields;
    public $elements;
    public $view;
    public $sites;
    public function getIsInstalled() { return true; }
    public function getIsUpdating() { return false; }
    public function getTimeZone() { return 'Europe/Zurich'; }
    public function getFields() { return $this->fields; }
    public function getElements() { return $this->elements; }
    public function getView() { return $this->view; }
    public function set($id, $service) { $this->$id = $service; }
    public function get($id) { return $this->$id; }
}

class ReservationCustomFieldsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _before()
    {
        parent::_before();

        $app = new MockApplication();
        $app->fields = Stub::makeEmpty(\craft\services\Fields::class);
        $app->elements = Stub::makeEmpty(\craft\services\Elements::class);
        $app->view = Stub::makeEmpty(\craft\web\View::class);
        $app->sites = new class {
            public function getCurrentSite() {
                return new class { public int $id = 1; };
            }
        };

        Craft::$app = $app;
    }

    public function testReservationHasFieldLayout()
    {
        // Use Stub::make to bypass the real init() if it still causes trouble
        $reservation = Stub::make(Reservation::class, [
            'init' => null
        ]);
        
        // Mock the fields service
        $fieldsService = Stub::make(\craft\services\Fields::class, [
            'getLayoutByType' => Stub::make(FieldLayout::class)
        ]);
        Craft::$app->set('fields', $fieldsService);

        $fieldLayout = $reservation->getFieldLayout();
        $this->assertInstanceOf(FieldLayout::class, $fieldLayout);
    }

    public function testGetCustomFieldDataInJob()
    {
        // Mock a field
        $field = Stub::make(Field::class, [
            'handle' => 'myCustomField',
            'name' => 'My Custom Field'
        ]);

        // Mock a field layout
        $fieldLayout = Stub::make(FieldLayout::class, [
            'getCustomFields' => [$field]
        ]);

        // Mock a reservation with the layout and a value
        $reservation = Stub::make(Reservation::class, [
            'getFieldLayout' => $fieldLayout,
            'getFieldValue' => function($handle) {
                return $handle === 'myCustomField' ? 'Some Value' : null;
            }
        ]);

        // Use reflection to test the private getCustomFieldData method in SendBookingEmailJob
        $job = new \fabian\booked\queue\jobs\SendBookingEmailJob();
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('getCustomFieldData');
        $method->setAccessible(true);

        $data = $method->invoke($job, $reservation);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('My Custom Field', $data[0]['label']);
        $this->assertEquals('Some Value', $data[0]['value']);
    }

    public function testGetCustomFieldDataWithObjectValue()
    {
        // Mock an object value (e.g., an Asset or Entry)
        $mockObject = new class {
            public string $name = 'Object Name';
            public function __toString() { return 'String Representation'; }
        };

        $field = Stub::make(Field::class, [
            'handle' => 'objectField',
            'name' => 'Object Field'
        ]);

        $fieldLayout = Stub::make(FieldLayout::class, [
            'getCustomFields' => [$field]
        ]);

        $reservation = Stub::make(Reservation::class, [
            'getFieldLayout' => $fieldLayout,
            'getFieldValue' => $mockObject
        ]);

        $job = new \fabian\booked\queue\jobs\SendBookingEmailJob();
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('getCustomFieldData');
        $method->setAccessible(true);

        $data = $method->invoke($job, $reservation);

        $this->assertIsArray($data);
        $this->assertEquals('String Representation', $data[0]['value']);
    }
}

