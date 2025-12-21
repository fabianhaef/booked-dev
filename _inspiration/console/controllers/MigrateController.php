<?php

namespace modules\booking\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\MigrationManager;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Manages booking module migrations
 *
 * Usage:
 *   php craft booking/migrate/up    - Run all pending migrations
 *   php craft booking/migrate/down  - Revert the last migration
 *   php craft booking/migrate/fresh - Drop all tables and re-run migrations
 */
class MigrateController extends Controller
{
    /**
     * @var string|null The migration namespace
     */
    public $migrationNamespace = 'modules\\booking\\migrations';

    /**
     * @var string The migration table name
     */
    public $migrationTable = '{{%booking_migrations}}';

    /**
     * Run all pending booking module migrations
     */
    public function actionUp(): int
    {
        $this->stdout("Running booking module migrations...\n", Console::FG_YELLOW);

        $migrationManager = $this->getMigrationManager();

        // Get pending migrations
        $migrations = $migrationManager->getNewMigrations();

        if (empty($migrations)) {
            $this->stdout("No new migrations found.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($migrations) . " pending migration(s):\n", Console::FG_YELLOW);
        foreach ($migrations as $migration) {
            $this->stdout("  - $migration\n");
        }
        $this->stdout("\n");

        // Apply migrations
        foreach ($migrations as $migration) {
            if (!$this->applyMigration($migrationManager, $migration)) {
                $this->stderr("Migration failed: $migration\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout("\nAll migrations completed successfully!\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Revert the last booking module migration
     */
    public function actionDown(): int
    {
        $this->stdout("Reverting last booking module migration...\n", Console::FG_YELLOW);

        $migrationManager = $this->getMigrationManager();
        $migrations = $migrationManager->getMigrationHistory();

        if (empty($migrations)) {
            $this->stdout("No migrations to revert.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $lastMigration = array_key_first($migrations);

        if (!$this->confirm("Revert migration '$lastMigration'?")) {
            $this->stdout("Migration revert cancelled.\n");
            return ExitCode::OK;
        }

        if (!$this->revertMigration($migrationManager, $lastMigration)) {
            $this->stderr("Failed to revert migration: $lastMigration\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nMigration reverted successfully!\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Display the migration history
     */
    public function actionHistory(int $limit = 10): int
    {
        $migrationManager = $this->getMigrationManager();
        $migrations = $migrationManager->getMigrationHistory($limit);

        if (empty($migrations)) {
            $this->stdout("No migration history found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Showing last " . count($migrations) . " applied migration(s):\n\n", Console::FG_YELLOW);

        foreach ($migrations as $migration => $time) {
            $this->stdout("  - $migration\n");
            $this->stdout("    Applied at: " . date('Y-m-d H:i:s', $time) . "\n", Console::FG_GREY);
        }

        return ExitCode::OK;
    }

    /**
     * Display pending migrations
     */
    public function actionPending(): int
    {
        $migrationManager = $this->getMigrationManager();
        $migrations = $migrationManager->getNewMigrations();

        if (empty($migrations)) {
            $this->stdout("No pending migrations found.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($migrations) . " pending migration(s):\n\n", Console::FG_YELLOW);

        foreach ($migrations as $migration) {
            $this->stdout("  - $migration\n");
        }

        return ExitCode::OK;
    }

    /**
     * Migrate existing reservation times from Europe/Zurich to UTC
     *
     * This command converts all existing booking times from Europe/Zurich timezone to UTC.
     * It's a one-time migration needed when implementing timezone support.
     *
     * Usage:
     *   php craft booking/migrate/timezone [--dry-run]
     *
     * Options:
     *   --dry-run  Show what would be changed without making actual changes
     */
    public function actionTimezone(): int
    {
        $dryRun = $this->interactive && $this->prompt(
            'Run in dry-run mode? (y/n)',
            ['default' => 'n']
        ) === 'y';

        $this->stdout("\n========================================\n", Console::FG_CYAN);
        $this->stdout("  Timezone Migration Tool\n", Console::FG_CYAN);
        $this->stdout("========================================\n\n", Console::FG_CYAN);

        if ($dryRun) {
            $this->stdout("Running in DRY-RUN mode - no changes will be made\n\n", Console::FG_YELLOW);
        }

        // Get all reservations
        $db = Craft::$app->getDb();
        $reservations = $db->createCommand(
            'SELECT id, bookingDate, startTime, endTime, userTimezone FROM {{%bookings_reservations}}'
        )->queryAll();

        if (empty($reservations)) {
            $this->stdout("No reservations found to migrate.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($reservations) . " reservation(s) to process\n\n", Console::FG_YELLOW);

        $converted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($reservations as $reservation) {
            $id = $reservation['id'];
            $originalDate = $reservation['bookingDate'];
            $originalStart = $reservation['startTime'];
            $originalEnd = $reservation['endTime'];
            $userTimezone = $reservation['userTimezone'] ?: 'Europe/Zurich';

            try {
                // Parse times in Europe/Zurich timezone (original storage format)
                $localStart = new \DateTime(
                    $originalDate . ' ' . $originalStart,
                    new \DateTimeZone('Europe/Zurich')
                );
                $localEnd = new \DateTime(
                    $originalDate . ' ' . $originalEnd,
                    new \DateTimeZone('Europe/Zurich')
                );

                // Convert to UTC
                $localStart->setTimezone(new \DateTimeZone('UTC'));
                $localEnd->setTimezone(new \DateTimeZone('UTC'));

                $utcDate = $localStart->format('Y-m-d');
                $utcStart = $localStart->format('H:i:s');
                $utcEnd = $localEnd->format('H:i:s');

                // Check if conversion is needed
                if ($utcDate === $originalDate && $utcStart === $originalStart && $utcEnd === $originalEnd) {
                    $this->stdout("[{$id}] No conversion needed\n", Console::FG_GREY);
                    $skipped++;
                    continue;
                }

                // Display conversion
                $this->stdout("[{$id}] Converting:\n", Console::FG_YELLOW);
                $this->stdout("      From: {$originalDate} {$originalStart}-{$originalEnd} (Europe/Zurich)\n");
                $this->stdout("      To:   {$utcDate} {$utcStart}-{$utcEnd} (UTC)\n");

                if ($utcDate !== $originalDate) {
                    $this->stdout("      ⚠ Date changed due to timezone crossing\n", Console::FG_YELLOW);
                }

                // Update database (unless dry-run)
                if (!$dryRun) {
                    $db->createCommand()->update(
                        '{{%bookings_reservations}}',
                        [
                            'bookingDate' => $utcDate,
                            'startTime' => $utcStart,
                            'endTime' => $utcEnd,
                        ],
                        ['id' => $id]
                    )->execute();
                }

                $converted++;

            } catch (\Exception $e) {
                $this->stderr("[{$id}] ERROR: " . $e->getMessage() . "\n", Console::FG_RED);
                Craft::error("Timezone migration failed for reservation {$id}: " . $e->getMessage(), __METHOD__);
                $errors++;
            }
        }

        // Summary
        $this->stdout("\n========================================\n", Console::FG_CYAN);
        $this->stdout("  Migration Summary\n", Console::FG_CYAN);
        $this->stdout("========================================\n\n", Console::FG_CYAN);
        $this->stdout("Total reservations:  " . count($reservations) . "\n");
        $this->stdout("Converted:           {$converted}\n", Console::FG_GREEN);
        $this->stdout("Skipped:             {$skipped}\n", Console::FG_GREY);
        $this->stdout("Errors:              {$errors}\n", $errors > 0 ? Console::FG_RED : Console::FG_GREY);

        if ($dryRun) {
            $this->stdout("\nDRY-RUN: No changes were made to the database.\n", Console::FG_YELLOW);
            $this->stdout("Run without --dry-run to apply changes.\n");
        } else {
            $this->stdout("\n✓ Migration completed successfully!\n", Console::FG_GREEN);
        }

        return $errors > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Get migration manager instance
     */
    protected function getMigrationManager(): MigrationManager
    {
        // Ensure migration table exists
        $this->ensureMigrationTableExists();

        $manager = new MigrationManager([
            'db' => Craft::$app->getDb(),
            'migrationNamespace' => $this->migrationNamespace,
            'migrationPath' => Craft::getAlias('@booking/migrations'),
            'migrationTable' => $this->migrationTable,
            'track' => 'booking', // Track as 'booking' plugin migrations
        ]);

        return $manager;
    }

    /**
     * Ensure the migration tracking table exists
     */
    protected function ensureMigrationTableExists(): void
    {
        $db = Craft::$app->getDb();
        $tableName = $this->migrationTable;

        // Check if table exists
        if ($db->getTableSchema($tableName, true) !== null) {
            return;
        }

        // Create the migration table
        $this->stdout("Creating migration tracking table...\n", Console::FG_YELLOW);

        $db->createCommand()
            ->createTable($tableName, [
                'name' => 'varchar(180) NOT NULL PRIMARY KEY',
                'track' => 'varchar(180) NOT NULL',
                'applyTime' => 'integer NOT NULL',
            ])
            ->execute();

        // Create index on track column
        $db->createCommand()
            ->createIndex('idx_track', $tableName, 'track')
            ->execute();

        $this->stdout("Migration tracking table created.\n", Console::FG_GREEN);
    }

    /**
     * Apply a migration
     */
    protected function applyMigration(MigrationManager $manager, string $migration): bool
    {
        $this->stdout(">>> Applying migration: $migration\n", Console::FG_YELLOW);

        $start = microtime(true);

        try {
            $manager->migrateUp($migration);
            $time = microtime(true) - $start;
            $this->stdout(">>> Migration applied successfully (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
            return true;
        } catch (\Exception $e) {
            $this->stderr(">>> Migration failed: " . $e->getMessage() . "\n\n", Console::FG_RED);
            Craft::error("Migration failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Revert a migration
     */
    protected function revertMigration(MigrationManager $manager, string $migration): bool
    {
        $this->stdout(">>> Reverting migration: $migration\n", Console::FG_YELLOW);

        $start = microtime(true);

        try {
            $manager->migrateDown($migration);
            $time = microtime(true) - $start;
            $this->stdout(">>> Migration reverted successfully (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
            return true;
        } catch (\Exception $e) {
            $this->stderr(">>> Migration revert failed: " . $e->getMessage() . "\n\n", Console::FG_RED);
            Craft::error("Migration revert failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
