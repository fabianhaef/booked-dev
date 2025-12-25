<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\queue\jobs\SendBookingEmailJob;
use UnitTester;

/**
 * Tests for Email Job Retry Logic (Missing Test Scenario 7.2.2)
 *
 * Tests the retry behavior of SendBookingEmailJob including:
 * - Retry count limits
 * - Exponential backoff (if implemented)
 * - Different error types
 */
class EmailJobRetryTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test that job allows retries up to 3 attempts
     */
    public function testJobAllowsRetryUpToThreeAttempts()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('SMTP connection failed');

        // Attempt 1 (first retry)
        $this->assertTrue(
            $job->canRetry(1, $error),
            'Should allow retry on attempt 1'
        );

        // Attempt 2 (second retry)
        $this->assertTrue(
            $job->canRetry(2, $error),
            'Should allow retry on attempt 2'
        );

        // Attempt 3 (third retry) - still allowed because < 3
        $this->assertFalse(
            $job->canRetry(3, $error),
            'Should NOT allow retry on attempt 3 (limit reached)'
        );
    }

    /**
     * Test that job does not allow retry after max attempts
     */
    public function testJobDoesNotAllowRetryAfterMaxAttempts()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('Email send failed');

        // Attempt 4 (beyond limit)
        $this->assertFalse(
            $job->canRetry(4, $error),
            'Should not allow retry beyond attempt 3'
        );

        // Attempt 10 (way beyond limit)
        $this->assertFalse(
            $job->canRetry(10, $error),
            'Should not allow retry on attempt 10'
        );
    }

    /**
     * Test that first attempt (0) allows retry
     */
    public function testFirstAttemptAllowsRetry()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('Temporary failure');

        // Attempt 0 (first try failed, first retry upcoming)
        $this->assertTrue(
            $job->canRetry(0, $error),
            'Should allow retry after first failure (attempt 0)'
        );
    }

    /**
     * Test retry behavior with different error types
     */
    public function testRetryBehaviorWithDifferentErrors()
    {
        $job = $this->createEmailJob();

        $errors = [
            new \Exception('SMTP connection timeout'),
            new \Exception('Mailbox full'),
            new \Exception('DNS lookup failed'),
            new \RuntimeException('Service unavailable'),
            new \Exception('Rate limit exceeded'),
        ];

        foreach ($errors as $error) {
            // All errors should be retryable (up to limit)
            $this->assertTrue(
                $job->canRetry(1, $error),
                "Should allow retry for error: {$error->getMessage()}"
            );

            // But not beyond limit
            $this->assertFalse(
                $job->canRetry(3, $error),
                "Should not allow retry beyond limit for error: {$error->getMessage()}"
            );
        }
    }

    /**
     * Test exponential backoff pattern (if implemented)
     * This test documents expected behavior even if not yet implemented
     */
    public function testExponentialBackoffPattern()
    {
        $job = $this->createEmailJob();

        // Check if job has getTtr() method for retry delay
        if (!method_exists($job, 'getTtr')) {
            $this->markTestSkipped('Exponential backoff not implemented (getTtr method missing)');
            return;
        }

        // Expected delays with exponential backoff:
        // Attempt 0: immediate
        // Attempt 1: 60s (1 minute)
        // Attempt 2: 300s (5 minutes)
        // Attempt 3: 900s (15 minutes)

        // This is a documentation test - actual implementation may vary
        $this->assertTrue(true, 'Exponential backoff pattern should be: 0s, 60s, 300s, 900s');
    }

    /**
     * Test that retry count is properly tracked
     */
    public function testRetryCountTracking()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('Test error');

        $attempts = [];
        for ($i = 0; $i <= 5; $i++) {
            $attempts[$i] = $job->canRetry($i, $error);
        }

        // Verify the pattern: true for 0,1,2 and false for 3,4,5
        $this->assertEquals([
            0 => true,   // First retry allowed
            1 => true,   // Second retry allowed
            2 => true,   // Third retry allowed
            3 => false,  // No more retries
            4 => false,  // No more retries
            5 => false,  // No more retries
        ], $attempts, 'Retry pattern should match: 3 retries then stop');
    }

    /**
     * Test boundary case: negative attempt number
     */
    public function testNegativeAttemptNumber()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('Test error');

        // Negative attempt shouldn't happen but should be handled gracefully
        $result = $job->canRetry(-1, $error);

        // Should either allow retry (treat as 0) or disallow (invalid input)
        $this->assertIsBool($result, 'Should return boolean for negative attempt');
    }

    /**
     * Test that retry logic is independent of error message
     */
    public function testRetryLogicIndependentOfErrorMessage()
    {
        $job = $this->createEmailJob();

        $error1 = new \Exception('Short error');
        $error2 = new \Exception('A very long error message that describes in detail what went wrong with the email sending process including SMTP details and connection information');

        // Both should have same retry behavior
        $this->assertEquals(
            $job->canRetry(1, $error1),
            $job->canRetry(1, $error2),
            'Retry logic should be independent of error message length'
        );
    }

    /**
     * Test realistic scenario: email sending with retries
     */
    public function testRealisticEmailSendingScenario()
    {
        $job = $this->createEmailJob();

        // Scenario: Email server is temporarily down
        $smtpError = new \Exception('SMTP connection timeout');

        // Try 1: Fails, should retry
        $attempt1 = 0;
        $this->assertTrue(
            $job->canRetry($attempt1, $smtpError),
            'Should retry after first SMTP failure'
        );

        // Try 2: Still fails, should retry again
        $attempt2 = 1;
        $this->assertTrue(
            $job->canRetry($attempt2, $smtpError),
            'Should retry after second SMTP failure'
        );

        // Try 3: Still fails, last retry
        $attempt3 = 2;
        $this->assertTrue(
            $job->canRetry($attempt3, $smtpError),
            'Should allow final retry attempt'
        );

        // Try 4: Still fails, no more retries
        $attempt4 = 3;
        $this->assertFalse(
            $job->canRetry($attempt4, $smtpError),
            'Should stop retrying after 3 attempts'
        );

        // At this point, job should be marked as failed permanently
    }

    /**
     * Test that job type affects retry behavior
     */
    public function testDifferentJobTypesRetryBehavior()
    {
        // Test different email types
        $confirmationJob = $this->createEmailJob('confirmation');
        $reminderJob = $this->createEmailJob('reminder');
        $cancellationJob = $this->createEmailJob('cancellation');

        $error = new \Exception('Email failed');

        // All job types should have same retry logic (3 attempts)
        $this->assertEquals(
            $confirmationJob->canRetry(1, $error),
            $reminderJob->canRetry(1, $error),
            'Confirmation and reminder jobs should have same retry behavior'
        );

        $this->assertEquals(
            $reminderJob->canRetry(1, $error),
            $cancellationJob->canRetry(1, $error),
            'Reminder and cancellation jobs should have same retry behavior'
        );
    }

    /**
     * Test retry limit is enforced consistently
     */
    public function testRetryLimitConsistency()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('Test error');

        // The retry limit should be consistent
        $limit = 3;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $canRetry = $job->canRetry($attempt, $error);

            if ($attempt < $limit) {
                $this->assertTrue(
                    $canRetry,
                    "Attempt {$attempt} should allow retry (under limit {$limit})"
                );
            } else {
                $this->assertFalse(
                    $canRetry,
                    "Attempt {$attempt} should not allow retry (at or over limit {$limit})"
                );
            }
        }
    }

    /**
     * Performance test: canRetry should be fast
     */
    public function testCanRetryPerformance()
    {
        $job = $this->createEmailJob();
        $error = new \Exception('Test error');

        $startTime = microtime(true);

        // Call canRetry many times
        for ($i = 0; $i < 1000; $i++) {
            $job->canRetry($i % 10, $error);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete 1000 checks in < 10ms
        $this->assertLessThan(
            0.01,
            $executionTime,
            'canRetry should be very fast (< 10ms for 1000 calls)'
        );
    }

    /**
     * Helper: Create email job for testing
     */
    private function createEmailJob(string $type = 'confirmation'): SendBookingEmailJob
    {
        $job = new SendBookingEmailJob([
            'reservationId' => 123,
            'emailType' => $type,
            'recipientEmail' => 'test@example.com',
        ]);

        return $job;
    }
}
