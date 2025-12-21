<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Settings Active Record
 *
 * @property int $id
 * @property int|null $employeeFieldLayoutId
 * @property int|null $serviceFieldLayoutId
 * @property int|null $locationFieldLayoutId
 * @property int $softLockDurationMinutes
 * @property int $availabilityCacheTtl
 * @property string|null $defaultTimezone
 * @property bool $enableRateLimiting
 * @property int $rateLimitPerEmail
 * @property int $rateLimitPerIp
 * @property bool $googleCalendarEnabled
 * @property string|null $googleCalendarClientId
 * @property string|null $googleCalendarClientSecret
 * @property string|null $googleCalendarWebhookUrl
 * @property bool $outlookCalendarEnabled
 * @property string|null $outlookCalendarClientId
 * @property string|null $outlookCalendarClientSecret
 * @property string|null $outlookCalendarWebhookUrl
 * @property bool $zoomEnabled
 * @property string|null $zoomApiKey
 * @property string|null $zoomApiSecret
 * @property bool $zoomAutoCreate
 * @property bool $googleMeetEnabled
 * @property bool $googleMeetAutoCreate
 * @property bool $ownerNotificationEnabled
 * @property string|null $ownerNotificationSubject
 * @property string|null $ownerEmail
 * @property string|null $ownerName
 * @property string|null $bookingConfirmationSubject
 * @property string|null $bookingConfirmationBody
 * @property bool $emailRemindersEnabled
 * @property int $emailReminderHoursBefore
 * @property bool $emailReminderOneHourBefore
 * @property bool $smsEnabled
 * @property string|null $smsProvider
 * @property string|null $twilioApiKey
 * @property string|null $twilioApiSecret
 * @property string|null $twilioPhoneNumber
 * @property bool $smsRemindersEnabled
 * @property int $smsReminderHoursBefore
 * @property bool $commerceEnabled
 * @property string|null $defaultPaymentGateway
 * @property bool $requirePaymentBeforeConfirmation
 * @property string $defaultViewMode
 * @property bool $enableRealTimeAvailability
 * @property bool $showEmployeeSelection
 * @property bool $showLocationSelection
 * @property int|null $bufferMinutes
 * @property int|null $slotDurationMinutes
 * @property int|null $paymentQrAssetId
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class SettingsRecord extends ActiveRecord
{
    // Field Layouts
    public ?int $employeeFieldLayoutId = null;
    public ?int $serviceFieldLayoutId = null;
    public ?int $locationFieldLayoutId = null;
    
    // General Settings
    public int $softLockDurationMinutes = 15;
    public int $availabilityCacheTtl = 3600;
    public ?string $defaultTimezone = null;
    public bool $enableRateLimiting = true;
    public int $rateLimitPerEmail = 5;
    public int $rateLimitPerIp = 10;
    
    // Calendar Integration - Google
    public bool $googleCalendarEnabled = false;
    public ?string $googleCalendarClientId = null;
    public ?string $googleCalendarClientSecret = null;
    public ?string $googleCalendarWebhookUrl = null;
    
    // Calendar Integration - Outlook
    public bool $outlookCalendarEnabled = false;
    public ?string $outlookCalendarClientId = null;
    public ?string $outlookCalendarClientSecret = null;
    public ?string $outlookCalendarWebhookUrl = null;
    
    // Virtual Meetings - Zoom
    public bool $zoomEnabled = false;
    public ?string $zoomApiKey = null;
    public ?string $zoomApiSecret = null;
    public bool $zoomAutoCreate = true;
    
    // Virtual Meetings - Google Meet
    public bool $googleMeetEnabled = false;
    public bool $googleMeetAutoCreate = true;
    
    // Notifications - Email
    public bool $ownerNotificationEnabled = true;
    public ?string $ownerNotificationSubject = null;
    public ?string $ownerEmail = null;
    public ?string $ownerName = null;
    public ?string $bookingConfirmationSubject = null;
    public ?string $bookingConfirmationBody = null;
    public bool $emailRemindersEnabled = true;
    public int $emailReminderHoursBefore = 24;
    public bool $emailReminderOneHourBefore = true;
    
    // Notifications - SMS
    public bool $smsEnabled = false;
    public ?string $smsProvider = null;
    public ?string $twilioApiKey = null;
    public ?string $twilioApiSecret = null;
    public ?string $twilioPhoneNumber = null;
    public bool $smsRemindersEnabled = false;
    public int $smsReminderHoursBefore = 24;
    
    // Commerce Integration
    public bool $commerceEnabled = false;
    public ?string $defaultPaymentGateway = null;
    public bool $requirePaymentBeforeConfirmation = true;
    
    // Frontend Settings
    public string $defaultViewMode = 'wizard';
    public bool $enableRealTimeAvailability = true;
    public bool $showEmployeeSelection = true;
    public bool $showLocationSelection = true;
    
    // Legacy/Deprecated
    public ?int $bufferMinutes = null;
    public ?int $slotDurationMinutes = null;
    public ?int $paymentQrAssetId = null;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%bookings_settings}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            // Field layouts
            [['employeeFieldLayoutId', 'serviceFieldLayoutId', 'locationFieldLayoutId'], 'integer'],
            
            // General settings
            [['softLockDurationMinutes', 'availabilityCacheTtl', 'rateLimitPerEmail', 'rateLimitPerIp'], 'integer', 'min' => 1],
            [['defaultTimezone'], 'string'],
            [['enableRateLimiting'], 'boolean'],
            
            // Calendar integration
            [['googleCalendarEnabled', 'outlookCalendarEnabled'], 'boolean'],
            [['googleCalendarClientId', 'googleCalendarClientSecret', 'googleCalendarWebhookUrl', 'outlookCalendarClientId', 'outlookCalendarClientSecret', 'outlookCalendarWebhookUrl'], 'string'],
            
            // Virtual meetings
            [['zoomEnabled', 'zoomAutoCreate', 'googleMeetEnabled', 'googleMeetAutoCreate'], 'boolean'],
            [['zoomApiKey', 'zoomApiSecret'], 'string'],
            
            // Notifications
            [['ownerNotificationEnabled', 'emailRemindersEnabled', 'emailReminderOneHourBefore', 'smsEnabled', 'smsRemindersEnabled'], 'boolean'],
            [['emailReminderHoursBefore', 'smsReminderHoursBefore'], 'integer', 'min' => 0],
            [['ownerEmail'], 'email'],
            [['ownerName', 'ownerNotificationSubject', 'bookingConfirmationSubject'], 'string'],
            [['bookingConfirmationBody'], 'string'],
            [['smsProvider', 'twilioApiKey', 'twilioApiSecret', 'twilioPhoneNumber'], 'string'],
            
            // Commerce
            [['commerceEnabled', 'requirePaymentBeforeConfirmation'], 'boolean'],
            [['defaultPaymentGateway'], 'string'],
            
            // Frontend
            [['defaultViewMode'], 'in', 'range' => ['wizard', 'catalog', 'search']],
            [['enableRealTimeAvailability', 'showEmployeeSelection', 'showLocationSelection'], 'boolean'],
            
            // Legacy
            [['bufferMinutes', 'slotDurationMinutes', 'paymentQrAssetId'], 'integer'],
        ];
    }
}
