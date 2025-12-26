<?php

namespace fabian\booked\models;

use Craft;
use craft\base\Model;
use fabian\booked\records\SettingsRecord;

/**
 * Settings Model
 * 
 * Comprehensive settings for the Booked plugin, organized by category:
 * - General Settings
 * - Calendar Integration
 * - Virtual Meetings
 * - Notifications
 * - Commerce Integration
 * - Frontend Settings
 */
class Settings extends Model
{
    public ?int $id = null;
    
    // ============================================================================
    // General Settings
    // ============================================================================

    /** @var int Soft lock duration in minutes (default: 15) */
    public int $softLockDurationMinutes = 15;

    /** @var int Availability cache TTL in seconds (default: 3600 = 1 hour) */
    public int $availabilityCacheTtl = 3600;

    /** @var int Minimum advance booking hours - how far in advance users must book (default: 0 = no minimum) */
    public int $minimumAdvanceBookingHours = 0;

    /** @var int Maximum advance booking days - how far in advance users can book (default: 90) */
    public int $maximumAdvanceBookingDays = 90;

    /** @var string Default timezone (e.g., 'America/New_York') */
    public ?string $defaultTimezone = null;

    /** @var bool Enable rate limiting for booking submissions */
    public bool $enableRateLimiting = true;

    /** @var int Rate limit: max bookings per email per hour */
    public int $rateLimitPerEmail = 5;

    /** @var int Rate limit: max bookings per IP per hour */
    public int $rateLimitPerIp = 10;

    /** @var bool Enable virtual meeting functionality globally */
    public bool $enableVirtualMeetings = false;

    // ============================================================================
    // Calendar Integration - Google Calendar
    // ============================================================================
    
    /** @var bool Enable Google Calendar integration */
    public bool $googleCalendarEnabled = false;
    
    /** @var string Google Calendar OAuth Client ID */
    public ?string $googleCalendarClientId = null;
    
    /** @var string Google Calendar OAuth Client Secret */
    public ?string $googleCalendarClientSecret = null;
    
    /** @var string Google Calendar Webhook URL (auto-generated) */
    public ?string $googleCalendarWebhookUrl = null;

    // ============================================================================
    // Calendar Integration - Microsoft Outlook
    // ============================================================================
    
    /** @var bool Enable Microsoft Outlook integration */
    public bool $outlookCalendarEnabled = false;
    
    /** @var string Microsoft Outlook OAuth Client ID */
    public ?string $outlookCalendarClientId = null;
    
    /** @var string Microsoft Outlook OAuth Client Secret */
    public ?string $outlookCalendarClientSecret = null;
    
    /** @var string Microsoft Outlook Webhook URL (auto-generated) */
    public ?string $outlookCalendarWebhookUrl = null;

    // ============================================================================
    // Virtual Meetings - Zoom
    // ============================================================================
    
    /** @var bool Enable Zoom integration */
    public bool $zoomEnabled = false;
    
    /** @var string Zoom Account ID (Server-to-Server OAuth) */
    public ?string $zoomAccountId = null;

    /** @var string Zoom Client ID */
    public ?string $zoomClientId = null;
    
    /** @var string Zoom Client Secret */
    public ?string $zoomClientSecret = null;
    
    /** @var bool Auto-create Zoom meetings for bookings */
    public bool $zoomAutoCreate = true;

    // ============================================================================
    // Virtual Meetings - Google Meet
    // ============================================================================
    
    /** @var bool Enable Google Meet integration */
    public bool $googleMeetEnabled = false;
    
    /** @var bool Auto-create Google Meet links for bookings */
    public bool $googleMeetAutoCreate = true;

    // ============================================================================
    // Notifications - Email
    // ============================================================================
    
    /** @var bool Enable owner notification emails */
    public bool $ownerNotificationEnabled = true;
    
    /** @var string Owner notification email subject */
    public ?string $ownerNotificationSubject = null;
    
    /** @var string Owner email address */
    public ?string $ownerEmail = null;
    
    /** @var string Owner name */
    public ?string $ownerName = null;
    
    /** @var string Booking confirmation email subject */
    public ?string $bookingConfirmationSubject = null;
    
    /** @var string Booking confirmation email body (Twig template) */
    public ?string $bookingConfirmationBody = null;
    
    /** @var bool Send email reminders */
    public bool $emailRemindersEnabled = true;
    
    /** @var int Reminder time: hours before appointment (default: 24) */
    public int $emailReminderHoursBefore = 24;
    
    /** @var bool Send second reminder 1 hour before */
    public bool $emailReminderOneHourBefore = true;

    // ============================================================================
    // Notifications - SMS
    // ============================================================================
    
    /** @var bool Enable SMS notifications */
    public bool $smsEnabled = false;
    
    /** @var string SMS provider (e.g., 'twilio') */
    public ?string $smsProvider = null;
    
    /** @var string Twilio Account SID */
    public ?string $twilioAccountSid = null;
    
    /** @var string Twilio Auth Token */
    public ?string $twilioAuthToken = null;
    
    /** @var string Twilio Phone Number */
    public ?string $twilioPhoneNumber = null;
    
    /** @var bool Send SMS reminders */
    public bool $smsRemindersEnabled = false;
    
    /** @var int SMS reminder time: hours before appointment (default: 24) */
    public int $smsReminderHoursBefore = 24;

    // ============================================================================
    // Commerce Integration
    // ============================================================================
    
    /** @var bool Enable Craft Commerce integration */
    public bool $commerceEnabled = false;
    
    /** @var string Default payment gateway handle (if Commerce enabled) */
    public ?string $defaultPaymentGateway = null;
    
    /** @var bool Require payment before booking confirmation */
    public bool $requirePaymentBeforeConfirmation = true;

    // ============================================================================
    // Frontend Settings
    // ============================================================================

    /** @var string Default booking view mode ('wizard' for example UI, 'custom' for own implementation) */
    public string $defaultViewMode = 'wizard';

    /** @var string|null URL to the booking page (optional - users can create their own) */
    public ?string $bookingPageUrl = null;
    
    /** @var bool Enable real-time availability updates (AJAX) */
    public bool $enableRealTimeAvailability = true;
    
    /** @var bool Show employee selection in booking form */
    public bool $showEmployeeSelection = true;
    
    /** @var bool Show location selection in booking form */
    public bool $showLocationSelection = true;

    // ============================================================================
    // Legacy/Deprecated Settings (for backward compatibility)
    // ============================================================================
    
    /** @var int Buffer minutes (deprecated - now per service) */
    public ?int $bufferMinutes = null;
    
    /** @var int Slot duration minutes (deprecated - now per service) */
    public ?int $slotDurationMinutes = null;
    
    /** @var int|null Payment QR asset ID (legacy) */
    public ?int $paymentQrAssetId = null;

    /**
     * Get the effective owner email address
     */
    public function getEffectiveEmail(): ?string
    {
        return $this->ownerEmail ?: Craft::$app->getProjectConfig()->get('email.fromEmail');
    }

    /**
     * Get the effective owner name
     */
    public function getEffectiveName(): ?string
    {
        return $this->ownerName ?: Craft::$app->getProjectConfig()->get('email.fromName');
    }

    /**
     * Get the effective owner notification subject
     */
    public function getEffectiveOwnerNotificationSubject(): string
    {
        if (!empty($this->ownerNotificationSubject)) {
            return $this->ownerNotificationSubject;
        }

        return 'New Booking Received';
    }

    /**
     * Get the effective booking confirmation subject
     */
    public function getEffectiveBookingConfirmationSubject(): string
    {
        if (!empty($this->bookingConfirmationSubject)) {
            return $this->bookingConfirmationSubject;
        }

        return 'Booking Confirmation';
    }

    /**
     * Get attributes that should be excluded from Project Config
     * (sensitive or environment-specific values)
     *
     * @return array
     */
    public function getProjectConfigExcludedAttributes(): array
    {
        return [
            // Sensitive API credentials
            'googleCalendarClientSecret',
            'outlookCalendarClientSecret',
            'zoomClientSecret',
            'twilioAuthToken',

            // Environment-specific URLs
            'googleRedirectUri',
            'outlookRedirectUri',

            // Database IDs (these are environment-specific)
            'id',
            'paymentQrAssetId',
        ];
    }

    /**
     * Get attributes for Project Config
     * Excludes sensitive and environment-specific values
     *
     * @return array
     */
    public function getProjectConfigAttributes(): array
    {
        $attributes = $this->toArray();
        $excluded = $this->getProjectConfigExcludedAttributes();

        foreach ($excluded as $attribute) {
            unset($attributes[$attribute]);
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            // General settings
            [['softLockDurationMinutes', 'availabilityCacheTtl', 'rateLimitPerEmail', 'rateLimitPerIp'], 'integer', 'min' => 1],
            [['minimumAdvanceBookingHours', 'maximumAdvanceBookingDays'], 'integer', 'min' => 0],
            [['softLockDurationMinutes'], 'default', 'value' => 15],
            [['availabilityCacheTtl'], 'default', 'value' => 3600],
            [['minimumAdvanceBookingHours'], 'default', 'value' => 0],
            [['maximumAdvanceBookingDays'], 'default', 'value' => 90],
            [['rateLimitPerEmail'], 'default', 'value' => 5],
            [['rateLimitPerIp'], 'default', 'value' => 10],
            [['defaultTimezone'], 'string'],
            [['enableRateLimiting', 'enableVirtualMeetings'], 'boolean'],
            
            // Calendar integration
            [['googleCalendarEnabled', 'outlookCalendarEnabled'], 'boolean'],
            [['googleCalendarClientId', 'googleCalendarClientSecret', 'outlookCalendarClientId', 'outlookCalendarClientSecret'], 'string'],
            
            // Virtual meetings
            [['zoomEnabled', 'zoomAutoCreate', 'googleMeetEnabled', 'googleMeetAutoCreate'], 'boolean'],
            [['zoomAccountId', 'zoomClientId', 'zoomClientSecret'], 'string'],
            
            // Notifications
            [['ownerNotificationEnabled', 'emailRemindersEnabled', 'emailReminderOneHourBefore', 'smsEnabled', 'smsRemindersEnabled'], 'boolean'],
            [['emailReminderHoursBefore', 'smsReminderHoursBefore'], 'integer', 'min' => 0],
            [['ownerEmail'], 'email'],
            [['ownerName', 'ownerNotificationSubject', 'bookingConfirmationSubject'], 'string'],
            [['bookingConfirmationBody'], 'string'],
            [['smsProvider', 'twilioAccountSid', 'twilioAuthToken', 'twilioPhoneNumber'], 'string'],
            
            // Commerce
            [['commerceEnabled', 'requirePaymentBeforeConfirmation'], 'boolean'],
            [['defaultPaymentGateway'], 'string'],
            
            // Frontend
            [['defaultViewMode'], 'in', 'range' => ['wizard', 'custom']],
            [['bookingPageUrl'], 'string'],
            [['enableRealTimeAvailability', 'showEmployeeSelection', 'showLocationSelection'], 'boolean'],
            
            // Legacy
            [['bufferMinutes', 'slotDurationMinutes', 'paymentQrAssetId'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            // General
            'softLockDurationMinutes' => Craft::t('booked', 'Soft Lock Duration (minutes)'),
            'availabilityCacheTtl' => Craft::t('booked', 'Availability Cache TTL (seconds)'),
            'defaultTimezone' => Craft::t('booked', 'Default Timezone'),
            'enableRateLimiting' => Craft::t('booked', 'Enable Rate Limiting'),
            'rateLimitPerEmail' => Craft::t('booked', 'Rate Limit: Bookings per Email per Hour'),
            'rateLimitPerIp' => Craft::t('booked', 'Rate Limit: Bookings per IP per Hour'),
            'enableVirtualMeetings' => Craft::t('booked', 'Enable Virtual Meetings'),
            
            // Calendar
            'googleCalendarEnabled' => Craft::t('booked', 'Enable Google Calendar'),
            'googleCalendarClientId' => Craft::t('booked', 'Google Calendar Client ID'),
            'googleCalendarClientSecret' => Craft::t('booked', 'Google Calendar Client Secret'),
            'outlookCalendarEnabled' => Craft::t('booked', 'Enable Microsoft Outlook'),
            'outlookCalendarClientId' => Craft::t('booked', 'Outlook Client ID'),
            'outlookCalendarClientSecret' => Craft::t('booked', 'Outlook Client Secret'),
            
            // Virtual meetings
            'zoomEnabled' => Craft::t('booked', 'Enable Zoom'),
            'zoomAccountId' => Craft::t('booked', 'Zoom Account ID'),
            'zoomClientId' => Craft::t('booked', 'Zoom Client ID'),
            'zoomClientSecret' => Craft::t('booked', 'Zoom Client Secret'),
            'zoomAutoCreate' => Craft::t('booked', 'Auto-create Zoom Meetings'),
            'googleMeetEnabled' => Craft::t('booked', 'Enable Google Meet'),
            'googleMeetAutoCreate' => Craft::t('booked', 'Auto-create Google Meet Links'),
            
            // Notifications
            'ownerNotificationEnabled' => Craft::t('booked', 'Enable Owner Notifications'),
            'ownerNotificationSubject' => Craft::t('booked', 'Owner Notification Subject'),
            'ownerEmail' => Craft::t('booked', 'Owner Email'),
            'ownerName' => Craft::t('booked', 'Owner Name'),
            'bookingConfirmationSubject' => Craft::t('booked', 'Booking Confirmation Subject'),
            'bookingConfirmationBody' => Craft::t('booked', 'Booking Confirmation Body'),
            'emailRemindersEnabled' => Craft::t('booked', 'Enable Email Reminders'),
            'emailReminderHoursBefore' => Craft::t('booked', 'Email Reminder Hours Before'),
            'emailReminderOneHourBefore' => Craft::t('booked', 'Send 1-Hour Reminder'),
            'smsEnabled' => Craft::t('booked', 'Enable SMS Notifications'),
            'smsProvider' => Craft::t('booked', 'SMS Provider'),
            'twilioAccountSid' => Craft::t('booked', 'Twilio Account SID'),
            'twilioAuthToken' => Craft::t('booked', 'Twilio Auth Token'),
            'twilioPhoneNumber' => Craft::t('booked', 'Twilio Phone Number'),
            'smsRemindersEnabled' => Craft::t('booked', 'Enable SMS Reminders'),
            'smsReminderHoursBefore' => Craft::t('booked', 'SMS Reminder Hours Before'),
            
            // Commerce
            'commerceEnabled' => Craft::t('booked', 'Enable Commerce Integration'),
            'defaultPaymentGateway' => Craft::t('booked', 'Default Payment Gateway'),
            'requirePaymentBeforeConfirmation' => Craft::t('booked', 'Require Payment Before Confirmation'),
            
            // Frontend
            'defaultViewMode' => Craft::t('booked', 'Default View Mode'),
            'enableRealTimeAvailability' => Craft::t('booked', 'Enable Real-Time Availability'),
            'showEmployeeSelection' => Craft::t('booked', 'Show Employee Selection'),
            'showLocationSelection' => Craft::t('booked', 'Show Location Selection'),
        ];
    }

    /**
     * Save settings to database
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $record = SettingsRecord::find()->one();
        if (!$record) {
            $record = new SettingsRecord();
        }

        // Save all attributes that exist on the record
        foreach ($this->getAttributes() as $attribute => $value) {
            // Check if it's a property or an attribute/column on the record
            if (property_exists($record, $attribute) || $record->hasAttribute($attribute)) {
                $record->$attribute = $value;
            }
        }

        if ($record->save()) {
            $this->id = $record->id;
            return true;
        }

        $this->addErrors($record->getErrors());
        return false;
    }

    /**
     * Load settings from database
     */
    public static function loadSettings(): self
    {
        $record = SettingsRecord::find()->one();
        $model = new self();

        if ($record) {
            // Load all attributes from record
            foreach ($model->getAttributes() as $attribute => $value) {
                if (isset($record->$attribute)) {
                    $model->$attribute = $record->$attribute;
                }
            }
            $model->id = $record->id;
        }

        return $model;
    }

    /**
     * Check if Commerce is installed and enabled
     */
    public function isCommerceEnabled(): bool
    {
        return $this->commerceEnabled && Craft::$app->plugins->isPluginEnabled('commerce');
    }

    /**
     * Check if Google Calendar is configured
     */
    public function isGoogleCalendarConfigured(): bool
    {
        return $this->googleCalendarEnabled && 
               !empty($this->googleCalendarClientId) && 
               !empty($this->googleCalendarClientSecret);
    }

    /**
     * Check if Outlook Calendar is configured
     */
    public function isOutlookCalendarConfigured(): bool
    {
        return $this->outlookCalendarEnabled && 
               !empty($this->outlookCalendarClientId) && 
               !empty($this->outlookCalendarClientSecret);
    }

    /**
     * Check if Zoom is configured
     */
    public function isZoomConfigured(): bool
    {
        return $this->zoomEnabled && 
               !empty($this->zoomAccountId) && 
               !empty($this->zoomClientId) && 
               !empty($this->zoomClientSecret);
    }

    /**
     * Check if SMS is configured
     */
    public function isSmsConfigured(): bool
    {
        return $this->smsEnabled && 
               $this->smsProvider === 'twilio' &&
               !empty($this->twilioAccountSid) && 
               !empty($this->twilioAuthToken) &&
               !empty($this->twilioPhoneNumber);
    }
}
