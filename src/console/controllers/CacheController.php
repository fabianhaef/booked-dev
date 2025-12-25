<?php

namespace fabian\booked\console\controllers;

use Craft;
use craft\console\Controller;
use fabian\booked\Booked;
use yii\console\ExitCode;

/**
 * Cache management commands
 *
 * Provides CLI commands for cache warming, clearing, and monitoring
 */
class CacheController extends Controller
{
    /**
     * Warm the availability cache for upcoming dates
     *
     * @param int $days Number of days to warm (default: 7)
     * @return int
     */
    public function actionWarm(int $days = 7): int
    {
        $this->stdout("Warming availability cache for {$days} days...\n");

        try {
            $performanceCache = Booked::getInstance()->performanceCache;
            $performanceCache->warmAvailabilityCache($days);

            $this->stdout("✓ Cache warmed successfully for {$days} days\n", \yii\helpers\Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("✗ Failed to warm cache: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Clear all performance caches
     *
     * @return int
     */
    public function actionClear(): int
    {
        $this->stdout("Clearing all performance caches...\n");

        try {
            $performanceCache = Booked::getInstance()->performanceCache;
            $performanceCache->clearAll();

            $availabilityCache = Booked::getInstance()->availabilityCache;
            $availabilityCache->flush();

            $this->stdout("✓ All caches cleared successfully\n", \yii\helpers\Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("✗ Failed to clear cache: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Clean up old date caches
     *
     * @return int
     */
    public function actionCleanup(): int
    {
        $this->stdout("Cleaning up old date caches...\n");

        try {
            $performanceCache = Booked::getInstance()->performanceCache;
            $performanceCache->cleanupOldCaches();

            $this->stdout("✓ Old caches cleaned up successfully\n", \yii\helpers\Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("✗ Failed to cleanup cache: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Show cache statistics
     *
     * @return int
     */
    public function actionStats(): int
    {
        $this->stdout("Cache Statistics:\n\n");

        try {
            $performanceCache = Booked::getInstance()->performanceCache;
            $stats = $performanceCache->getStats();

            $this->stdout("Hits:      {$stats['hits']}\n");
            $this->stdout("Misses:    {$stats['misses']}\n");
            $this->stdout("Hit Rate:  " . number_format($stats['hitRate'] * 100, 2) . "%\n");

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("✗ Failed to get stats: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
