<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Remove price, sku, and maxCapacity columns from variations table
 */
class m241109_220000_remove_ecommerce_fields_from_variations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Drop columns from bookings_variations table
        if ($this->db->columnExists('{{%bookings_variations}}', 'price')) {
            $this->dropColumn('{{%bookings_variations}}', 'price');
        }

        if ($this->db->columnExists('{{%bookings_variations}}', 'sku')) {
            $this->dropColumn('{{%bookings_variations}}', 'sku');
        }

        if ($this->db->columnExists('{{%bookings_variations}}', 'maxCapacity')) {
            $this->dropColumn('{{%bookings_variations}}', 'maxCapacity');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Re-add columns if migration is rolled back
        $this->addColumn('{{%bookings_variations}}', 'price', $this->decimal(10, 2)->after('bufferMinutes'));
        $this->addColumn('{{%bookings_variations}}', 'sku', $this->string(255)->after('price'));
        $this->addColumn('{{%bookings_variations}}', 'maxCapacity', $this->integer()->after('sku'));

        return true;
    }
}
