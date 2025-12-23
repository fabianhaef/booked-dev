<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251223_132811_booked_add_address_fields_to_locations migration.
 */
class m251223_132811_booked_add_address_fields_to_locations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%booked_locations}}', 'addressLine1', $this->string());
        $this->addColumn('{{%booked_locations}}', 'addressLine2', $this->string());
        $this->addColumn('{{%booked_locations}}', 'locality', $this->string());
        $this->addColumn('{{%booked_locations}}', 'administrativeArea', $this->string());
        $this->addColumn('{{%booked_locations}}', 'postalCode', $this->string());
        $this->addColumn('{{%booked_locations}}', 'countryCode', $this->string(2));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%booked_locations}}', 'addressLine1');
        $this->dropColumn('{{%booked_locations}}', 'addressLine2');
        $this->dropColumn('{{%booked_locations}}', 'locality');
        $this->dropColumn('{{%booked_locations}}', 'administrativeArea');
        $this->dropColumn('{{%booked_locations}}', 'postalCode');
        $this->dropColumn('{{%booked_locations}}', 'countryCode');

        return true;
    }
}
