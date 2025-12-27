<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add service-level booking configuration fields
 *
 * - minTimeBeforeBooking: Minimum time (in minutes) required before booking
 * - minTimeBeforeCanceling: Minimum time (in minutes) required before canceling
 * - finalStepUrl: URL to redirect to after successful booking
 */
class m251227_020000_add_service_booking_settings extends Migration
{
    public function safeUp(): bool
    {
        // Add minimum time before booking (in minutes, null = use default from settings)
        if (!$this->db->columnExists('{{%booked_services}}', 'minTimeBeforeBooking')) {
            $this->addColumn(
                '{{%booked_services}}',
                'minTimeBeforeBooking',
                $this->integer()->null()->after('virtualMeetingProvider')
            );
        }

        // Add minimum time before canceling (in minutes, null = use default from settings)
        if (!$this->db->columnExists('{{%booked_services}}', 'minTimeBeforeCanceling')) {
            $this->addColumn(
                '{{%booked_services}}',
                'minTimeBeforeCanceling',
                $this->integer()->null()->after('minTimeBeforeBooking')
            );
        }

        // Add final step URL (null = use default done step)
        if (!$this->db->columnExists('{{%booked_services}}', 'finalStepUrl')) {
            $this->addColumn(
                '{{%booked_services}}',
                'finalStepUrl',
                $this->string(500)->null()->after('minTimeBeforeCanceling')
            );
        }

        Craft::info('Added service booking settings columns', __METHOD__);

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%booked_services}}', 'minTimeBeforeBooking')) {
            $this->dropColumn('{{%booked_services}}', 'minTimeBeforeBooking');
        }

        if ($this->db->columnExists('{{%booked_services}}', 'minTimeBeforeCanceling')) {
            $this->dropColumn('{{%booked_services}}', 'minTimeBeforeCanceling');
        }

        if ($this->db->columnExists('{{%booked_services}}', 'finalStepUrl')) {
            $this->dropColumn('{{%booked_services}}', 'finalStepUrl');
        }

        return true;
    }
}
