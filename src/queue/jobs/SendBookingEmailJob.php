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
     * @var string|null Optional recipient email override (for testing or special cases)
     */
    public ?string $recipientEmail = null;

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
    /**
     * Render confirmation email body
     * Uses centralized EmailRenderService
     */
    private function renderConfirmationEmail(Reservation $reservation, Settings $settings): string
    {
        return Booked::getInstance()->emailRender->renderConfirmationEmail($reservation, $settings);
    }

    /**
     * Render status change email body
     * Uses centralized EmailRenderService
     */
    private function renderStatusChangeEmail(Reservation $reservation, ?string $oldStatus, Settings $settings): string
    {
        return Booked::getInstance()->emailRender->renderStatusChangeEmail($reservation, $oldStatus ?? 'unknown', $settings);
    }

    /**
     * Render cancellation email body
     * Uses centralized EmailRenderService
     */
    private function renderCancellationEmail(Reservation $reservation, Settings $settings): string
    {
        return Booked::getInstance()->emailRender->renderCancellationEmail($reservation, $settings);
    }

    /**
     * Render owner notification email body
     * Uses centralized EmailRenderService
     */
    private function renderOwnerNotificationEmail(Reservation $reservation, Settings $settings): string
    {
        return Booked::getInstance()->emailRender->renderOwnerNotificationEmail($reservation, $settings);
    }

    /**
     * Render reminder email body
     * Uses centralized EmailRenderService
     */
    private function renderReminderEmail(Reservation $reservation, string $type, Settings $settings): string
    {
        $hoursBefore = ($type === '24h') ? 24 : 1;
        return Booked::getInstance()->emailRender->renderReminderEmail($reservation, $settings, $hoursBefore);
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
