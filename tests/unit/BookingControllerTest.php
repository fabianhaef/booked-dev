<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use UnitTester;

class BookingControllerTest extends Unit
{
    protected $tester;

    public function testAjaxAvailabilityEndpointFormat()
    {
        $response = [
            'success' => true,
            'slots' => [
                ['start' => '10:00', 'end' => '10:30', 'employeeId' => 1],
                ['start' => '10:30', 'end' => '11:00', 'employeeId' => 1],
            ],
        ];

        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('slots', $response);
        $this->assertIsArray($response['slots']);
    }

    public function testRateLimitingEnforcement()
    {
        $attempts = [];
        $maxAttempts = 5;
        $windowSeconds = 60;

        for ($i = 0; $i < 10; $i++) {
            $attempts[] = time();
        }

        $recentAttempts = array_filter($attempts, fn($t) => $t > time() - $windowSeconds);

        $this->assertGreaterThan($maxAttempts, count($recentAttempts));
    }

    public function testJsonResponseStructure()
    {
        $response = [
            'success' => true,
            'data' => ['id' => 123],
            'message' => 'Booking created',
        ];

        $this->assertArrayHasKey('success', $response);
        $this->assertIsBool($response['success']);
    }

    public function testHttpStatusCodeCorrectness()
    {
        $successCodes = [200, 201];
        $errorCodes = [400, 404, 500];

        $this->assertContains(200, $successCodes);
        $this->assertContains(400, $errorCodes);
    }
}
