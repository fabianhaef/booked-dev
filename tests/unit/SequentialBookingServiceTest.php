<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\Booked;
use fabian\booked\elements\Service;
use fabian\booked\exceptions\BookingException;
use fabian\booked\services\SequentialBookingService;
use fabian\booked\services\AvailabilityService;

/**
 * SequentialBookingService Unit Tests
 *
 * Tests the sequential booking service logic without database dependencies
 */
class SequentialBookingServiceTest extends Unit
{
    protected $tester;
    protected SequentialBookingService $service;
    protected $mockAvailabilityService;
    protected $mockPlugin;

    protected function _before()
    {
        parent::_before();
        $this->service = new SequentialBookingService();
    }

    /**
     * Test validation of empty service IDs
     */
    public function testGetAvailableSlotsThrowsExceptionForEmptyServiceIds()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('No services provided');

        $this->service->getAvailableSequenceSlots(
            [],
            '2025-12-26',
            null,
            null
        );
    }

    /**
     * Test validation of missing customer name
     */
    public function testCreateSequentialBookingValidatesCustomerName()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Missing required field: customerName');

        // Use reflection to call private validateBookingData method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1, 2],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerEmail' => 'test@example.com',
            // Missing customerName
        ]);
    }

    /**
     * Test validation of missing customer email
     */
    public function testCreateSequentialBookingValidatesCustomerEmail()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Missing required field: customerEmail');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1, 2],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            // Missing customerEmail
        ]);
    }

    /**
     * Test validation of invalid email format
     */
    public function testCreateSequentialBookingValidatesEmailFormat()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Invalid email address');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1, 2],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'invalid-email', // Invalid format
        ]);
    }

    /**
     * Test validation of missing service IDs
     */
    public function testCreateSequentialBookingValidatesServiceIds()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Missing required field: serviceIds');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            // Missing serviceIds
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);
    }

    /**
     * Test validation of empty service IDs array
     */
    public function testCreateSequentialBookingValidatesServiceIdsArray()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('serviceIds must be a non-empty array');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [], // Empty array
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);
    }

    /**
     * Test validation of invalid date format
     */
    public function testCreateSequentialBookingValidatesDateFormat()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Invalid date format');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1, 2],
            'date' => '26-12-2025', // Wrong format
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);
    }

    /**
     * Test validation of invalid time format
     */
    public function testCreateSequentialBookingValidatesTimeFormat()
    {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Invalid time format');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1, 2],
            'date' => '2025-12-26',
            'startTime' => '10:00 AM', // Wrong format
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);
    }

    /**
     * Test validation passes with valid data
     */
    public function testCreateSequentialBookingValidationPassesWithValidData()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->service, [
            'serviceIds' => [1, 2, 3],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'customerPhone' => '+41 79 123 45 67',
        ]);

        // If we get here, validation passed
        $this->assertTrue(true);
    }

    /**
     * Test getSuggestedSequences returns empty array
     */
    public function testGetSuggestedSequencesReturnsEmptyArray()
    {
        $sequences = $this->service->getSuggestedSequences();
        $this->assertIsArray($sequences);
        $this->assertEmpty($sequences);
    }

    /**
     * Test getSequenceById returns null for non-existent sequence
     *
     * Note: This is a basic unit test. Integration tests will test actual database queries.
     */
    public function testGetSequenceByIdBasic()
    {
        // In unit test environment without database, this should handle gracefully
        $sequence = $this->service->getSequenceById(999999);

        // Should return null for non-existent sequence
        $this->assertNull($sequence);
    }

    /**
     * Test getSequencesByCustomerEmail basic functionality
     */
    public function testGetSequencesByCustomerEmailBasic()
    {
        // In unit test environment, should return empty array or handle gracefully
        $sequences = $this->service->getSequencesByCustomerEmail('test@example.com');

        $this->assertIsArray($sequences);
    }

    /**
     * Test validation accepts international phone numbers
     */
    public function testValidationAcceptsInternationalPhoneNumbers()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        // Test various phone formats (validation doesn't enforce format, just accepts string)
        $validData = [
            'serviceIds' => [1],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ];

        // Without phone - should work
        $method->invoke($this->service, $validData);
        $this->assertTrue(true);

        // With phone - should work
        $validData['customerPhone'] = '+41 79 123 45 67';
        $method->invoke($this->service, $validData);
        $this->assertTrue(true);
    }

    /**
     * Test validation accepts optional fields
     */
    public function testValidationAcceptsOptionalFields()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $validData = [
            'serviceIds' => [1, 2],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
            'customerPhone' => '+41 79 123 45 67',
            'employeeId' => 5,
            'locationId' => 3,
            'userId' => 10,
        ];

        $method->invoke($this->service, $validData);
        $this->assertTrue(true);
    }

    /**
     * Test validation handles edge case dates
     */
    public function testValidationHandlesEdgeCaseDates()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        // Leap year date
        $data = [
            'serviceIds' => [1],
            'date' => '2024-02-29',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ];
        $method->invoke($this->service, $data);
        $this->assertTrue(true);

        // Invalid leap year date should throw
        $this->expectException(BookingException::class);
        $data['date'] = '2025-02-29'; // 2025 is not a leap year
        $method->invoke($this->service, $data);
    }

    /**
     * Test validation handles edge case times
     */
    public function testValidationHandlesEdgeCaseTimes()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $baseData = [
            'serviceIds' => [1],
            'date' => '2025-12-26',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ];

        // Midnight
        $data = array_merge($baseData, ['startTime' => '00:00']);
        $method->invoke($this->service, $data);
        $this->assertTrue(true);

        // Almost midnight
        $data = array_merge($baseData, ['startTime' => '23:59']);
        $method->invoke($this->service, $data);
        $this->assertTrue(true);

        // Noon
        $data = array_merge($baseData, ['startTime' => '12:00']);
        $method->invoke($this->service, $data);
        $this->assertTrue(true);
    }

    /**
     * Test validation rejects times with seconds
     */
    public function testValidationRejectsTimesWithSeconds()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Invalid time format');

        $method->invoke($this->service, [
            'serviceIds' => [1],
            'date' => '2025-12-26',
            'startTime' => '10:00:00', // Has seconds
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);
    }

    /**
     * Test validation accepts single service
     */
    public function testValidationAcceptsSingleService()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1], // Single service
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test validation accepts many services
     */
    public function testValidationAcceptsManyServices()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], // 10 services
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test validation handles special characters in customer name
     */
    public function testValidationHandlesSpecialCharactersInName()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => "O'Brien-Smith (René)", // Special chars
            'customerEmail' => 'test@example.com',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Test validation handles unicode in customer name
     */
    public function testValidationHandlesUnicodeInName()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateBookingData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'serviceIds' => [1],
            'date' => '2025-12-26',
            'startTime' => '10:00',
            'customerName' => '山田太郎', // Japanese
            'customerEmail' => 'test@example.com',
        ]);

        $this->assertTrue(true);
    }
}
