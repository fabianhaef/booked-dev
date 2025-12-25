<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;

/**
 * Batch Send Reminder Emails Job
 *
 * Processes multiple reminder emails in a single queue job.
 * More efficient than creating individual jobs for each email.
 */
class BatchSendReminderEmailsJob extends BaseJob
{
    /**
     * @var array Reservation IDs to send reminders for
     */
    public array $reservationIds = [];

    /**
     * @var string Reminder type: '24h' or '1h'
     */
    public string $reminderType = '24h';

    /**
     * @var int Batch size for processing (to limit memory usage)
     */
    public int $batchSize = 100;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $total = count($this->reservationIds);

        if ($total === 0) {
            Craft::warning("No reservation IDs provided for batch reminder emails", __METHOD__);
            return;
        }

        Craft::info("Starting batch reminder email send: {$total} reservations ({$this->reminderType})", __METHOD__);

        $successCount = 0;
        $failureCount = 0;

        // Process in chunks to limit memory usage
        $chunks = array_chunk($this->reservationIds, $this->batchSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->setProgress(
                $queue,
                $chunkIndex / $totalChunks,
                "Processing chunk " . ($chunkIndex + 1) . " of {$totalChunks}"
            );

            // Fetch reservations for this chunk
            $reservations = Reservation::find()
                ->id($chunk)
                ->status(['confirmed', 'pending'])
                ->all();

            foreach ($reservations as $reservation) {
                try {
                    // Queue individual email job with high priority
                    $emailType = "reminder_{$this->reminderType}";

                    Craft::$app->queue->priority(1024)->push(new SendBookingEmailJob([
                        'reservationId' => $reservation->id,
                        'emailType' => $emailType,
                    ]));

                    $successCount++;

                } catch (\Throwable $e) {
                    $failureCount++;
                    Craft::error(
                        "Failed to queue reminder email for reservation #{$reservation->id}: " . $e->getMessage(),
                        __METHOD__
                    );
                }
            }

            // Free memory after each chunk
            unset($reservations);
            gc_collect_cycles();
        }

        $this->setProgress($queue, 1, "Complete: {$successCount} queued, {$failureCount} failed");

        Craft::info(
            "Batch reminder email job complete: {$successCount} queued, {$failureCount} failed ({$this->reminderType})",
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        $count = count($this->reservationIds);
        return "Sending {$this->reminderType} reminders for {$count} bookings";
    }

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        // Time To Reserve: 5 minutes for large batches
        return 300;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        // Retry once for batch jobs
        return $attempt < 2;
    }
}
