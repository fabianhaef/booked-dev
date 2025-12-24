<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\records\OAuthStateTokenRecord;
use UnitTester;
use Craft;

/**
 * Tests for OAuth State Token Security (Security Issue 4.1)
 *
 * Validates that secure UUID-based state tokens are used instead of
 * reversible base64-encoded employeeIds, preventing:
 * - Information disclosure (employeeId exposure)
 * - CSRF attacks
 * - Token replay attacks
 */
class OAuthStateTokenSecurityTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _before()
    {
        parent::_before();
        $this->mockDatabase();
    }

    /**
     * Test that state token creation generates secure UUID
     */
    public function testStateTokenIsSecureUUID()
    {
        $record = OAuthStateTokenRecord::createToken(123, 'google');

        $this->assertIsString($record->token);
        $this->assertGreaterThan(30, strlen($record->token), 'Token should be long enough to be secure');

        // UUID pattern: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $record->token,
            'Token should be a valid UUID'
        );
    }

    /**
     * Test that state token CANNOT be reverse-engineered to get employeeId
     */
    public function testStateTokenDoesNotExposeEmployeeId()
    {
        $employeeId = 123;
        $record = OAuthStateTokenRecord::createToken($employeeId, 'google');

        // Token should NOT contain employeeId in any form
        $this->assertStringNotContainsString((string)$employeeId, $record->token);

        // Token should NOT be decodable as base64
        $decoded = base64_decode($record->token, true);
        $this->assertFalse($decoded || !json_decode($decoded), 'Token should not be base64-encoded JSON');
    }

    /**
     * Test that expired tokens are rejected
     */
    public function testExpiredTokensAreRejected()
    {
        // Create token that expired 1 hour ago
        $record = new OAuthStateTokenRecord();
        $record->token = \craft\helpers\StringHelper::UUID();
        $record->employeeId = 123;
        $record->provider = 'google';
        $record->createdAt = (new \DateTime('-2 hours'))->format('Y-m-d H:i:s');
        $record->expiresAt = (new \DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $record->save();

        $result = OAuthStateTokenRecord::verifyAndConsume($record->token);

        $this->assertNull($result, 'Expired token should be rejected');
    }

    /**
     * Test that tokens are one-time use (consumed after verification)
     */
    public function testTokensAreOneTimeUse()
    {
        $record = OAuthStateTokenRecord::createToken(123, 'google');
        $token = $record->token;

        // First verification should succeed
        $result1 = OAuthStateTokenRecord::verifyAndConsume($token);
        $this->assertNotNull($result1);
        $this->assertEquals(123, $result1['employeeId']);
        $this->assertEquals('google', $result1['provider']);

        // Second verification with same token should fail (token consumed)
        $result2 = OAuthStateTokenRecord::verifyAndConsume($token);
        $this->assertNull($result2, 'Token should be consumed after first use');
    }

    /**
     * Test that invalid tokens are rejected
     */
    public function testInvalidTokensAreRejected()
    {
        $invalidTokens = [
            'not-a-uuid',
            base64_encode(json_encode(['employeeId' => 123])), // Old insecure format
            '12345',
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', // Valid UUID but not in database
        ];

        foreach ($invalidTokens as $token) {
            $result = OAuthStateTokenRecord::verifyAndConsume($token);
            $this->assertNull($result, "Invalid token '{$token}' should be rejected");
        }
    }

    /**
     * Test that tokens have 1-hour expiration
     */
    public function testTokensExpireAfterOneHour()
    {
        $record = OAuthStateTokenRecord::createToken(123, 'google');

        $createdAt = new \DateTime($record->createdAt);
        $expiresAt = new \DateTime($record->expiresAt);

        $diff = $createdAt->diff($expiresAt);
        $hoursDifference = ($diff->h) + ($diff->days * 24);

        $this->assertEquals(1, $hoursDifference, 'Token should expire exactly 1 hour after creation');
    }

    /**
     * Test cleanup of expired tokens
     */
    public function testCleanupExpiredTokens()
    {
        // Create 3 tokens: 1 valid, 2 expired
        $validToken = OAuthStateTokenRecord::createToken(1, 'google');

        $expiredToken1 = new OAuthStateTokenRecord();
        $expiredToken1->token = \craft\helpers\StringHelper::UUID();
        $expiredToken1->employeeId = 2;
        $expiredToken1->provider = 'google';
        $expiredToken1->createdAt = (new \DateTime('-2 hours'))->format('Y-m-d H:i:s');
        $expiredToken1->expiresAt = (new \DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $expiredToken1->save();

        $expiredToken2 = new OAuthStateTokenRecord();
        $expiredToken2->token = \craft\helpers\StringHelper::UUID();
        $expiredToken2->employeeId = 3;
        $expiredToken2->provider = 'outlook';
        $expiredToken2->createdAt = (new \DateTime('-3 hours'))->format('Y-m-d H:i:s');
        $expiredToken2->expiresAt = (new \DateTime('-2 hours'))->format('Y-m-d H:i:s');
        $expiredToken2->save();

        // Cleanup expired tokens
        $deletedCount = OAuthStateTokenRecord::cleanupExpired();

        $this->assertEquals(2, $deletedCount, 'Should delete 2 expired tokens');

        // Valid token should still exist
        $result = OAuthStateTokenRecord::verifyAndConsume($validToken->token);
        $this->assertNotNull($result, 'Valid token should not be deleted');
    }

    /**
     * Test that different providers are supported
     */
    public function testMultipleProvidersSupported()
    {
        $googleToken = OAuthStateTokenRecord::createToken(123, 'google');
        $outlookToken = OAuthStateTokenRecord::createToken(456, 'outlook');

        $googleResult = OAuthStateTokenRecord::verifyAndConsume($googleToken->token);
        $this->assertEquals('google', $googleResult['provider']);

        $outlookResult = OAuthStateTokenRecord::verifyAndConsume($outlookToken->token);
        $this->assertEquals('outlook', $outlookResult['provider']);
    }

    /**
     * Test CSRF protection: tokens from different employees don't conflict
     */
    public function testTokensAreEmployeeSpecific()
    {
        $token1 = OAuthStateTokenRecord::createToken(123, 'google');
        $token2 = OAuthStateTokenRecord::createToken(456, 'google');

        // Tokens should be different even for same provider
        $this->assertNotEquals($token1->token, $token2->token);

        // Each token should return correct employeeId
        $result1 = OAuthStateTokenRecord::verifyAndConsume($token1->token);
        $this->assertEquals(123, $result1['employeeId']);

        $result2 = OAuthStateTokenRecord::verifyAndConsume($token2->token);
        $this->assertEquals(456, $result2['employeeId']);
    }

    /**
     * Security test: Ensure old base64 format cannot be used
     */
    public function testOldBase64FormatIsRejected()
    {
        // Simulate old insecure format
        $oldFormatToken = base64_encode(json_encode([
            'employeeId' => 999,
            'provider' => 'google',
        ]));

        $result = OAuthStateTokenRecord::verifyAndConsume($oldFormatToken);

        $this->assertNull($result, 'Old base64-encoded format should be rejected for security');
    }

    /**
     * Mock database for testing
     */
    private function mockDatabase()
    {
        // Mock database connection would go here
        // For now, tests assume database is available
    }
}
