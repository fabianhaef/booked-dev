<?php

namespace modules\booking\migrations;

use craft\db\Migration;

/**
 * Migration: Add owner notification settings to bookings_settings table
 * 
 * Adds fields for owner notification email toggle and subject.
 * 
 * Note: Payment QR code is file-based - just place a file at web/media/payment-qr.png
 * and it will automatically be attached to client confirmation emails.
 */
class m241201_100000_add_payment_qr_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add owner notification enabled toggle
        if (!$this->db->columnExists('{{%bookings_settings}}', 'ownerNotificationEnabled')) {
            $this->addColumn(
                '{{%bookings_settings}}',
                'ownerNotificationEnabled',
                $this->boolean()->defaultValue(true)->after('bookingConfirmationBody')
            );
        }

        // Add owner notification subject
        if (!$this->db->columnExists('{{%bookings_settings}}', 'ownerNotificationSubject')) {
            $this->addColumn(
                '{{%bookings_settings}}',
                'ownerNotificationSubject',
                $this->string(255)->null()->after('ownerNotificationEnabled')
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bookings_settings}}', 'ownerNotificationEnabled')) {
            $this->dropColumn('{{%bookings_settings}}', 'ownerNotificationEnabled');
        }

        if ($this->db->columnExists('{{%bookings_settings}}', 'ownerNotificationSubject')) {
            $this->dropColumn('{{%bookings_settings}}', 'ownerNotificationSubject');
        }

        return true;
    }
}

