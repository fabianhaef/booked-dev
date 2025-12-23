<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\BlackoutDateService;
use UnitTester;

/**
 * Mock Query class for Yii2 ActiveQuery
 */
class MockQuery {
    public $where = [];
    public $andWhere = [];
    public $existsResult = false;
    
    public function where($condition) {
        $this->where = $condition;
        return $this;
    }
    public function andWhere($condition) {
        $this->andWhere[] = $condition;
        return $this;
    }
    public function exists() {
        return $this->existsResult;
    }
}

/**
 * Testable version of BlackoutDateService
 */
class TestableBlackoutDateService extends BlackoutDateService {
    public $mockQuery;
    protected function getBlackoutQuery() {
        return $this->mockQuery;
    }
}

class BlackoutDateServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableBlackoutDateService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        $this->service = new TestableBlackoutDateService();
        $this->service->mockQuery = new MockQuery();
    }

    public function testIsDateBlackedOut()
    {
        $date = '2025-12-25';
        
        // Case 1: Date is blacked out
        $this->service->mockQuery->existsResult = true;
        $this->assertTrue($this->service->isDateBlackedOut($date));
        
        // Check if query was built correctly
        $this->assertEquals(['isActive' => true], $this->service->mockQuery->where);
        $this->assertCount(3, $this->service->mockQuery->andWhere);
        $this->assertEquals(['<=', 'startDate', $date], $this->service->mockQuery->andWhere[0]);
        $this->assertEquals(['>=', 'endDate', $date], $this->service->mockQuery->andWhere[1]);
        $this->assertEquals(['locationId' => null, 'employeeId' => null], $this->service->mockQuery->andWhere[2]);

        // Case 2: Date is NOT blacked out
        $this->service->mockQuery->existsResult = false;
        $this->assertFalse($this->service->isDateBlackedOut($date));
    }
}

