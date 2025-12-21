<?php

namespace modules\booking\records;

use craft\db\ActiveRecord;

/**
 * Settings Active Record
 *
 * @property int $id
 * @property int $bufferMinutes
 * @property int $slotDurationMinutes
 * @property string $ownerEmail
 * @property string $ownerName
 * @property string|null $bookingConfirmationSubject
 * @property string|null $bookingConfirmationBody
 * @property bool $ownerNotificationEnabled
 * @property string|null $ownerNotificationSubject
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
            [['bufferMinutes', 'slotDurationMinutes', 'paymentQrAssetId'], 'integer', 'min' => 1],
            [['ownerEmail', 'ownerName'], 'required'],
            [['ownerEmail'], 'email'],
            [['ownerName', 'ownerEmail', 'bookingConfirmationSubject', 'ownerNotificationSubject'], 'string', 'max' => 255],
            [['bookingConfirmationBody'], 'string'],
            [['bufferMinutes'], 'default', 'value' => 30],
            [['slotDurationMinutes'], 'default', 'value' => 60],
            [['ownerNotificationEnabled'], 'boolean'],
            [['ownerNotificationEnabled'], 'default', 'value' => true],
            [['paymentQrAssetId'], 'default', 'value' => null],
        ];
    }
}
