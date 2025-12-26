<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251226_000001_remove_legacy_employee_id_from_schedules migration.
 *
 * Removes the legacy employeeId column from the schedules table.
 * Schedules now use the many-to-many booked_schedule_employees junction table instead.
 * This eliminates data inconsistency risk between the old one-to-many and new many-to-many relationships.
 */
class m251226_000001_remove_legacy_employee_id_from_schedules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Check if there's any data still using the legacy employeeId field
        $legacyData = (new \yii\db\Query())
            ->select(['id', 'title', 'employeeId'])
            ->from('{{%booked_schedules}}')
            ->where(['not', ['employeeId' => null]])
            ->all();

        if (count($legacyData) > 0) {
            echo "Found " . count($legacyData) . " schedules with legacy employeeId values.\n";
            echo "Migrating to junction table...\n";

            foreach ($legacyData as $schedule) {
                // Check if this relationship already exists in the junction table
                $exists = (new \yii\db\Query())
                    ->from('{{%booked_schedule_employees}}')
                    ->where([
                        'scheduleId' => $schedule['id'],
                        'employeeId' => $schedule['employeeId']
                    ])
                    ->exists();

                if (!$exists) {
                    // Add to junction table
                    $this->insert('{{%booked_schedule_employees}}', [
                        'scheduleId' => $schedule['id'],
                        'employeeId' => $schedule['employeeId'],
                        'dateCreated' => date('Y-m-d H:i:s'),
                        'dateUpdated' => date('Y-m-d H:i:s'),
                        'uid' => \craft\helpers\StringHelper::UUID(),
                    ]);

                    echo "  Migrated schedule '{$schedule['title']}' (ID: {$schedule['id']}) -> Employee ID: {$schedule['employeeId']}\n";
                } else {
                    echo "  Skipped schedule '{$schedule['title']}' (ID: {$schedule['id']}) - already in junction table\n";
                }
            }
        } else {
            echo "No legacy employeeId data found. All schedules already use junction table.\n";
        }

        // Drop foreign key constraint on employeeId before dropping the column
        echo "Dropping foreign key constraint on employeeId...\n";
        $foreignKeys = $this->db->getSchema()->getTableSchema('{{%booked_schedules}}')->foreignKeys;
        foreach ($foreignKeys as $fkName => $fkData) {
            if (isset($fkData['employeeId'])) {
                $this->dropForeignKey($fkName, '{{%booked_schedules}}');
                echo "  Dropped foreign key: {$fkName}\n";
            }
        }

        // Drop the legacy employeeId column
        echo "Dropping legacy employeeId column...\n";
        $this->dropColumn('{{%booked_schedules}}', 'employeeId');

        echo "Legacy employeeId field removed successfully!\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "Adding back employeeId column...\n";
        $this->addColumn('{{%booked_schedules}}', 'employeeId', $this->integer()->null()->after('title'));

        // Optionally restore foreign key
        $this->addForeignKey(
            null,
            '{{%booked_schedules}}',
            'employeeId',
            '{{%elements}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        echo "Note: employeeId column restored but data is NOT migrated back from junction table.\n";
        echo "Use the junction table (booked_schedule_employees) for schedule-employee relationships.\n";

        return true;
    }
}
