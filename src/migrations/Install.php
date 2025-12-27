<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Installation migration for the Booked plugin
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
                'zoomAccountId' => $this->string(255)->null(),
                'zoomClientId' => $this->string(255)->null(),
                'zoomClientSecret' => $this->string(255)->null(),
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
                'twilioAccountSid' => $this->string(255)->null(),
                'twilioAuthToken' => $this->string(255)->null(),
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

        // Create booked_employees table (Element-based)
        if (!$this->db->tableExists('{{%booked_employees}}')) {
            $this->createTable('{{%booked_employees}}', [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->null(),
                'locationId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_employees}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
            $this->addForeignKey(null, '{{%booked_employees}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);
            $this->addForeignKey(null, '{{%booked_employees}}', 'locationId', '{{%elements}}', 'id', 'SET NULL', null);

            $this->createIndex(null, '{{%booked_employees}}', 'userId');
            $this->createIndex(null, '{{%booked_employees}}', 'locationId');
        }

        // Create booked_locations table (Element-based)
        if (!$this->db->tableExists('{{%booked_locations}}')) {
            $this->createTable('{{%booked_locations}}', [
                'id' => $this->primaryKey(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_locations}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        }

        // Create booked_services table (Element-based)
        if (!$this->db->tableExists('{{%booked_services}}')) {
            $this->createTable('{{%booked_services}}', [
                'id' => $this->primaryKey(),
                'durationMinutes' => $this->integer()->null(),
                'bufferMinutes' => $this->integer()->null(),
                'price' => $this->decimal(14, 4)->null(),
                'currency' => $this->string(3)->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_services}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        }

        // Create booked_schedules table (Element-based)
        if (!$this->db->tableExists('{{%booked_schedules}}')) {
            $this->createTable('{{%booked_schedules}}', [
                'id' => $this->primaryKey(),
                'dayOfWeek' => $this->integer()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'isActive' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_schedules}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
            $this->createIndex(null, '{{%booked_schedules}}', ['dayOfWeek', 'isActive']);
        }

        // Create booked_service_extras table (Element-based)
        if (!$this->db->tableExists('{{%booked_service_extras}}')) {
            $this->createTable('{{%booked_service_extras}}', [
                'id' => $this->primaryKey(),
                'price' => $this->decimal(14, 4)->null(),
                'currency' => $this->string(3)->null(),
                'durationMinutes' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_service_extras}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        }

        // Create booked_employees_services junction table
        if (!$this->db->tableExists('{{%booked_employees_services}}')) {
            $this->createTable('{{%booked_employees_services}}', [
                'id' => $this->primaryKey(),
                'employeeId' => $this->integer()->notNull(),
                'serviceId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_employees_services}}', 'employeeId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%booked_employees_services}}', 'serviceId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%booked_employees_services}}', ['employeeId', 'serviceId'], true);
        }


        // Create booked_service_extras_services junction table
        if (!$this->db->tableExists('{{%booked_service_extras_services}}')) {
            $this->createTable('{{%booked_service_extras_services}}', [
                'id' => $this->primaryKey(),
                'serviceExtraId' => $this->integer()->notNull(),
                'serviceId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_service_extras_services}}', 'serviceExtraId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%booked_service_extras_services}}', 'serviceId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%booked_service_extras_services}}', ['serviceExtraId', 'serviceId'], true);
        }

        // Create bookings_availability table
        if (!$this->db->tableExists('{{%bookings_availability}}')) {
            $this->createTable('{{%bookings_availability}}', [
                'id' => $this->primaryKey(),
                'dayOfWeek' => $this->integer()->null(),
                'startTime' => $this->time()->null(),
                'endTime' => $this->time()->null(),
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

        // Create bookings_event_dates table
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

            $this->addForeignKey(null, '{{%bookings_event_dates}}', 'availabilityId', '{{%bookings_availability}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%bookings_event_dates}}', 'availabilityId');
            $this->createIndex(null, '{{%bookings_event_dates}}', 'eventDate');
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

            $this->addForeignKey(null, '{{%bookings_variations}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
            $this->createIndex(null, '{{%bookings_variations}}', 'isActive');
            $this->createIndex('idx_bookings_variations_capacity', '{{%bookings_variations}}', 'maxCapacity');
        }

        // Create bookings_availability_variations junction table
        if (!$this->db->tableExists('{{%bookings_availability_variations}}')) {
            $this->createTable('{{%bookings_availability_variations}}', [
                'id' => $this->primaryKey(),
                'availabilityId' => $this->integer()->notNull(),
                'variationId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%bookings_availability_variations}}', 'availabilityId', '{{%bookings_availability}}', 'id', 'CASCADE', null);
            $this->addForeignKey(null, '{{%bookings_availability_variations}}', 'variationId', '{{%bookings_variations}}', 'id', 'CASCADE', null);
            $this->createIndex(null, '{{%bookings_availability_variations}}', 'availabilityId');
            $this->createIndex(null, '{{%bookings_availability_variations}}', 'variationId');
            $this->createIndex(null, '{{%bookings_availability_variations}}', ['availabilityId', 'variationId'], true);
        }

        // Create bookings_reservations table (Element-based)
        if (!$this->db->tableExists('{{%bookings_reservations}}')) {
            $this->createTable('{{%bookings_reservations}}', [
                'id' => $this->primaryKey(),
                'userName' => $this->string()->notNull(),
                'userEmail' => $this->string()->notNull(),
                'userPhone' => $this->string(),
                'userTimezone' => $this->string(50)->null(),
                'bookingDate' => $this->date()->notNull(),
                'startTime' => $this->time()->notNull(),
                'endTime' => $this->time()->notNull(),
                'status' => $this->enum('status', ['pending', 'confirmed', 'cancelled'])->notNull()->defaultValue('confirmed'),
                'sourceType' => $this->enum('sourceType', ['entry', 'section'])->null(),
                'sourceId' => $this->integer()->null(),
                'sourceHandle' => $this->string()->null(),
                'variationId' => $this->integer()->null(),
                'employeeId' => $this->integer()->null(),
                'locationId' => $this->integer()->null(),
                'serviceId' => $this->integer()->null(),
                'quantity' => $this->integer()->notNull()->defaultValue(1),
                'notes' => $this->text(),
                'virtualMeetingUrl' => $this->string()->null(),
                'virtualMeetingProvider' => $this->string(50)->null(),
                'virtualMeetingId' => $this->string()->null(),
                'notificationSent' => $this->boolean()->notNull()->defaultValue(false),
                'confirmationToken' => $this->string(64)->notNull()->unique(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%bookings_reservations}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
            $this->addForeignKey(null, '{{%bookings_reservations}}', 'variationId', '{{%bookings_variations}}', 'id', 'SET NULL', null);
            $this->addForeignKey(null, '{{%bookings_reservations}}', 'employeeId', '{{%elements}}', 'id', 'SET NULL', null);
            $this->addForeignKey(null, '{{%bookings_reservations}}', 'locationId', '{{%elements}}', 'id', 'SET NULL', null);
            $this->addForeignKey(null, '{{%bookings_reservations}}', 'serviceId', '{{%elements}}', 'id', 'SET NULL', null);

            $this->createIndex(null, '{{%bookings_reservations}}', ['bookingDate', 'startTime']);
            $this->createIndex(null, '{{%bookings_reservations}}', 'userEmail');
            $this->createIndex(null, '{{%bookings_reservations}}', 'status');
            $this->createIndex(null, '{{%bookings_reservations}}', ['sourceType', 'sourceId']);
            $this->createIndex(null, '{{%bookings_reservations}}', ['sourceType', 'sourceHandle']);
            $this->createIndex(null, '{{%bookings_reservations}}', 'variationId');
            $this->createIndex(null, '{{%bookings_reservations}}', 'employeeId');
            $this->createIndex(null, '{{%bookings_reservations}}', 'locationId');
            $this->createIndex(null, '{{%bookings_reservations}}', 'serviceId');
            $this->createIndex('idx_confirmationToken', '{{%bookings_reservations}}', 'confirmationToken', true);
            $this->createIndex('idx_unique_active_booking', '{{%bookings_reservations}}', ['bookingDate', 'startTime', 'endTime', 'status'], true);
            $this->createIndex('idx_bookings_reservations_capacity_lookup', '{{%bookings_reservations}}', ['bookingDate', 'startTime', 'endTime', 'variationId', 'status']);
        }

        // Create booked_reservation_extras table
        if (!$this->db->tableExists('{{%booked_reservation_extras}}')) {
            $this->createTable('{{%booked_reservation_extras}}', [
                'id' => $this->primaryKey(),
                'reservationId' => $this->integer()->notNull(),
                'serviceExtraId' => $this->integer()->notNull(),
                'quantity' => $this->integer()->notNull()->defaultValue(1),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_reservation_extras}}', 'reservationId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%booked_reservation_extras}}', 'serviceExtraId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%booked_reservation_extras}}', ['reservationId', 'serviceExtraId'], true);
        }

        // Create booked_order_reservations table (Commerce integration)
        if (!$this->db->tableExists('{{%booked_order_reservations}}')) {
            $this->createTable('{{%booked_order_reservations}}', [
                'id' => $this->primaryKey(),
                'orderId' => $this->integer()->notNull(),
                'reservationId' => $this->integer()->notNull(),
                'lineItemId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_order_reservations}}', ['orderId', 'reservationId'], true);
            $this->createIndex(null, '{{%booked_order_reservations}}', 'reservationId');
        }

        // Create bookings_blackout_dates table (Element-based)
        if (!$this->db->tableExists('{{%bookings_blackout_dates}}')) {
            $this->createTable('{{%bookings_blackout_dates}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'startDate' => $this->date()->notNull(),
                'endDate' => $this->date()->notNull(),
                'reason' => $this->text()->null(),
                'isActive' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%bookings_blackout_dates}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
            $this->createIndex(null, '{{%bookings_blackout_dates}}', ['startDate', 'endDate']);
            $this->createIndex(null, '{{%bookings_blackout_dates}}', 'isActive');
        }

        // Create bookings_blackout_dates_locations junction table
        if (!$this->db->tableExists('{{%bookings_blackout_dates_locations}}')) {
            $this->createTable('{{%bookings_blackout_dates_locations}}', [
                'id' => $this->primaryKey(),
                'blackoutDateId' => $this->integer()->notNull(),
                'locationId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%bookings_blackout_dates_locations}}', 'blackoutDateId', '{{%bookings_blackout_dates}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%bookings_blackout_dates_locations}}', 'locationId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%bookings_blackout_dates_locations}}', ['blackoutDateId', 'locationId'], true);
        }

        // Create bookings_blackout_dates_employees junction table
        if (!$this->db->tableExists('{{%bookings_blackout_dates_employees}}')) {
            $this->createTable('{{%bookings_blackout_dates_employees}}', [
                'id' => $this->primaryKey(),
                'blackoutDateId' => $this->integer()->notNull(),
                'employeeId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%bookings_blackout_dates_employees}}', 'blackoutDateId', '{{%bookings_blackout_dates}}', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%bookings_blackout_dates_employees}}', 'employeeId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%bookings_blackout_dates_employees}}', ['blackoutDateId', 'employeeId'], true);
        }

        // Create booked_soft_locks table
        if (!$this->db->tableExists('{{%booked_soft_locks}}')) {
            $this->createTable('{{%booked_soft_locks}}', [
                'id' => $this->primaryKey(),
                'sessionId' => $this->string()->notNull(),
                'reservationData' => $this->text()->notNull(),
                'expiresAt' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_soft_locks}}', 'sessionId');
            $this->createIndex(null, '{{%booked_soft_locks}}', 'expiresAt');
        }

        // Create booked_calendar_tokens table (OAuth tokens)
        if (!$this->db->tableExists('{{%booked_calendar_tokens}}')) {
            $this->createTable('{{%booked_calendar_tokens}}', [
                'id' => $this->primaryKey(),
                'employeeId' => $this->integer()->notNull(),
                'provider' => $this->string(50)->notNull(),
                'accessToken' => $this->text()->notNull(),
                'refreshToken' => $this->text()->null(),
                'expiresAt' => $this->dateTime()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_calendar_tokens}}', 'employeeId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%booked_calendar_tokens}}', ['employeeId', 'provider'], true);
        }

        // Create booked_oauth_state_tokens table
        if (!$this->db->tableExists('{{%booked_oauth_state_tokens}}')) {
            $this->createTable('{{%booked_oauth_state_tokens}}', [
                'id' => $this->primaryKey(),
                'state' => $this->string()->notNull()->unique(),
                'provider' => $this->string(50)->notNull(),
                'employeeId' => $this->integer()->null(),
                'expiresAt' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%booked_oauth_state_tokens}}', 'state', true);
            $this->createIndex(null, '{{%booked_oauth_state_tokens}}', 'expiresAt');
        }

        // Create booked_external_events table (synced calendar events)
        if (!$this->db->tableExists('{{%booked_external_events}}')) {
            $this->createTable('{{%booked_external_events}}', [
                'id' => $this->primaryKey(),
                'employeeId' => $this->integer()->notNull(),
                'provider' => $this->string(50)->notNull(),
                'externalId' => $this->string()->notNull(),
                'eventData' => $this->text()->notNull(),
                'startTime' => $this->dateTime()->notNull(),
                'endTime' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->addForeignKey(null, '{{%booked_external_events}}', 'employeeId', '{{%elements}}', 'id', 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%booked_external_events}}', ['employeeId', 'provider', 'externalId'], true);
            $this->createIndex(null, '{{%booked_external_events}}', ['startTime', 'endTime']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop all tables in reverse order of dependencies
        $this->dropTableIfExists('{{%booked_external_events}}');
        $this->dropTableIfExists('{{%booked_oauth_state_tokens}}');
        $this->dropTableIfExists('{{%booked_calendar_tokens}}');
        $this->dropTableIfExists('{{%booked_soft_locks}}');
        $this->dropTableIfExists('{{%bookings_blackout_dates_employees}}');
        $this->dropTableIfExists('{{%bookings_blackout_dates_locations}}');
        $this->dropTableIfExists('{{%bookings_blackout_dates}}');
        $this->dropTableIfExists('{{%booked_order_reservations}}');
        $this->dropTableIfExists('{{%booked_reservation_extras}}');
        $this->dropTableIfExists('{{%bookings_reservations}}');
        $this->dropTableIfExists('{{%bookings_availability_variations}}');
        $this->dropTableIfExists('{{%bookings_variations}}');
        $this->dropTableIfExists('{{%bookings_event_dates}}');
        $this->dropTableIfExists('{{%bookings_availability}}');
        $this->dropTableIfExists('{{%booked_service_extras_services}}');
        $this->dropTableIfExists('{{%booked_schedule_employees}}');
        $this->dropTableIfExists('{{%booked_employees_services}}');
        $this->dropTableIfExists('{{%booked_service_extras}}');
        $this->dropTableIfExists('{{%booked_schedules}}');
        $this->dropTableIfExists('{{%booked_services}}');
        $this->dropTableIfExists('{{%booked_locations}}');
        $this->dropTableIfExists('{{%booked_employees}}');
        $this->dropTableIfExists('{{%bookings_settings}}');

        return true;
    }
}
