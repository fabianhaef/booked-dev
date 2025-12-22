<?php

namespace fabian\booked\records;

use craft\db\ActiveRecord;

/**
 * Settings Active Record
 *
 * @property int $id
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
 * @property string|null $zoomAccountId
 * @property string|null $zoomClientId
 * @property string|null $zoomClientSecret
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
 * @property string|null $twilioAccountSid
 * @property string|null $twilioAuthToken
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
            // Only validate ID here, or core columns that always exist.
            // Other settings are validated in the Settings model.
            [['id'], 'integer'],
        ];
    }
}
