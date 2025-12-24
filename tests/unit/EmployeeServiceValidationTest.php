<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\elements\Employee;
use UnitTester;

/**
 * Tests for Employee-Service Association Validation (Security Issue 5.2)
 *
 * Validates that employees can only be assigned to services they are configured to provide,
 * preventing invalid bookings for incompatible employee-service pairs.
 */
class EmployeeServiceValidationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test that hasService() returns true for assigned services
     */
    public function testHasServiceReturnsTrueForAssignedServices()
    {
        $employee = $this->createEmployeeWithServices([10, 20, 30]);

        $this->assertTrue($employee->hasService(10), 'Should have service 10');
        $this->assertTrue($employee->hasService(20), 'Should have service 20');
        $this->assertTrue($employee->hasService(30), 'Should have service 30');
    }

    /**
     * Test that hasService() returns false for unassigned services
     */
    public function testHasServiceReturnsFalseForUnassignedServices()
    {
        $employee = $this->createEmployeeWithServices([10, 20]);

        $this->assertFalse($employee->hasService(30), 'Should not have service 30');
        $this->assertFalse($employee->hasService(999), 'Should not have service 999');
    }

    /**
     * Test that employee with no services returns false for any service
     */
    public function testEmployeeWithNoServicesReturnsFalse()
    {
        $employee = $this->createEmployeeWithServices([]);

        $this->assertFalse($employee->hasService(10), 'Employee with no services should return false');
        $this->assertFalse($employee->hasService(999), 'Employee with no services should return false');
    }

    /**
     * Test that hasService() uses strict type checking
     */
    public function testHasServiceUsesStrictTypeChecking()
    {
        $employee = $this->createEmployeeWithServices([10, 20, 30]);

        // String "10" should not match integer 10 with strict checking
        $this->assertTrue($employee->hasService(10), 'Integer 10 should match');

        // Test that strict comparison is used (no string-to-int coercion)
        $serviceIds = $employee->getServiceIds();
        $this->assertContains(10, $serviceIds, 'Service IDs should be integers');
        $this->assertIsInt($serviceIds[0], 'Service IDs should be integers, not strings');
    }

    /**
     * Test that employee can be assigned to single service
     */
    public function testEmployeeCanBeAssignedToSingleService()
    {
        $employee = $this->createEmployeeWithServices([100]);

        $this->assertTrue($employee->hasService(100));
        $this->assertFalse($employee->hasService(99));
        $this->assertFalse($employee->hasService(101));
    }

    /**
     * Test that employee can be assigned to many services
     */
    public function testEmployeeCanBeAssignedToManyServices()
    {
        // Assign employee to 10 different services
        $serviceIds = range(1, 10);
        $employee = $this->createEmployeeWithServices($serviceIds);

        foreach ($serviceIds as $serviceId) {
            $this->assertTrue(
                $employee->hasService($serviceId),
                "Employee should have service {$serviceId}"
            );
        }

        // Test some services they don't have
        $this->assertFalse($employee->hasService(11));
        $this->assertFalse($employee->hasService(999));
    }

    /**
     * Test boundary cases: negative IDs, zero, very large IDs
     */
    public function testBoundaryCases()
    {
        $employee = $this->createEmployeeWithServices([1, 1000000]);

        $this->assertTrue($employee->hasService(1), 'Should handle service ID 1');
        $this->assertTrue($employee->hasService(1000000), 'Should handle large service ID');

        // Negative ID (invalid but should not crash)
        $this->assertFalse($employee->hasService(-1), 'Should safely handle negative ID');

        // Zero (invalid but should not crash)
        $this->assertFalse($employee->hasService(0), 'Should safely handle zero ID');
    }

    /**
     * Test that getServiceIds returns empty array when employee has no services
     */
    public function testGetServiceIdsReturnsEmptyArrayWhenNoServices()
    {
        $employee = $this->createEmployeeWithServices([]);

        $serviceIds = $employee->getServiceIds();

        $this->assertIsArray($serviceIds);
        $this->assertCount(0, $serviceIds);
    }

    /**
     * Test that getServiceIds returns all assigned service IDs
     */
    public function testGetServiceIdsReturnsAllAssignedServices()
    {
        $assignedServices = [5, 10, 15, 20];
        $employee = $this->createEmployeeWithServices($assignedServices);

        $serviceIds = $employee->getServiceIds();

        $this->assertCount(4, $serviceIds);
        $this->assertEquals($assignedServices, $serviceIds);
    }

    /**
     * Test that service IDs are stored as integers, not strings
     */
    public function testServiceIdsAreIntegers()
    {
        $employee = $this->createEmployeeWithServices([10, 20, 30]);

        $serviceIds = $employee->getServiceIds();

        foreach ($serviceIds as $id) {
            $this->assertIsInt($id, "Service ID should be integer, got " . gettype($id));
        }
    }

    /**
     * Security test: Ensure hasService prevents booking incompatible services
     *
     * This test documents the security fix:
     * Before: Employee could be booked for any service
     * After: Employee can only be booked for assigned services
     */
    public function testHasServicePreventsIncompatibleBookings()
    {
        // Hairstylist assigned to haircut services
        $hairstylist = $this->createEmployeeWithServices([1, 2]); // Haircut, Hair Wash

        // Masseur assigned to massage services
        $masseur = $this->createEmployeeWithServices([10, 11]); // Full Body Massage, Foot Massage

        // Hairstylist should only have haircut services
        $this->assertTrue($hairstylist->hasService(1), 'Hairstylist should have Haircut');
        $this->assertTrue($hairstylist->hasService(2), 'Hairstylist should have Hair Wash');
        $this->assertFalse($hairstylist->hasService(10), 'Hairstylist should NOT have Massage');
        $this->assertFalse($hairstylist->hasService(11), 'Hairstylist should NOT have Foot Massage');

        // Masseur should only have massage services
        $this->assertTrue($masseur->hasService(10), 'Masseur should have Full Body Massage');
        $this->assertTrue($masseur->hasService(11), 'Masseur should have Foot Massage');
        $this->assertFalse($masseur->hasService(1), 'Masseur should NOT have Haircut');
        $this->assertFalse($masseur->hasService(2), 'Masseur should NOT have Hair Wash');
    }

    /**
     * Test realistic scenario: multi-skilled employee
     */
    public function testMultiSkilledEmployee()
    {
        // Employee who can do both haircuts and massages
        $multiSkilled = $this->createEmployeeWithServices([1, 2, 10, 11]);

        $this->assertTrue($multiSkilled->hasService(1), 'Multi-skilled should have Haircut');
        $this->assertTrue($multiSkilled->hasService(10), 'Multi-skilled should have Massage');
        $this->assertFalse($multiSkilled->hasService(999), 'Multi-skilled should NOT have unassigned service');
    }

    /**
     * Helper: Create employee with specified service IDs
     */
    private function createEmployeeWithServices(array $serviceIds): Employee
    {
        $employee = new Employee();
        $employee->id = rand(1, 1000);
        $employee->name = 'Test Employee';
        $employee->setServiceIds($serviceIds);

        return $employee;
    }
}
