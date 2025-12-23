<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * m251223_150000_add_missing_columns_to_reservations migration.
 */
class m251223_150000_add_missing_columns_to_reservations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%bookings_reservations}}';

        if (!$this->db->columnExists($table, 'employeeId')) {
            $this->addColumn($table, 'employeeId', $this->integer()->after('variationId'));
            $this->createIndex(null, $table, 'employeeId');
            $this->addForeignKey(
                null,
                $table,
                'employeeId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );
        }

        if (!$this->db->columnExists($table, 'locationId')) {
            $this->addColumn($table, 'locationId', $this->integer()->after('employeeId'));
            $this->createIndex(null, $table, 'locationId');
            $this->addForeignKey(
                null,
                $table,
                'locationId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );
        }

        if (!$this->db->columnExists($table, 'serviceId')) {
            $this->addColumn($table, 'serviceId', $this->integer()->after('locationId'));
            $this->createIndex(null, $table, 'serviceId');
            $this->addForeignKey(
                null,
                $table,
                'serviceId',
                '{{%elements}}',
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
        $table = '{{%bookings_reservations}}';

        $this->dropColumn($table, 'serviceId');
        $this->dropColumn($table, 'locationId');
        $this->dropColumn($table, 'employeeId');

        return true;
    }
}

