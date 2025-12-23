<?php

namespace modules\booking\variables;

use Craft;
use modules\booking\BookingModule;
use modules\booking\services\AvailabilityService;
use modules\booking\services\BookingService;
use modules\booking\models\Settings;

/**
 * Booking Variable
 *
 * Provides template access to booking functionality
 * Usage: {{ craft.booking.getForm() }}
 */
class BookingVariable
{
    private AvailabilityService $availabilityService;
    private BookingService $bookingService;

    public function __construct()
    {
        $this->availabilityService = BookingModule::getInstance()->availability;
        $this->bookingService = BookingModule::getInstance()->booking;
    }

    /**
     * Get booking form HTML
     *
     * @param array $options Optional configuration
     *   - title: Form title
     *   - text: Form description text
     *   - entry: Entry element or entry ID to filter availabilities (optional - will auto-detect current entry if not provided)
     * @return string
     */
    public function getForm(array $options = []): string
    {
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
        } else {
            // Auto-detect current entry from template variables if not explicitly provided
            $templateVariables = Craft::$app->view->getTwig()->getGlobals();
            if (isset($templateVariables['entry']) && is_object($templateVariables['entry']) && isset($templateVariables['entry']->id)) {
                $entryId = $templateVariables['entry']->id;
            }
        }

        return Craft::$app->view->renderTemplate('booking/booking-form', [
            'title' => $title,
            'text' => $text,
            'entryId' => $entryId,
        ]);
    }

    /**
     * Get available time slots for a date
     *
     * @param string $date Date in Y-m-d format
     * @return array
     */
    public function getAvailableSlots(string $date): array
    {
        return $this->availabilityService->getAvailableSlots($date);
    }

    /**
     * Get next available date
     *
     * @return string|null
     */
    public function getNextAvailableDate(): ?string
    {
        return $this->availabilityService->getNextAvailableDate();
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
        return $this->availabilityService->getAvailabilitySummary($startDate, $endDate);
    }

    /**
     * Get upcoming reservations
     *
     * @param int $limit Number of reservations to return
     * @return array
     */
    public function getUpcomingReservations(int $limit = 10): array
    {
        return $this->bookingService->getUpcomingReservations($limit);
    }

    /**
     * Get booking settings
     *
     * @return Settings
     */
    public function getSettings(): Settings
    {
        return Settings::loadSettings();
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
        return $this->availabilityService->isSlotAvailable($date, $startTime, $endTime);
    }

    /**
     * Get booking statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->bookingService->getBookingStats();
    }
}
