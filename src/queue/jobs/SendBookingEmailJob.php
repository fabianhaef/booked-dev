<?php

namespace fabian\booked\queue\jobs;

use Craft;
use craft\helpers\UrlHelper;
use craft\mail\Message;
use craft\queue\BaseJob;
use fabian\booked\elements\Reservation;
use fabian\booked\models\Settings;

/**
 * Send Booking Email Job
 *
 * Queued job for sending booking-related emails with retry logic.
 * Supports sending to both clients and site owner, with optional attachments.
 */
class SendBookingEmailJob extends BaseJob
{
    /**
     * @var int Reservation ID
     */
    public int $reservationId;

    /**
     * @var string Email type: 'confirmation', 'status_change', 'cancellation', 'owner_notification'
     */
    public string $emailType;

    /**
     * @var string|null Old status (for status change emails)
     */
    public ?string $oldStatus = null;

    /**
     * @var int Current attempt number (for tracking)
     */
    public int $attempt = 1;


    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $reservation = $this->getReservation();

        if (!$reservation) {
            Craft::error("Cannot send email: Reservation #{$this->reservationId} not found", __METHOD__);
            return;
        }

        $settings = Settings::loadSettings();
        
        // Determine recipient based on email type
        $isOwnerEmail = ($this->emailType === 'owner_notification');
        $recipientEmail = $isOwnerEmail ? $settings->getEffectiveEmail() : $reservation->userEmail;

        $this->setProgress($queue, 0.1, "Preparing email for {$recipientEmail}");

        try {
            // Prepare email based on type
            [$subject, $body] = $this->prepareEmail($reservation, $settings);

            $this->setProgress($queue, 0.3, "Sending email");

            // Get email settings using the Settings helper methods (uses Craft defaults if not overridden)
            $fromEmail = $settings->getEffectiveEmail();
            $fromName = $settings->getEffectiveName();

            $message = new Message();
            $message->setTo($recipientEmail)
                   ->setFrom([$fromEmail => $fromName])
                   ->setSubject($subject)
                   ->setHtmlBody($body);

            // Add ICS attachment for client confirmation and reminder emails
            if ($this->emailType === 'confirmation' || strpos($this->emailType, 'reminder_') === 0) {
                $icsContent = \fabian\booked\helpers\IcsHelper::generate($reservation);
                $message->attachContent($icsContent, [
                    'fileName' => 'termin.ics',
                    'contentType' => 'text/calendar; charset=utf-8; method=REQUEST',
                ]);
            }

            $sent = Craft::$app->mailer->send($message);

            if ($sent) {
                $this->setProgress($queue, 0.9, "Email sent successfully");

                // Mark notification as sent for confirmation emails (only for client emails)
                if ($this->emailType === 'confirmation' && !$reservation->notificationSent) {
                    $reservationElement = \fabian\booked\elements\Reservation::find()->id($reservation->id)->one();
                    if ($reservationElement) {
                        $reservationElement->notificationSent = true;
                        Craft::$app->elements->saveElement($reservationElement);
                    }
                }

                Craft::info("Email sent successfully: {$this->emailType} to {$recipientEmail} (Attempt {$this->attempt})", __METHOD__);
            } else {
                throw new \Exception('Mailer returned false');
            }

            $this->setProgress($queue, 1, "Complete");

        } catch (\Throwable $e) {
            Craft::error(
                "Failed to send {$this->emailType} email to {$recipientEmail} (Attempt {$this->attempt}): " . $e->getMessage(),
                __METHOD__
            );

            // Re-throw to trigger queue retry logic
            throw $e;
        }
    }


    /**
     * Get reservation by ID
     */
    private function getReservation(): ?Reservation
    {
        return Reservation::find()
            ->id($this->reservationId)
            ->status(null)
            ->one();
    }

    /**
     * Prepare email content based on type
     */
    private function prepareEmail(Reservation $reservation, Settings $settings): array
    {
        switch ($this->emailType) {
            case 'confirmation':
                $subject = 'Buchungsbestätigung';
                $body = $this->renderConfirmationEmail($reservation, $settings);
                break;

            case 'status_change':
                $subject = 'Buchungsstatus geändert';
                $body = $this->renderStatusChangeEmail($reservation, $this->oldStatus, $settings);
                break;

            case 'cancellation':
                $subject = 'Buchung storniert';
                $body = $this->renderCancellationEmail($reservation, $settings);
                break;

            case 'owner_notification':
                $subject = $settings->getEffectiveOwnerNotificationSubject();
                $body = $this->renderOwnerNotificationEmail($reservation, $settings);
                break;

            case 'reminder_24h':
                $subject = 'Erinnerung: Ihr Termin morgen';
                $body = $this->renderReminderEmail($reservation, '24h', $settings);
                break;

            case 'reminder_1h':
                $subject = 'Erinnerung: Ihr Termin in einer Stunde';
                $body = $this->renderReminderEmail($reservation, '1h', $settings);
                break;

            default:
                throw new \Exception("Unknown email type: {$this->emailType}");
        }

        return [$subject, $body];
    }

    /**
     * Render confirmation email body
     */
    private function renderConfirmationEmail(Reservation $reservation, Settings $settings): string
    {
        // Get variation information if available
        $variationInfo = '';
        if ($reservation->variationId) {
            $variation = \fabian\booked\elements\BookingVariation::find()
                ->id($reservation->variationId)
                ->one();
            if ($variation) {
                $variationInfo = $variation->title;
            }
        }

        // Format date nicely
        $dateObj = \DateTime::createFromFormat('Y-m-d', $reservation->bookingDate);
        $formattedDate = $dateObj ? $dateObj->format('d.m.Y') : $reservation->bookingDate;

        // Get source information
        $sourceName = $reservation->getSourceName();

        $variables = [
            // Customer data
            'userName' => $reservation->userName,
            'userEmail' => $reservation->userEmail,
            'userPhone' => $reservation->userPhone ?: '',
            
            // Booking details
            'bookingDate' => $formattedDate,
            'startTime' => $reservation->startTime,
            'endTime' => $reservation->endTime,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'duration' => $reservation->getDurationMinutes(),
            'status' => $reservation->getStatusLabel(),
            'notes' => $reservation->notes ?: '',
            
            // Variation & quantity
            'variationName' => $variationInfo,
            'quantity' => $reservation->quantity,
            'quantityDisplay' => $reservation->quantity > 1,
            
            // Source (what was booked)
            'sourceName' => $sourceName ?: '',
            'sourceType' => $reservation->sourceType ?: '',
            
            // Booking reference
            'bookingId' => $reservation->id,
            'confirmationToken' => $reservation->confirmationToken,
            
            // Custom Fields
            'customFields' => $this->getCustomFieldData($reservation),
            
            // Sender info (uses Craft defaults if not overridden)
            'ownerName' => $settings->getEffectiveName(),
            'ownerEmail' => $settings->getEffectiveEmail(),
            'siteName' => \Craft::$app->sites->getCurrentSite()->name,
            
            // Management URLs
            'managementUrl' => $reservation->getManagementUrl(),
            'cancelUrl' => $reservation->getCancelUrl(),
            
            // Virtual Meeting
            'virtualMeetingUrl' => $reservation->virtualMeetingUrl ?: '',
            'virtualMeetingProvider' => $reservation->virtualMeetingProvider ?: '',
            'isVirtual' => !empty($reservation->virtualMeetingUrl),

            // Date created
            'dateCreated' => $reservation->dateCreated ? $reservation->dateCreated->format('d.m.Y H:i') : '',
        ];

        // Always use the Twig template
        return Craft::$app->view->renderTemplate('booked/emails/confirmation', $variables);
    }

    /**
     * Render status change email body
     */
    private function renderStatusChangeEmail(Reservation $reservation, ?string $oldStatus, Settings $settings): string
    {
        $variables = [
            'userName' => $reservation->userName,
            'userEmail' => $reservation->userEmail,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'oldStatus' => $oldStatus ? ucfirst($oldStatus) : 'Unknown',
            'newStatus' => $reservation->getStatusLabel(),
            'ownerName' => $settings->getEffectiveName(),
            'ownerEmail' => $settings->getEffectiveEmail(),
            'siteName' => Craft::$app->sites->getCurrentSite()->name,
            'managementUrl' => $reservation->getManagementUrl(),
            'bookingId' => $reservation->id,
        ];

        return Craft::$app->view->renderTemplate('booked/emails/status-change', $variables);
    }

    /**
     * Render cancellation email body
     */
    private function renderCancellationEmail(Reservation $reservation, Settings $settings): string
    {
        $variables = [
            'userName' => $reservation->userName,
            'userEmail' => $reservation->userEmail,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'ownerName' => $settings->getEffectiveName(),
            'ownerEmail' => $settings->getEffectiveEmail(),
            'siteName' => Craft::$app->sites->getCurrentSite()->name,
            'bookingId' => $reservation->id,
        ];

        return Craft::$app->view->renderTemplate('booked/emails/cancellation', $variables);
    }

    /**
     * Render owner notification email body
     */
    private function renderOwnerNotificationEmail(Reservation $reservation, Settings $settings): string
    {
        // Get variation information if available
        $variationInfo = '';
        if ($reservation->variationId) {
            $variation = \fabian\booked\elements\BookingVariation::find()
                ->id($reservation->variationId)
                ->one();
            if ($variation) {
                $variationInfo = $variation->title;
            }
        }

        // Format date nicely
        $dateObj = \DateTime::createFromFormat('Y-m-d', $reservation->bookingDate);
        $formattedDate = $dateObj ? $dateObj->format('d.m.Y') : $reservation->bookingDate;

        // Get source information
        $sourceName = $reservation->getSourceName();

        // Build CP edit URL
        $cpEditUrl = UrlHelper::cpUrl('booked/bookings/edit/' . $reservation->id);

        $variables = [
            // Customer data
            'userName' => $reservation->userName,
            'userEmail' => $reservation->userEmail,
            'userPhone' => $reservation->userPhone ?: '',
            
            // Booking details
            'bookingDate' => $formattedDate,
            'startTime' => $reservation->startTime,
            'endTime' => $reservation->endTime,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'duration' => $reservation->getDurationMinutes(),
            'status' => $reservation->getStatusLabel(),
            'notes' => $reservation->notes ?: '',
            
            // Variation & quantity
            'variationName' => $variationInfo,
            'quantity' => $reservation->quantity,
            'quantityDisplay' => $reservation->quantity > 1,
            
            // Source (what was booked)
            'sourceName' => $sourceName ?: '',
            'sourceType' => $reservation->sourceType ?: '',
            
            // Booking reference
            'bookingId' => $reservation->id,
            
            // Site info
            'ownerName' => $settings->getEffectiveName(),
            'ownerEmail' => $settings->getEffectiveEmail(),
            'siteName' => Craft::$app->sites->getCurrentSite()->name,
            
            // CP URL for management
            'cpEditUrl' => $cpEditUrl,
            
            // Date created
            'dateCreated' => $reservation->dateCreated ? $reservation->dateCreated->format('d.m.Y H:i') : '',
        ];

        return Craft::$app->view->renderTemplate('booked/emails/owner-notification', $variables);
    }

    /**
     * Render reminder email body
     */
    private function renderReminderEmail(Reservation $reservation, string $type, Settings $settings): string
    {
        $variables = [
            'userName' => $reservation->userName,
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'reminderType' => $type,
            'ownerName' => $settings->getEffectiveName(),
            'managementUrl' => $reservation->getManagementUrl(),
            'virtualMeetingUrl' => $reservation->virtualMeetingUrl ?: '',
            'virtualMeetingProvider' => $reservation->virtualMeetingProvider ?: '',
            'isVirtual' => !empty($reservation->virtualMeetingUrl),
        ];

        return Craft::$app->view->renderTemplate('booked/emails/reminder', $variables);
    }

    /**
     * Get custom field data for the reservation
     */
    private function getCustomFieldData(Reservation $reservation): array
    {
        $data = [];
        $fieldLayout = $reservation->getFieldLayout();
        if (!$fieldLayout) {
            return $data;
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            $value = $reservation->getFieldValue($field->handle);
            if ($value !== null && $value !== '') {
                // Format the value based on field type if necessary
                $label = $field->name;
                
                // Simplified formatting for email
                if (is_object($value)) {
                    if (method_exists($value, '__toString')) {
                        $value = (string)$value;
                    } else if (property_exists($value, 'name')) {
                        $value = $value->name;
                    } else {
                        $value = '[Object]';
                    }
                } else if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                $data[] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return "Sending {$this->emailType} email for booking #{$this->reservationId}";
    }

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        // Time To Reserve: 60 seconds per email attempt
        return 60;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        // Retry up to 3 times with exponential backoff
        return $attempt < 3;
    }
}
