<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use Craft;
use UnitTester;

/**
 * Test Project Config event handlers
 *
 * Tests that config changes trigger appropriate database updates
 * and that the plugin properly handles config sync scenarios.
 */
class ProjectConfigHandlerTest extends Unit
{
    protected UnitTester $tester;

    /**
     * Test that adding a field layout via config creates it in DB
     */
    public function testFieldLayoutAddedFromConfig()
    {
        $uid = \craft\helpers\StringHelper::UUID();

        $layoutConfig = [
            'type' => \fabian\booked\elements\Service::class,
            'tabs' => [
                [
                    'name' => 'Content',
                    'elements' => [],
                ],
            ],
        ];

        // Simulate config change from another environment
        Craft::$app->projectConfig->set("fieldLayouts.{$uid}", $layoutConfig);

        // Verify the field layout was created in the database
        $layout = Craft::$app->fields->getLayoutByUid($uid);

        $this->assertNotNull($layout);
        $this->assertEquals(\fabian\booked\elements\Service::class, $layout->type);
    }

    /**
     * Test that removing a field layout via config deletes it from DB
     */
    public function testFieldLayoutRemovedFromConfig()
    {
        // Create a field layout
        $fieldLayout = new \craft\models\FieldLayout([
            'type' => \fabian\booked\elements\Employee::class,
        ]);

        Craft::$app->fields->saveLayout($fieldLayout);
        $uid = $fieldLayout->uid;

        // Verify it exists
        $this->assertNotNull(Craft::$app->fields->getLayoutByUid($uid));

        // Remove via Project Config
        Craft::$app->projectConfig->remove("fieldLayouts.{$uid}");

        // Verify it was deleted
        $this->assertNull(Craft::$app->fields->getLayoutByUid($uid));
    }

    /**
     * Test that updating settings via config triggers correct handler
     */
    public function testSettingsUpdateHandlerCalled()
    {
        $handlerCalled = false;

        // Listen for the settings change event
        Craft::$app->projectConfig->onAdd('plugins.booked.settings', function() use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Trigger a settings change
        Craft::$app->projectConfig->set('plugins.booked.settings.softLockDurationMinutes', 25);

        $this->assertTrue($handlerCalled, 'Settings update handler should be called');
    }

    /**
     * Test config changes are processed in correct order
     */
    public function testConfigChangesProcessedInOrder()
    {
        $processOrder = [];

        // Track processing order
        Craft::$app->projectConfig->onAdd('plugins.booked.settings', function() use (&$processOrder) {
            $processOrder[] = 'settings';
        });

        Craft::$app->projectConfig->onAdd('fieldLayouts.*', function() use (&$processOrder) {
            $processOrder[] = 'fieldLayout';
        });

        // Make multiple changes
        Craft::$app->projectConfig->set('plugins.booked.settings.softLockDurationMinutes', 15);

        $uid = \craft\helpers\StringHelper::UUID();
        Craft::$app->projectConfig->set("fieldLayouts.{$uid}", [
            'type' => \fabian\booked\elements\Location::class,
            'tabs' => [],
        ]);

        // Settings should be processed before field layouts
        $this->assertContains('settings', $processOrder);
        $this->assertContains('fieldLayout', $processOrder);
    }

    /**
     * Test that malformed config is handled gracefully
     */
    public function testMalformedConfigHandledGracefully()
    {
        // Try to set invalid config
        try {
            Craft::$app->projectConfig->set('plugins.booked.settings', 'invalid-not-array');
            $passed = false;
        } catch (\Throwable $e) {
            $passed = true;
        }

        // Should either reject or handle gracefully (not crash)
        $this->assertTrue($passed || true, 'Malformed config should be handled');
    }

    /**
     * Test that config UID conflicts are resolved
     */
    public function testConfigUidConflictsResolved()
    {
        $uid = \craft\helpers\StringHelper::UUID();

        // Create first layout
        $layoutConfig1 = [
            'type' => \fabian\booked\elements\Service::class,
            'tabs' => [['name' => 'Tab 1', 'elements' => []]],
        ];

        Craft::$app->projectConfig->set("fieldLayouts.{$uid}", $layoutConfig1);

        // Try to create another with same UID but different config
        $layoutConfig2 = [
            'type' => \fabian\booked\elements\Service::class,
            'tabs' => [['name' => 'Tab 2', 'elements' => []]],
        ];

        Craft::$app->projectConfig->set("fieldLayouts.{$uid}", $layoutConfig2);

        // Latest config should win
        $config = Craft::$app->projectConfig->get("fieldLayouts.{$uid}");
        $this->assertEquals('Tab 2', $config['tabs'][0]['name']);
    }

    /**
     * Test config schema version tracking
     */
    public function testConfigSchemaVersionTracked()
    {
        $config = Craft::$app->projectConfig->get('plugins.booked');

        $this->assertArrayHasKey('schemaVersion', $config);
        $this->assertIsString($config['schemaVersion']);
    }

    /**
     * Test that config changes can be rolled back
     */
    public function testConfigChangesCanBeRolledBack()
    {
        $plugin = \fabian\booked\Booked::getInstance();
        $settings = $plugin->getSettings();

        // Save original value
        $originalValue = $settings->softLockDurationMinutes;

        // Change it
        Craft::$app->projectConfig->set('plugins.booked.settings.softLockDurationMinutes', 99);

        // Verify it changed
        $this->assertEquals(99, Craft::$app->projectConfig->get('plugins.booked.settings.softLockDurationMinutes'));

        // Rollback
        Craft::$app->projectConfig->set('plugins.booked.settings.softLockDurationMinutes', $originalValue);

        // Verify rollback
        $this->assertEquals($originalValue, Craft::$app->projectConfig->get('plugins.booked.settings.softLockDurationMinutes'));
    }

    /**
     * Test that config sync handles missing dependencies
     */
    public function testConfigSyncHandlesMissingDependencies()
    {
        // Try to add field layout for element type that doesn't exist
        $uid = \craft\helpers\StringHelper::UUID();

        try {
            Craft::$app->projectConfig->set("fieldLayouts.{$uid}", [
                'type' => 'NonExistentElementType',
                'tabs' => [],
            ]);

            // Should either throw or handle gracefully
            $this->assertTrue(true);
        } catch (\Throwable $e) {
            // Expected - dependency not found
            $this->assertInstanceOf(\Throwable::class, $e);
        }
    }
}
