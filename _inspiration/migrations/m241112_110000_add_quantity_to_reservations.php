<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add quantity tracking to reservations table
 */
class m241112_110000_add_quantity_to_reservations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add quantity field - how many spots this reservation takes
        // Default to 1 for backward compatibility and existing reservations
        $this->addColumn(
            '{{%bookings_reservations}}',
            'quantity',
            $this->integer()->defaultValue(1)->notNull()->after('variationId')
        );

        // Add composite index for capacity queries
        // This helps efficiently calculate remaining capacity for a specific time slot
        $this->createIndex(
            'idx_bookings_reservations_capacity_lookup',
            '{{%bookings_reservations}}',
            ['bookingDate', 'startTime', 'endTime', 'variationId', 'status']
        );

        // Update existing reservations to have quantity = 1
        $this->update(
            '{{%bookings_reservations}}',
            ['quantity' => 1],
            'quantity IS NULL OR quantity = 0'
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop index
        $this->dropIndexIfExists('{{%bookings_reservations}}', 'idx_bookings_reservations_capacity_lookup');

        // Drop column
        if ($this->db->columnExists('{{%bookings_reservations}}', 'quantity')) {
            $this->dropColumn('{{%bookings_reservations}}', 'quantity');
        }

        return true;
    }
}
