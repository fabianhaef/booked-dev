<?php

use craft\test\TestSetup;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('date.timezone', 'UTC');

// Define path constants relative to the project root
define('CRAFT_ROOT_PATH', dirname(__DIR__, 2));
define('CRAFT_VENDOR_PATH', CRAFT_ROOT_PATH . '/vendor');
define('CRAFT_CONFIG_PATH', CRAFT_ROOT_PATH . '/config');
define('CRAFT_STORAGE_PATH', CRAFT_ROOT_PATH . '/storage');
define('CRAFT_TEMPLATES_PATH', CRAFT_ROOT_PATH . '/templates');
define('CRAFT_MIGRATIONS_PATH', CRAFT_ROOT_PATH . '/migrations');
define('CRAFT_TRANSLATIONS_PATH', CRAFT_ROOT_PATH . '/translations');
define('CRAFT_TESTS_PATH', __DIR__);

// Manual autoloader for test support classes and plugin classes
spl_autoload_register(function ($class) {
    // Handle plugin namespace
    $pluginPrefix = 'fabian\\booked\\';
    if (strncmp($pluginPrefix, $class, strlen($pluginPrefix)) === 0) {
        $relativeClass = substr($class, strlen($pluginPrefix));
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Handle test namespace
    $testPrefix = 'fabian\\booked\\tests\\';
    if (strncmp($testPrefix, $class, strlen($testPrefix)) === 0) {
        $relativeClass = substr($class, strlen($testPrefix));
        if (strpos($relativeClass, '_generated\\') === 0) {
            $file = __DIR__ . '/_support/_generated/' . str_replace('\\', '/', substr($relativeClass, 11)) . '.php';
        } elseif (strpos($relativeClass, '_support\\') === 0) {
            $file = __DIR__ . '/_support/' . str_replace('\\', '/', substr($relativeClass, 9)) . '.php';
        } else {
            $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
        }
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// TestSetup::configureCraft(); // Disabling this to prevent DB connection attempts
