<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use fabian\booked\Booked;

/**
 * CleanupSoftLocksJob queue job
 */
class CleanupSoftLocksJob extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $count = Booked::getInstance()->getSoftLock()->cleanupExpiredLocks();
        
        if ($count > 0) {
            Craft::info(
                Craft::t('booked', 'Cleaned up {count} expired soft locks.', ['count' => $count]),
                __METHOD__
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('booked', 'Cleaning up expired soft locks');
    }
}

