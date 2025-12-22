<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\records\SoftLockRecord;
use fabian\booked\models\SoftLock;
use DateTime;
use craft\helpers\Db;
use yii\db\ActiveQuery;

/**
 * SoftLockService
 */
class SoftLockService extends Component
{
    /**
     * Create a soft lock for a slot
     * 
     * @param array $data
     * @param int $durationMinutes
     * @return string|false Token if successful
     */
    public function createLock(array $data, int $durationMinutes = 15): string|false
    {
        // Check if already locked
        if ($this->isLocked($data['date'], $data['startTime'], $data['serviceId'], $data['employeeId'] ?? null)) {
            return false;
        }

        $token = bin2hex(random_bytes(16));
        $expiresAt = (new DateTime())->modify("+{$durationMinutes} minutes");

        $record = $this->createRecord();
        $record->token = $token;
        $record->serviceId = $data['serviceId'];
        $record->employeeId = $data['employeeId'] ?? null;
        $record->locationId = $data['locationId'] ?? null;
        $record->date = $data['date'];
        $record->startTime = $data['startTime'];
        $record->endTime = $data['endTime'];
        $record->expiresAt = $expiresAt->format('Y-m-d H:i:s');

        if (!$this->saveRecord($record)) {
            return false;
        }

        return $token;
    }

    /**
     * Check if a slot is currently locked
     * 
     * @param string $date
     * @param string $startTime
     * @param int $serviceId
     * @param int|null $employeeId
     * @return bool
     */
    public function isLocked(string $date, string $startTime, int $serviceId, ?int $employeeId = null): bool
    {
        $query = $this->getRecordQuery()
            ->where([
                'date' => $date,
                'startTime' => $startTime,
                'serviceId' => $serviceId,
            ])
            ->andWhere(['>', 'expiresAt', Db::prepareDateForDb(new DateTime())]);

        if ($employeeId !== null) {
            $query->andWhere(['employeeId' => $employeeId]);
        }

        return $query->exists();
    }

    /**
     * Release a lock by token
     * 
     * @param string $token
     * @return bool
     */
    public function releaseLock(string $token): bool
    {
        $record = $this->getRecordByToken($token);
        if ($record) {
            return (bool)$this->deleteRecord($record);
        }
        return false;
    }

    /**
     * Cleanup expired locks
     * 
     * @return int Number of locks deleted
     */
    public function cleanupExpiredLocks(): int
    {
        return $this->deleteExpiredRecords();
    }

    /**
     * Protected helper to create record (for testing/mocking)
     */
    protected function createRecord()
    {
        return new SoftLockRecord();
    }

    /**
     * Protected helper to get record query (for testing/mocking)
     */
    protected function getRecordQuery(): ActiveQuery
    {
        return SoftLockRecord::find();
    }

    /**
     * Protected helper to get record by token (for testing/mocking)
     */
    protected function getRecordByToken(string $token)
    {
        return SoftLockRecord::findOne(['token' => $token]);
    }

    /**
     * Protected helper to save record (for testing/mocking)
     */
    protected function saveRecord($record): bool
    {
        return $record->save();
    }

    /**
     * Protected helper to delete record (for testing/mocking)
     */
    protected function deleteRecord($record): int
    {
        return $record->delete();
    }

    /**
     * Protected helper to delete expired records (for testing/mocking)
     */
    protected function deleteExpiredRecords(): int
    {
        return SoftLockRecord::deleteAll(['<=', 'expiresAt', Db::prepareDateForDb(new DateTime())]);
    }
}
