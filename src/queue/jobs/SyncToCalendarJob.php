<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use fabian\booked\Booked;

/**
 * SyncToCalendarJob queue job
 */
class SyncToCalendarJob extends BaseJob
{
    /**
     * @var int Reservation ID
     */
    public int $reservationId;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $reservation = Booked::getInstance()->getBooking()->getReservationById($this->reservationId);
        if (!$reservation) {
            return;
        }

        Booked::getInstance()->getCalendarSync()->syncToExternal($reservation);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('booked', 'Syncing booking to external calendar');
    }
}

