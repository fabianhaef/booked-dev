<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add source fields to availability table
 */
class m241015_000000_add_source_to_availability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add source fields to availability table
        if (!$this->db->columnExists('{{%bookings_availability}}', 'sourceType')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'sourceType',
                $this->enum('sourceType', ['entry', 'section'])->notNull()->defaultValue('section')->after('isActive')
            );
        }

        if (!$this->db->columnExists('{{%bookings_availability}}', 'sourceId')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'sourceId',
                $this->integer()->null()->after('sourceType')
            );
        }

        if (!$this->db->columnExists('{{%bookings_availability}}', 'sourceHandle')) {
            $this->addColumn(
                '{{%bookings_availability}}',
                'sourceHandle',
                $this->string()->null()->after('sourceId')
            );
        }

        // Add indexes for better performance
        $this->createIndex(
            null,
            '{{%bookings_availability}}',
            ['sourceType', 'sourceId', 'isActive']
        );

        $this->createIndex(
            null,
            '{{%bookings_availability}}',
            ['sourceType', 'sourceHandle', 'isActive']
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%bookings_availability}}', 'sourceHandle');
        $this->dropColumn('{{%bookings_availability}}', 'sourceId');
        $this->dropColumn('{{%bookings_availability}}', 'sourceType');

        return true;
    }
}
