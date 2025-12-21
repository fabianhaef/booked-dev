<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Create core element tables: Service, Employee, Location, Schedule
 */
class m250101_000000_create_core_elements extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create booked_services table
        if (!$this->db->tableExists('{{%booked_services}}')) {
            $this->createTable('{{%booked_services}}', [
                'id' => $this->integer()->notNull(),
                'duration' => $this->integer(),
                'bufferBefore' => $this->integer(),
                'bufferAfter' => $this->integer(),
                'price' => $this->decimal(10, 2),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]])',
            ]);

            $this->addForeignKey(
                null,
                '{{%booked_services}}',
                'id',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );
        }

        // Create booked_employees table
        if (!$this->db->tableExists('{{%booked_employees}}')) {
            $this->createTable('{{%booked_employees}}', [
                'id' => $this->integer()->notNull(),
                'userId' => $this->integer(),
                'locationId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]])',
            ]);

            $this->addForeignKey(
                null,
                '{{%booked_employees}}',
                'id',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );

            $this->addForeignKey(
                null,
                '{{%booked_employees}}',
                'userId',
                '{{%users}}',
                'id',
                'SET NULL',
                null
            );

            $this->addForeignKey(
                null,
                '{{%booked_employees}}',
                'locationId',
                '{{%elements}}',
                'id',
                'SET NULL',
                null
            );

            $this->createIndex(null, '{{%booked_employees}}', ['userId']);
            $this->createIndex(null, '{{%booked_employees}}', ['locationId']);
        }

        // Create booked_locations table
        if (!$this->db->tableExists('{{%booked_locations}}')) {
            $this->createTable('{{%booked_locations}}', [
                'id' => $this->integer()->notNull(),
                'address' => $this->text(),
                'timezone' => $this->string(50),
                'contactInfo' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]])',
            ]);

            $this->addForeignKey(
                null,
                '{{%booked_locations}}',
                'id',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );
        }

        // Create booked_schedules table
        if (!$this->db->tableExists('{{%booked_schedules}}')) {
            $this->createTable('{{%booked_schedules}}', [
                'id' => $this->integer()->notNull(),
                'employeeId' => $this->integer()->notNull(),
                'dayOfWeek' => $this->integer()->notNull(),
                'startTime' => $this->time(),
                'endTime' => $this->time(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY([[id]])',
            ]);

            $this->addForeignKey(
                null,
                '{{%booked_schedules}}',
                'id',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );

            $this->addForeignKey(
                null,
                '{{%booked_schedules}}',
                'employeeId',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );

            $this->createIndex(null, '{{%booked_schedules}}', ['employeeId', 'dayOfWeek']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop tables in reverse order
        if ($this->db->tableExists('{{%booked_schedules}}')) {
            $this->dropTable('{{%booked_schedules}}');
        }

        if ($this->db->tableExists('{{%booked_locations}}')) {
            $this->dropTable('{{%booked_locations}}');
        }

        if ($this->db->tableExists('{{%booked_employees}}')) {
            $this->dropTable('{{%booked_employees}}');
        }

        if ($this->db->tableExists('{{%booked_services}}')) {
            $this->dropTable('{{%booked_services}}');
        }

        return true;
    }
}

