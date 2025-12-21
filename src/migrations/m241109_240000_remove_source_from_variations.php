<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Remove source fields from variations table (no longer needed as variations are selected per availability)
 */
class m241109_240000_remove_source_from_variations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Drop source columns from variations table
        if ($this->db->columnExists('{{%bookings_variations}}', 'sourceType')) {
            $this->dropColumn('{{%bookings_variations}}', 'sourceType');
        }

        if ($this->db->columnExists('{{%bookings_variations}}', 'sourceId')) {
            $this->dropColumn('{{%bookings_variations}}', 'sourceId');
        }

        if ($this->db->columnExists('{{%bookings_variations}}', 'sourceHandle')) {
            $this->dropColumn('{{%bookings_variations}}', 'sourceHandle');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Re-add columns if migration is rolled back
        $this->addColumn('{{%bookings_variations}}', 'sourceType', $this->string(50)->notNull()->defaultValue('section')->after('isActive'));
        $this->addColumn('{{%bookings_variations}}', 'sourceId', $this->integer()->after('sourceType'));
        $this->addColumn('{{%bookings_variations}}', 'sourceHandle', $this->string(255)->after('sourceId'));

        return true;
    }
}
