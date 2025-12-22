<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Create booked_soft_locks table
 */
class m250102_000000_create_soft_locks_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%booked_soft_locks}}')) {
            $this->createTable('{{%booked_soft_locks}}', [
                'id' => $this->primaryKey(),
                'token' => $this->string()->notNull(),
                'serviceId' => $this->integer()->notNull(),
                'employeeId' => $this->integer(),
                'locationId' => $this->integer(),
                'date' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'expiresAt' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_soft_locks}}', ['token'], true);
            $this->createIndex(null, '{{%booked_soft_locks}}', ['expiresAt']);
            $this->createIndex(null, '{{%booked_soft_locks}}', ['date', 'startTime', 'serviceId', 'employeeId']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%booked_soft_locks}}')) {
            $this->dropTable('{{%booked_soft_locks}}');
        }

        return true;
    }
}

