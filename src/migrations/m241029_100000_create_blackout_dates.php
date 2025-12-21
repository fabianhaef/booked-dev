<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Create blackout_dates table for holidays, vacations, and unavailable date ranges
 */
class m241029_100000_create_blackout_dates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create bookings_blackout_dates table
        if (!$this->db->tableExists('{{%bookings_blackout_dates}}')) {
            $this->createTable('{{%bookings_blackout_dates}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull()->comment('Name/description of the blackout period (e.g. "Christmas Holiday")'),
                'startDate' => $this->date()->notNull()->comment('First day of the blackout period'),
                'endDate' => $this->date()->notNull()->comment('Last day of the blackout period'),
                'reason' => $this->text()->null()->comment('Optional reason for the blackout'),
                'isActive' => $this->boolean()->notNull()->defaultValue(true)->comment('Whether this blackout is currently active'),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add indexes for better performance
            $this->createIndex(null, '{{%bookings_blackout_dates}}', ['startDate', 'endDate']);
            $this->createIndex(null, '{{%bookings_blackout_dates}}', ['isActive']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bookings_blackout_dates}}');
        return true;
    }
}
