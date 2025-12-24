<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use UnitTester;
use Craft;

/**
 * Tests for AvailabilityService slot deduplication
 * Ensures deduplicateSlotsByTime() correctly merges duplicate time slots
 * when user selects "random employee" option
 */
class AvailabilitySlotDeduplicationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var AvailabilityService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();

        // Mock Craft::$app
        $this->mockCraftApp();

        // Create service instance
        $this->service = new AvailabilityService();
    }

    /**
     * Test that deduplication removes duplicate time slots
     */
    public function testDeduplicationRemovesDuplicateTimeSlots()
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '10:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
            ['time' => '10:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
            ['time' => '11:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '11:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        $this->assertCount(2, $deduplicated, 'Should have 2 unique time slots (10:00 and 11:00)');

        // Extract times
        $times = array_column($deduplicated, 'time');
        $this->assertContains('10:00', $times);
        $this->assertContains('11:00', $times);

        // Verify no duplicates
        $uniqueTimes = array_unique($times);
        $this->assertCount(count($times), $uniqueTimes, 'All times should be unique');
    }

    /**
     * Test that deduplicated slots have employeeId set to null
     */
    public function testDeduplicatedSlotsHaveNullEmployeeId()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '10:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        foreach ($deduplicated as $slot) {
            $this->assertNull(
                $slot['employeeId'],
                'Deduplicated slots should have employeeId set to null'
            );
        }
    }

    /**
     * Test that deduplicated slots have employeeName set to "Beliebig"
     */
    public function testDeduplicatedSlotsHaveBeliebigEmployeeName()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '10:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        foreach ($deduplicated as $slot) {
            $this->assertEquals(
                'Beliebig',
                $slot['employeeName'],
                'Deduplicated slots should have employeeName "Beliebig"'
            );
        }
    }

    /**
     * Test that deduplication preserves first occurrence of each time
     */
    public function testDeduplicationPreservesFirstOccurrence()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1', 'extraData' => 'first'],
            ['time' => '10:00', 'employeeId' => 2, 'employeeName' => 'Employee 2', 'extraData' => 'second'],
            ['time' => '10:00', 'employeeId' => 3, 'employeeName' => 'Employee 3', 'extraData' => 'third'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        $this->assertCount(1, $deduplicated);
        // First occurrence's extra data should be preserved (except employeeId and employeeName which are overwritten)
        $this->assertEquals('first', $deduplicated[0]['extraData']);
    }

    /**
     * Test that empty array returns empty array
     */
    public function testEmptyArrayReturnsEmptyArray()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [];
        $deduplicated = $method->invoke($this->service, $slots);

        $this->assertIsArray($deduplicated);
        $this->assertCount(0, $deduplicated);
    }

    /**
     * Test that single slot returns single slot
     */
    public function testSingleSlotReturnsSingleSlot()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        $this->assertCount(1, $deduplicated);
        $this->assertEquals('10:00', $deduplicated[0]['time']);
        $this->assertNull($deduplicated[0]['employeeId']);
        $this->assertEquals('Beliebig', $deduplicated[0]['employeeName']);
    }

    /**
     * Test that slots with unique times are all preserved
     */
    public function testUniqueSlotsAreAllPreserved()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '11:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
            ['time' => '12:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        $this->assertCount(3, $deduplicated, 'All unique slots should be preserved');

        $times = array_column($deduplicated, 'time');
        $this->assertContains('10:00', $times);
        $this->assertContains('11:00', $times);
        $this->assertContains('12:00', $times);
    }

    /**
     * Test realistic scenario: 3 employees, overlapping availability
     */
    public function testRealisticScenarioWithThreeEmployees()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        // Employee 1 available: 10:00, 11:00, 12:00
        // Employee 2 available: 10:00, 11:00, 14:00
        // Employee 3 available: 11:00, 12:00, 13:00
        $slots = [
            ['time' => '10:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '10:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
            ['time' => '11:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '11:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
            ['time' => '11:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
            ['time' => '12:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '12:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
            ['time' => '13:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
            ['time' => '14:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        // Should have 5 unique times: 10:00, 11:00, 12:00, 13:00, 14:00
        $this->assertCount(5, $deduplicated);

        $times = array_column($deduplicated, 'time');
        sort($times);

        $expected = ['10:00', '11:00', '12:00', '13:00', '14:00'];
        $this->assertEquals($expected, $times);

        // All should have null employeeId and "Beliebig" name
        foreach ($deduplicated as $slot) {
            $this->assertNull($slot['employeeId']);
            $this->assertEquals('Beliebig', $slot['employeeName']);
        }
    }

    /**
     * Test that deduplication maintains slot order
     */
    public function testDeduplicationMaintainsSlotOrder()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            ['time' => '14:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '10:00', 'employeeId' => 2, 'employeeName' => 'Employee 2'],
            ['time' => '14:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
            ['time' => '12:00', 'employeeId' => 1, 'employeeName' => 'Employee 1'],
            ['time' => '10:00', 'employeeId' => 3, 'employeeName' => 'Employee 3'],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        // Should maintain order of first occurrence
        $times = array_column($deduplicated, 'time');
        $this->assertEquals(['14:00', '10:00', '12:00'], $times, 'Should preserve order of first occurrence');
    }

    /**
     * Test large dataset performance (100 employees, 20 time slots each = 2000 slots)
     */
    public function testLargeDatasetPerformance()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [];
        $numEmployees = 100;
        $numSlots = 20;

        // Generate 2000 slots (100 employees × 20 time slots)
        for ($emp = 1; $emp <= $numEmployees; $emp++) {
            for ($hour = 8; $hour < 8 + $numSlots; $hour++) {
                $slots[] = [
                    'time' => sprintf('%02d:00', $hour),
                    'employeeId' => $emp,
                    'employeeName' => "Employee {$emp}",
                ];
            }
        }

        $this->assertCount(2000, $slots, 'Should have 2000 input slots');

        $startTime = microtime(true);
        $deduplicated = $method->invoke($this->service, $slots);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        // Should deduplicate to 20 unique time slots
        $this->assertCount($numSlots, $deduplicated, 'Should deduplicate to 20 unique times');

        // Should complete quickly (< 100ms)
        $this->assertLessThan(0.1, $executionTime, 'Deduplication should complete in < 100ms');

        // Verify all have null employeeId
        foreach ($deduplicated as $slot) {
            $this->assertNull($slot['employeeId']);
            $this->assertEquals('Beliebig', $slot['employeeName']);
        }
    }

    /**
     * Test that additional slot properties are preserved
     */
    public function testAdditionalSlotPropertiesArePreserved()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('deduplicateSlotsByTime');
        $method->setAccessible(true);

        $slots = [
            [
                'time' => '10:00',
                'employeeId' => 1,
                'employeeName' => 'Employee 1',
                'duration' => 60,
                'price' => 100,
                'available' => true,
            ],
            [
                'time' => '10:00',
                'employeeId' => 2,
                'employeeName' => 'Employee 2',
                'duration' => 60,
                'price' => 100,
                'available' => true,
            ],
        ];

        $deduplicated = $method->invoke($this->service, $slots);

        $this->assertCount(1, $deduplicated);

        // Additional properties should be preserved from first occurrence
        $slot = $deduplicated[0];
        $this->assertEquals('10:00', $slot['time']);
        $this->assertNull($slot['employeeId']); // Overwritten
        $this->assertEquals('Beliebig', $slot['employeeName']); // Overwritten
        $this->assertEquals(60, $slot['duration']); // Preserved
        $this->assertEquals(100, $slot['price']); // Preserved
        $this->assertTrue($slot['available']); // Preserved
    }

    /**
     * Mock Craft application
     */
    private function mockCraftApp()
    {
        if (!isset(Craft::$app)) {
            $app = new class {
                public function getTimeZone()
                {
                    return 'UTC';
                }
            };

            Craft::$app = $app;
        }
    }
}
