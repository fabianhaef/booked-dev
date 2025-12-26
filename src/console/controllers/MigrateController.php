<?php

namespace fabian\booked\console\controllers;

use Craft;
use craft\console\Controller;
use fabian\booked\elements\ServiceExtra;
use yii\console\ExitCode;

/**
 * Migration commands
 *
 * Provides CLI commands for data migrations
 */
class MigrateController extends Controller
{
    /**
     * Migrate existing ServiceExtra records to elements
     *
     * @return int
     */
    public function actionServiceExtras(): int
    {
        $this->stdout("Migrating ServiceExtra records to elements...\n");

        try {
            // Get all records from booked_service_extras table
            $db = Craft::$app->getDb();
            $records = $db->createCommand(
                'SELECT * FROM {{%booked_service_extras}}'
            )->queryAll();

            if (empty($records)) {
                $this->stdout("No ServiceExtra records found to migrate.\n", \yii\helpers\Console::FG_YELLOW);
                return ExitCode::OK;
            }

            $migrated = 0;
            $skipped = 0;

            foreach ($records as $record) {
                // Check if the ID conflicts with a different element type
                $elementType = $db->createCommand(
                    'SELECT type FROM {{%elements}} WHERE id = :id',
                    [':id' => $record['id']]
                )->queryScalar();

                if ($elementType === 'fabian\\booked\\elements\\ServiceExtra') {
                    // Already a valid ServiceExtra element
                    $this->stdout("  Skipping ID {$record['id']} - already a valid ServiceExtra element\n", \yii\helpers\Console::FG_YELLOW);
                    $skipped++;
                    continue;
                }

                if ($elementType && $elementType !== 'fabian\\booked\\elements\\ServiceExtra') {
                    // ID is occupied by another element type - delete the orphaned record
                    $this->stdout("  Removing orphaned record ID {$record['id']} (conflicts with {$elementType})\n");

                    $db->createCommand()
                        ->delete('{{%booked_service_extras}}', ['id' => $record['id']])
                        ->execute();
                } else {
                    // Orphaned record with no element entry - delete it
                    $this->stdout("  Removing orphaned record ID {$record['id']} (no element entry)\n");

                    $db->createCommand()
                        ->delete('{{%booked_service_extras}}', ['id' => $record['id']])
                        ->execute();
                }

                // Create new ServiceExtra element
                $extra = new ServiceExtra();
                $extra->title = $record['name'];
                $extra->price = (float)$record['price'];
                $extra->duration = (int)$record['duration'];
                $extra->maxQuantity = (int)$record['maxQuantity'];
                $extra->isRequired = (bool)$record['isRequired'];
                $extra->description = $record['description'];
                $extra->enabled = (bool)$record['enabled'];

                // Save the element - this will create the element entry and create a new record
                if (Craft::$app->elements->saveElement($extra, false)) {
                    $this->stdout("  ✓ Migrated '{$record['name']}' (old ID: {$record['id']}, new ID: {$extra->id})\n", \yii\helpers\Console::FG_GREEN);
                    $migrated++;
                } else {
                    $this->stderr("  ✗ Failed to migrate '{$record['name']}': " . implode(', ', $extra->getErrorSummary(true)) . "\n", \yii\helpers\Console::FG_RED);
                }
            }

            $this->stdout("\n");
            $this->stdout("Migration complete:\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("  Migrated: {$migrated}\n");
            $this->stdout("  Skipped: {$skipped}\n");

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("✗ Migration failed: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            $this->stderr("  {$e->getTraceAsString()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
