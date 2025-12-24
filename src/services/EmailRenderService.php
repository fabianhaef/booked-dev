<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\elements\Reservation;
use fabian\booked\models\Settings;

/**
 * Email Render Service
 *
 * Centralized email template rendering for booking notifications.
 * Consolidates duplicate rendering logic previously split between
 * BookingService and SendBookingEmailJob.
 */
class EmailRenderService extends Component
{
    /**
     * Render booking confirmation email
     *
     * @param Reservation $reservation
     * @param Settings $settings
     * @return string HTML email content
     */
    public function renderConfirmationEmail(Reservation $reservation, Settings $settings): string
    {
        $service = $reservation->getService();
        $employee = $reservation->getEmployee();
        $location = $reservation->getLocation();

        // Use custom template body if configured
        if ($settings->bookingConfirmationBody) {
            return Craft::$app->view->renderString($settings->bookingConfirmationBody, [
                'reservation' => $reservation,
                'service' => $service,
                'employee' => $employee,
                'location' => $location,
                'settings' => $settings,
            ]);
        }

        // Render default confirmation template
        return Craft::$app->view->renderTemplate('booked/emails/confirmation', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
            'location' => $location,
            'settings' => $settings,
            'manageUrl' => $reservation->getManagementUrl(),
            'cancelUrl' => $reservation->getCancelUrl(),
            'icsUrl' => $reservation->getIcsUrl(),
        ]);
    }

    /**
     * Render status change notification email
     *
     * @param Reservation $reservation
     * @param string $oldStatus
     * @param Settings $settings
     * @return string HTML email content
     */
    public function renderStatusChangeEmail(Reservation $reservation, string $oldStatus, Settings $settings): string
    {
        $service = $reservation->getService();
        $employee = $reservation->getEmployee();
        $location = $reservation->getLocation();

        return Craft::$app->view->renderTemplate('booked/emails/status-change', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
            'location' => $location,
            'oldStatus' => $oldStatus,
            'newStatus' => $reservation->status,
            'settings' => $settings,
            'manageUrl' => $reservation->getManagementUrl(),
        ]);
    }

    /**
     * Render cancellation notification email
     *
     * @param Reservation $reservation
     * @param Settings $settings
     * @return string HTML email content
     */
    public function renderCancellationEmail(Reservation $reservation, Settings $settings): string
    {
        $service = $reservation->getService();
        $employee = $reservation->getEmployee();
        $location = $reservation->getLocation();

        return Craft::$app->view->renderTemplate('booked/emails/cancellation', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
            'location' => $location,
            'settings' => $settings,
            'cancelledAt' => new \DateTime(),
            'cancellationReason' => $reservation->cancellationReason ?? 'No reason provided',
        ]);
    }

    /**
     * Render reminder email
     *
     * @param Reservation $reservation
     * @param Settings $settings
     * @param int $hoursBefore Hours before appointment
     * @return string HTML email content
     */
    public function renderReminderEmail(Reservation $reservation, Settings $settings, int $hoursBefore = 24): string
    {
        $service = $reservation->getService();
        $employee = $reservation->getEmployee();
        $location = $reservation->getLocation();

        return Craft::$app->view->renderTemplate('booked/emails/reminder', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
            'location' => $location,
            'settings' => $settings,
            'hoursBefore' => $hoursBefore,
            'manageUrl' => $reservation->getManagementUrl(),
            'icsUrl' => $reservation->getIcsUrl(),
        ]);
    }

    /**
     * Render owner notification email
     *
     * @param Reservation $reservation
     * @param Settings $settings
     * @return string HTML email content
     */
    public function renderOwnerNotificationEmail(Reservation $reservation, Settings $settings): string
    {
        $service = $reservation->getService();
        $employee = $reservation->getEmployee();
        $location = $reservation->getLocation();

        return Craft::$app->view->renderTemplate('booked/emails/owner-notification', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
            'location' => $location,
            'settings' => $settings,
            'adminUrl' => \craft\helpers\UrlHelper::cpUrl('booked/reservations/' . $reservation->id),
        ]);
    }
}
