<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251223_134214_booked_add_scope_to_blackout_dates migration.
 */
class m251223_134214_booked_add_scope_to_blackout_dates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%bookings_blackout_dates}}', 'locationId', $this->integer()->after('id'));
        $this->addColumn('{{%bookings_blackout_dates}}', 'employeeId', $this->integer()->after('locationId'));

        $this->addForeignKey(
            null,
            '{{%bookings_blackout_dates}}',
            'locationId',
            '{{%elements}}',
            'id',
            'SET NULL',
            null
        );

        $this->addForeignKey(
            null,
            '{{%bookings_blackout_dates}}',
            'employeeId',
            '{{%elements}}',
            'id',
            'SET NULL',
            null
        );

        $this->createIndex(null, '{{%bookings_blackout_dates}}', ['locationId']);
        $this->createIndex(null, '{{%bookings_blackout_dates}}', ['employeeId']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKey('{{%bookings_blackout_dates}}', 'locationId');
        $this->dropForeignKey('{{%bookings_blackout_dates}}', 'employeeId');
        $this->dropColumn('{{%bookings_blackout_dates}}', 'locationId');
        $this->dropColumn('{{%bookings_blackout_dates}}', 'employeeId');

        return true;
    }
}
