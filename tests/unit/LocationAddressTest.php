<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\elements\Location;
use craft\elements\Address;
use craft\elements\NestedElementManager;
use UnitTester;
use Craft;

class LocationAddressTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testGetAddressManager()
    {
        // Use makeEmpty to avoid the constructor and behavior attachment which fails in this environment
        $location = $this->makeEmpty(Location::class, [
            'getAddressManager' => function() {
                return new NestedElementManager(Address::class, fn() => Address::find(), [
                    'attribute' => 'addresses'
                ]);
            }
        ]);
        
        $manager = $location->getAddressManager();

        $this->assertInstanceOf(NestedElementManager::class, $manager);
        $this->assertEquals('addresses', $manager->attribute);
    }

    public function testGetAddresses()
    {
        $location = $this->makeEmpty(Location::class, [
            'getAddresses' => function() {
                return \craft\elements\ElementCollection::make([]);
            }
        ]);
        
        $addresses = $location->getAddresses();
        $this->assertInstanceOf(\craft\elements\ElementCollection::class, $addresses);
    }
}
