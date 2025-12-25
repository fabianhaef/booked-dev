<?php
/**
 * Unit Test Bootstrap
 *
 * Lightweight bootstrap for fast unit tests without full Craft CMS
 * Use this for testing pure logic: algorithms, calculations, data transformations
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('date.timezone', 'UTC');
date_default_timezone_set('UTC');

// Define minimal path constants
define('CRAFT_TESTS_PATH', __DIR__);
define('CRAFT_VENDOR_PATH', dirname(__DIR__, 3) . '/vendor');

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

// Mock Craft class for pure PHP unit tests
if (!class_exists('Craft')) {
    class Craft {
        public static $app;

        public static function t($category, $message, $params = [], $language = null) {
            // Simple parameter replacement for testing
            foreach ($params as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }
            return $message;
        }

        public static function info($message, $category = 'app') {}
        public static function error($message, $category = 'app') {}
        public static function warning($message, $category = 'app') {}
        public static function debug($message, $category = 'app') {}

        public static function dd($var) {
            var_dump($var);
            die();
        }

        public static function getAlias($alias) {
            return $alias;
        }
    }

    // Initialize minimal Craft::$app mock
    Craft::$app = new class {
        public $components = [];

        public function __construct() {
            $this->components['cache'] = new class {
                private $cache = [];
                public function get($key) { return $this->cache[$key] ?? false; }
                public function set($key, $value, $duration = null, $dependency = null) { $this->cache[$key] = $value; }
                public function delete($key) { unset($this->cache[$key]); }
                public function flush() { $this->cache = []; }
            };

            $this->components['request'] = new class {
                public function getIsConsoleRequest() { return true; }
                public function getUserIP() { return '127.0.0.1'; }
            };
        }

        public function get($id) {
            return $this->components[$id] ?? null;
        }

        public function getTimeZone() {
            return 'UTC';
        }

        public function getIsConsoleRequest() {
            return $this->get('request')->getIsConsoleRequest();
        }
    };

    if (!class_exists('Yii')) {
        class Yii extends Craft {}
    }
}

// Initialize mock Booked plugin for unit tests
\fabian\booked\tests\_support\PluginTestHelper::setupMockPlugin();
