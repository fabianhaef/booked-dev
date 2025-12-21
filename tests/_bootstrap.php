<?php

use craft\test\TestSetup;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('date.timezone', 'UTC');

// Define path constants
define('CRAFT_ROOT_PATH', dirname(__DIR__, 3)); // Go up 3 levels to the project root
define('CRAFT_TESTS_PATH', __DIR__);
define('CRAFT_STORAGE_PATH', CRAFT_ROOT_PATH . '/storage');
define('CRAFT_TEMPLATES_PATH', CRAFT_ROOT_PATH . '/templates');
define('CRAFT_CONFIG_PATH', CRAFT_ROOT_PATH . '/config');
define('CRAFT_MIGRATIONS_PATH', CRAFT_ROOT_PATH . '/migrations');
define('CRAFT_TRANSLATIONS_PATH', CRAFT_ROOT_PATH . '/translations');
define('CRAFT_VENDOR_PATH', CRAFT_ROOT_PATH . '/vendor');

// Ensure storage exists
if (!is_dir(CRAFT_STORAGE_PATH)) {
    mkdir(CRAFT_STORAGE_PATH, 0777, true);
}

// Manual autoloader
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

// Mock Craft class for pure PHP unit tests
if (!class_exists('Craft')) {
    class Craft {
        public static $app;
        public static function t($category, $message, $params = [], $language = null) {
            return $message;
        }
        public static function info($message, $category = 'app') {}
        public static function error($message, $category = 'app') {}
        public static function warning($message, $category = 'app') {}
        public static function dd($var) { var_dump($var); die(); }
    }
}

// TestSetup::configureCraft(); // Commented out to prevent Craft initialization and DB connection
