<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * m250102_000005_create_employee_services_table migration.
 */
class m250102_000005_create_employee_services_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%booked_employees_services}}')) {
            $this->createTable('{{%booked_employees_services}}', [
                'id' => $this->primaryKey(),
                'employeeId' => $this->integer()->notNull(),
                'serviceId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(
                null,
                '{{%booked_employees_services}}',
                'employeeId',
                '{{%booked_employees}}',
                'id',
                'CASCADE',
                null
            );

            $this->addForeignKey(
                null,
                '{{%booked_employees_services}}',
                'serviceId',
                '{{%booked_services}}',
                'id',
                'CASCADE',
                null
            );

            $this->createIndex(null, '{{%booked_employees_services}}', ['employeeId', 'serviceId'], true);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%booked_employees_services}}');
        return true;
    }
}

