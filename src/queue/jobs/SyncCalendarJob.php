<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;

/**
 * Sync Calendar Job
 *
 * Background job for syncing reservations to external calendars (Google, Outlook).
 * Runs async to prevent blocking booking creation.
 */
class SyncCalendarJob extends BaseJob
{
    /**
     * @var int Reservation ID
     */
    public int $reservationId;

    /**
     * @var string Sync action: 'create', 'update', 'delete'
     */
    public string $action = 'create';

    /**
     * @var int Current attempt number (for tracking)
     */
    public int $attempt = 1;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $reservation = $this->getReservation();

        if (!$reservation) {
            Craft::error("Cannot sync calendar: Reservation #{$this->reservationId} not found", __METHOD__);
            return;
        }

        $this->setProgress($queue, 0.1, "Preparing calendar sync");

        try {
            $calendarSyncService = Booked::getInstance()->calendarSync;

            switch ($this->action) {
                case 'create':
                    $this->setProgress($queue, 0.3, "Creating calendar event");
                    $calendarSyncService->createEvent($reservation);
                    break;

                case 'update':
                    $this->setProgress($queue, 0.3, "Updating calendar event");
                    $calendarSyncService->updateEvent($reservation);
                    break;

                case 'delete':
                    $this->setProgress($queue, 0.3, "Deleting calendar event");
                    $calendarSyncService->deleteEvent($reservation);
                    break;

                default:
                    throw new \Exception("Unknown calendar sync action: {$this->action}");
            }

            $this->setProgress($queue, 0.9, "Calendar sync successful");

            Craft::info("Calendar sync successful: {$this->action} for reservation #{$this->reservationId} (Attempt {$this->attempt})", __METHOD__);

            $this->setProgress($queue, 1, "Complete");

        } catch (\Throwable $e) {
            Craft::error(
                "Failed to sync calendar ({$this->action}) for reservation #{$this->reservationId} (Attempt {$this->attempt}): " . $e->getMessage(),
                __METHOD__
            );

            // Re-throw to trigger queue retry logic
            throw $e;
        }
    }

    /**
     * Get reservation by ID
     */
    private function getReservation(): ?Reservation
    {
        return Reservation::find()
            ->id($this->reservationId)
            ->status(null)
            ->one();
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return "Syncing calendar ({$this->action}) for booking #{$this->reservationId}";
    }

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        // Time To Reserve: 90 seconds for calendar API calls
        return 90;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        // Retry up to 3 times for calendar sync failures
        return $attempt < 3;
    }
}
