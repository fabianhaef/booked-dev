<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\elements\Reservation;
use fabian\booked\Booked;
use fabian\booked\models\Settings;
use fabian\booked\records\ReservationRecord;
use DateTime;

/**
 * Reminder Service
 */
class ReminderService extends Component
{
    /**
     * Send all pending reminders
     * 
     * @return int Number of reminders sent
     */
    public function sendReminders(): int
    {
        $settings = Booked::getInstance()->getSettings();
        if (!$settings->emailRemindersEnabled && !$settings->smsRemindersEnabled) {
            return 0;
        }

        $sentCount = 0;
        $reservations = $this->getPendingReminders();

        foreach ($reservations as $reservation) {
            if ($this->processReservationReminders($reservation, $settings)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Process reminders for a single reservation
     */
    protected function processReservationReminders(Reservation $reservation, Settings $settings): bool
    {
        $now = new DateTime();
        $bookingTime = new DateTime($reservation->bookingDate . ' ' . $reservation->startTime);
        $diff = $now->diff($bookingTime);
        $hoursRemaining = ($diff->days * 24) + $diff->h + ($diff->i / 60);
        
        // Don't send reminders for past bookings
        if ($bookingTime < $now) {
            return false;
        }

        $sent = false;

        // 24h Email Reminder
        if ($settings->emailRemindersEnabled && 
            !$reservation->emailReminder24hSent && 
            $hoursRemaining <= $settings->emailReminderHoursBefore && 
            $hoursRemaining > 1) {
            if ($this->sendEmailReminder($reservation, '24h')) {
                $reservation->emailReminder24hSent = true;
                $sent = true;
            }
        }

        // 1h Email Reminder
        if ($settings->emailRemindersEnabled && 
            $settings->emailReminderOneHourBefore && 
            !$reservation->emailReminder1hSent && 
            $hoursRemaining <= 1) {
            if ($this->sendEmailReminder($reservation, '1h')) {
                $reservation->emailReminder1hSent = true;
                $sent = true;
            }
        }

        // 24h SMS Reminder
        if ($settings->smsRemindersEnabled && 
            !$reservation->smsReminder24hSent && 
            $hoursRemaining <= $settings->smsReminderHoursBefore && 
            $hoursRemaining > 1) {
            if ($this->sendSmsReminder($reservation, '24h')) {
                $reservation->smsReminder24hSent = true;
                $sent = true;
            }
        }

        // 1h SMS Reminder
        if ($settings->smsRemindersEnabled && 
            !$reservation->smsReminder1hSent && 
            $hoursRemaining <= 1) {
            if ($this->sendSmsReminder($reservation, '1h')) {
                $reservation->smsReminder1hSent = true;
                $sent = true;
            }
        }

        if ($sent) {
            $this->saveReservation($reservation);
        }

        return $sent;
    }

    /**
     * Save reservation element
     */
    protected function saveReservation(Reservation $reservation): bool
    {
        return Craft::$app->getElements()->saveElement($reservation);
    }

    /**
     * Get reservations that might need reminders
     */
    public function getPendingReminders(): array
    {
        $tomorrow = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $now = (new DateTime())->format('Y-m-d H:i:s');

        return Reservation::find()
            ->status(ReservationRecord::STATUS_CONFIRMED)
            ->andWhere(['>=', 'bookingDate', date('Y-m-d')])
            ->all();
    }

    /**
     * Send email reminder
     */
    protected function sendEmailReminder(Reservation $reservation, string $type): bool
    {
        Booked::getInstance()->getBooking()->queueBookingEmail($reservation->id, 'reminder_' . $type);
        return true;
    }

    /**
     * Send SMS reminder
     */
    protected function sendSmsReminder(Reservation $reservation, string $type): bool
    {
        if (empty($reservation->userPhone)) {
            return false;
        }

        $settings = Booked::getInstance()->getSettings();
        if (!$settings->isSmsConfigured()) {
            return false;
        }

        $client = new \GuzzleHttp\Client();
        $message = "Erinnerung: Ihr Termin ({$reservation->getService()->title}) am " . 
                   Craft::$app->getFormatter()->asDate($reservation->bookingDate, 'short') . 
                   " um {$reservation->startTime} Uhr.";

        try {
            $response = $client->post("https://api.twilio.com/2010-04-01/Accounts/{$settings->twilioAccountSid}/Messages.json", [
                'auth' => [$settings->twilioAccountSid, $settings->twilioAuthToken],
                'form_params' => [
                    'From' => $settings->twilioPhoneNumber,
                    'To' => $reservation->userPhone,
                    'Body' => $message,
                ],
            ]);

            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            Craft::error("Twilio SMS failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}

