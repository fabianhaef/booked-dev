<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251223_100000_add_title_and_multiple_days_to_schedules migration.
 *
 * Adds:
 * - title field for naming schedules (e.g., "Morning Shift", "Weekend Hours")
 * - daysOfWeek JSON field for storing multiple days instead of single dayOfWeek
 */
class m251223_100000_add_title_and_multiple_days_to_schedules extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add title column
        if (!$this->db->columnExists('{{%booked_schedules}}', 'title')) {
            $this->addColumn(
                '{{%booked_schedules}}',
                'title',
                $this->string()->null()->after('id')
            );
        }

        // Add daysOfWeek JSON column for storing array of days
        if (!$this->db->columnExists('{{%booked_schedules}}', 'daysOfWeek')) {
            $this->addColumn(
                '{{%booked_schedules}}',
                'daysOfWeek',
                $this->json()->null()->after('employeeId')
            );
        }

        // Migrate existing dayOfWeek values to daysOfWeek array
        $this->migrateExistingDayOfWeek();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Remove daysOfWeek column
        if ($this->db->columnExists('{{%booked_schedules}}', 'daysOfWeek')) {
            $this->dropColumn('{{%booked_schedules}}', 'daysOfWeek');
        }

        // Remove title column
        if ($this->db->columnExists('{{%booked_schedules}}', 'title')) {
            $this->dropColumn('{{%booked_schedules}}', 'title');
        }

        return true;
    }

    /**
     * Migrate existing single dayOfWeek values to daysOfWeek array
     * Converts from old format (0=Sunday, 6=Saturday) to new format (1=Monday, 7=Sunday)
     */
    private function migrateExistingDayOfWeek(): void
    {
        // Get all existing schedules with dayOfWeek set
        $schedules = (new \yii\db\Query())
            ->select(['id', 'dayOfWeek'])
            ->from('{{%booked_schedules}}')
            ->where(['not', ['dayOfWeek' => null]])
            ->all();

        foreach ($schedules as $schedule) {
            $oldDay = (int)$schedule['dayOfWeek'];

            // Convert from old format (0-6, Sunday-Saturday) to new format (1-7, Monday-Sunday)
            // Old: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
            // New: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
            $newDay = $oldDay === 0 ? 7 : $oldDay;

            // Convert single day to array
            $daysArray = [$newDay];

            // Update the record with the array
            $this->update(
                '{{%booked_schedules}}',
                ['daysOfWeek' => json_encode($daysArray)],
                ['id' => $schedule['id']]
            );
        }
    }
}
