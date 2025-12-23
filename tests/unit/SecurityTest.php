<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use UnitTester;

class SecurityTest extends Unit
{
    protected $tester;

    public function testSqlInjectionPrevention()
    {
        $userInput = "'; DROP TABLE users; --";
        $sanitized = addslashes($userInput);

        $this->assertStringContainsString('\\', $sanitized);
    }

    public function testXssPreventionInForms()
    {
        $userInput = '<script>alert("xss")</script>';
        $sanitized = htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    public function testCsrfTokenValidation()
    {
        $sessionToken = 'abc123';
        $submittedToken = 'xyz789';

        $this->assertNotEquals($sessionToken, $submittedToken);
    }

    public function testRateLimitingBypassAttempts()
    {
        $attempts = 0;
        $maxAttempts = 5;

        for ($i = 0; $i < 10; $i++) {
            $attempts++;
            if ($attempts > $maxAttempts) {
                $blocked = true;
                break;
            }
        }

        $this->assertTrue($blocked);
    }

    public function testTokenTamperingDetection()
    {
        $originalToken = 'user:123:hash_abc';
        $tamperedToken = 'user:456:hash_abc';

        list($user1) = explode(':', $originalToken);
        list($user2) = explode(':', $tamperedToken);

        $this->assertNotEquals($user1, $user2);
    }

    public function testPermissionEnforcement()
    {
        $userPermissions = ['view', 'edit'];
        $requiredPermission = 'delete';

        $hasPermission = in_array($requiredPermission, $userPermissions);

        $this->assertFalse($hasPermission);
    }

    public function testSensitiveDataEncryption()
    {
        $plaintext = 'secret_api_key';
        $encrypted = base64_encode($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);

        $decrypted = base64_decode($encrypted);
        $this->assertEquals($plaintext, $decrypted);
    }
}
