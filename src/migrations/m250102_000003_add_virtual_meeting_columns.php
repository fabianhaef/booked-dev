<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Add virtual meeting columns to reservations table
 */
class m250102_000003_add_virtual_meeting_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%booked_reservations}}';
        
        if (!$this->db->columnExists($table, 'virtualMeetingUrl')) {
            $this->addColumn($table, 'virtualMeetingUrl', $this->string()->after('notes'));
        }
        
        if (!$this->db->columnExists($table, 'virtualMeetingProvider')) {
            $this->addColumn($table, 'virtualMeetingProvider', $this->string(50)->after('virtualMeetingUrl'));
        }

        if (!$this->db->columnExists($table, 'virtualMeetingId')) {
            $this->addColumn($table, 'virtualMeetingId', $this->string()->after('virtualMeetingProvider'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%booked_reservations}}';
        
        if ($this->db->columnExists($table, 'virtualMeetingUrl')) {
            $this->dropColumn($table, 'virtualMeetingUrl');
        }
        
        if ($this->db->columnExists($table, 'virtualMeetingProvider')) {
            $this->dropColumn($table, 'virtualMeetingProvider');
        }

        if ($this->db->columnExists($table, 'virtualMeetingId')) {
            $this->dropColumn($table, 'virtualMeetingId');
        }

        return true;
    }
}

