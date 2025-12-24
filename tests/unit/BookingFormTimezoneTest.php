<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\models\forms\BookingForm;
use UnitTester;

/**
 * Tests for BookingForm timezone validation
 * Ensures timezone validation prevents security vulnerability (CVE-style issue)
 * where invalid timezone strings could cause DateTime crashes
 */
class BookingFormTimezoneTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test that valid timezones pass validation
     */
    public function testValidTimezonesPassValidation()
    {
        $validTimezones = [
            'UTC',
            'Europe/Zurich',
            'America/New_York',
            'Asia/Tokyo',
            'Australia/Sydney',
            'Europe/London',
            'America/Los_Angeles',
            'Pacific/Auckland',
        ];

        foreach ($validTimezones as $timezone) {
            $form = new BookingForm();
            $form->userTimezone = $timezone;
            $form->serviceId = 1;
            $form->bookingDate = '2025-12-26';
            $form->startTime = '10:00';
            $form->userName = 'Test User';
            $form->userEmail = 'test@example.com';

            $form->validate(['userTimezone']);

            $this->assertFalse(
                $form->hasErrors('userTimezone'),
                "Valid timezone '{$timezone}' should pass validation"
            );
        }
    }

    /**
     * Test that invalid timezones fail validation
     */
    public function testInvalidTimezonesFailValidation()
    {
        $invalidTimezones = [
            'Invalid/Timezone',
            'Europe/NotACity',
            'UTC+5', // Offset format not valid
            'GMT+1', // GMT offsets not in listIdentifiers()
            'PST', // Abbreviations not valid
            'EST',
            'CET',
            '../../../etc/passwd', // Path traversal attempt
            'America/New York', // Space instead of underscore
            'europe/zurich', // Wrong case
            'America\New_York', // Wrong separator
        ];

        foreach ($invalidTimezones as $timezone) {
            $form = new BookingForm();
            $form->userTimezone = $timezone;
            $form->serviceId = 1;
            $form->bookingDate = '2025-12-26';
            $form->startTime = '10:00';
            $form->userName = 'Test User';
            $form->userEmail = 'test@example.com';

            $form->validate(['userTimezone']);

            $this->assertTrue(
                $form->hasErrors('userTimezone'),
                "Invalid timezone '{$timezone}' should fail validation"
            );

            $errors = $form->getErrors('userTimezone');
            $this->assertStringContainsString(
                'Invalid timezone',
                $errors[0],
                "Error message should mention invalid timezone"
            );
        }
    }

    /**
     * Test that empty/null timezone is allowed (will use system default)
     */
    public function testEmptyTimezoneIsAllowed()
    {
        $form = new BookingForm();
        $form->userTimezone = '';
        $form->serviceId = 1;
        $form->bookingDate = '2025-12-26';
        $form->startTime = '10:00';
        $form->userName = 'Test User';
        $form->userEmail = 'test@example.com';

        $form->validate(['userTimezone']);

        $this->assertFalse(
            $form->hasErrors('userTimezone'),
            'Empty timezone should be allowed (uses system default)'
        );

        // Test null
        $form2 = new BookingForm();
        $form2->userTimezone = null;
        $form2->serviceId = 1;
        $form2->bookingDate = '2025-12-26';
        $form2->startTime = '10:00';
        $form2->userName = 'Test User';
        $form2->userEmail = 'test@example.com';

        $form2->validate(['userTimezone']);

        $this->assertFalse(
            $form2->hasErrors('userTimezone'),
            'Null timezone should be allowed (uses system default)'
        );
    }

    /**
     * Test that all PHP timezone identifiers are accepted
     */
    public function testAllPhpTimezonesAreAccepted()
    {
        $allTimezones = \DateTimeZone::listIdentifiers();

        // Test a sample of all available timezones (testing all ~400 would be slow)
        $sampleSize = min(50, count($allTimezones));
        $sample = array_rand(array_flip($allTimezones), $sampleSize);

        if (!is_array($sample)) {
            $sample = [$sample];
        }

        foreach ($sample as $timezone) {
            $form = new BookingForm();
            $form->userTimezone = $timezone;
            $form->serviceId = 1;
            $form->bookingDate = '2025-12-26';
            $form->startTime = '10:00';
            $form->userName = 'Test User';
            $form->userEmail = 'test@example.com';

            $form->validate(['userTimezone']);

            $this->assertFalse(
                $form->hasErrors('userTimezone'),
                "PHP timezone '{$timezone}' from listIdentifiers() should be accepted"
            );
        }
    }

    /**
     * Test that timezone validation prevents DateTime crashes
     * This test documents the security issue we're preventing
     */
    public function testTimezoneValidationPreventsDateTimeCrashes()
    {
        // Before fix: invalid timezone could crash DateTime constructor
        // After fix: validation prevents invalid timezones from reaching DateTime

        $form = new BookingForm();
        $form->userTimezone = 'INVALID_TIMEZONE_THAT_WOULD_CRASH';
        $form->serviceId = 1;
        $form->bookingDate = '2025-12-26';
        $form->startTime = '10:00';
        $form->userName = 'Test User';
        $form->userEmail = 'test@example.com';

        // Validate should catch the invalid timezone
        $isValid = $form->validate();

        $this->assertFalse($isValid, 'Form with invalid timezone should not validate');
        $this->assertTrue($form->hasErrors('userTimezone'), 'Should have timezone error');

        // If we tried to create DateTime with this timezone before validation,
        // it would throw: Exception: DateTimeZone::__construct(): Unknown or bad timezone
        // Now validation prevents this from ever happening
    }

    /**
     * Test case sensitivity of timezone validation
     */
    public function testTimezoneValidationIsCaseSensitive()
    {
        // PHP timezones are case-sensitive
        $form = new BookingForm();
        $form->userTimezone = 'europe/zurich'; // lowercase
        $form->serviceId = 1;
        $form->bookingDate = '2025-12-26';
        $form->startTime = '10:00';
        $form->userName = 'Test User';
        $form->userEmail = 'test@example.com';

        $form->validate(['userTimezone']);

        $this->assertTrue(
            $form->hasErrors('userTimezone'),
            'Lowercase timezone should fail validation (case-sensitive)'
        );

        // Correct case should pass
        $form2 = new BookingForm();
        $form2->userTimezone = 'Europe/Zurich'; // correct case
        $form2->serviceId = 1;
        $form2->bookingDate = '2025-12-26';
        $form2->startTime = '10:00';
        $form2->userName = 'Test User';
        $form2->userEmail = 'test@example.com';

        $form2->validate(['userTimezone']);

        $this->assertFalse(
            $form2->hasErrors('userTimezone'),
            'Correct case timezone should pass validation'
        );
    }

    /**
     * Test that SQL injection attempts are blocked
     */
    public function testSqlInjectionAttemptsAreBlocked()
    {
        $sqlInjectionAttempts = [
            "'; DROP TABLE bookings_reservations; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM users--",
        ];

        foreach ($sqlInjectionAttempts as $maliciousInput) {
            $form = new BookingForm();
            $form->userTimezone = $maliciousInput;
            $form->serviceId = 1;
            $form->bookingDate = '2025-12-26';
            $form->startTime = '10:00';
            $form->userName = 'Test User';
            $form->userEmail = 'test@example.com';

            $form->validate(['userTimezone']);

            $this->assertTrue(
                $form->hasErrors('userTimezone'),
                "SQL injection attempt '{$maliciousInput}' should be blocked"
            );
        }
    }

    /**
     * Test that path traversal attempts are blocked
     */
    public function testPathTraversalAttemptsAreBlocked()
    {
        $pathTraversalAttempts = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32\\config\\sam',
            '/etc/passwd',
            'C:\\Windows\\System32',
            '....//....//....//etc/passwd',
        ];

        foreach ($pathTraversalAttempts as $maliciousInput) {
            $form = new BookingForm();
            $form->userTimezone = $maliciousInput;
            $form->serviceId = 1;
            $form->bookingDate = '2025-12-26';
            $form->startTime = '10:00';
            $form->userName = 'Test User';
            $form->userEmail = 'test@example.com';

            $form->validate(['userTimezone']);

            $this->assertTrue(
                $form->hasErrors('userTimezone'),
                "Path traversal attempt '{$maliciousInput}' should be blocked"
            );
        }
    }

    /**
     * Test common timezone edge cases
     */
    public function testCommonTimezoneEdgeCases()
    {
        $edgeCases = [
            ['timezone' => 'UTC', 'shouldPass' => true],
            ['timezone' => 'GMT', 'shouldPass' => true], // GMT is valid
            ['timezone' => 'Etc/GMT', 'shouldPass' => true],
            ['timezone' => 'Etc/GMT+5', 'shouldPass' => true], // Etc/GMT offsets are valid
            ['timezone' => 'Etc/GMT-5', 'shouldPass' => true],
            ['timezone' => 'GMT+5', 'shouldPass' => false], // But GMT+5 without Etc/ is not
            ['timezone' => 'UTC+5', 'shouldPass' => false],
            ['timezone' => 'America/Argentina/Buenos_Aires', 'shouldPass' => true], // Multi-level
            ['timezone' => 'Pacific/Port_Moresby', 'shouldPass' => true],
        ];

        foreach ($edgeCases as $testCase) {
            $form = new BookingForm();
            $form->userTimezone = $testCase['timezone'];
            $form->serviceId = 1;
            $form->bookingDate = '2025-12-26';
            $form->startTime = '10:00';
            $form->userName = 'Test User';
            $form->userEmail = 'test@example.com';

            $form->validate(['userTimezone']);

            if ($testCase['shouldPass']) {
                $this->assertFalse(
                    $form->hasErrors('userTimezone'),
                    "Timezone '{$testCase['timezone']}' should pass validation"
                );
            } else {
                $this->assertTrue(
                    $form->hasErrors('userTimezone'),
                    "Timezone '{$testCase['timezone']}' should fail validation"
                );
            }
        }
    }
}
