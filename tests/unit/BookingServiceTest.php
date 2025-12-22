<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use fabian\booked\services\BookingService;
use fabian\booked\services\AvailabilityService;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;
use UnitTester;
use DateTime;
use Craft;

/**
 * Mock Service
 */
class BookingMockService extends \fabian\booked\elements\Service {
    public ?int $duration = 60;
    public function __construct() {}
}

/**
 * Mock Mutex
 */
class MockMutex {
    public $acquired = [];
    public function acquire($name, $timeout = 0) {
        if (isset($this->acquired[$name])) return false;
        $this->acquired[$name] = true;
        return true;
    }
    public function release($name) {
        unset($this->acquired[$name]);
    }
}

/**
 * Testable version of BookingService
 */
class TestableBookingService extends BookingService {
    public $mockReservation = null;
    public $mockSettings = null;
    public $mockAvailabilityService = null;
    public $mockMutex = null;

    public function getReservationById(int $id): ?Reservation {
        return $this->mockReservation;
    }

    protected function createReservationModel(): Reservation {
        return new MockReservationElement();
    }

    protected function getServiceById(int $id): ?\fabian\booked\elements\Service {
        $s = new BookingMockService();
        $s->duration = 60;
        return $s;
    }

    protected function getReservationRecordQuery(): \yii\db\ActiveQuery {
        return new class extends \yii\db\ActiveQuery {
            public function __construct() { parent::__construct(\fabian\booked\records\ReservationRecord::class); }
            public function where($condition, $params = []) { return $this; }
            public function andWhere($condition, $params = []) { return $this; }
            public function orderBy($columns) { return $this; }
            public function count($q = '*', $db = null) { return 0; }
            public function one($db = null) { return null; }
        };
    }

    protected function getRequestService() {
        return new class {
            public function getIsConsoleRequest() { return true; }
            public function getUserIP() { return '127.0.0.1'; }
        };
    }

    protected function getQueueService() {
        return new class {
            public function priority($p) { return $this; }
            public function push($job) { return true; }
        };
    }

    protected function getCacheService() {
        return new class {
            public function get($key) { return null; }
            public function set($key, $val, $ttl) { return true; }
        };
    }

    protected function getSettingsModel(): \fabian\booked\models\Settings {
        return $this->mockSettings ?? new \fabian\booked\models\Settings();
    }

    protected function getAvailabilityService(): AvailabilityService {
        return $this->mockAvailabilityService;
    }

    protected function getAvailabilityCacheService(): \fabian\booked\services\AvailabilityCacheService {
        return new class extends \fabian\booked\services\AvailabilityCacheService {
            public function init(): void {}
            public function invalidateDateCache(string $date): bool { return true; }
        };
    }

    protected function getMutex() {
        return $this->mockMutex;
    }

    protected function getDb() {
        // Mock DB connection for transactions
        $db = new class {
            public function beginTransaction() {
                return new class {
                    public function commit() {}
                    public function rollBack() {}
                };
            }
        };
        return $db;
    }

    protected function getElementsService() {
        return new class {
            public function saveElement($element) {
                if ($element->id === null) {
                    $element->id = 123;
                }
                return true;
            }
        };
    }
}

/**
 * Real Mock extending Reservation to pass type hints
 */
class MockReservationElement extends Reservation {
    public function __construct() {}
    public function getService(): ?\fabian\booked\elements\Service {
        return null;
    }
    public function getEmployee(): ?\fabian\booked\elements\Employee {
        return null;
    }
    public function getFieldLayout(): ?\craft\models\FieldLayout {
        return null;
    }
    protected function getRecord(): ?\fabian\booked\records\ReservationRecord {
        return new \fabian\booked\records\ReservationRecord();
    }
    protected function getSettings(): \fabian\booked\models\Settings {
        return new \fabian\booked\models\Settings();
    }
}

/**
 * Mock Availability Service
 */
class MockAvailabilityService extends AvailabilityService {
    public $slots = [];
    public function getAvailableSlots(string $date, ?int $empId = null, ?int $locId = null, ?int $servId = null, int $qty = 1, ?string $tz = null): array {
        return $this->slots;
    }
}

/**
 * Simple mock application to avoid Codeception Stub issues with Craft's Application class
 */
class BookingMockApplication {
    public $fields;
    public $elements;
    public $view;
    public $sites;
    public $request;
    public $cache;
    public $queue;
    public $projectConfig;
    public function getIsInstalled() { return true; }
    public function getIsUpdating() { return false; }
    public function getTimeZone() { return 'Europe/Zurich'; }
    public function getFields() { return $this->fields; }
    public function getElements() { return $this->elements; }
    public function getView() { return $this->view; }
    public function getRequest() { return $this->request; }
    public function getCache() { return $this->cache; }
    public function getQueue() { return $this->queue; }
    public function getProjectConfig() { return $this->projectConfig; }
    public function set($id, $service) { $this->$id = $service; }
    public function get($id) { return $this->$id; }
}

class BookingServiceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TestableBookingService
     */
    protected $service;

    protected function _before()
    {
        parent::_before();
        
        // Mock Craft::$app
        $app = new BookingMockApplication();
        $app->fields = \Codeception\Stub::makeEmpty(\craft\services\Fields::class);
        $app->elements = \Codeception\Stub::makeEmpty(\craft\services\Elements::class);
        $app->view = \Codeception\Stub::makeEmpty(\craft\web\View::class);
        $app->projectConfig = \Codeception\Stub::makeEmpty(\craft\services\ProjectConfig::class);
        $app->sites = new class {
            public function getCurrentSite() {
                return new class { public int $id = 1; };
            }
        };
        Craft::$app = $app;

        // Mock the Booked plugin singleton
        $pluginMock = $this->getMockBuilder(Booked::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAvailability', 'getSoftLock', 'getCalendarSync', 'getVirtualMeeting', 'getReminder'])
            ->getMock();
            
        $pluginMock->method('getAvailability')->willReturn(new AvailabilityService());
        $pluginMock->method('getSoftLock')->willReturn(\Codeception\Stub::makeEmpty(\fabian\booked\services\SoftLockService::class, [
            'isLocked' => false,
            'createLock' => 'mock-token',
            'releaseLock' => true,
        ]));
        $pluginMock->method('getCalendarSync')->willReturn(\Codeception\Stub::makeEmpty(\fabian\booked\services\CalendarSyncService::class));
        $pluginMock->method('getVirtualMeeting')->willReturn(\Codeception\Stub::makeEmpty(\fabian\booked\services\VirtualMeetingService::class));
        $pluginMock->method('getReminder')->willReturn(\Codeception\Stub::makeEmpty(\fabian\booked\services\ReminderService::class));
            
        // Force the mock into the private static property
        $reflection = new \ReflectionClass(Booked::class);
        $property = $reflection->getProperty('plugin');
        $property->setAccessible(true);
        $property->setValue(null, $pluginMock);

        $this->service = new TestableBookingService();
    }

    public function testCanCancelReservation()
    {
        $now = new DateTime();
        $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
        $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');

        // Case 1: Future reservation
        $res1 = new MockReservationElement();
        $res1->bookingDate = $tomorrow;
        $res1->startTime = '10:00';
        $res1->status = 'confirmed';
        
        $this->assertTrue($this->invokeMethod($this->service, 'canCancelReservation', [$res1]));

        // Case 2: Past reservation
        $res2 = new MockReservationElement();
        $res2->bookingDate = $yesterday;
        $res2->startTime = '10:00';
        $res2->status = 'confirmed';
        
        $this->assertFalse($this->invokeMethod($this->service, 'canCancelReservation', [$res2]));

        // Case 3: Already cancelled
        $res3 = new MockReservationElement();
        $res3->bookingDate = $tomorrow;
        $res3->status = 'cancelled';
        
        $this->assertFalse($this->invokeMethod($this->service, 'canCancelReservation', [$res3]));
    }

    /**
     * Test that createBooking uses a mutex lock to prevent race conditions
     */
    public function testRaceConditionPrevention()
    {
        $this->service->mockMutex = new MockMutex();
        $this->service->mockAvailabilityService = new MockAvailabilityService();
        $this->service->mockAvailabilityService->slots = [['startTime' => '10:00', 'time' => '10:00', 'endTime' => '11:00', 'employeeId' => 1]];
        
        $bookingData = [
            'date' => '2026-01-01',
            'time' => '10:00',
            'serviceId' => 1,
            'employeeId' => 1,
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com'
        ];

        // This should pass if mutex is acquired and released
        $result = $this->service->createBooking($bookingData);
        
        $this->assertTrue($result);
        // Mutex should be empty now because it was released
        $this->assertEmpty($this->service->mockMutex->acquired);
    }

    /**
     * Test that createBooking fails if mutex cannot be acquired
     */
    public function testMutexLockFailure()
    {
        $this->service->mockMutex = new MockMutex();
        // Pre-acquire the lock to simulate another process holding it
        $lockName = 'booked-booking-2026-01-01-10:00-1-1';
        $this->service->mockMutex->acquire($lockName);
        
        $bookingData = [
            'date' => '2026-01-01',
            'time' => '10:00',
            'serviceId' => 1,
            'employeeId' => 1,
            'customerName' => 'Test User',
            'customerEmail' => 'test@example.com'
        ];

        $this->expectException(\fabian\booked\exceptions\BookingException::class);
        $this->service->createBooking($bookingData);
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        if (!$reflection->hasMethod($methodName)) {
            throw new \Exception("Method $methodName does not exist");
        }
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
