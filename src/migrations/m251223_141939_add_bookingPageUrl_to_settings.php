<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251223_141939_add_bookingPageUrl_to_settings migration.
 */
class m251223_141939_add_bookingPageUrl_to_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bookings_settings}}', 'bookingPageUrl')) {
            $this->addColumn('{{%bookings_settings}}', 'bookingPageUrl', $this->string()->after('defaultViewMode'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bookings_settings}}', 'bookingPageUrl')) {
            $this->dropColumn('{{%bookings_settings}}', 'bookingPageUrl');
        }

        return true;
    }
}
