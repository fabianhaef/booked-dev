<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add description field to availability table
 */
class m241109_000000_add_description_to_availability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bookings_availability}}', 'description')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'description',
                $this->text()->after('availabilityType')
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bookings_availability}}', 'description')) {
            $this->dropColumn('{{%bookings_availability}}', 'description');
        }

        return true;
    }
}
