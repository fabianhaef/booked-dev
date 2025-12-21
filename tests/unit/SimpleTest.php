<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use UnitTester;

class SimpleTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testLogic()
    {
        $this->assertTrue(true);
        $this->assertEquals(4, 2 + 2);
    }
}

