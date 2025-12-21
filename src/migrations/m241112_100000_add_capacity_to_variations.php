<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add capacity tracking fields to variations table
 */
class m241112_100000_add_capacity_to_variations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add maxCapacity field - default to 1 for backward compatibility
        $this->addColumn(
            '{{%bookings_variations}}',
            'maxCapacity',
            $this->integer()->defaultValue(1)->notNull()->after('bufferMinutes')
        );

        // Add allowQuantitySelection field - allows users to book multiple spots in one reservation
        $this->addColumn(
            '{{%bookings_variations}}',
            'allowQuantitySelection',
            $this->boolean()->defaultValue(false)->notNull()->after('maxCapacity')
        );

        // Add index for capacity queries
        $this->createIndex(
            'idx_bookings_variations_capacity',
            '{{%bookings_variations}}',
            'maxCapacity'
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop index
        $this->dropIndexIfExists('{{%bookings_variations}}', 'idx_bookings_variations_capacity');

        // Drop columns
        if ($this->db->columnExists('{{%bookings_variations}}', 'allowQuantitySelection')) {
            $this->dropColumn('{{%bookings_variations}}', 'allowQuantitySelection');
        }

        if ($this->db->columnExists('{{%bookings_variations}}', 'maxCapacity')) {
            $this->dropColumn('{{%bookings_variations}}', 'maxCapacity');
        }

        return true;
    }
}
