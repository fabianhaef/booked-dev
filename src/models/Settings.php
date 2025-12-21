<?php

namespace modules\booking\models;

use Craft;
use craft\base\Model;
use modules\booking\records\SettingsRecord;

/**
 * Settings Model
 */
class Settings extends Model
{
    public ?int $id = null;
    public ?string $ownerEmail = null; // Optional - uses Craft system email if empty
    public ?string $ownerName = null;  // Optional - uses Craft site name if empty

    // Owner notification settings
    public bool $ownerNotificationEnabled = true; // Send notification to owner on new booking
    public ?string $ownerNotificationSubject = null; // Custom subject for owner notification

    // Payment QR code
    public ?int $paymentQrAssetId = null; // Asset ID for payment QR code image
    
    // Default values for backwards compatibility (not exposed in UI anymore)
    private int $defaultBufferMinutes = 30;
    private int $defaultSlotDurationMinutes = 60;

    // Spam protection settings
    public int $maxBookingsPerEmail = 50; // Max bookings per email per day
    public int $maxBookingsPerIP = 100; // Max bookings per IP per day
    public int $rateLimitMinutes = 0; // Minimum minutes between bookings from same email/IP (0 = disabled)

    // Cancellation policy
    public int $cancellationPolicyHours = 24; // Hours before appointment when cancellation is allowed
    public int $minimumAdvanceBookingHours = 2; // Minimum hours in advance for bookings

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['maxBookingsPerEmail', 'maxBookingsPerIP', 'rateLimitMinutes', 'cancellationPolicyHours', 'minimumAdvanceBookingHours', 'paymentQrAssetId'], 'integer', 'min' => 0],
            [['ownerEmail'], 'email', 'skipOnEmpty' => true],
            [['ownerName', 'ownerEmail', 'ownerNotificationSubject'], 'string', 'max' => 255],
            [['ownerNotificationEnabled'], 'boolean'],
        ];
    }

    /**
     * Get the effective email address (custom or Craft default)
     */
    public function getEffectiveEmail(): string
    {
        if (!empty($this->ownerEmail)) {
            return $this->ownerEmail;
        }
        
        // Use Craft system email
        return Craft::$app->projectConfig->get('email.fromEmail') 
            ?? Craft::$app->systemSettings->getEmailSettings()->fromEmail 
            ?? '';
    }

    /**
     * Get the effective name (custom or Craft site name)
     */
    public function getEffectiveName(): string
    {
        if (!empty($this->ownerName)) {
            return $this->ownerName;
        }
        
        // Use Craft site name
        return Craft::$app->sites->getCurrentSite()->name ?? 'Booking System';
    }

    /**
     * Get the Craft default email for display in settings
     */
    public static function getCraftDefaultEmail(): string
    {
        return Craft::$app->projectConfig->get('email.fromEmail') 
            ?? Craft::$app->systemSettings->getEmailSettings()->fromEmail 
            ?? '';
    }

    /**
     * Get the Craft default site name for display in settings
     */
    public static function getCraftDefaultName(): string
    {
        return Craft::$app->sites->getCurrentSite()->name ?? '';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'ownerEmail' => 'Owner Email',
            'ownerName' => 'Owner Name',
            'ownerNotificationEnabled' => 'Send Owner Notification',
            'ownerNotificationSubject' => 'Owner Notification Subject',
            'maxBookingsPerEmail' => 'Max Bookings Per Email (per day)',
            'maxBookingsPerIP' => 'Max Bookings Per IP (per day)',
            'rateLimitMinutes' => 'Rate Limit (minutes between bookings)',
            'cancellationPolicyHours' => 'Cancellation Policy (hours before appointment)',
            'minimumAdvanceBookingHours' => 'Minimum Advance Booking (hours)',
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

        $record->ownerEmail = $this->ownerEmail;
        $record->ownerName = $this->ownerName;
        $record->ownerNotificationEnabled = $this->ownerNotificationEnabled;
        $record->ownerNotificationSubject = $this->ownerNotificationSubject;
        $record->paymentQrAssetId = $this->paymentQrAssetId;

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
        
        // Get Craft defaults
        $craftName = Craft::$app->sites->getCurrentSite()->name ?? '';
        $craftEmail = Craft::$app->projectConfig->get('email.fromEmail') ?? '';

        if ($record) {
            $model->id = $record->id;

            // Owner notification settings
            $model->ownerNotificationEnabled = (bool)($record->ownerNotificationEnabled ?? true);
            $model->ownerNotificationSubject = $record->ownerNotificationSubject;

            // Payment QR code
            $model->paymentQrAssetId = $record->paymentQrAssetId;
            
            // Use DB value only if it's a real custom value (not empty and not a known placeholder)
            $dbName = trim($record->ownerName ?? '');
            $dbEmail = trim($record->ownerEmail ?? '');
            
            // Check if DB values are meaningful custom values
            $isPlaceholderName = empty($dbName) || strtolower($dbName) === 'site owner' || strtolower($dbName) === 'owner';
            $isPlaceholderEmail = empty($dbEmail) || strpos($dbEmail, 'example.com') !== false;
            
            $model->ownerName = $isPlaceholderName ? $craftName : $dbName;
            $model->ownerEmail = $isPlaceholderEmail ? $craftEmail : $dbEmail;
        } else {
            // No record - use Craft defaults
            $model->ownerName = $craftName;
            $model->ownerEmail = $craftEmail;
        }

        return $model;
    }

    /**
     * Get default buffer minutes (for backwards compatibility)
     */
    public function getDefaultBufferMinutes(): int
    {
        return $this->defaultBufferMinutes;
    }

    /**
     * Get default slot duration minutes (for backwards compatibility)
     */
    public function getDefaultSlotDurationMinutes(): int
    {
        return $this->defaultSlotDurationMinutes;
    }

    /**
     * Get the effective owner notification subject
     */
    public function getEffectiveOwnerNotificationSubject(): string
    {
        if (!empty($this->ownerNotificationSubject)) {
            return $this->ownerNotificationSubject;
        }
        
        return 'Neue Buchung eingegangen';
    }

    /**
     * Get the payment QR code asset
     *
     * @return \craft\elements\Asset|null
     */
    public function getPaymentQrAsset(): ?\craft\elements\Asset
    {
        if (!$this->paymentQrAssetId) {
            return null;
        }

        return Craft::$app->assets->getAssetById($this->paymentQrAssetId);
    }

    /**
     * Get the payment QR code file path
     *
     * First checks if an asset is uploaded via the settings.
     * Falls back to checking for a file at web/media/payment-qr.png (or .jpg, .gif, .webp)
     * If found, it will be attached to client confirmation emails.
     *
     * @return string|null Full path to the file, or null if not found
     */
    public function getPaymentQrFilePath(): ?string
    {
        // First, check if we have an uploaded asset
        $asset = $this->getPaymentQrAsset();
        if ($asset) {
            // Get the volume and file system path
            $volume = $asset->getVolume();
            if ($volume && $volume->fs) {
                try {
                    // Get the file system path to the asset
                    $path = $asset->getCopyOfFile();
                    if ($path && file_exists($path)) {
                        return $path;
                    }
                } catch (\Throwable $e) {
                    Craft::error("Failed to get asset file path: " . $e->getMessage(), __METHOD__);
                }
            }
        }

        // Fall back to checking the old location
        $webPath = Craft::getAlias('@webroot');
        $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

        foreach ($extensions as $ext) {
            $path = $webPath . '/media/payment-qr.' . $ext;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if a payment QR code exists (either uploaded asset or file)
     */
    public function hasPaymentQr(): bool
    {
        return $this->getPaymentQrFilePath() !== null;
    }
}
