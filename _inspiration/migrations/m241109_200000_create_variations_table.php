<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Create booking variations table
 */
class m241109_200000_create_variations_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%bookings_variations}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'slotDurationMinutes' => $this->integer(),
            'bufferMinutes' => $this->integer(),
            'price' => $this->decimal(10, 2),
            'sku' => $this->string(255),
            'maxCapacity' => $this->integer(),
            'isActive' => $this->boolean()->notNull()->defaultValue(true),
            'sourceType' => $this->string(50)->notNull()->defaultValue('section'),
            'sourceId' => $this->integer(),
            'sourceHandle' => $this->string(255),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign key to elements table
        $this->addForeignKey(
            null,
            '{{%bookings_variations}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        // Add indexes for common queries
        $this->createIndex(null, '{{%bookings_variations}}', 'name');
        $this->createIndex(null, '{{%bookings_variations}}', 'isActive');
        $this->createIndex(null, '{{%bookings_variations}}', 'sourceType');
        $this->createIndex(null, '{{%bookings_variations}}', 'sourceId');
        $this->createIndex(null, '{{%bookings_variations}}', 'sourceHandle');
        $this->createIndex(null, '{{%bookings_variations}}', 'price');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bookings_variations}}');
        return true;
    }
}
