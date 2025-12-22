<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * m250102_000004_add_reminder_sent_columns migration.
 */
class m250102_000004_add_reminder_sent_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%bookings_reservations}}';

        if (!$this->db->columnExists($table, 'emailReminder24hSent')) {
            $this->addColumn($table, 'emailReminder24hSent', $this->boolean()->notNull()->defaultValue(false)->after('notificationSent'));
        }

        if (!$this->db->columnExists($table, 'emailReminder1hSent')) {
            $this->addColumn($table, 'emailReminder1hSent', $this->boolean()->notNull()->defaultValue(false)->after('emailReminder24hSent'));
        }

        if (!$this->db->columnExists($table, 'smsReminder24hSent')) {
            $this->addColumn($table, 'smsReminder24hSent', $this->boolean()->notNull()->defaultValue(false)->after('emailReminder1hSent'));
        }

        if (!$this->db->columnExists($table, 'smsReminder1hSent')) {
            $this->addColumn($table, 'smsReminder1hSent', $this->boolean()->notNull()->defaultValue(false)->after('smsReminder24hSent'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%bookings_reservations}}';

        $this->dropColumn($table, 'emailReminder24hSent');
        $this->dropColumn($table, 'emailReminder1hSent');
        $this->dropColumn($table, 'smsReminder24hSent');
        $this->dropColumn($table, 'smsReminder1hSent');

        return true;
    }
}

