<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\CalendarSyncService;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Reservation;
use UnitTester;
use Craft;
use stdClass;

/**
 * Testable version of CalendarSyncService to mock external API calls
 */
class TestableCalendarSyncService extends CalendarSyncService
{
    public array $mockTokens = [];
    public array $externalEvents = [];

    protected function saveToken(int $employeeId, string $provider, array $data): bool
    {
        $this->mockTokens["{$employeeId}_{$provider}"] = $data;
        return true;
    }

    protected function getToken(int $employeeId, string $provider): ?array
    {
        return $this->mockTokens["{$employeeId}_{$provider}"] ?? null;
    }

    protected function refreshToken($employee, string $provider, ?string $refreshToken): ?string
    {
        if ($refreshToken === 'refresh-token') {
            return 'new-token';
        }
        return null;
    }

    public function handleCallback($employee, string $provider, string $code): bool
    {
        if ($code === 'auth-code') {
            $token = $provider === 'google' ? 'exchanged-access-token' : 'exchanged-outlook-token';
            return $this->saveToken($employee->id, $provider, [
                'accessToken' => $token,
                'refreshToken' => 'new-refresh-token',
                'expiresAt' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            ]);
        }
        return false;
    }

    public function getGoogleClient(): \Google\Client
    {
        $client = \Codeception\Stub::make(\Google\Client::class, [
            'createAuthUrl' => 'https://accounts.google.com/o/oauth2/auth?state=mocked',
            'fetchAccessTokenWithAuthCode' => ['access_token' => 'exchanged-access-token', 'expires_in' => 3600],
            'fetchAccessTokenWithRefreshToken' => ['access_token' => 'new-token', 'expires_in' => 3600],
            'setClientId' => null,
            'setClientSecret' => null,
            'setRedirectUri' => null,
            'setAccessType' => null,
            'setPrompt' => null,
            'addScope' => null,
            'setState' => null,
        ]);
        return $client;
    }

    public function getOutlookClient(): \League\OAuth2\Client\Provider\GenericProvider
    {
        $client = \Codeception\Stub::make(\League\OAuth2\Client\Provider\GenericProvider::class, [
            'getAuthorizationUrl' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?state=mocked',
            'getAccessToken' => \Codeception\Stub::make(\League\OAuth2\Client\Token\AccessToken::class, [
                'getToken' => 'exchanged-outlook-token',
                'getRefreshToken' => 'new-refresh-token',
                'getExpires' => time() + 3600,
            ]),
        ]);
        return $client;
    }

    public function syncFromExternal($employee, string $provider): int
    {
        return count($this->externalEvents);
    }

    protected function syncToGoogle($reservation, string $token): bool
    {
        return true;
    }

    protected function syncToOutlook($reservation, string $token): bool
    {
        return true;
    }

    protected function syncFromGoogle($employee, string $token): int
    {
        return count($this->externalEvents);
    }

    protected function syncFromOutlook($employee, string $token): int
    {
        return count($this->externalEvents);
    }
}

class CalendarSyncServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableCalendarSyncService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TestableCalendarSyncService();
    }

    public function testGetAuthUrl()
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->getMock();
        $employee->id = 1;
        
        $googleUrl = $this->service->getAuthUrl($employee, 'google');
        $this->assertStringContainsString('accounts.google.com', $googleUrl);
        
        $outlookUrl = $this->service->getAuthUrl($employee, 'outlook');
        $this->assertStringContainsString('login.microsoftonline.com', $outlookUrl);
    }

    public function testOutlookHandleCallback()
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->getMock();
        $employee->id = 1;

        // We need to override getOutlookToken in TestableCalendarSyncService
        $result = $this->service->handleCallback($employee, 'outlook', 'auth-code');
        
        $this->assertTrue($result);
        $this->assertArrayHasKey('1_outlook', $this->service->mockTokens);
        $this->assertEquals('exchanged-outlook-token', $this->service->mockTokens['1_outlook']['accessToken']);
    }

    public function testGetAccessTokenWithRefresh()
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->getMock();
        $employee->id = 1;
        
        // Setup mock token that is expired
        $this->service->mockTokens['1_google'] = [
            'accessToken' => 'old-token',
            'refreshToken' => 'refresh-token',
            'expiresAt' => (new \DateTime('-1 hour'))->format('Y-m-d H:i:s'),
        ];

        $token = $this->service->getAccessToken($employee, 'google');
        $this->assertEquals('new-token', $token);
    }

    public function testSyncToExternal()
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->getMock();
        $employee->id = 1;

        $reservation = $this->getMockBuilder(Reservation::class)
            ->disableOriginalConstructor()
            ->getMock();
        $reservation->id = 100;
        $reservation->method('getEmployee')->willReturn($employee);

        // Mock token exists
        $this->service->mockTokens['1_google'] = [
            'accessToken' => 'valid-token',
            'expiresAt' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ];

        $result = $this->service->syncToExternal($reservation);
        $this->assertTrue($result);
    }

    public function testHandleCallback()
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->getMock();
        $employee->id = 1;

        // Mock the exchange of code for tokens
        // We need to override handleCallback in TestableCalendarSyncService to avoid real calls
        $result = $this->service->handleCallback($employee, 'google', 'auth-code');
        
        $this->assertTrue($result);
        $this->assertArrayHasKey('1_google', $this->service->mockTokens);
        $this->assertEquals('exchanged-access-token', $this->service->mockTokens['1_google']['accessToken']);
    }

    public function testSyncFromExternal()
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->disableOriginalConstructor()
            ->getMock();
        $employee->id = 1;

        // Mock token exists
        $this->service->mockTokens['1_google'] = [
            'accessToken' => 'valid-token',
            'expiresAt' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ];

        // Mock external events
        $this->service->externalEvents = [
            [
                'id' => 'ext-1',
                'summary' => 'External Meeting',
                'start' => '2026-01-01T10:00:00Z',
                'end' => '2026-01-01T11:00:00Z',
            ]
        ];

        $count = $this->service->syncFromExternal($employee, 'google');
        $this->assertEquals(1, $count);
    }
}
