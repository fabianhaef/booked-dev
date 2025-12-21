<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241028_100000_create_event_dates_table migration.
 * Creates a separate table for event dates with their own times
 */
class m241028_100000_create_event_dates_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create bookings_event_dates table
        if (!$this->db->tableExists('{{%bookings_event_dates}}')) {
            $this->createTable('{{%bookings_event_dates}}', [
                'id' => $this->primaryKey(),
                'availabilityId' => $this->integer()->notNull(),
                'eventDate' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add foreign key to availability table
            $this->addForeignKey(
                null,
                '{{%bookings_event_dates}}',
                'availabilityId',
                '{{%bookings_availability}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            // Add indexes for efficient lookups
            $this->createIndex(null, '{{%bookings_event_dates}}', ['availabilityId']);
            $this->createIndex(null, '{{%bookings_event_dates}}', ['eventDate']);
            $this->createIndex(null, '{{%bookings_event_dates}}', ['availabilityId', 'eventDate']);
        }

        // Remove eventDate column from availability table since we're moving it to event_dates
        if ($this->db->columnExists('{{%bookings_availability}}', 'eventDate')) {
            $this->dropColumn('{{%bookings_availability}}', 'eventDate');
        }

        // Make dayOfWeek, startTime, endTime nullable for event-type availabilities
        $this->alterColumn('{{%bookings_availability}}', 'dayOfWeek', $this->integer()->null());
        $this->alterColumn('{{%bookings_availability}}', 'startTime', $this->time()->null());
        $this->alterColumn('{{%bookings_availability}}', 'endTime', $this->time()->null());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Add eventDate column back to availability table
        if (!$this->db->columnExists('{{%bookings_availability}}', 'eventDate')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'eventDate',
                $this->date()->null()->after('availabilityType')
            );
        }

        // Drop the event_dates table
        $this->dropTableIfExists('{{%bookings_event_dates}}');

        return true;
    }
}
