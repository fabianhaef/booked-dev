<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Create booked_calendar_tokens table
 */
class m250102_000001_create_calendar_tokens_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%booked_calendar_tokens}}')) {
            $this->createTable('{{%booked_calendar_tokens}}', [
                'id' => $this->primaryKey(),
                'employeeId' => $this->integer()->notNull(),
                'provider' => $this->string(50)->notNull(),
                'accessToken' => $this->text()->notNull(),
                'refreshToken' => $this->text(),
                'expiresAt' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_calendar_tokens}}', ['employeeId', 'provider'], true);
            
            $this->addForeignKey(
                null,
                '{{%booked_calendar_tokens}}',
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
        if ($this->db->tableExists('{{%booked_calendar_tokens}}')) {
            $this->dropTable('{{%booked_calendar_tokens}}');
        }

        return true;
    }
}

