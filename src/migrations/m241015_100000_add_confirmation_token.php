<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241015_100000_add_confirmation_token migration.
 */
class m241015_100000_add_confirmation_token extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add confirmationToken column to reservations table
        if (!$this->db->columnExists('{{%bookings_reservations}}', 'confirmationToken')) {
            $this->addColumn(
                '{{%bookings_reservations}}',
                'confirmationToken',
                $this->string(64)->notNull()->after('notificationSent')
            );

            // Add unique index for fast lookups
            $this->createIndex(
                'idx_confirmationToken',
                '{{%bookings_reservations}}',
                'confirmationToken',
                true // unique
            );

            // Generate tokens for existing reservations
            $this->generateTokensForExistingReservations();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bookings_reservations}}', 'confirmationToken')) {
            $this->dropIndex('idx_confirmationToken', '{{%bookings_reservations}}');
            $this->dropColumn('{{%bookings_reservations}}', 'confirmationToken');
        }

        return true;
    }

    /**
     * Generate confirmation tokens for existing reservations
     */
    private function generateTokensForExistingReservations(): void
    {
        $reservations = (new \yii\db\Query())
            ->select(['id'])
            ->from('{{%bookings_reservations}}')
            ->all();

        foreach ($reservations as $reservation) {
            $token = $this->generateSecureToken();
            
            $this->update(
                '{{%bookings_reservations}}',
                ['confirmationToken' => $token],
                ['id' => $reservation['id']]
            );
        }
    }

    /**
     * Generate a cryptographically secure token
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }
}

