<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Add field layout ID columns to settings table
 */
class m250101_000003_add_field_layouts_to_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%bookings_settings}}';

        // Add field layout ID columns
        if (!$this->db->columnExists($table, 'employeeFieldLayoutId')) {
            $this->addColumn($table, 'employeeFieldLayoutId', $this->integer()->null()->after('id'));
            $this->addForeignKey(
                null,
                $table,
                'employeeFieldLayoutId',
                '{{%fieldlayouts}}',
                'id',
                'SET NULL',
                null
            );
        }

        if (!$this->db->columnExists($table, 'serviceFieldLayoutId')) {
            $this->addColumn($table, 'serviceFieldLayoutId', $this->integer()->null()->after('employeeFieldLayoutId'));
            $this->addForeignKey(
                null,
                $table,
                'serviceFieldLayoutId',
                '{{%fieldlayouts}}',
                'id',
                'SET NULL',
                null
            );
        }

        if (!$this->db->columnExists($table, 'locationFieldLayoutId')) {
            $this->addColumn($table, 'locationFieldLayoutId', $this->integer()->null()->after('serviceFieldLayoutId'));
            $this->addForeignKey(
                null,
                $table,
                'locationFieldLayoutId',
                '{{%fieldlayouts}}',
                'id',
                'SET NULL',
                null
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%bookings_settings}}';

        // Remove field layout ID columns
        if ($this->db->columnExists($table, 'locationFieldLayoutId')) {
            $this->dropForeignKey(null, $table);
            $this->dropColumn($table, 'locationFieldLayoutId');
        }

        if ($this->db->columnExists($table, 'serviceFieldLayoutId')) {
            $this->dropForeignKey(null, $table);
            $this->dropColumn($table, 'serviceFieldLayoutId');
        }

        if ($this->db->columnExists($table, 'employeeFieldLayoutId')) {
            $this->dropForeignKey(null, $table);
            $this->dropColumn($table, 'employeeFieldLayoutId');
        }

        return true;
    }
}

