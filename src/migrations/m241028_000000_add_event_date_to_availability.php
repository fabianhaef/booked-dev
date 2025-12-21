<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241028_000000_add_event_date_to_availability migration.
 */
class m241028_000000_add_event_date_to_availability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add availabilityType column (recurring or event)
        if (!$this->db->columnExists('{{%bookings_availability}}', 'availabilityType')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'availabilityType',
                $this->enum('availabilityType', ['recurring', 'event'])
                    ->notNull()
                    ->defaultValue('recurring')
                    ->after('isActive')
            );
        }

        // Add eventDate column for one-off events
        if (!$this->db->columnExists('{{%bookings_availability}}', 'eventDate')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'eventDate',
                $this->date()->null()->after('availabilityType')
            );
        }

        // Add index for event date lookups
        $this->createIndex(
            null,
            '{{%bookings_availability}}',
            ['availabilityType', 'eventDate', 'isActive']
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop index
        $this->dropIndex(
            ['availabilityType', 'eventDate', 'isActive'],
            '{{%bookings_availability}}'
        );

        // Drop columns
        if ($this->db->columnExists('{{%bookings_availability}}', 'eventDate')) {
            $this->dropColumn('{{%bookings_availability}}', 'eventDate');
        }

        if ($this->db->columnExists('{{%bookings_availability}}', 'availabilityType')) {
            $this->dropColumn('{{%bookings_availability}}', 'availabilityType');
        }

        return true;
    }
}
