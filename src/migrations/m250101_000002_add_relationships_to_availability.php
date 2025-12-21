<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Add employeeId, locationId, serviceId to availability table
 */
class m250101_000002_add_relationships_to_availability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%bookings_availability}}';

        // Add employeeId column
        if (!$this->db->columnExists($table, 'employeeId')) {
            $this->addColumn($table, 'employeeId', $this->integer()->null()->after('sourceHandle'));
            $this->addForeignKey(
                null,
                $table,
                'employeeId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );
            $this->createIndex(null, $table, ['employeeId']);
        }

        // Add locationId column
        if (!$this->db->columnExists($table, 'locationId')) {
            $this->addColumn($table, 'locationId', $this->integer()->null()->after('employeeId'));
            $this->addForeignKey(
                null,
                $table,
                'locationId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );
            $this->createIndex(null, $table, ['locationId']);
        }

        // Add serviceId column
        if (!$this->db->columnExists($table, 'serviceId')) {
            $this->addColumn($table, 'serviceId', $this->integer()->null()->after('locationId'));
            $this->addForeignKey(
                null,
                $table,
                'serviceId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );
            $this->createIndex(null, $table, ['serviceId']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%bookings_availability}}';

        // Remove serviceId
        if ($this->db->columnExists($table, 'serviceId')) {
            $this->dropForeignKey(null, $table);
            $this->dropIndex(null, $table);
            $this->dropColumn($table, 'serviceId');
        }

        // Remove locationId
        if ($this->db->columnExists($table, 'locationId')) {
            $this->dropForeignKey(null, $table);
            $this->dropIndex(null, $table);
            $this->dropColumn($table, 'locationId');
        }

        // Remove employeeId
        if ($this->db->columnExists($table, 'employeeId')) {
            $this->dropForeignKey(null, $table);
            $this->dropIndex(null, $table);
            $this->dropColumn($table, 'employeeId');
        }

        return true;
    }
}

