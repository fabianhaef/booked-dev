<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add source fields to reservations table
 */
class m241015_000001_add_source_to_reservations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add source fields to reservations table
        if (!$this->db->columnExists('{{%bookings_reservations}}', 'sourceType')) {
            $this->addColumn(
                '{{%bookings_reservations}}',
                'sourceType',
                $this->enum('sourceType', ['entry', 'section'])->null()->after('status')
            );
        }

        if (!$this->db->columnExists('{{%bookings_reservations}}', 'sourceId')) {
            $this->addColumn(
                '{{%bookings_reservations}}',
                'sourceId',
                $this->integer()->null()->after('sourceType')
            );
        }

        if (!$this->db->columnExists('{{%bookings_reservations}}', 'sourceHandle')) {
            $this->addColumn(
                '{{%bookings_reservations}}',
                'sourceHandle',
                $this->string()->null()->after('sourceId')
            );
        }

        // Add indexes for better performance
        $this->createIndex(
            null,
            '{{%bookings_reservations}}',
            ['sourceType', 'sourceId']
        );

        $this->createIndex(
            null,
            '{{%bookings_reservations}}',
            ['sourceType', 'sourceHandle']
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%bookings_reservations}}', 'sourceHandle');
        $this->dropColumn('{{%bookings_reservations}}', 'sourceId');
        $this->dropColumn('{{%bookings_reservations}}', 'sourceType');

        return true;
    }
}
