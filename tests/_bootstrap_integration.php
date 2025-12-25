<?php
/**
 * Integration Test Bootstrap
 *
 * Full Craft CMS bootstrap for integration tests
 * Use this for testing complete workflows with database, elements, and services
 */

use craft\test\TestSetup;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('date.timezone', 'UTC');
date_default_timezone_set('UTC');

// Define path constants for Craft testing
define('CRAFT_ROOT_PATH', dirname(__DIR__, 3)); // Project root
define('CRAFT_TESTS_PATH', __DIR__);
define('CRAFT_STORAGE_PATH', __DIR__ . '/_craft/storage');
define('CRAFT_TEMPLATES_PATH', __DIR__ . '/_craft/templates');
define('CRAFT_CONFIG_PATH', __DIR__ . '/_craft/config');
define('CRAFT_MIGRATIONS_PATH', __DIR__ . '/_craft/migrations');
define('CRAFT_TRANSLATIONS_PATH', __DIR__ . '/_craft/translations');
define('CRAFT_VENDOR_PATH', CRAFT_ROOT_PATH . '/vendor');

// Ensure required directories exist
$requiredDirs = [
    CRAFT_STORAGE_PATH,
    CRAFT_TEMPLATES_PATH,
    CRAFT_CONFIG_PATH,
    CRAFT_MIGRATIONS_PATH,
    CRAFT_TRANSLATIONS_PATH,
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Manual autoloader for test support classes
spl_autoload_register(function ($class) {
    $prefix = 'fabian\\booked\\tests\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    if (strpos($relative_class, '_generated\\') === 0) {
        $file = __DIR__ . '/_support/_generated/' . str_replace('\\', '/', substr($relative_class, 11)) . '.php';
    } elseif (strpos($relative_class, '_support\\') === 0) {
        $file = __DIR__ . '/_support/' . str_replace('\\', '/', substr($relative_class, 9)) . '.php';
    } else {
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative_class) . '.php';
    }
    if (file_exists($file)) require_once $file;
});

// Initialize Craft for integration testing
// Note: Craft's Codeception module will handle initialization
// TestSetup::configureCraft() is called by the \craft\test\Craft module
