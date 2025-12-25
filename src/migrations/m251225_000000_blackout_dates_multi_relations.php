<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Migration to support multiple locations and employees for blackout dates
 * Converts from single foreign keys to many-to-many junction tables
 */
class m251225_000000_blackout_dates_multi_relations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create junction table for blackout dates <-> locations
        if (!$this->db->tableExists('{{%bookings_blackout_dates_locations}}')) {
            $this->createTable('{{%bookings_blackout_dates_locations}}', [
                'id' => $this->primaryKey(),
                'blackoutDateId' => $this->integer()->notNull(),
                'locationId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add foreign keys
            $this->addForeignKey(
                null,
                '{{%bookings_blackout_dates_locations}}',
                'blackoutDateId',
                '{{%bookings_blackout_dates}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            $this->addForeignKey(
                null,
                '{{%bookings_blackout_dates_locations}}',
                'locationId',
                '{{%elements}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            // Create index for faster lookups
            $this->createIndex(
                null,
                '{{%bookings_blackout_dates_locations}}',
                ['blackoutDateId', 'locationId'],
                true // unique
            );
        }

        // Create junction table for blackout dates <-> employees
        if (!$this->db->tableExists('{{%bookings_blackout_dates_employees}}')) {
            $this->createTable('{{%bookings_blackout_dates_employees}}', [
                'id' => $this->primaryKey(),
                'blackoutDateId' => $this->integer()->notNull(),
                'employeeId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add foreign keys
            $this->addForeignKey(
                null,
                '{{%bookings_blackout_dates_employees}}',
                'blackoutDateId',
                '{{%bookings_blackout_dates}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            $this->addForeignKey(
                null,
                '{{%bookings_blackout_dates_employees}}',
                'employeeId',
                '{{%elements}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            // Create index for faster lookups
            $this->createIndex(
                null,
                '{{%bookings_blackout_dates_employees}}',
                ['blackoutDateId', 'employeeId'],
                true // unique
            );
        }

        // Migrate existing data from single columns to junction tables
        $this->migrateExistingData();

        // Drop foreign key constraints before dropping columns
        $this->dropForeignKeyIfExists('{{%bookings_blackout_dates}}', 'locationId');
        $this->dropForeignKeyIfExists('{{%bookings_blackout_dates}}', 'employeeId');

        // Drop old single-relation columns
        if ($this->db->columnExists('{{%bookings_blackout_dates}}', 'locationId')) {
            $this->dropColumn('{{%bookings_blackout_dates}}', 'locationId');
        }

        if ($this->db->columnExists('{{%bookings_blackout_dates}}', 'employeeId')) {
            $this->dropColumn('{{%bookings_blackout_dates}}', 'employeeId');
        }

        return true;
    }

    /**
     * Migrate existing single relations to junction tables
     */
    protected function migrateExistingData(): void
    {
        // Migrate location relationships
        $blackoutDatesWithLocation = (new \craft\db\Query())
            ->select(['id', 'locationId'])
            ->from('{{%bookings_blackout_dates}}')
            ->where(['not', ['locationId' => null]])
            ->all();

        foreach ($blackoutDatesWithLocation as $row) {
            $this->insert('{{%bookings_blackout_dates_locations}}', [
                'blackoutDateId' => $row['id'],
                'locationId' => $row['locationId'],
                'dateCreated' => date('Y-m-d H:i:s'),
                'dateUpdated' => date('Y-m-d H:i:s'),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }

        // Migrate employee relationships
        $blackoutDatesWithEmployee = (new \craft\db\Query())
            ->select(['id', 'employeeId'])
            ->from('{{%bookings_blackout_dates}}')
            ->where(['not', ['employeeId' => null]])
            ->all();

        foreach ($blackoutDatesWithEmployee as $row) {
            $this->insert('{{%bookings_blackout_dates_employees}}', [
                'blackoutDateId' => $row['id'],
                'employeeId' => $row['employeeId'],
                'dateCreated' => date('Y-m-d H:i:s'),
                'dateUpdated' => date('Y-m-d H:i:s'),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Add back single-relation columns
        if (!$this->db->columnExists('{{%bookings_blackout_dates}}', 'locationId')) {
            $this->addColumn('{{%bookings_blackout_dates}}', 'locationId', $this->integer()->null());
        }

        if (!$this->db->columnExists('{{%bookings_blackout_dates}}', 'employeeId')) {
            $this->addColumn('{{%bookings_blackout_dates}}', 'employeeId', $this->integer()->null());
        }

        // Drop junction tables
        $this->dropTableIfExists('{{%bookings_blackout_dates_locations}}');
        $this->dropTableIfExists('{{%bookings_blackout_dates_employees}}');

        return true;
    }
}
