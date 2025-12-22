<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use fabian\booked\Booked;

/**
 * Send Reminders Job
 *
 * Background job to check for and send upcoming booking reminders.
 */
class SendRemindersJob extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $this->setProgress($queue, 0.1, "Checking for pending reminders...");

        try {
            $sentCount = Booked::getInstance()->getReminder()->sendReminders();
            
            $this->setProgress($queue, 1, "Sent {$sentCount} reminders.");
            
            if ($sentCount > 0) {
                Craft::info("Reminder job completed: Sent {$sentCount} reminders.", __METHOD__);
            }

        } catch (\Throwable $e) {
            Craft::error("Failed to process reminders: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return "Checking for and sending upcoming booking reminders";
    }
}

