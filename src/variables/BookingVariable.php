<?php

namespace fabian\booked\variables;

use Craft;
use craft\helpers\Template;
use fabian\booked\Booked;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\BookingService;
use fabian\booked\models\Settings;
use Twig\Markup;

/**
 * Booking Variable
 *
 * Provides template access to booking functionality
 * Usage: {{ craft.booked.getForm() }} or {{ craft.booking.getForm() }} (backward compatibility)
 */
class BookingVariable
{
    /**
     * Get booking form HTML
     *
     * @param array $options Optional configuration
     *   - title: Form title
     *   - text: Form description text
     *   - entry: Entry element or entry ID to filter availabilities (optional - will auto-detect current entry if not provided)
     * @return Markup
     */
    public function getForm(array $options = []): Markup
    {
        $viewMode = $options['viewMode'] ?? Booked::getInstance()->getSettings()->defaultViewMode ?? 'wizard';
        $title = $options['title'] ?? '';
        $text = $options['text'] ?? '';
        $entry = $options['entry'] ?? null;

        // Extract entry ID if entry object is passed
        $entryId = null;
        if ($entry) {
            if (is_object($entry) && isset($entry->id)) {
                $entryId = $entry->id;
            } elseif (is_numeric($entry)) {
                $entryId = (int)$entry;
            }
        }

        $template = 'booked/frontend/' . $viewMode;
        
        // Fallback to legacy form if requested specifically or template not found
        if ($viewMode === 'legacy' || !Craft::$app->view->doesTemplateExist($template)) {
            return Template::raw(Craft::$app->view->renderTemplate('booked/booking-form', [
                'title' => $title,
                'text' => $text,
                'entryId' => $entryId,
            ]));
        }

        return Template::raw(Craft::$app->view->renderTemplate($template, [
            'title' => $title,
            'text' => $text,
            'entryId' => $entryId,
            'options' => $options
        ]));
    }

    /**
     * Get the booking wizard HTML
     * @return Markup
     */
    public function getWizard(array $options = []): Markup
    {
        return Template::raw(Craft::$app->view->renderTemplate('booked/frontend/wizard', [
            'options' => $options
        ]));
    }

    /**
     * Get the booking catalog HTML
     * @return Markup
     */
    public function getCatalog(array $options = []): Markup
    {
        return Template::raw(Craft::$app->view->renderTemplate('booked/frontend/catalog', [
            'options' => $options
        ]));
    }

    /**
     * Get the booking search HTML
     * @return Markup
     */
    public function getSearch(array $options = []): Markup
    {
        return Template::raw(Craft::$app->view->renderTemplate('booked/frontend/search', [
            'options' => $options
        ]));
    }

    /**
     * Get available time slots for a date
     *
     * @param string $date Date in Y-m-d format
     * @return array
     */
    public function getAvailableSlots(string $date): array
    {
        return Booked::getInstance()->getAvailability()->getAvailableSlots($date);
    }

    /**
     * Get next available date
     *
     * @return string|null
     */
    public function getNextAvailableDate(): ?string
    {
        return Booked::getInstance()->getAvailability()->getNextAvailableDate();
    }

    /**
     * Get availability calendar for date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return array
     */
    public function getAvailabilityCalendar(string $startDate, string $endDate): array
    {
        return Booked::getInstance()->getAvailability()->getAvailabilitySummary($startDate, $endDate);
    }

    /**
     * Get upcoming reservations
     *
     * @param int $limit Number of reservations to return
     * @return array
     */
    public function getUpcomingReservations(int $limit = 10): array
    {
        return Booked::getInstance()->getBooking()->getUpcomingReservations($limit);
    }

    /**
     * Get booking settings
     *
     * @return Settings
     */
    public function getSettings(): Settings
    {
        return Booked::getInstance()->getSettings();
    }

    /**
     * Check if a time slot is available
     *
     * @param string $date Date in Y-m-d format
     * @param string $startTime Time in H:i format
     * @param string $endTime Time in H:i format
     * @return bool
     */
    public function isSlotAvailable(string $date, string $startTime, string $endTime): bool
    {
        return Booked::getInstance()->getAvailability()->isSlotAvailable($date, $startTime, $endTime);
    }

    /**
     * Get booking statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return Booked::getInstance()->getBooking()->getBookingStats();
    }

    /**
     * Check if a payment QR code exists
     *
     * Checks for an uploaded asset or a file at web/media/payment-qr.png (or .jpg, .gif, .webp)
     *
     * @return bool
     */
    public function hasPaymentQrFile(): bool
    {
        $settings = Booked::getInstance()->getSettings();
        // Assuming settings has this method, if not we check for the legacy attribute
        return method_exists($settings, 'hasPaymentQr') ? $settings->hasPaymentQr() : false;
    }
}
}
