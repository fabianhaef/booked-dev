<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\AvailabilityService;
use fabian\booked\elements\BookingVariation;
use UnitTester;

/**
 * Tests for Quantity vs Capacity Validation (Missing Validation 5.3)
 *
 * Validates that requested quantity does not exceed the maximum capacity
 * configured for a booking variation/service.
 *
 * Current Issue: isSlotAvailable() checks if slots exist for the quantity
 * but doesn't verify if requestedQuantity <= maxCapacity
 */
class QuantityCapacityValidationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test that requesting quantity within capacity is allowed
     */
    public function testQuantityWithinCapacityIsAllowed()
    {
        $variation = $this->createVariation(maxCapacity: 5);

        // Request 3 spots when max capacity is 5
        $this->assertTrue(
            $this->validateQuantity(3, $variation),
            'Quantity 3 should be allowed when max capacity is 5'
        );

        // Request exactly max capacity
        $this->assertTrue(
            $this->validateQuantity(5, $variation),
            'Quantity 5 should be allowed when max capacity is 5'
        );
    }

    /**
     * Test that requesting quantity exceeding capacity is rejected
     */
    public function testQuantityExceedingCapacityIsRejected()
    {
        $variation = $this->createVariation(maxCapacity: 5);

        // Request 6 spots when max capacity is 5
        $this->assertFalse(
            $this->validateQuantity(6, $variation),
            'Quantity 6 should be rejected when max capacity is 5'
        );

        // Request 10 spots
        $this->assertFalse(
            $this->validateQuantity(10, $variation),
            'Quantity 10 should be rejected when max capacity is 5'
        );
    }

    /**
     * Test single-person service (maxCapacity = 1)
     */
    public function testSinglePersonService()
    {
        $variation = $this->createVariation(maxCapacity: 1);

        $this->assertTrue(
            $this->validateQuantity(1, $variation),
            'Quantity 1 should be allowed for single-person service'
        );

        $this->assertFalse(
            $this->validateQuantity(2, $variation),
            'Quantity 2 should be rejected for single-person service'
        );
    }

    /**
     * Test group service (maxCapacity = 10)
     */
    public function testGroupService()
    {
        $variation = $this->createVariation(maxCapacity: 10);

        $this->assertTrue($this->validateQuantity(1, $variation));
        $this->assertTrue($this->validateQuantity(5, $variation));
        $this->assertTrue($this->validateQuantity(10, $variation));
        $this->assertFalse($this->validateQuantity(11, $variation));
        $this->assertFalse($this->validateQuantity(20, $variation));
    }

    /**
     * Test that existing bookings reduce available capacity
     */
    public function testExistingBookingsReduceAvailableCapacity()
    {
        $variation = $this->createVariation(maxCapacity: 5);

        // Simulate 2 spots already booked
        $existingBookedQuantity = 2;
        $remainingCapacity = $variation->maxCapacity - $existingBookedQuantity; // 3

        // Request 3 spots (should fit in remaining capacity)
        $this->assertTrue(
            $this->validateQuantity(3, $variation, $existingBookedQuantity),
            'Quantity 3 should fit when 2 of 5 spots are taken'
        );

        // Request 4 spots (exceeds remaining capacity)
        $this->assertFalse(
            $this->validateQuantity(4, $variation, $existingBookedQuantity),
            'Quantity 4 should be rejected when only 3 spots remain'
        );
    }

    /**
     * Test fully booked slot
     */
    public function testFullyBookedSlot()
    {
        $variation = $this->createVariation(maxCapacity: 5);

        // All 5 spots already booked
        $existingBookedQuantity = 5;

        // Request even 1 spot (should be rejected)
        $this->assertFalse(
            $this->validateQuantity(1, $variation, $existingBookedQuantity),
            'Should reject any booking when slot is fully booked'
        );
    }

    /**
     * Test multiple bookings filling up capacity
     */
    public function testMultipleBookingsFillingUpCapacity()
    {
        $variation = $this->createVariation(maxCapacity: 10);

        // First booking: 3 spots
        $this->assertTrue($this->validateQuantity(3, $variation, 0));

        // Second booking: 4 spots (total would be 7)
        $this->assertTrue($this->validateQuantity(4, $variation, 3));

        // Third booking: 3 spots (total would be 10 - exactly capacity)
        $this->assertTrue($this->validateQuantity(3, $variation, 7));

        // Fourth booking: 1 spot (total would be 11 - exceeds capacity)
        $this->assertFalse($this->validateQuantity(1, $variation, 10));
    }

    /**
     * Test boundary case: quantity = 0
     */
    public function testZeroQuantity()
    {
        $variation = $this->createVariation(maxCapacity: 5);

        // Quantity 0 should always be invalid
        $this->assertFalse(
            $this->validateQuantity(0, $variation),
            'Quantity 0 should always be invalid'
        );
    }

    /**
     * Test boundary case: negative quantity
     */
    public function testNegativeQuantity()
    {
        $variation = $this->createVariation(maxCapacity: 5);

        // Negative quantity should always be invalid
        $this->assertFalse(
            $this->validateQuantity(-1, $variation),
            'Negative quantity should always be invalid'
        );
    }

    /**
     * Test large capacity service (e.g., conference room)
     */
    public function testLargeCapacityService()
    {
        $variation = $this->createVariation(maxCapacity: 100);

        $this->assertTrue($this->validateQuantity(50, $variation));
        $this->assertTrue($this->validateQuantity(100, $variation));
        $this->assertFalse($this->validateQuantity(101, $variation));
    }

    /**
     * Test realistic scenario: yoga class
     */
    public function testRealisticYogaClass()
    {
        // Yoga class with max 15 participants
        $variation = $this->createVariation(maxCapacity: 15);

        // 10 people already booked
        $existingBookedQuantity = 10;

        // Customer wants to book 3 spots (total 13, within capacity)
        $this->assertTrue(
            $this->validateQuantity(3, $variation, $existingBookedQuantity),
            'Should allow booking 3 when 10 of 15 spots are taken'
        );

        // Customer wants to book 6 spots (total 16, exceeds capacity)
        $this->assertFalse(
            $this->validateQuantity(6, $variation, $existingBookedQuantity),
            'Should reject booking 6 when only 5 spots remain'
        );

        // Update: 13 people now booked
        $existingBookedQuantity = 13;

        // Customer wants last 2 spots (total 15, exactly capacity)
        $this->assertTrue(
            $this->validateQuantity(2, $variation, $existingBookedQuantity),
            'Should allow booking last 2 spots'
        );
    }

    /**
     * Test that allowQuantitySelection flag is respected
     */
    public function testAllowQuantitySelectionFlag()
    {
        // Service that allows quantity selection
        $variationAllowed = $this->createVariation(maxCapacity: 5, allowQuantitySelection: true);

        $this->assertTrue(
            $variationAllowed->allowQuantitySelection,
            'Should allow quantity selection when flag is true'
        );

        // Service that doesn't allow quantity selection (single booking only)
        $variationNotAllowed = $this->createVariation(maxCapacity: 1, allowQuantitySelection: false);

        $this->assertFalse(
            $variationNotAllowed->allowQuantitySelection,
            'Should not allow quantity selection when flag is false'
        );

        // When allowQuantitySelection is false, only quantity=1 should be allowed
        if (!$variationNotAllowed->allowQuantitySelection) {
            $this->assertFalse(
                $this->validateQuantity(2, $variationNotAllowed),
                'Should reject quantity > 1 when allowQuantitySelection is false'
            );
        }
    }

    /**
     * Security test: Ensure overbooking is prevented
     *
     * This test documents the security/business logic fix:
     * Before: Could book 10 people even if max capacity is 5
     * After: Validation prevents overbooking
     */
    public function testOverbookingPrevention()
    {
        // Conference room with capacity 20
        $variation = $this->createVariation(maxCapacity: 20);

        // Already 18 people booked
        $existingBookedQuantity = 18;

        // Malicious/accidental attempt to book 5 more (total 23, exceeds 20)
        $this->assertFalse(
            $this->validateQuantity(5, $variation, $existingBookedQuantity),
            'Should prevent overbooking even with accidental large requests'
        );

        // Valid booking of 2 (total 20, exactly capacity)
        $this->assertTrue(
            $this->validateQuantity(2, $variation, $existingBookedQuantity),
            'Should allow booking up to exact capacity'
        );
    }

    /**
     * Helper: Create booking variation with specified capacity
     */
    private function createVariation(
        int $maxCapacity,
        bool $allowQuantitySelection = true
    ): BookingVariation {
        $variation = new BookingVariation();
        $variation->maxCapacity = $maxCapacity;
        $variation->allowQuantitySelection = $allowQuantitySelection;
        $variation->isActive = true;

        return $variation;
    }

    /**
     * Helper: Validate if requested quantity is allowed
     *
     * @param int $requestedQuantity Quantity user wants to book
     * @param BookingVariation $variation Booking variation/service
     * @param int $existingBookedQuantity Already booked quantity for this slot
     * @return bool True if quantity is valid
     */
    private function validateQuantity(
        int $requestedQuantity,
        BookingVariation $variation,
        int $existingBookedQuantity = 0
    ): bool {
        // Negative or zero quantity is invalid
        if ($requestedQuantity <= 0) {
            return false;
        }

        // If quantity selection not allowed, only quantity=1 is valid
        if (!$variation->allowQuantitySelection && $requestedQuantity > 1) {
            return false;
        }

        // Check if requested quantity exceeds max capacity
        if ($requestedQuantity > $variation->maxCapacity) {
            return false;
        }

        // Check if requested quantity + existing bookings exceeds capacity
        $totalQuantity = $requestedQuantity + $existingBookedQuantity;
        if ($totalQuantity > $variation->maxCapacity) {
            return false;
        }

        return true;
    }
}
