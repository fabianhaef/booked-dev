<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\Booked;
use fabian\booked\gql\queries\ServiceExtrasQuery;
use fabian\booked\models\ServiceExtra;
use UnitTester;

/**
 * Service Extras GraphQL Tests
 *
 * Tests GraphQL queries for service extras
 */
class ServiceExtrasGraphQLTest extends Unit
{
    protected UnitTester $tester;
    private array $testExtras = [];

    protected function _before()
    {
        $this->setupTestExtras();
    }

    protected function _after()
    {
        $this->cleanupTestExtras();
    }

    /**
     * Test serviceExtras query structure
     */
    public function testServiceExtrasQueryStructure()
    {
        $queries = ServiceExtrasQuery::getQueries(false);

        $this->assertArrayHasKey('serviceExtras', $queries);
        $this->assertArrayHasKey('serviceExtra', $queries);

        // Check serviceExtras query structure
        $serviceExtrasQuery = $queries['serviceExtras'];
        $this->assertArrayHasKey('type', $serviceExtrasQuery);
        $this->assertArrayHasKey('args', $serviceExtrasQuery);
        $this->assertArrayHasKey('resolve', $serviceExtrasQuery);

        // Check serviceExtra query structure
        $serviceExtraQuery = $queries['serviceExtra'];
        $this->assertArrayHasKey('type', $serviceExtraQuery);
        $this->assertArrayHasKey('args', $serviceExtraQuery);
        $this->assertArrayHasKey('resolve', $serviceExtraQuery);
    }

    /**
     * Test serviceExtras query returns all enabled extras
     */
    public function testServiceExtrasQueryReturnsAllEnabled()
    {
        $queries = ServiceExtrasQuery::getQueries(false);
        $resolver = $queries['serviceExtras']['resolve'];

        $result = $resolver(null, ['enabled' => true]);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result)); // Should have test extras

        // Verify all returned extras are enabled
        foreach ($result as $extra) {
            $this->assertTrue($extra->enabled);
        }
    }

    /**
     * Test serviceExtras query filters by service
     */
    public function testServiceExtrasQueryFiltersByService()
    {
        $service = $this->createTestService();
        $extra = $this->testExtras[0];

        // Assign extra to service
        Booked::getInstance()->serviceExtra->assignExtraToService($extra->id, $service->id);

        $queries = ServiceExtrasQuery::getQueries(false);
        $resolver = $queries['serviceExtras']['resolve'];

        $result = $resolver(null, [
            'serviceId' => $service->id,
            'enabled' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));

        // Verify returned extra matches
        $foundExtra = false;
        foreach ($result as $resultExtra) {
            if ($resultExtra->id === $extra->id) {
                $foundExtra = true;
                break;
            }
        }
        $this->assertTrue($foundExtra, 'Assigned extra should be in results');
    }

    /**
     * Test serviceExtra query returns single extra
     */
    public function testServiceExtraQueryReturnsSingleExtra()
    {
        $testExtra = $this->testExtras[0];

        $queries = ServiceExtrasQuery::getQueries(false);
        $resolver = $queries['serviceExtra']['resolve'];

        $result = $resolver(null, ['id' => $testExtra->id]);

        $this->assertInstanceOf(ServiceExtra::class, $result);
        $this->assertEquals($testExtra->id, $result->id);
        $this->assertEquals($testExtra->name, $result->name);
        $this->assertEquals($testExtra->price, $result->price);
    }

    /**
     * Test serviceExtra query with non-existent ID
     */
    public function testServiceExtraQueryWithNonExistentId()
    {
        $queries = ServiceExtrasQuery::getQueries(false);
        $resolver = $queries['serviceExtra']['resolve'];

        $result = $resolver(null, ['id' => 999999]);

        $this->assertNull($result);
    }

    /**
     * Test GraphQL type field mappings
     */
    public function testGraphQLTypeFieldMappings()
    {
        $extra = $this->testExtras[0];

        // These fields should be accessible
        $this->assertNotNull($extra->id);
        $this->assertNotNull($extra->name);
        $this->assertNotNull($extra->price);
        $this->assertNotNull($extra->duration);
        $this->assertNotNull($extra->maxQuantity);
        $this->assertNotNull($extra->sortOrder);
        $this->assertIsBool($extra->isRequired);
        $this->assertIsBool($extra->enabled);
    }

    /**
     * Test that disabled extras are filtered by default
     */
    public function testDisabledExtrasFilteredByDefault()
    {
        // Create disabled extra
        $disabledExtra = new ServiceExtra();
        $disabledExtra->name = 'Disabled GraphQL Test Extra';
        $disabledExtra->price = 99.99;
        $disabledExtra->enabled = false;
        Booked::getInstance()->serviceExtra->saveExtra($disabledExtra);

        $queries = ServiceExtrasQuery::getQueries(false);
        $resolver = $queries['serviceExtras']['resolve'];

        // Query with default enabled=true
        $result = $resolver(null, ['enabled' => true]);

        // Disabled extra should not be in results
        $foundDisabled = false;
        foreach ($result as $extra) {
            if ($extra->id === $disabledExtra->id) {
                $foundDisabled = true;
                break;
            }
        }

        $this->assertFalse($foundDisabled, 'Disabled extras should be filtered out');

        // Cleanup
        Booked::getInstance()->serviceExtra->deleteExtra($disabledExtra->id);
    }

    /**
     * Test extras in Reservation extraFields
     */
    public function testReservationExtraFields()
    {
        $reservation = new \fabian\booked\elements\Reservation();
        $extraFields = $reservation->extraFields();

        // Check that extras-related fields are exposed
        $this->assertArrayHasKey('extras', $extraFields);
        $this->assertArrayHasKey('extrasPrice', $extraFields);
        $this->assertArrayHasKey('extrasSummary', $extraFields);
        $this->assertArrayHasKey('totalPrice', $extraFields);
        $this->assertArrayHasKey('totalDuration', $extraFields);
        $this->assertArrayHasKey('hasExtras', $extraFields);
    }

    // ========== Helper Methods ==========

    private function setupTestExtras(): void
    {
        $extra1 = new ServiceExtra();
        $extra1->name = 'GraphQL Test Extra 1';
        $extra1->description = 'First test extra for GraphQL';
        $extra1->price = 25.00;
        $extra1->duration = 30;
        $extra1->maxQuantity = 3;
        $extra1->isRequired = false;
        $extra1->sortOrder = 1;
        $extra1->enabled = true;
        Booked::getInstance()->serviceExtra->saveExtra($extra1);
        $this->testExtras[] = $extra1;

        $extra2 = new ServiceExtra();
        $extra2->name = 'GraphQL Test Extra 2';
        $extra2->description = 'Second test extra for GraphQL';
        $extra2->price = 35.00;
        $extra2->duration = 15;
        $extra2->maxQuantity = 2;
        $extra2->isRequired = true;
        $extra2->sortOrder = 2;
        $extra2->enabled = true;
        Booked::getInstance()->serviceExtra->saveExtra($extra2);
        $this->testExtras[] = $extra2;
    }

    private function createTestService(): \fabian\booked\elements\Service
    {
        $service = new \fabian\booked\elements\Service();
        $service->title = 'GraphQL Test Service ' . uniqid();
        $service->duration = 60;
        $service->price = 100.00;
        $service->enabled = true;
        \Craft::$app->elements->saveElement($service);

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
