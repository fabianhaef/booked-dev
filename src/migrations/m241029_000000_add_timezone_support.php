<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add timezone support to reservations table
 * This migration adds userTimezone field to store the user's timezone
 */
class m241029_000000_add_timezone_support extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add userTimezone field to reservations table
        if (!$this->db->columnExists('{{%bookings_reservations}}', 'userTimezone')) {
            $this->addColumn(
                '{{%bookings_reservations}}',
                'userTimezone',
                $this->string(50)->null()->after('userPhone')->comment('User\'s timezone identifier (e.g. Europe/Zurich)')
            );
        }

        // Set default timezone for existing records
        $this->update(
            '{{%bookings_reservations}}',
            ['userTimezone' => 'Europe/Zurich'],
            ['userTimezone' => null]
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bookings_reservations}}', 'userTimezone')) {
            $this->dropColumn('{{%bookings_reservations}}', 'userTimezone');
        }

        return true;
    }
}
