<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Add comprehensive settings columns to bookings_settings table
 * 
 * This migration adds all settings organized by category:
 * - General Settings
 * - Calendar Integration (Google, Outlook)
 * - Virtual Meetings (Zoom, Google Meet)
 * - Notifications (Email, SMS)
 * - Commerce Integration
 * - Frontend Settings
 */
class m250101_000004_add_comprehensive_settings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%bookings_settings}}';

        // ============================================================================
        // General Settings
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'softLockDurationMinutes')) {
            $this->addColumn($table, 'softLockDurationMinutes', $this->integer()->notNull()->defaultValue(15)->after('id'));
        }
        
        if (!$this->db->columnExists($table, 'availabilityCacheTtl')) {
            $this->addColumn($table, 'availabilityCacheTtl', $this->integer()->notNull()->defaultValue(3600)->after('softLockDurationMinutes'));
        }
        
        if (!$this->db->columnExists($table, 'defaultTimezone')) {
            $this->addColumn($table, 'defaultTimezone', $this->string(50)->null()->after('availabilityCacheTtl'));
        }
        
        if (!$this->db->columnExists($table, 'enableRateLimiting')) {
            $this->addColumn($table, 'enableRateLimiting', $this->boolean()->notNull()->defaultValue(true)->after('defaultTimezone'));
        }
        
        if (!$this->db->columnExists($table, 'rateLimitPerEmail')) {
            $this->addColumn($table, 'rateLimitPerEmail', $this->integer()->notNull()->defaultValue(5)->after('enableRateLimiting'));
        }
        
        if (!$this->db->columnExists($table, 'rateLimitPerIp')) {
            $this->addColumn($table, 'rateLimitPerIp', $this->integer()->notNull()->defaultValue(10)->after('rateLimitPerEmail'));
        }

        // ============================================================================
        // Calendar Integration - Google Calendar
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'googleCalendarEnabled')) {
            $this->addColumn($table, 'googleCalendarEnabled', $this->boolean()->notNull()->defaultValue(false)->after('rateLimitPerIp'));
        }
        
        if (!$this->db->columnExists($table, 'googleCalendarClientId')) {
            $this->addColumn($table, 'googleCalendarClientId', $this->string(255)->null()->after('googleCalendarEnabled'));
        }
        
        if (!$this->db->columnExists($table, 'googleCalendarClientSecret')) {
            $this->addColumn($table, 'googleCalendarClientSecret', $this->string(255)->null()->after('googleCalendarClientId'));
        }
        
        if (!$this->db->columnExists($table, 'googleCalendarWebhookUrl')) {
            $this->addColumn($table, 'googleCalendarWebhookUrl', $this->string(255)->null()->after('googleCalendarClientSecret'));
        }

        // ============================================================================
        // Calendar Integration - Microsoft Outlook
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'outlookCalendarEnabled')) {
            $this->addColumn($table, 'outlookCalendarEnabled', $this->boolean()->notNull()->defaultValue(false)->after('googleCalendarWebhookUrl'));
        }
        
        if (!$this->db->columnExists($table, 'outlookCalendarClientId')) {
            $this->addColumn($table, 'outlookCalendarClientId', $this->string(255)->null()->after('outlookCalendarEnabled'));
        }
        
        if (!$this->db->columnExists($table, 'outlookCalendarClientSecret')) {
            $this->addColumn($table, 'outlookCalendarClientSecret', $this->string(255)->null()->after('outlookCalendarClientId'));
        }
        
        if (!$this->db->columnExists($table, 'outlookCalendarWebhookUrl')) {
            $this->addColumn($table, 'outlookCalendarWebhookUrl', $this->string(255)->null()->after('outlookCalendarClientSecret'));
        }

        // ============================================================================
        // Virtual Meetings - Zoom
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'zoomEnabled')) {
            $this->addColumn($table, 'zoomEnabled', $this->boolean()->notNull()->defaultValue(false)->after('outlookCalendarWebhookUrl'));
        }
        
        if (!$this->db->columnExists($table, 'zoomApiKey')) {
            $this->addColumn($table, 'zoomApiKey', $this->string(255)->null()->after('zoomEnabled'));
        }
        
        if (!$this->db->columnExists($table, 'zoomApiSecret')) {
            $this->addColumn($table, 'zoomApiSecret', $this->string(255)->null()->after('zoomApiKey'));
        }
        
        if (!$this->db->columnExists($table, 'zoomAutoCreate')) {
            $this->addColumn($table, 'zoomAutoCreate', $this->boolean()->notNull()->defaultValue(true)->after('zoomApiSecret'));
        }

        // ============================================================================
        // Virtual Meetings - Google Meet
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'googleMeetEnabled')) {
            $this->addColumn($table, 'googleMeetEnabled', $this->boolean()->notNull()->defaultValue(false)->after('zoomAutoCreate'));
        }
        
        if (!$this->db->columnExists($table, 'googleMeetAutoCreate')) {
            $this->addColumn($table, 'googleMeetAutoCreate', $this->boolean()->notNull()->defaultValue(true)->after('googleMeetEnabled'));
        }

        // ============================================================================
        // Notifications - Email (some may already exist from previous migrations)
        // ============================================================================
        
        // These might already exist, so we check first
        if (!$this->db->columnExists($table, 'ownerEmail')) {
            $this->addColumn($table, 'ownerEmail', $this->string(255)->null()->after('googleMeetAutoCreate'));
        }
        
        if (!$this->db->columnExists($table, 'ownerName')) {
            $this->addColumn($table, 'ownerName', $this->string(255)->null()->after('ownerEmail'));
        }
        
        if (!$this->db->columnExists($table, 'emailRemindersEnabled')) {
            $this->addColumn($table, 'emailRemindersEnabled', $this->boolean()->notNull()->defaultValue(true)->after('bookingConfirmationBody'));
        }
        
        if (!$this->db->columnExists($table, 'emailReminderHoursBefore')) {
            $this->addColumn($table, 'emailReminderHoursBefore', $this->integer()->notNull()->defaultValue(24)->after('emailRemindersEnabled'));
        }
        
        if (!$this->db->columnExists($table, 'emailReminderOneHourBefore')) {
            $this->addColumn($table, 'emailReminderOneHourBefore', $this->boolean()->notNull()->defaultValue(true)->after('emailReminderHoursBefore'));
        }

        // ============================================================================
        // Notifications - SMS
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'smsEnabled')) {
            $this->addColumn($table, 'smsEnabled', $this->boolean()->notNull()->defaultValue(false)->after('emailReminderOneHourBefore'));
        }
        
        if (!$this->db->columnExists($table, 'smsProvider')) {
            $this->addColumn($table, 'smsProvider', $this->string(50)->null()->after('smsEnabled'));
        }
        
        if (!$this->db->columnExists($table, 'twilioApiKey')) {
            $this->addColumn($table, 'twilioApiKey', $this->string(255)->null()->after('smsProvider'));
        }
        
        if (!$this->db->columnExists($table, 'twilioApiSecret')) {
            $this->addColumn($table, 'twilioApiSecret', $this->string(255)->null()->after('twilioApiKey'));
        }
        
        if (!$this->db->columnExists($table, 'twilioPhoneNumber')) {
            $this->addColumn($table, 'twilioPhoneNumber', $this->string(50)->null()->after('twilioApiSecret'));
        }
        
        if (!$this->db->columnExists($table, 'smsRemindersEnabled')) {
            $this->addColumn($table, 'smsRemindersEnabled', $this->boolean()->notNull()->defaultValue(false)->after('twilioPhoneNumber'));
        }
        
        if (!$this->db->columnExists($table, 'smsReminderHoursBefore')) {
            $this->addColumn($table, 'smsReminderHoursBefore', $this->integer()->notNull()->defaultValue(24)->after('smsRemindersEnabled'));
        }

        // ============================================================================
        // Commerce Integration
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'commerceEnabled')) {
            $this->addColumn($table, 'commerceEnabled', $this->boolean()->notNull()->defaultValue(false)->after('smsReminderHoursBefore'));
        }
        
        if (!$this->db->columnExists($table, 'defaultPaymentGateway')) {
            $this->addColumn($table, 'defaultPaymentGateway', $this->string(50)->null()->after('commerceEnabled'));
        }
        
        if (!$this->db->columnExists($table, 'requirePaymentBeforeConfirmation')) {
            $this->addColumn($table, 'requirePaymentBeforeConfirmation', $this->boolean()->notNull()->defaultValue(true)->after('defaultPaymentGateway'));
        }

        // ============================================================================
        // Frontend Settings
        // ============================================================================
        
        if (!$this->db->columnExists($table, 'defaultViewMode')) {
            $this->addColumn($table, 'defaultViewMode', $this->string(20)->notNull()->defaultValue('wizard')->after('requirePaymentBeforeConfirmation'));
        }
        
        if (!$this->db->columnExists($table, 'enableRealTimeAvailability')) {
            $this->addColumn($table, 'enableRealTimeAvailability', $this->boolean()->notNull()->defaultValue(true)->after('defaultViewMode'));
        }
        
        if (!$this->db->columnExists($table, 'showEmployeeSelection')) {
            $this->addColumn($table, 'showEmployeeSelection', $this->boolean()->notNull()->defaultValue(true)->after('enableRealTimeAvailability'));
        }
        
        if (!$this->db->columnExists($table, 'showLocationSelection')) {
            $this->addColumn($table, 'showLocationSelection', $this->boolean()->notNull()->defaultValue(true)->after('showEmployeeSelection'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%bookings_settings}}';

        // Drop columns in reverse order
        $columns = [
            // Frontend
            'showLocationSelection',
            'showEmployeeSelection',
            'enableRealTimeAvailability',
            'defaultViewMode',
            
            // Commerce
            'requirePaymentBeforeConfirmation',
            'defaultPaymentGateway',
            'commerceEnabled',
            
            // SMS
            'smsReminderHoursBefore',
            'smsRemindersEnabled',
            'twilioPhoneNumber',
            'twilioApiSecret',
            'twilioApiKey',
            'smsProvider',
            'smsEnabled',
            
            // Email (only new ones)
            'emailReminderOneHourBefore',
            'emailReminderHoursBefore',
            'emailRemindersEnabled',
            
            // Google Meet
            'googleMeetAutoCreate',
            'googleMeetEnabled',
            
            // Zoom
            'zoomAutoCreate',
            'zoomApiSecret',
            'zoomApiKey',
            'zoomEnabled',
            
            // Outlook
            'outlookCalendarWebhookUrl',
            'outlookCalendarClientSecret',
            'outlookCalendarClientId',
            'outlookCalendarEnabled',
            
            // Google Calendar
            'googleCalendarWebhookUrl',
            'googleCalendarClientSecret',
            'googleCalendarClientId',
            'googleCalendarEnabled',
            
            // General
            'rateLimitPerIp',
            'rateLimitPerEmail',
            'enableRateLimiting',
            'defaultTimezone',
            'availabilityCacheTtl',
            'softLockDurationMinutes',
        ];

        foreach ($columns as $column) {
            if ($this->db->columnExists($table, $column)) {
                $this->dropColumn($table, $column);
            }
        }

        return true;
    }
}

