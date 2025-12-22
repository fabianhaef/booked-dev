<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Create booked_external_events table
 */
class m250102_000002_create_external_events_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%booked_external_events}}')) {
            $this->createTable('{{%booked_external_events}}', [
                'id' => $this->primaryKey(),
                'employeeId' => $this->integer()->notNull(),
                'provider' => $this->string(50)->notNull(),
                'externalId' => $this->string()->notNull(),
                'summary' => $this->string(),
                'startDate' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endDate' => $this->date()->notNull(),
                'endTime' => $this->time()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_external_events}}', ['employeeId', 'provider', 'externalId'], true);
            $this->createIndex(null, '{{%booked_external_events}}', ['startDate']);
            
            $this->addForeignKey(
                null,
                '{{%booked_external_events}}',
                'employeeId',
                '{{%booked_employees}}',
                'id',
                'CASCADE',
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
        if ($this->db->tableExists('{{%booked_external_events}}')) {
            $this->dropTable('{{%booked_external_events}}');
        }

        return true;
    }
}

