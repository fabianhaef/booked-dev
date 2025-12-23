<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add support for multiple employees per schedule
 */
class m251223_000000_add_multiple_employees_to_schedules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create junction table for schedule-employee relationships
        $this->createTable('{{%booked_schedule_employees}}', [
            'id' => $this->primaryKey(),
            'scheduleId' => $this->integer()->notNull(),
            'employeeId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add indexes
        $this->createIndex(null, '{{%booked_schedule_employees}}', ['scheduleId'], false);
        $this->createIndex(null, '{{%booked_schedule_employees}}', ['employeeId'], false);
        $this->createIndex(null, '{{%booked_schedule_employees}}', ['scheduleId', 'employeeId'], true);

        // Add foreign keys
        $this->addForeignKey(null, '{{%booked_schedule_employees}}', ['scheduleId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%booked_schedule_employees}}', ['employeeId'], '{{%elements}}', ['id'], 'CASCADE', null);

        // Migrate existing data from schedules table
        $this->migrateSingleEmployeeToMany();

        // Make employeeId nullable for new schedules using employeeIds array
        $this->alterColumn('{{%booked_schedules}}', 'employeeId', $this->integer()->null());

        return true;
    }

    /**
     * Migrate existing single-employee schedules to many-to-many relationship
     */
    private function migrateSingleEmployeeToMany(): void
    {
        // Get all existing schedules with employees
        $schedules = (new \yii\db\Query())
            ->select(['id', 'employeeId'])
            ->from('{{%booked_schedules}}')
            ->where(['not', ['employeeId' => null]])
            ->all();

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        foreach ($schedules as $schedule) {
            // Insert into junction table
            $this->insert('{{%booked_schedule_employees}}', [
                'scheduleId' => $schedule['id'],
                'employeeId' => $schedule['employeeId'],
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }

        Craft::info("Migrated " . count($schedules) . " schedules to many-to-many relationship", __METHOD__);
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251223_000000_add_multiple_employees_to_schedules cannot be reverted.\n";
        return false;
    }
}
