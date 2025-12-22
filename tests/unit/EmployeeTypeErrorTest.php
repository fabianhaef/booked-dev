<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\elements\Employee;
use UnitTester;

/**
 * A testable version of Employee that doesn't trigger Craft element initialization
 */
class TestableEmployee extends Employee
{
    public function __construct()
    {
        // Do nothing
    }
}

class EmployeeTypeErrorTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testSetServiceIdsWithArray()
    {
        $employee = new TestableEmployee();
        $employee->setServiceIds([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $employee->getServiceIds());
    }

    public function testSetServiceIdsWithEmptyString()
    {
        $employee = new TestableEmployee();
        
        // This should NOT throw a TypeError anymore
        $employee->setServiceIds("");
        $this->assertEquals([], $employee->getServiceIds());
    }
}
