<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241225_000001_add_sequential_booking migration.
 */
class m241225_000001_add_sequential_booking extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create booking sequences table
        $this->createTable('{{%booked_booking_sequences}}', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->null(),
            'customerEmail' => $this->string()->notNull(),
            'customerName' => $this->string()->notNull(),
            'status' => $this->string(20)->defaultValue('pending'),
            'totalPrice' => $this->decimal(10, 2)->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign key to users table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%booked_booking_sequences}}', 'userId'),
            '{{%booked_booking_sequences}}',
            'userId',
            '{{%users}}',
            'id',
            'SET NULL'
        );

        // Add indexes
        $this->createIndex(
            $this->db->getIndexName('{{%booked_booking_sequences}}', 'userId'),
            '{{%booked_booking_sequences}}',
            'userId'
        );

        $this->createIndex(
            $this->db->getIndexName('{{%booked_booking_sequences}}', 'customerEmail'),
            '{{%booked_booking_sequences}}',
            'customerEmail'
        );

        $this->createIndex(
            $this->db->getIndexName('{{%booked_booking_sequences}}', 'status'),
            '{{%booked_booking_sequences}}',
            'status'
        );

        // Add sequenceId to reservations table (note: table is named bookings_reservations)
        $this->addColumn(
            '{{%bookings_reservations}}',
            'sequenceId',
            $this->integer()->null()->after('id')
        );

        $this->addColumn(
            '{{%bookings_reservations}}',
            'sequenceOrder',
            $this->integer()->defaultValue(0)->after('sequenceId')
        );

        // Add foreign key from reservations to sequences
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%bookings_reservations}}', 'sequenceId'),
            '{{%bookings_reservations}}',
            'sequenceId',
            '{{%booked_booking_sequences}}',
            'id',
            'CASCADE'
        );

        // Add index on sequenceId
        $this->createIndex(
            $this->db->getIndexName('{{%bookings_reservations}}', 'sequenceId'),
            '{{%bookings_reservations}}',
            'sequenceId'
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop foreign key
        $this->dropForeignKey(
            $this->db->getForeignKeyName('{{%bookings_reservations}}', 'sequenceId'),
            '{{%bookings_reservations}}'
        );

        // Drop columns from reservations
        $this->dropColumn('{{%bookings_reservations}}', 'sequenceId');
        $this->dropColumn('{{%bookings_reservations}}', 'sequenceOrder');

        // Drop booking sequences table
        $this->dropTable('{{%booked_booking_sequences}}');

        return true;
    }
}
