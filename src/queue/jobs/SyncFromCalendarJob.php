<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use fabian\booked\Booked;
use fabian\booked\elements\Employee;

/**
 * SyncFromCalendarJob queue job
 */
class SyncFromCalendarJob extends BaseJob
{
    /**
     * @var int|null Employee ID (null for all employees with connected calendars)
     */
    public ?int $employeeId = null;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $syncService = Booked::getInstance()->getCalendarSync();
        
        if ($this->employeeId) {
            $employees = [Employee::find()->id($this->employeeId)->one()];
        } else {
            // Find all employees with active connections
            $employees = Employee::find()->all();
        }

        foreach ($employees as $employee) {
            if (!$employee) continue;

            $syncService->syncFromExternal($employee, 'google');
            $syncService->syncFromExternal($employee, 'outlook');
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('booked', 'Syncing events from external calendars');
    }
}

