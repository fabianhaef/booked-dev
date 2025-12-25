<?php

namespace fabian\booked\tests\_support;

use fabian\booked\Booked;
use fabian\booked\models\Settings;

/**
 * Plugin Test Helper
 *
 * Provides utilities for setting up mocked plugin instances in tests
 */
class PluginTestHelper
{
    /**
     * Setup mock Booked plugin instance for tests
     */
    public static function setupMockPlugin(): void
    {
        // Create mock plugin using anonymous class to avoid parent constructor
        $mockPlugin = new class('booked', null) extends Booked {
            public function __construct($id, $parent) {
                // Skip parent constructor - just set ID
                $this->id = $id;
            }

            public function getSettings(): ?\craft\base\Model {
                if (!isset($this->_testSettings)) {
                    $this->_testSettings = new Settings();
                }
                return $this->_testSettings;
            }

            private $_testSettings;
        };

        // Use reflection to set the static $plugin property
        $reflection = new \ReflectionClass(Booked::class);
        $pluginProperty = $reflection->getProperty('plugin');
        $pluginProperty->setAccessible(true);
        $pluginProperty->setValue(null, $mockPlugin);

        // Setup mock services using magic __get support
        $mockPlugin->set('availabilityCache', new class {
            public function getCachedAvailability($date, $employeeId, $serviceId) {
                return null;
            }
            public function setCachedAvailability($date, $employeeId, $serviceId, $slots) {}
            public function invalidateDateCache($date) {}
            public function invalidateEmployeeCache($employeeId) {}
            public function invalidateServiceCache($serviceId) {}
            public function clearAll() {}
        });

        $mockPlugin->set('blackoutDate', new class {
            public function isDateBlackedOut($date, $employeeId = null, $locationId = null) {
                return false;
            }
        });
    }

    /**
     * Reset plugin instance (useful for test cleanup)
     */
    public static function resetPlugin(): void
    {
        $reflection = new \ReflectionClass(Booked::class);
        $pluginProperty = $reflection->getProperty('plugin');
        $pluginProperty->setAccessible(true);
        $pluginProperty->setValue(null, null);
    }
}
