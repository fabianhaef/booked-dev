<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Installation migration for the Booking module
 */
class m241001_000000_install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create bookings_settings table
        if (!$this->db->tableExists('{{%bookings_settings}}')) {
            $this->createTable('{{%bookings_settings}}', [
                'id' => $this->primaryKey(),
                'bufferMinutes' => $this->integer()->notNull()->defaultValue(30),
                'slotDurationMinutes' => $this->integer()->notNull()->defaultValue(60),
                'ownerEmail' => $this->string()->notNull(),
                'ownerName' => $this->string()->notNull(),
                'bookingConfirmationSubject' => $this->string(),
                'bookingConfirmationBody' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Insert default settings
            $this->insert('{{%bookings_settings}}', [
                'bufferMinutes' => 30,
                'slotDurationMinutes' => 60,
                'ownerEmail' => 'owner@example.com',
                'ownerName' => 'Site Owner',
                'bookingConfirmationSubject' => 'Your booking confirmation',
                'bookingConfirmationBody' => 'Thank you for your booking.',
                'dateCreated' => date('Y-m-d H:i:s'),
                'dateUpdated' => date('Y-m-d H:i:s'),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }

        // Create bookings_availability table
        if (!$this->db->tableExists('{{%bookings_availability}}')) {
            $this->createTable('{{%bookings_availability}}', [
                'id' => $this->primaryKey(),
                'dayOfWeek' => $this->integer()->notNull(), // 0 = Sunday, 6 = Saturday
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'isActive' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%bookings_availability}}', ['dayOfWeek', 'isActive']);
        }

        // Create bookings_reservations table
        if (!$this->db->tableExists('{{%bookings_reservations}}')) {
            $this->createTable('{{%bookings_reservations}}', [
                'id' => $this->primaryKey(),
                'userName' => $this->string()->notNull(),
                'userEmail' => $this->string()->notNull(),
                'userPhone' => $this->string(),
                'bookingDate' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'status' => $this->enum('status', ['pending', 'confirmed', 'cancelled'])
                    ->notNull()
                    ->defaultValue('confirmed'),
                'notes' => $this->text(),
                'notificationSent' => $this->boolean()->notNull()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%bookings_reservations}}', ['bookingDate', 'startTime']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['userEmail']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['status']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bookings_reservations}}');
        $this->dropTableIfExists('{{%bookings_availability}}');
        $this->dropTableIfExists('{{%bookings_settings}}');

        return true;
    }
}
