<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Installation migration for the Booking module
 * Creates all tables with their complete final schema
 * 
 * This consolidates all incremental migrations into a single install for fresh setups
 */
class Install extends Migration
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
                // Field Layouts
                'employeeFieldLayoutId' => $this->integer()->null(),
                'serviceFieldLayoutId' => $this->integer()->null(),
                'locationFieldLayoutId' => $this->integer()->null(),
                // General Settings
                'softLockDurationMinutes' => $this->integer()->notNull()->defaultValue(15),
                'availabilityCacheTtl' => $this->integer()->notNull()->defaultValue(3600),
                'defaultTimezone' => $this->string(50)->null(),
                'enableRateLimiting' => $this->boolean()->notNull()->defaultValue(true),
                'rateLimitPerEmail' => $this->integer()->notNull()->defaultValue(5),
                'rateLimitPerIp' => $this->integer()->notNull()->defaultValue(10),
                // Calendar Integration
                'googleCalendarEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'googleCalendarClientId' => $this->string(255)->null(),
                'googleCalendarClientSecret' => $this->string(255)->null(),
                'googleCalendarWebhookUrl' => $this->string(255)->null(),
                'outlookCalendarEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'outlookCalendarClientId' => $this->string(255)->null(),
                'outlookCalendarClientSecret' => $this->string(255)->null(),
                'outlookCalendarWebhookUrl' => $this->string(255)->null(),
                // Virtual Meetings
                'zoomEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'zoomApiKey' => $this->string(255)->null(),
                'zoomApiSecret' => $this->string(255)->null(),
                'zoomAutoCreate' => $this->boolean()->notNull()->defaultValue(true),
                'googleMeetEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'googleMeetAutoCreate' => $this->boolean()->notNull()->defaultValue(true),
                // Notifications
                'ownerNotificationEnabled' => $this->boolean()->notNull()->defaultValue(true),
                'ownerNotificationSubject' => $this->string(255)->null(),
                'ownerEmail' => $this->string()->notNull(),
                'ownerName' => $this->string()->notNull(),
                'bookingConfirmationSubject' => $this->string(),
                'bookingConfirmationBody' => $this->text(),
                'emailRemindersEnabled' => $this->boolean()->notNull()->defaultValue(true),
                'emailReminderHoursBefore' => $this->integer()->notNull()->defaultValue(24),
                'emailReminderOneHourBefore' => $this->boolean()->notNull()->defaultValue(true),
                'smsEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'smsProvider' => $this->string(50)->null(),
                'twilioApiKey' => $this->string(255)->null(),
                'twilioApiSecret' => $this->string(255)->null(),
                'twilioPhoneNumber' => $this->string(50)->null(),
                'smsRemindersEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'smsReminderHoursBefore' => $this->integer()->notNull()->defaultValue(24),
                // Commerce Integration
                'commerceEnabled' => $this->boolean()->notNull()->defaultValue(false),
                'defaultPaymentGateway' => $this->string(50)->null(),
                'requirePaymentBeforeConfirmation' => $this->boolean()->notNull()->defaultValue(true),
                // Frontend Settings
                'defaultViewMode' => $this->string(20)->notNull()->defaultValue('wizard'),
                'enableRealTimeAvailability' => $this->boolean()->notNull()->defaultValue(true),
                'showEmployeeSelection' => $this->boolean()->notNull()->defaultValue(true),
                'showLocationSelection' => $this->boolean()->notNull()->defaultValue(true),
                // Legacy/Deprecated
                'bufferMinutes' => $this->integer()->notNull()->defaultValue(30),
                'slotDurationMinutes' => $this->integer()->notNull()->defaultValue(60),
                'paymentQrAssetId' => $this->integer()->null(),
                // Audit columns
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add foreign keys for field layouts
            $this->addForeignKey(null, '{{%bookings_settings}}', 'employeeFieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);
            $this->addForeignKey(null, '{{%bookings_settings}}', 'serviceFieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);
            $this->addForeignKey(null, '{{%bookings_settings}}', 'locationFieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);

            // Insert default settings
            $this->insert('{{%bookings_settings}}', [
                'ownerEmail' => 'owner@example.com',
                'ownerName' => 'Site Owner',
                'bookingConfirmationSubject' => 'Your booking confirmation',
                'bookingConfirmationBody' => 'Thank you for your booking.',
                'dateCreated' => date('Y-m-d H:i:s'),
                'dateUpdated' => date('Y-m-d H:i:s'),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);
        }

        // Create bookings_availability table (with all fields from migrations)
        if (!$this->db->tableExists('{{%bookings_availability}}')) {
            $this->createTable('{{%bookings_availability}}', [
                'id' => $this->primaryKey(),
                'dayOfWeek' => $this->integer()->null(), // 0 = Sunday, 6 = Saturday (null for event-type)
                'startTime' => $this->time()->null(), // Null for event-type availabilities
                'endTime' => $this->time()->null(), // Null for event-type availabilities
                'isActive' => $this->boolean()->notNull()->defaultValue(true),
                'availabilityType' => $this->enum('availabilityType', ['recurring', 'event'])->notNull()->defaultValue('recurring'),
                'description' => $this->text(),
                'sourceType' => $this->enum('sourceType', ['entry', 'section'])->notNull()->defaultValue('section'),
                'sourceId' => $this->integer()->null(),
                'sourceHandle' => $this->string()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%bookings_availability}}', ['dayOfWeek', 'isActive']);
            $this->createIndex(null, '{{%bookings_availability}}', ['sourceType', 'sourceId', 'isActive']);
            $this->createIndex(null, '{{%bookings_availability}}', ['sourceType', 'sourceHandle', 'isActive']);
            $this->createIndex(null, '{{%bookings_availability}}', ['availabilityType', 'isActive']);
        }

        // Create bookings_event_dates table (for event-type availabilities)
        if (!$this->db->tableExists('{{%bookings_event_dates}}')) {
            $this->createTable('{{%bookings_event_dates}}', [
                'id' => $this->primaryKey(),
                'availabilityId' => $this->integer()->notNull(),
                'eventDate' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(
                null,
                '{{%bookings_event_dates}}',
                'availabilityId',
                '{{%bookings_availability}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            $this->createIndex(null, '{{%bookings_event_dates}}', ['availabilityId']);
            $this->createIndex(null, '{{%bookings_event_dates}}', ['eventDate']);
            $this->createIndex(null, '{{%bookings_event_dates}}', ['availabilityId', 'eventDate']);
        }

        // Create bookings_variations table (Element-based)
        if (!$this->db->tableExists('{{%bookings_variations}}')) {
            $this->createTable('{{%bookings_variations}}', [
                'id' => $this->primaryKey(),
                'description' => $this->text(),
                'slotDurationMinutes' => $this->integer(),
                'bufferMinutes' => $this->integer(),
                'maxCapacity' => $this->integer()->notNull()->defaultValue(1),
                'allowQuantitySelection' => $this->boolean()->notNull()->defaultValue(false),
                'isActive' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add foreign key to elements table
            $this->addForeignKey(
                null,
                '{{%bookings_variations}}',
                'id',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );

            $this->createIndex(null, '{{%bookings_variations}}', 'isActive');
            $this->createIndex('idx_bookings_variations_capacity', '{{%bookings_variations}}', 'maxCapacity');
        }

        // Create junction table for availability-variation many-to-many relationship
        if (!$this->db->tableExists('{{%bookings_availability_variations}}')) {
            $this->createTable('{{%bookings_availability_variations}}', [
                'id' => $this->primaryKey(),
                'availabilityId' => $this->integer()->notNull(),
                'variationId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(
                null,
                '{{%bookings_availability_variations}}',
                'availabilityId',
                '{{%bookings_availability}}',
                'id',
                'CASCADE',
                null
            );

            $this->addForeignKey(
                null,
                '{{%bookings_availability_variations}}',
                'variationId',
                '{{%bookings_variations}}',
                'id',
                'CASCADE',
                null
            );

            $this->createIndex(null, '{{%bookings_availability_variations}}', 'availabilityId');
            $this->createIndex(null, '{{%bookings_availability_variations}}', 'variationId');
            $this->createIndex(
                null,
                '{{%bookings_availability_variations}}',
                ['availabilityId', 'variationId'],
                true // Unique
            );
        }

        // Create bookings_reservations table (with all fields from migrations)
        if (!$this->db->tableExists('{{%bookings_reservations}}')) {
            $this->createTable('{{%bookings_reservations}}', [
                'id' => $this->primaryKey(),
                'userName' => $this->string()->notNull(),
                'userEmail' => $this->string()->notNull(),
                'userPhone' => $this->string(),
                'userTimezone' => $this->string(50)->null()->comment('User\'s timezone identifier (e.g. Europe/Zurich)'),
                'bookingDate' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'status' => $this->enum('status', ['pending', 'confirmed', 'cancelled'])
                    ->notNull()
                    ->defaultValue('confirmed'),
                'sourceType' => $this->enum('sourceType', ['entry', 'section'])->null(),
                'sourceId' => $this->integer()->null(),
                'sourceHandle' => $this->string()->null(),
                'variationId' => $this->integer()->null(),
                'quantity' => $this->integer()->notNull()->defaultValue(1),
                'notes' => $this->text(),
                'notificationSent' => $this->boolean()->notNull()->defaultValue(false),
                'confirmationToken' => $this->string(64)->notNull()->unique(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Add foreign key to variations table
            $this->addForeignKey(
                null,
                '{{%bookings_reservations}}',
                'variationId',
                '{{%bookings_variations}}',
                'id',
                'SET NULL',
                null
            );

            $this->createIndex(null, '{{%bookings_reservations}}', ['bookingDate', 'startTime']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['userEmail']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['status']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['sourceType', 'sourceId']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['sourceType', 'sourceHandle']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['variationId']);
            $this->createIndex('idx_confirmationToken', '{{%bookings_reservations}}', 'confirmationToken', true);
            $this->createIndex(
                'idx_unique_active_booking',
                '{{%bookings_reservations}}',
                ['bookingDate', 'startTime', 'endTime', 'status'],
                true
            );
            $this->createIndex(
                'idx_bookings_reservations_capacity_lookup',
                '{{%bookings_reservations}}',
                ['bookingDate', 'startTime', 'endTime', 'variationId', 'status']
            );
        }

        // Create bookings_blackout_dates table
        if (!$this->db->tableExists('{{%bookings_blackout_dates}}')) {
            $this->createTable('{{%bookings_blackout_dates}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull()->comment('Name/description of the blackout period'),
                'startDate' => $this->date()->notNull()->comment('First day of the blackout period'),
                'endDate' => $this->date()->notNull()->comment('Last day of the blackout period'),
                'reason' => $this->text()->null()->comment('Optional reason for the blackout'),
                'isActive' => $this->boolean()->notNull()->defaultValue(true)->comment('Whether this blackout is currently active'),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%bookings_blackout_dates}}', ['startDate', 'endDate']);
            $this->createIndex(null, '{{%bookings_blackout_dates}}', ['isActive']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bookings_availability_variations}}');
        $this->dropTableIfExists('{{%bookings_reservations}}');
        $this->dropTableIfExists('{{%bookings_variations}}');
        $this->dropTableIfExists('{{%bookings_event_dates}}');
        $this->dropTableIfExists('{{%bookings_availability}}');
        $this->dropTableIfExists('{{%bookings_blackout_dates}}');
        $this->dropTableIfExists('{{%bookings_settings}}');

        return true;
    }
}

