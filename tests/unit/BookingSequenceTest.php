<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\elements\BookingSequence;
use fabian\booked\records\BookingSequenceRecord;

/**
 * BookingSequence Element Unit Tests
 *
 * Tests the BookingSequence element class logic
 */
class BookingSequenceTest extends Unit
{
    protected $tester;

    /**
     * Test element display names
     */
    public function testElementDisplayNames()
    {
        $this->assertEquals('Booking Sequence', BookingSequence::displayName());
        $this->assertEquals('Booking Sequences', BookingSequence::pluralDisplayName());
        $this->assertEquals('bookingSequence', BookingSequence::refHandle());
    }

    /**
     * Test element configuration
     */
    public function testElementConfiguration()
    {
        $this->assertFalse(BookingSequence::hasContent());
        $this->assertFalse(BookingSequence::hasTitles());
        $this->assertTrue(BookingSequence::hasStatuses());
    }

    /**
     * Test status definitions
     */
    public function testStatusDefinitions()
    {
        $statuses = BookingSequence::statuses();

        $this->assertIsArray($statuses);
        $this->assertArrayHasKey(BookingSequenceRecord::STATUS_PENDING, $statuses);
        $this->assertArrayHasKey(BookingSequenceRecord::STATUS_CONFIRMED, $statuses);
        $this->assertArrayHasKey(BookingSequenceRecord::STATUS_CANCELLED, $statuses);
        $this->assertArrayHasKey(BookingSequenceRecord::STATUS_COMPLETED, $statuses);

        // Check status colors
        $this->assertEquals('orange', $statuses[BookingSequenceRecord::STATUS_PENDING]['color']);
        $this->assertEquals('green', $statuses[BookingSequenceRecord::STATUS_CONFIRMED]['color']);
        $this->assertEquals('red', $statuses[BookingSequenceRecord::STATUS_CANCELLED]['color']);
        $this->assertEquals('blue', $statuses[BookingSequenceRecord::STATUS_COMPLETED]['color']);
    }

    /**
     * Test default property values
     */
    public function testDefaultPropertyValues()
    {
        $sequence = new BookingSequence();

        $this->assertNull($sequence->userId);
        $this->assertEquals('', $sequence->customerEmail);
        $this->assertEquals('', $sequence->customerName);
        $this->assertEquals(BookingSequenceRecord::STATUS_PENDING, $sequence->status);
        $this->assertEquals(0.0, $sequence->totalPrice);
    }

    /**
     * Test setting properties
     */
    public function testSettingProperties()
    {
        $sequence = new BookingSequence();

        $sequence->userId = 123;
        $sequence->customerEmail = 'test@example.com';
        $sequence->customerName = 'Test Customer';
        $sequence->status = BookingSequenceRecord::STATUS_CONFIRMED;
        $sequence->totalPrice = 250.50;

        $this->assertEquals(123, $sequence->userId);
        $this->assertEquals('test@example.com', $sequence->customerEmail);
        $this->assertEquals('Test Customer', $sequence->customerName);
        $this->assertEquals(BookingSequenceRecord::STATUS_CONFIRMED, $sequence->status);
        $this->assertEquals(250.50, $sequence->totalPrice);
    }

    /**
     * Test getStatus method
     */
    public function testGetStatus()
    {
        $sequence = new BookingSequence();

        $sequence->status = BookingSequenceRecord::STATUS_PENDING;
        $this->assertEquals(BookingSequenceRecord::STATUS_PENDING, $sequence->getStatus());

        $sequence->status = BookingSequenceRecord::STATUS_CONFIRMED;
        $this->assertEquals(BookingSequenceRecord::STATUS_CONFIRMED, $sequence->getStatus());

        $sequence->status = BookingSequenceRecord::STATUS_CANCELLED;
        $this->assertEquals(BookingSequenceRecord::STATUS_CANCELLED, $sequence->getStatus());
    }

    /**
     * Test getCpEditUrl returns correct URL format
     */
    public function testGetCpEditUrl()
    {
        $sequence = new BookingSequence();
        $sequence->id = 123;

        $url = $sequence->getCpEditUrl();

        $this->assertStringContainsString('booked/sequences/123', $url);
    }

    /**
     * Test getCpEditUrl returns null for unsaved element
     */
    public function testGetCpEditUrlForUnsavedElement()
    {
        $sequence = new BookingSequence();

        $url = $sequence->getCpEditUrl();

        $this->assertNull($url);
    }

    /**
     * Test source definitions
     */
    public function testSourceDefinitions()
    {
        $sources = $this->invokePrivateMethod(BookingSequence::class, 'defineSources');

        $this->assertIsArray($sources);
        $this->assertCount(4, $sources);

        // All sequences
        $this->assertEquals('*', $sources[0]['key']);

        // Pending
        $this->assertEquals('pending', $sources[1]['key']);
        $this->assertEquals(BookingSequenceRecord::STATUS_PENDING, $sources[1]['criteria']['status']);

        // Confirmed
        $this->assertEquals('confirmed', $sources[2]['key']);
        $this->assertEquals(BookingSequenceRecord::STATUS_CONFIRMED, $sources[2]['criteria']['status']);

        // Cancelled
        $this->assertEquals('cancelled', $sources[3]['key']);
        $this->assertEquals(BookingSequenceRecord::STATUS_CANCELLED, $sources[3]['criteria']['status']);
    }

    /**
     * Test table attributes
     */
    public function testTableAttributes()
    {
        $attributes = $this->invokePrivateMethod(BookingSequence::class, 'defineTableAttributes');

        $this->assertArrayHasKey('customerName', $attributes);
        $this->assertArrayHasKey('customerEmail', $attributes);
        $this->assertArrayHasKey('itemCount', $attributes);
        $this->assertArrayHasKey('totalDuration', $attributes);
        $this->assertArrayHasKey('totalPrice', $attributes);
        $this->assertArrayHasKey('status', $attributes);
        $this->assertArrayHasKey('dateCreated', $attributes);
    }

    /**
     * Test default table attributes
     */
    public function testDefaultTableAttributes()
    {
        $defaults = $this->invokePrivateMethod(BookingSequence::class, 'defineDefaultTableAttributes', ['*']);

        $this->assertContains('customerName', $defaults);
        $this->assertContains('customerEmail', $defaults);
        $this->assertContains('itemCount', $defaults);
        $this->assertContains('totalPrice', $defaults);
        $this->assertContains('status', $defaults);
        $this->assertContains('dateCreated', $defaults);
    }

    /**
     * Helper method to invoke private/protected methods for testing
     */
    private function invokePrivateMethod(string $className, string $methodName, ...$args)
    {
        $reflection = new \ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        // For static methods
        if ($method->isStatic()) {
            return $method->invoke(null, ...$args);
        }

        // For instance methods
        $instance = $reflection->newInstanceWithoutConstructor();
        return $method->invoke($instance, ...$args);
    }
}
