<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * m241109_200000_add_duration_buffer_to_availability migration.
 */
class m241109_200000_add_duration_buffer_to_availability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add slotDurationMinutes and bufferMinutes to bookings_availability table
        $this->addColumn(
            '{{%bookings_availability}}',
            'slotDurationMinutes',
            $this->integer()->unsigned()->null()->after('endTime')
        );

        $this->addColumn(
            '{{%bookings_availability}}',
            'bufferMinutes',
            $this->integer()->unsigned()->null()->after('slotDurationMinutes')
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%bookings_availability}}', 'bufferMinutes');
        $this->dropColumn('{{%bookings_availability}}', 'slotDurationMinutes');

        return true;
    }
}

