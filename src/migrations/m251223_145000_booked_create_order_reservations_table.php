<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251223_145000_booked_create_order_reservations_table migration.
 */
class m251223_145000_booked_create_order_reservations_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%booked_order_reservations}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'reservationId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            '{{%booked_order_reservations}}',
            'reservationId',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        // We can't strictly foreign key to commerce_orders if commerce is not installed,
        // but Craft Commerce usually creates its tables with 'commerce_' prefix.
        // However, it's safer to just set the integer for now if we want to be flexible,
        // or check if the table exists.
        
        if ($this->db->tableExists('{{%commerce_orders}}')) {
            $this->addForeignKey(
                null,
                '{{%booked_order_reservations}}',
                'orderId',
                '{{%commerce_orders}}',
                'id',
                'CASCADE',
                null
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTable('{{%booked_order_reservations}}');
        return true;
    }
}

