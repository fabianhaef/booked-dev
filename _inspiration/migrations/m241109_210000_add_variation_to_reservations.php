<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add variationId to reservations table
 */
class m241109_210000_add_variation_to_reservations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%bookings_reservations}}', 'variationId', $this->integer()->after('sourceHandle'));

        // Add foreign key to variations table
        $this->addForeignKey(
            null,
            '{{%bookings_reservations}}',
            'variationId',
            '{{%bookings_variations}}',
            'id',
            'SET NULL',
            null
        );

        // Add index
        $this->createIndex(null, '{{%bookings_reservations}}', 'variationId');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%bookings_reservations}}', 'variationId');
        return true;
    }
}
