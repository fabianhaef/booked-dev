<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use Craft;
use fabian\booked\Booked;
use UnitTester;

/**
 * Test Project Config synchronization
 *
 * Project Config ensures that schema changes (field layouts, settings)
 * are synced across dev/staging/production environments via YAML files.
 */
class ProjectConfigTest extends Unit
{
    protected UnitTester $tester;

    /**
     * Test that plugin settings are saved to Project Config
     */
    public function testPluginSettingsSavedToProjectConfig()
    {
        $plugin = Booked::getInstance();
        $settings = $plugin->getSettings();

        // Modify a setting
        $settings->softLockDurationMinutes = 20;

        // Save plugin settings
        Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

        // Check that it's in Project Config
        $config = Craft::$app->projectConfig->get('plugins.booked.settings');

        $this->assertIsArray($config);
        $this->assertEquals(20, $config['softLockDurationMinutes']);
    }

    /**
     * Test that Project Config changes trigger settings update
     */
    public function testProjectConfigChangesUpdateSettings()
    {
        $plugin = Booked::getInstance();

        // Simulate Project Config change (as if synced from another environment)
        Craft::$app->projectConfig->set('plugins.booked.settings.softLockDurationMinutes', 25);

        // Reload settings
        $settings = $plugin->getSettings();

        $this->assertEquals(25, $settings->softLockDurationMinutes);
    }

    /**
     * Test that sensitive settings are NOT saved to Project Config
     */
    public function testSensitiveSettingsNotInProjectConfig()
    {
        $plugin = Booked::getInstance();
        $settings = $plugin->getSettings();

        // Set sensitive values
        $settings->googleClientId = 'secret-client-id';
        $settings->googleClientSecret = 'secret-client-secret';
        $settings->outlookClientId = 'secret-outlook-id';
        $settings->outlookClientSecret = 'secret-outlook-secret';
        $settings->zoomApiKey = 'secret-zoom-key';
        $settings->zoomApiSecret = 'secret-zoom-secret';

        Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

        // Check Project Config
        $config = Craft::$app->projectConfig->get('plugins.booked.settings');

        // Sensitive fields should NOT be in Project Config
        $this->assertArrayNotHasKey('googleClientSecret', $config);
        $this->assertArrayNotHasKey('outlookClientSecret', $config);
        $this->assertArrayNotHasKey('zoomApiSecret', $config);
    }

    /**
     * Test that field layouts are saved to Project Config
     */
    public function testFieldLayoutsSavedToProjectConfig()
    {
        $fieldLayout = new \craft\models\FieldLayout([
            'type' => \fabian\booked\elements\Service::class,
        ]);

        // Add a field to the layout
        $tab = new \craft\models\FieldLayoutTab([
            'name' => 'Content',
            'layout' => $fieldLayout,
        ]);

        $fieldLayout->setTabs([$tab]);

        // Save the field layout
        Craft::$app->fields->saveLayout($fieldLayout);

        // Check that it's in Project Config
        $config = Craft::$app->projectConfig->get("fieldLayouts.{$fieldLayout->uid}");

        $this->assertIsArray($config);
        $this->assertEquals(\fabian\booked\elements\Service::class, $config['type']);
    }

    /**
     * Test Project Config rebuild includes plugin config
     */
    public function testProjectConfigRebuildIncludesPluginConfig()
    {
        $plugin = Booked::getInstance();

        // Trigger a rebuild
        Craft::$app->projectConfig->rebuild();

        // Check that plugin config exists after rebuild
        $config = Craft::$app->projectConfig->get('plugins.booked');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('settings', $config);
        $this->assertArrayHasKey('schemaVersion', $config);
    }

    /**
     * Test that non-sensitive settings are included
     */
    public function testNonSensitiveSettingsIncluded()
    {
        $plugin = Booked::getInstance();
        $settings = $plugin->getSettings();

        $settings->softLockDurationMinutes = 15;
        $settings->availabilityCacheTtl = 3600;
        $settings->enableRateLimiting = true;
        $settings->rateLimitPerEmail = 5;

        Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

        $config = Craft::$app->projectConfig->get('plugins.booked.settings');

        // These should be included (they're structural, not sensitive)
        $this->assertEquals(15, $config['softLockDurationMinutes']);
        $this->assertEquals(3600, $config['availabilityCacheTtl']);
        $this->assertTrue($config['enableRateLimiting']);
        $this->assertEquals(5, $config['rateLimitPerEmail']);
    }

    /**
     * Test Project Config changes can be applied from YAML
     */
    public function testProjectConfigAppliedFromYaml()
    {
        // Simulate loading from YAML file (as happens in staging/prod)
        $yamlConfig = [
            'settings' => [
                'softLockDurationMinutes' => 30,
                'availabilityCacheTtl' => 7200,
                'enableRateLimiting' => false,
            ],
        ];

        // Apply the config
        Craft::$app->projectConfig->set('plugins.booked', $yamlConfig);

        // Verify settings were updated
        $plugin = Booked::getInstance();
        $settings = $plugin->getSettings();

        $this->assertEquals(30, $settings->softLockDurationMinutes);
        $this->assertEquals(7200, $settings->availabilityCacheTtl);
        $this->assertFalse($settings->enableRateLimiting);
    }

    /**
     * Test that config changes are idempotent
     */
    public function testConfigChangesAreIdempotent()
    {
        $plugin = Booked::getInstance();
        $settings = $plugin->getSettings();

        $settings->softLockDurationMinutes = 15;

        // Save twice
        Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());
        Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

        // Should still be the same
        $config = Craft::$app->projectConfig->get('plugins.booked.settings.softLockDurationMinutes');

        $this->assertEquals(15, $config);
    }

    /**
     * Test environment-specific settings are NOT synced
     */
    public function testEnvironmentSpecificSettingsNotSynced()
    {
        $plugin = Booked::getInstance();
        $settings = $plugin->getSettings();

        // These are environment-specific (URLs, keys, etc.)
        $settings->googleRedirectUri = 'https://dev.example.com/callback';
        $settings->outlookRedirectUri = 'https://dev.example.com/outlook-callback';

        Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

        $config = Craft::$app->projectConfig->get('plugins.booked.settings');

        // Redirect URIs should NOT be in Project Config (they're environment-specific)
        $this->assertArrayNotHasKey('googleRedirectUri', $config);
        $this->assertArrayNotHasKey('outlookRedirectUri', $config);
    }
}
