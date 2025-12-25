<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m250101_000000_add_service_extras migration.
 *
 * Adds support for service extras and add-ons that can be selected during booking.
 * Allows businesses to upsell additional services, products, or upgrades.
 */
class m250101_000000_add_service_extras extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create service_extras table
        $this->createTable('{{%booked_service_extras}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue(0),
            'duration' => $this->integer()->notNull()->defaultValue(0)->comment('Additional duration in minutes'),
            'maxQuantity' => $this->integer()->notNull()->defaultValue(1)->comment('Max quantity per booking'),
            'isRequired' => $this->boolean()->notNull()->defaultValue(false),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Create service_extras_services junction table (many-to-many)
        $this->createTable('{{%booked_service_extras_services}}', [
            'id' => $this->primaryKey(),
            'extraId' => $this->integer()->notNull(),
            'serviceId' => $this->integer()->notNull(),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Create reservation_extras table (tracks selected extras per booking)
        $this->createTable('{{%booked_reservation_extras}}', [
            'id' => $this->primaryKey(),
            'reservationId' => $this->integer()->notNull(),
            'extraId' => $this->integer()->notNull(),
            'quantity' => $this->integer()->notNull()->defaultValue(1),
            'price' => $this->decimal(10, 2)->notNull()->comment('Price at time of booking'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add indexes
        $this->createIndex(null, '{{%booked_service_extras}}', ['enabled', 'sortOrder']);
        $this->createIndex(null, '{{%booked_service_extras_services}}', ['extraId', 'serviceId'], true); // Unique
        $this->createIndex(null, '{{%booked_service_extras_services}}', 'serviceId');
        $this->createIndex(null, '{{%booked_reservation_extras}}', ['reservationId']);
        $this->createIndex(null, '{{%booked_reservation_extras}}', 'extraId');

        // Add foreign keys
        $this->addForeignKey(
            null,
            '{{%booked_service_extras_services}}',
            'extraId',
            '{{%booked_service_extras}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%booked_service_extras_services}}',
            'serviceId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%booked_reservation_extras}}',
            'reservationId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%booked_reservation_extras}}',
            'extraId',
            '{{%booked_service_extras}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop foreign keys
        $this->dropAllForeignKeysToTable('{{%booked_service_extras_services}}');
        $this->dropAllForeignKeysToTable('{{%booked_reservation_extras}}');

        // Drop tables
        $this->dropTableIfExists('{{%booked_reservation_extras}}');
        $this->dropTableIfExists('{{%booked_service_extras_services}}');
        $this->dropTableIfExists('{{%booked_service_extras}}');

        return true;
    }
}
