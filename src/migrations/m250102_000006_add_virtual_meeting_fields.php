<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * m250102_000006_add_virtual_meeting_fields migration.
 */
class m250102_000006_add_virtual_meeting_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add virtualMeetingProvider to booked_services
        if ($this->db->tableExists('{{%booked_services}}')) {
            if (!$this->db->columnExists('{{%booked_services}}', 'virtualMeetingProvider')) {
                $this->addColumn('{{%booked_services}}', 'virtualMeetingProvider', $this->string()->after('price'));
            }
        }

        // Add enableVirtualMeetings to bookings_settings
        if ($this->db->tableExists('{{%bookings_settings}}')) {
            if (!$this->db->columnExists('{{%bookings_settings}}', 'enableVirtualMeetings')) {
                $this->addColumn('{{%bookings_settings}}', 'enableVirtualMeetings', $this->boolean()->notNull()->defaultValue(false)->after('rateLimitPerIp'));
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%booked_services}}')) {
            $this->dropColumn('{{%booked_services}}', 'virtualMeetingProvider');
        }

        if ($this->db->tableExists('{{%bookings_settings}}')) {
            $this->dropColumn('{{%bookings_settings}}', 'enableVirtualMeetings');
        }

        return true;
    }
}

