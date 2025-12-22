<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\SoftLockService;
use UnitTester;
use DateTime;
use yii\db\ActiveQuery;
use stdClass;

/**
 * Mock ActiveQuery
 */
class MockSoftLockQuery extends ActiveQuery
{
    public $mockRecords = [];
    public $where = [];

    public function __construct($records)
    {
        // Don't call parent constructor to avoid DB issues
        $this->mockRecords = $records;
    }

    public function where($condition, $params = [])
    {
        if (is_array($condition)) {
            $this->where = array_merge($this->where, $condition);
        }
        return $this;
    }

    public function andWhere($condition, $params = [])
    {
        if (is_array($condition)) {
            $this->where = array_merge($this->where, $condition);
        }
        return $this;
    }

    public function exists($db = null)
    {
        foreach ($this->mockRecords as $record) {
            $match = true;
            foreach ($this->where as $key => $value) {
                if ($key === 'date' && $record->date !== $value) $match = false;
                if ($key === 'startTime' && $record->startTime !== $value) $match = false;
                if ($key === 'serviceId' && $record->serviceId !== $value) $match = false;
                if ($key === 'employeeId' && ($record->employeeId ?? null) !== $value) $match = false;
                // Simplified expiresAt check for testing
            }
            if ($match) {
                $expiresAt = new DateTime($record->expiresAt);
                if ($expiresAt > new DateTime()) {
                    return true;
                }
            }
        }
        return false;
    }
}

/**
 * Testable version of SoftLockService
 */
class TestableSoftLockService extends SoftLockService
{
    public $mockRecords = [];

    protected function createRecord(): object
    {
        return new stdClass();
    }

    protected function getRecordQuery(): ActiveQuery
    {
        return new MockSoftLockQuery($this->mockRecords);
    }

    protected function getRecordByToken(string $token): ?object
    {
        return $this->mockRecords[$token] ?? null;
    }

    protected function saveRecord($record): bool
    {
        if (!isset($record->token)) {
            $record->token = bin2hex(random_bytes(16));
        }
        $this->mockRecords[$record->token] = $record;
        return true;
    }

    protected function deleteRecord($record): int
    {
        if (isset($this->mockRecords[$record->token])) {
            unset($this->mockRecords[$record->token]);
            return 1;
        }
        return 0;
    }

    protected function deleteExpiredRecords(): int
    {
        $count = 0;
        $now = new DateTime();
        foreach ($this->mockRecords as $token => $record) {
            $expiresAt = new DateTime($record->expiresAt);
            if ($expiresAt <= $now) {
                unset($this->mockRecords[$token]);
                $count++;
            }
        }
        return $count;
    }
}

class SoftLockServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableSoftLockService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TestableSoftLockService();
    }

    public function testCreateLock()
    {
        $data = [
            'serviceId' => 1,
            'employeeId' => 2,
            'date' => '2026-01-01',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ];

        $token = $this->service->createLock($data);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertTrue($this->service->isLocked('2026-01-01', '10:00', 1, 2));
    }

    public function testCannotDoubleLock()
    {
        $data = [
            'serviceId' => 1,
            'employeeId' => 2,
            'date' => '2026-01-01',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ];

        $token1 = $this->service->createLock($data);
        $this->assertIsString($token1);

        $token2 = $this->service->createLock($data);
        $this->assertFalse($token2);
    }

    public function testReleaseLock()
    {
        $data = [
            'serviceId' => 1,
            'date' => '2026-01-01',
            'startTime' => '10:00',
            'endTime' => '11:00',
        ];

        $token = $this->service->createLock($data);
        $this->assertTrue($this->service->isLocked('2026-01-01', '10:00', 1));
        
        $result = $this->service->releaseLock($token);
        $this->assertTrue($result);
        $this->assertFalse($this->service->isLocked('2026-01-01', '10:00', 1));
    }

    public function testCleanupExpiredLocks()
    {
        $expiredRecord = new stdClass();
        $expiredRecord->token = 'expired-token';
        $expiredRecord->expiresAt = (new DateTime('-1 minute'))->format('Y-m-d H:i:s');
        $this->service->mockRecords['expired-token'] = $expiredRecord;

        $validRecord = new stdClass();
        $validRecord->token = 'valid-token';
        $validRecord->expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');
        $this->service->mockRecords['valid-token'] = $validRecord;

        $count = $this->service->cleanupExpiredLocks();
        
        $this->assertEquals(1, $count);
        $this->assertArrayNotHasKey('expired-token', $this->service->mockRecords);
        $this->assertArrayHasKey('valid-token', $this->service->mockRecords);
    }
}
