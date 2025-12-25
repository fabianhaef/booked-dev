<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Create booked_oauth_state_tokens table
 *
 * This table stores secure state tokens for OAuth flows to prevent CSRF attacks
 * and protect employeeId from exposure in base64-encoded state.
 */
class m250102_000002_create_oauth_state_tokens_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%booked_oauth_state_tokens}}')) {
            $this->createTable('{{%booked_oauth_state_tokens}}', [
                'id' => $this->primaryKey(),
                'token' => $this->string(36)->notNull()->unique(),
                'employeeId' => $this->integer()->notNull(),
                'provider' => $this->string(50)->notNull(),
                'createdAt' => $this->dateTime()->notNull(),
                'expiresAt' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_oauth_state_tokens}}', 'token', true);
            $this->createIndex(null, '{{%booked_oauth_state_tokens}}', 'employeeId');
            $this->createIndex(null, '{{%booked_oauth_state_tokens}}', 'expiresAt');

            $this->addForeignKey(
                null,
                '{{%booked_oauth_state_tokens}}',
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
        if ($this->db->tableExists('{{%booked_oauth_state_tokens}}')) {
            $this->dropTable('{{%booked_oauth_state_tokens}}');
        }

        return true;
    }
}
