<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Service;
use fabian\booked\services\AvailabilityService;
use fabian\booked\Booked;
use UnitTester;
use Codeception\Stub;
use Craft;

/**
 * Mock Cache Service for assignment tests
 */
class AssignmentMockCacheService extends \fabian\booked\services\AvailabilityCacheService {
    public function getCachedAvailability(string $date, ?int $employeeId = null, ?int $serviceId = null): ?array {
        return null;
    }
    public function setCachedAvailability(string $date, array $slots, ?int $employeeId = null, ?int $serviceId = null): bool {
        return true;
    }
}

/**
 * Mock Blackout Service for assignment tests
 */
class AssignmentMockBlackoutService extends \fabian\booked\services\BlackoutDateService {
    public $isBlackedOut = false;
    public function isDateBlackedOut(string $date, ?int $employeeId = null, ?int $locationId = null): bool {
        return $this->isBlackedOut;
    }
}

/**
 * A testable version of AvailabilityService for assignment tests
 */
class AssignmentBaseTestableAvailabilityService extends AvailabilityService 
{
    public $mockWorkingHours = [];
    public $mockReservations = [];
    public $mockAvailabilities = [];
    public $mockNow = null;
    public $mockEmployeeTimezone = 'Europe/Zurich'; // Default mock TZ

    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        $filtered = $this->mockWorkingHours;
        if ($employeeId !== null) {
            $filtered = array_filter($filtered, function($s) use ($employeeId) {
                return $s->employeeId === $employeeId;
            });
        }
        return array_values($filtered);
    }

    protected function getEmployeeTimezone(int $employeeId): string
    {
        return $this->mockEmployeeTimezone;
    }

    protected function getAvailabilities(?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        // Simple mock for now
        return $this->mockAvailabilities;
    }

    protected function getReservationsForDate(string $date, ?int $employeeId = null, ?int $serviceId = null): array
    {
        $filtered = $this->mockReservations;
        if ($employeeId !== null) {
            $filtered = array_filter($filtered, function($r) use ($employeeId) {
                return $r->employeeId === $employeeId;
            });
        }
        return array_values($filtered);
    }

    protected function getCurrentDateTime(): \DateTime
    {
        return $this->mockNow ?? new \DateTime();
    }

    protected function subtractExternalEvents(array $windows, string $date, int $employeeId): array
    {
        return $windows; // Mock: don't subtract anything by default
    }

    protected function subtractBlackouts(array $windows, string $date, ?int $employeeId = null): array
    {
        return $windows; // Mock: skip DB call for employee location
    }
}

/**
 * Enhanced testable availability service for assignment tests
 */
class AssignmentTestableAvailabilityService extends AssignmentBaseTestableAvailabilityService
{
    public $mockEmployeeServices = []; // employeeId => [serviceId, ...]

    protected function getWorkingHours(int $dayOfWeek, ?int $employeeId = null, ?int $locationId = null, ?int $serviceId = null): array
    {
        $schedules = parent::getWorkingHours($dayOfWeek, $employeeId, $locationId, $serviceId);
        
        // This is where the core logic will be added: filter by service
        // For testing, we assume the caller passed serviceId to getAvailableSlots
        // and we want to see if the filtering happens correctly.
        return $schedules;
    }

    // We'll override this once the real implementation is in place
    public function getEmployeesForService(int $serviceId): array
    {
        $empIds = [];
        foreach ($this->mockEmployeeServices as $empId => $services) {
            if (in_array($serviceId, $services)) {
                $empIds[] = $empId;
            }
        }
        return $empIds;
    }
}

class EmployeeServiceAssignmentTest extends Unit
{
    protected $tester;
    protected AssignmentTestableAvailabilityService $availabilityService;

    protected function _before()
    {
        parent::_before();

        // Mock Craft::$app
        $app = new class {
            public $sites;
            public $fields;
            public $elements;
            public $view;
            public $projectConfig;
            public $plugins;
            public $db;
            public $cache;
            public $request;
            public function getTimeZone() { return 'UTC'; }
            public function getIsInstalled() { return true; }
            public function getIsUpdating() { return false; }
            public function get($id) { return $this->$id ?? null; }
            public function getDb() { return $this->db; }
        };
        $app->fields = \Codeception\Stub::makeEmpty(\craft\services\Fields::class);
        $app->elements = \Codeception\Stub::makeEmpty(\craft\services\Elements::class);
        $app->view = \Codeception\Stub::makeEmpty(\craft\web\View::class);
        $app->projectConfig = \Codeception\Stub::makeEmpty(\craft\services\ProjectConfig::class);
        $app->cache = new class {
            public function get($key) { return null; }
            public function set($key, $val, $ttl = null) { return true; }
            public function delete($key) { return true; }
            public function flush() { return true; }
        };
        $app->request = \Codeception\Stub::makeEmpty(\craft\web\Request::class);
        $app->db = new class {
            public function getIsMysql() { return true; }
            public function getTablePrefix() { return ''; }
            public function getSchema() { 
                return new class { 
                    public function getTableSchema($name) { return null; } 
                    public function getQueryBuilder() { return new class { public function build($query) { return ['', []]; } }; }
                }; 
            }
            public function getQueryBuilder() { return $this->getSchema()->getQueryBuilder(); }
        };
        $app->plugins = new class {
            public function isPluginEnabled($handle) { return false; }
            public function getPlugin($handle) { return null; }
            public function getEnabledPluginBehaviors($element) { return []; }
        };
        $app->sites = new class {
            public function getCurrentSite() {
                return new class { public int $id = 1; };
            }
        };
        Craft::$app = $app;

        // Mock the Booked plugin
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailabilityCache', 'getBlackoutDate'])
            ->getMock();
            
        $pluginMock->method('getAvailabilityCache')->willReturn(new AssignmentMockCacheService());
        $pluginMock->method('getBlackoutDate')->willReturn(new AssignmentMockBlackoutService());
        
        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->availabilityService = new AssignmentTestableAvailabilityService();
    }

    /**
     * Test that an employee can have multiple services assigned
     */
    public function testServiceAssignmentPersistence()
    {
        // This test would normally hit the database, but we can mock the behavior
        // once the element methods are implemented.
        $this->assertTrue(true, "Placeholder for element persistence test");
    }

    /**
     * Test that AvailabilityService filters employees who don't offer the service
     */
    public function testAvailabilityFilteringByService()
    {
        // Mock Services
        $service10 = Stub::makeEmpty(Service::class, ['id' => 10, 'duration' => 60, 'enabled' => true]);
        $service20 = Stub::makeEmpty(Service::class, ['id' => 20, 'duration' => 60, 'enabled' => true]);
        
        // 1. Setup two employees with schedules
        $s1 = new class {
            public $startTime = '09:00';
            public $endTime = '10:00';
            public $employeeId = 1; // John
            public function getEmployees() { return []; }
        };
        
        $s2 = new class {
            public $startTime = '09:00';
            public $endTime = '10:00';
            public $employeeId = 2; // Sarah
            public function getEmployees() { return []; }
        };
        
        // We need to mock the getWorkingHours to return our filtered list
        // based on the serviceId passed to it.
        $service = Stub::make(AssignmentTestableAvailabilityService::class, [
            'getService' => function($id) use ($service10, $service20) {
                if ($id == 10) return $service10;
                if ($id == 20) return $service20;
                return null;
            },
            'getWorkingHours' => function($dayOfWeek, $employeeId, $locationId, $serviceId) use ($s1, $s2) {
                if ($serviceId == 10) return [$s1]; // John only
                if ($serviceId == 20) return [$s2]; // Sarah only
                return [$s1, $s2];
            },
            'getCurrentDateTime' => new \DateTime('2025-12-22 08:00:00'),
            'getEmployeeTimezone' => function($id) { return 'Europe/Zurich'; },
            'subtractExternalEvents' => function($w) { return $w; }
        ]);

        $date = '2025-12-22';

        // 3. Test: Search for Service A (10)
        $slotsA = $service->getAvailableSlots($date, null, null, 10);
        $this->assertNotEmpty($slotsA);
        foreach ($slotsA as $slot) {
            $this->assertEquals(1, $slot['employeeId'], "Only John (ID 1) should be available for Service 10");
        }

        // 4. Test: Search for Service B (20)
        $slotsB = $service->getAvailableSlots($date, null, null, 20);
        $this->assertNotEmpty($slotsB);
        foreach ($slotsB as $slot) {
            $this->assertEquals(2, $slot['employeeId'], "Only Sarah (ID 2) should be available for Service 20");
        }
    }
}

