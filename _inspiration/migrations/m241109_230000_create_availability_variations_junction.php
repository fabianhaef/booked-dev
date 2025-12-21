<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Create junction table for availability-variation many-to-many relationship
 * and remove slot duration/buffer overrides from availability table
 */
class m241109_230000_create_availability_variations_junction extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create junction table for availability-variation relationship
        $this->createTable('{{%bookings_availability_variations}}', [
            'id' => $this->primaryKey(),
            'availabilityId' => $this->integer()->notNull(),
            'variationId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Add foreign keys
        $this->addForeignKey(
            null,
            '{{%bookings_availability_variations}}',
            'availabilityId',
            '{{%bookings_availability}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            '{{%bookings_availability_variations}}',
            'variationId',
            '{{%bookings_variations}}',
            'id',
            'CASCADE',
            null
        );

        // Add indexes
        $this->createIndex(null, '{{%bookings_availability_variations}}', 'availabilityId');
        $this->createIndex(null, '{{%bookings_availability_variations}}', 'variationId');

        // Ensure unique combinations
        $this->createIndex(
            null,
            '{{%bookings_availability_variations}}',
            ['availabilityId', 'variationId'],
            true
        );

        // Drop slot duration and buffer columns from availability table (now comes from variation)
        if ($this->db->columnExists('{{%bookings_availability}}', 'slotDurationMinutes')) {
            $this->dropColumn('{{%bookings_availability}}', 'slotDurationMinutes');
        }

        if ($this->db->columnExists('{{%bookings_availability}}', 'bufferMinutes')) {
            $this->dropColumn('{{%bookings_availability}}', 'bufferMinutes');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Re-add columns to availability table
        $this->addColumn('{{%bookings_availability}}', 'slotDurationMinutes', $this->integer()->after('description'));
        $this->addColumn('{{%bookings_availability}}', 'bufferMinutes', $this->integer()->after('slotDurationMinutes'));

        // Drop junction table
        $this->dropTableIfExists('{{%bookings_availability_variations}}');

        return true;
    }
}
