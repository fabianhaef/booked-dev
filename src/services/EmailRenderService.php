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

        // Use custom template body if configured
        if ($settings->bookingConfirmationBody) {
            return Craft::$app->view->renderString($settings->bookingConfirmationBody, [
                'reservation' => $reservation,
                'service' => $service,
                'employee' => $employee,
                'settings' => $settings,
            ]);
        }

        // Render default confirmation template
        return Craft::$app->view->renderTemplate('booked/emails/confirmation', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
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

        return Craft::$app->view->renderTemplate('booked/emails/status-change', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
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

        return Craft::$app->view->renderTemplate('booked/emails/cancellation', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
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

        return Craft::$app->view->renderTemplate('booked/emails/reminder', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
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

        // Get employee name from associated User
        $employeeName = '';
        if ($employee) {
            $user = $employee->getUser();
            $employeeName = $user ? $user->getName() : '';
        }

        // Get location name from title
        $locationName = $location ? $location->title : '';

        return Craft::$app->view->renderTemplate('booked/emails/owner-notification', [
            'reservation' => $reservation,
            'service' => $service,
            'employee' => $employee,
            'location' => $location,
            'settings' => $settings,
            'cpEditUrl' => \craft\helpers\UrlHelper::cpUrl('booked/bookings/' . $reservation->id),
            'siteName' => \Craft::$app->getSystemName(),
            'formattedDateTime' => $reservation->getFormattedDateTime(),
            'sourceName' => $service ? $service->title : null,
            'variationName' => null,
            // Individual template variables
            'userName' => $reservation->userName,
            'userEmail' => $reservation->userEmail,
            'userPhone' => $reservation->userPhone,
            'bookingId' => $reservation->id,
            'bookingDate' => $reservation->bookingDate,
            'startTime' => $reservation->startTime,
            'endTime' => $reservation->endTime,
            'duration' => $reservation->getDurationMinutes(),
            'serviceName' => $service ? $service->title : '',
            'employeeName' => $employeeName,
            'locationName' => $locationName,
            'quantity' => $reservation->quantity,
            'quantityDisplay' => $reservation->quantity > 1, // Only show if more than 1
            'status' => $reservation->getStatusLabel(),
            'notes' => $reservation->notes,
            'dateCreated' => $reservation->dateCreated->format('d.m.Y H:i'),
        ]);
    }
}
