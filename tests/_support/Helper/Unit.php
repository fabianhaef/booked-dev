<?php

namespace fabian\booked\tests\_support\Helper;

use Codeception\Module;

/**
 * Unit Test Helper
 *
 * Provides utilities for unit testing without database dependencies
 */
class Unit extends Module
{
    /**
     * Create a mock DateTime for testing
     *
     * @param string $time Time string (e.g., '2025-12-25 10:00:00')
     * @return \DateTime
     */
    public function createDateTime(string $time): \DateTime
    {
        return new \DateTime($time);
    }

    /**
     * Assert two times are equal (ignoring seconds if not provided)
     *
     * @param string $expected Expected time (H:i or H:i:s)
     * @param string $actual Actual time (H:i or H:i:s)
     * @param string $message Optional assertion message
     */
    public function assertTimeEquals(string $expected, string $actual, string $message = '')
    {
        $expectedParts = explode(':', $expected);
        $actualParts = explode(':', $actual);

        // Normalize to H:i format if seconds not provided
        $expectedNormalized = sprintf('%02d:%02d', (int)$expectedParts[0], (int)$expectedParts[1]);
        $actualNormalized = sprintf('%02d:%02d', (int)$actualParts[0], (int)$actualParts[1]);

        $this->assertEquals($expectedNormalized, $actualNormalized, $message);
    }

    /**
     * Assert array contains expected keys
     *
     * @param array $expectedKeys Keys that must be present
     * @param array $array Array to check
     * @param string $message Optional assertion message
     */
    public function assertArrayHasKeys(array $expectedKeys, array $array, string $message = '')
    {
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should contain key: $key");
        }
    }
}
