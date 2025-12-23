<?php

namespace fabian\booked\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\Reservation;
use fabian\booked\elements\Service;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use fabian\booked\exceptions\BookingConflictException;
use fabian\booked\exceptions\BookingException;
use fabian\booked\exceptions\BookingRateLimitException;
use fabian\booked\exceptions\BookingValidationException;
use fabian\booked\models\forms\BookingForm;
use fabian\booked\services\AvailabilityService;
use fabian\booked\services\BookingService;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Booking Controller - Handles frontend booking requests
 */
class BookingController extends Controller
{
    protected array|bool|int $allowAnonymous = [
        'get-available-slots', 
        'get-available-variations', 
        'get-event-dates', 
        'get-availability-calendar', 
        'create-booking', 
        'manage-booking', 
        'cancel-booking-by-token',
        'get-services',
        'get-employees',
        'get-locations'
    ];

    private AvailabilityService $availabilityService;
    private BookingService $bookingService;

    public function init(): void
    {
        parent::init();
        $this->availabilityService = Booked::getInstance()->availability;
        $this->bookingService = Booked::getInstance()->booking;
    }

    /**
     * Get available variations for a specific date
     */
    public function actionGetAvailableVariations(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $date = Craft::$app->request->getRequiredBodyParam('date');
        $entryId = Craft::$app->request->getBodyParam('entryId');

        // Validate date format
        if (!\DateTime::createFromFormat('Y-m-d', $date)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        $variations = $this->availabilityService->getAvailableVariations($date, $entryId);

        return $this->asJson([
            'success' => true,
            'variations' => $variations
        ]);
    }

    /**
     * Get available time slots for a specific date
     */
    public function actionGetAvailableSlots(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $date = Craft::$app->request->getRequiredBodyParam('date');
        $serviceId = Craft::$app->request->getBodyParam('serviceId');
        $employeeId = Craft::$app->request->getBodyParam('employeeId');
        $locationId = Craft::$app->request->getBodyParam('locationId');
        $quantity = (int)(Craft::$app->request->getBodyParam('quantity') ?? 1);

        // Validate date format
        if (!\DateTime::createFromFormat('Y-m-d', $date)) {
            throw new BadRequestHttpException('Invalid date format');
        }

        // Validate quantity
        if ($quantity < 1) {
            $quantity = 1;
        }

        // Convert empty strings to null and cast to int
        $employeeIdInt = ($employeeId !== null && $employeeId !== '') ? (int)$employeeId : null;
        $locationIdInt = ($locationId !== null && $locationId !== '') ? (int)$locationId : null;
        $serviceIdInt = ($serviceId !== null && $serviceId !== '') ? (int)$serviceId : null;

        $slots = $this->availabilityService->getAvailableSlots(
            $date,
            $employeeIdInt,
            $locationIdInt,
            $serviceIdInt,
            $quantity
        );

        return $this->asJson([
            'success' => true,
            'slots' => $slots
        ]);
    }

    /**
     * Get upcoming event dates (for event-based bookings)
     */
    public function actionGetEventDates(): Response
    {
        $this->requireAcceptsJson();

        $entryId = Craft::$app->request->getParam('entryId');
        $eventDates = $this->availabilityService->getUpcomingEventDates($entryId);

        return $this->asJson([
            'success' => true,
            'hasEvents' => !empty($eventDates),
            'eventDates' => $eventDates
        ]);
    }

    /**
     * Get availability calendar data for date picker
     * Returns which dates have availability and which are blacked out
     */
    public function actionGetAvailabilityCalendar(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->request;
        $startDate = $request->getParam('startDate', date('Y-m-d'));
        $endDate = $request->getParam('endDate', date('Y-m-d', strtotime('+90 days')));
        $entryId = $request->getParam('entryId');
        $quantity = (int)($request->getParam('quantity') ?? 1);
        $variationId = $request->getParam('variationId');

        // Validate quantity
        if ($quantity < 1) {
            $quantity = 1;
        }

        $calendar = [];
        $current = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');

            // Check if date is blacked out
            $isBlackedOut = $this->availabilityService->isDateBlackedOut($dateStr);

            // Check if date has available slots with enough capacity for requested quantity
            $hasSlots = false;
            if (!$isBlackedOut) {
                $slots = $this->availabilityService->getAvailableSlots($dateStr, $entryId, $variationId, $quantity);
                $hasSlots = !empty($slots);

            }

            $calendar[$dateStr] = [
                'hasAvailability' => $hasSlots,
                'isBlackedOut' => $isBlackedOut,
                'isBookable' => $hasSlots && !$isBlackedOut
            ];

            $current->add(new \DateInterval('P1D'));
        }

        return $this->asJson([
            'success' => true,
            'calendar' => $calendar
        ]);
    }

    /**
     * Create a new booking
     */
    public function actionCreateBooking(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $form = new BookingForm();

        // Populate form model
        $form->userName = $request->getBodyParam('userName');
        $form->userEmail = $request->getBodyParam('userEmail');
        $form->userPhone = $request->getBodyParam('userPhone');
        $form->userTimezone = Craft::$app->getTimeZone(); // Use system timezone for consistency
        $form->bookingDate = $request->getBodyParam('bookingDate');
        $form->startTime = $request->getBodyParam('startTime');
        $form->endTime = $request->getBodyParam('endTime');
        $form->notes = $request->getBodyParam('notes');
        $form->variationId = $request->getBodyParam('variationId') ? (int)$request->getBodyParam('variationId') : null;
        $form->quantity = $request->getBodyParam('quantity') ? (int)$request->getBodyParam('quantity') : 1;
        $form->honeypot = $request->getBodyParam('website');

        // Check spam
        if ($form->isSpam()) {
            Craft::warning('Booking blocked: Honeypot field was filled (spam bot detected)', __METHOD__);
            
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'message' => 'Buchung konnte nicht erstellt werden.']);
            }
            Craft::$app->session->setError('Buchung konnte nicht erstellt werden.');
            return $this->redirectToPostedUrl();
        }

        // Validate input
        if (!$form->validate()) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false, 
                    'message' => 'Validierungsfehler bei der Buchung.',
                    'errors' => $form->getErrors()
                ]);
            }
            Craft::$app->session->setError('Bitte überprüfen Sie Ihre Eingaben.');
            return $this->redirectToPostedUrl($form);
        }

        // Find the matching availability to get source information
        $availability = $this->availabilityService->getAvailabilityForSlot(
            $form->bookingDate, 
            $form->startTime, 
            $form->endTime
        );

        $data = $form->getReservationData();

        // Add source information from the availability
        if ($availability) {
            $data['sourceType'] = $availability->sourceType;
            $data['sourceId'] = $availability->sourceId;
            $data['sourceHandle'] = $availability->sourceHandle;
        }

        try {
            $reservation = $this->bookingService->createReservation($data);

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'Buchung erfolgreich erstellt',
                    'reservation' => [
                        'id' => $reservation->id,
                        'formattedDateTime' => $reservation->getFormattedDateTime(),
                        'status' => $reservation->getStatusLabel()
                    ]
                ]);
            } else {
                Craft::$app->session->setNotice('Ihre Buchung wurde bestätigt!');
                return $this->redirectToPostedUrl();
            }

        } catch (BookingRateLimitException $e) {
            $errorMessage = 'Sie haben zu viele Buchungen vorgenommen. Bitte versuchen Sie es später erneut.';

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => $errorMessage,
                    'error' => 'rate_limit'
                ]);
            } else {
                Craft::$app->session->setError($errorMessage);
                return $this->redirectToPostedUrl($form);
            }

        } catch (BookingConflictException $e) {
            $errorMessage = 'Der gewählte Zeitslot ist nicht mehr verfügbar. Bitte wählen Sie einen anderen Termin.';

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => $errorMessage,
                    'error' => 'conflict'
                ]);
            } else {
                Craft::$app->session->setError($errorMessage);
                return $this->redirectToPostedUrl($form);
            }

        } catch (BookingValidationException $e) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Validierungsfehler bei der Buchung.',
                    'error' => 'validation',
                    'errors' => $e->getValidationErrors()
                ]);
            } else {
                Craft::$app->session->setError('Validierungsfehler: Bitte überprüfen Sie Ihre Eingaben.');
                // Add errors to form so they can be displayed
                $form->addErrors($e->getValidationErrors());
                return $this->redirectToPostedUrl($form);
            }

        } catch (BookingException $e) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                    'error' => 'general'
                ]);
            } else {
                Craft::$app->session->setError('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
                return $this->redirectToPostedUrl($form);
            }
        }
    }

    /**
     * Cancel a booking
     */
    public function actionCancelBooking(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $reason = Craft::$app->request->getBodyParam('reason', '');

        $success = $this->bookingService->cancelReservation($id, $reason);

        if (Craft::$app->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => $success,
                'message' => $success ? 'Buchung erfolgreich storniert' : 'Buchung konnte nicht storniert werden'
            ]);
        } else {
            if ($success) {
                Craft::$app->session->setNotice('Ihre Buchung wurde storniert.');
            } else {
                Craft::$app->session->setError('Buchung konnte nicht storniert werden.');
            }
            return $this->redirectToPostedUrl();
        }
    }

    /**
     * Get next available date
     */
    public function actionGetNextAvailableDate(): Response
    {
        $this->requireAcceptsJson();

        $nextDate = $this->availabilityService->getNextAvailableDate();

        return $this->asJson([
            'success' => true,
            'nextAvailableDate' => $nextDate
        ]);
    }

    /**
     * Manage booking - View booking details by token
     * URL: /booking/manage/{token}
     */
    public function actionManageBooking(string $token): Response
    {
        $reservation = Reservation::findByToken($token);

        if (!$reservation) {
            throw new NotFoundHttpException('Buchung nicht gefunden');
        }

        // If it's a POST request, handle actions (cancel, reschedule)
        if (Craft::$app->request->getIsPost()) {
            $action = Craft::$app->request->getBodyParam('action');

            if ($action === 'cancel') {
                return $this->handleCancelAction($reservation);
            }

            if ($action === 'reschedule') {
                return $this->handleRescheduleAction($reservation);
            }
        }

        // Render the booking management template
        return $this->renderTemplate('booking/manage-booking', [
            'reservation' => $reservation,
            'canCancel' => $reservation->canBeCancelled(),
            'isPast' => strtotime($reservation->bookingDate . ' ' . $reservation->startTime) < time(),
        ]);
    }

    /**
     * Cancel booking by token (direct URL for convenience)
     * URL: /booking/cancel/{token}
     */
    public function actionCancelBookingByToken(string $token): Response
    {
        $reservation = Reservation::findByToken($token);
        
        if (!$reservation) {
            throw new NotFoundHttpException('Buchung nicht gefunden');
        }

        if (!$reservation->canBeCancelled()) {
            Craft::$app->session->setError('Diese Buchung kann nicht storniert werden (sie liegt möglicherweise zu nahe am Termin oder wurde bereits storniert).');
            
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Diese Buchung kann nicht storniert werden'
                ]);
            }
            
            return $this->renderTemplate('booking/manage-booking', [
                'reservation' => $reservation,
                'canCancel' => false,
                'isPast' => strtotime($reservation->bookingDate . ' ' . $reservation->startTime) < time(),
            ]);
        }

        // Show confirmation page if GET request
        if (!Craft::$app->request->getIsPost()) {
            return $this->renderTemplate('booking/cancel-confirmation', [
                'reservation' => $reservation,
            ]);
        }

        // Handle POST - actually cancel the booking
        $reason = Craft::$app->request->getBodyParam('reason', 'Cancelled by user');
        $success = $this->bookingService->cancelReservation($reservation->id, $reason);

        if ($success) {
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'Buchung erfolgreich storniert'
                ]);
            }
            
            Craft::$app->session->setNotice('Ihre Buchung wurde erfolgreich storniert.');
            return $this->renderTemplate('booking/cancelled', [
                'reservation' => $reservation,
            ]);
        }

        if (Craft::$app->request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'message' => 'Stornierung fehlgeschlagen'
            ]);
        }
        
        Craft::$app->session->setError('Failed to cancel booking. Please try again.');
        return $this->redirect($reservation->getManagementUrl());
    }

    /**
     * Handle cancel action from management page
     */
    private function handleCancelAction(Reservation $reservation): Response
    {
        if (!$reservation->canBeCancelled()) {
            return $this->asJson([
                'success' => false,
                'message' => 'Diese Buchung kann nicht storniert werden'
            ]);
        }

        $reason = Craft::$app->request->getBodyParam('reason', 'Storniert vom Benutzer');
        $success = $this->bookingService->cancelReservation($reservation->id, $reason);

        return $this->asJson([
            'success' => $success,
            'message' => $success ? 'Buchung erfolgreich storniert' : 'Stornierung fehlgeschlagen'
        ]);
    }

    /**
     * Handle reschedule action from management page
     */
    private function handleRescheduleAction(Reservation $reservation): Response
    {
        $newDate = Craft::$app->request->getBodyParam('newDate');
        $newStartTime = Craft::$app->request->getBodyParam('newStartTime');
        $newEndTime = Craft::$app->request->getBodyParam('newEndTime');

        if (!$newDate || !$newStartTime || !$newEndTime) {
            return $this->asJson([
                'success' => false,
                'message' => 'Erforderliche Parameter fehlen'
            ]);
        }

        // Check if new slot is available
        if (!$this->availabilityService->isSlotAvailable($newDate, $newStartTime, $newEndTime)) {
            return $this->asJson([
                'success' => false,
                'message' => 'Der gewählte Zeitslot ist nicht verfügbar'
            ]);
        }

        // Update the booking
        $updated = $this->bookingService->updateReservation($reservation->id, [
            'bookingDate' => $newDate,
            'startTime' => $newStartTime,
            'endTime' => $newEndTime,
        ]);

        if ($updated) {
            return $this->asJson([
                'success' => true,
                'message' => 'Buchung erfolgreich verschoben',
                'reservation' => [
                    'formattedDateTime' => $updated->getFormattedDateTime(),
                    'status' => $updated->getStatusLabel()
                ]
            ]);
        }

        return $this->asJson([
            'success' => false,
            'message' => 'Verschieben der Buchung fehlgeschlagen'
        ]);
    }

    /**
     * Get all enabled services
     */
    public function actionGetServices(): Response
    {
        $this->requireAcceptsJson();

        $services = Service::find()
            ->all();

        $data = [];
        foreach ($services as $service) {
            $data[] = [
                'id' => $service->id,
                'title' => $service->title,
                'description' => $service->description ?? '',
                'duration' => $service->duration,
                'price' => $service->price,
                'imageUrl' => null, // Placeholder for now
            ];
        }

        return $this->asJson([
            'success' => true,
            'services' => $data
        ]);
    }

    /**
     * Get all employees
     */
    public function actionGetEmployees(): Response
    {
        $this->requireAcceptsJson();

        $locationId = Craft::$app->request->getParam('locationId');
        $serviceId = Craft::$app->request->getParam('serviceId');

        $query = Employee::find();

        if ($locationId) {
            $query->locationId($locationId);
        }

        // TODO: Filter by service once the relationship is implemented
        
        $employees = $query->all();

        $data = [];
        foreach ($employees as $employee) {
            $data[] = [
                'id' => $employee->id,
                'name' => $employee->title,
                'bio' => '', // Placeholder
                'imageUrl' => null, // Placeholder
            ];
        }

        return $this->asJson([
            'success' => true,
            'employees' => $data
        ]);
    }

    /**
     * Get all locations
     */
    public function actionGetLocations(): Response
    {
        $this->requireAcceptsJson();

        $locations = Location::find()->all();

        $data = [];
        foreach ($locations as $location) {
            $addressParts = array_filter([
                $location->addressLine1,
                $location->addressLine2,
                $location->locality,
                $location->administrativeArea,
                $location->postalCode,
                $location->countryCode
            ]);
            
            $data[] = [
                'id' => $location->id,
                'name' => $location->title,
                'address' => implode(', ', $addressParts),
                'timezone' => $location->timezone,
            ];
        }

        return $this->asJson([
            'success' => true,
            'locations' => $data
        ]);
    }

    /**
     * Sanitize user input to prevent XSS and other injection attacks
     * @deprecated Use BookingForm model instead
     */
    private function sanitizeInput(string $input): string
    {
        // Remove any HTML tags and encode special characters
        $sanitized = strip_tags($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        $sanitized = trim($sanitized);

        return $sanitized;
    }

    /**
     * Sanitize and validate email address
     * @deprecated Use BookingForm model instead
     */
    private function sanitizeEmail(string $email): string
    {
        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
        $sanitized = strtolower(trim($sanitized));

        return $sanitized;
    }

    /**
     * Validate date format (Y-m-d)
     * @deprecated Use BookingForm model instead
     */
    private function validateDateFormat(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Validate time format (H:i or H:i:s)
     * @deprecated Use BookingForm model instead
     */
    private function validateTimeFormat(string $time): bool
    {
        // Accept both H:i and H:i:s formats
        $patterns = [
            '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',           // H:i
            '/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/' // H:i:s
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $time)) {
                return true;
            }
        }

        return false;
    }
}
