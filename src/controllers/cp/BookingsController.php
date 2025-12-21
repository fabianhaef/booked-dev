<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\BookingVariation;
use fabian\booked\elements\Reservation;
use fabian\booked\exceptions\BookingConflictException;
use fabian\booked\exceptions\BookingException;
use fabian\booked\exceptions\BookingNotFoundException;
use fabian\booked\exceptions\BookingValidationException;
use fabian\booked\services\BookingService;
use yii\web\NotFoundHttpException;

/**
 * CP Bookings Controller - Handles Control Panel booking management
 */
class BookingsController extends Controller
{
    private BookingService $bookingService;

    public function init(): void
    {
        parent::init();
        $this->bookingService = Booked::getInstance()->booking;
    }

    /**
     * Bookings index page - using element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booking/bookings/_index', [
            'title' => 'Buchungen',
        ]);
    }

    /**
     * View booking details
     */
    public function actionView(int $id): Response
    {
        $reservation = $this->bookingService->getReservationById($id);
        
        if (!$reservation) {
            throw new NotFoundHttpException('Booking not found');
        }

        return $this->renderTemplate('booking/bookings/view', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Edit booking
     */
    public function actionEdit(int $id = null): Response
    {
        if ($id) {
            $reservation = Reservation::find()->id($id)->one();
            if (!$reservation) {
                throw new NotFoundHttpException('Booking not found');
            }
        } else {
            $reservation = new Reservation();
        }

        // Load variation if exists
        $variation = null;
        if ($reservation->variationId) {
            $variation = BookingVariation::find()->id($reservation->variationId)->one();
        }

        return $this->renderTemplate('booking/bookings/edit', [
            'reservation' => $reservation,
            'variation' => $variation,
            'statuses' => Reservation::getStatuses(),
        ]);
    }

    /**
     * Save booking
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('id');

        if ($id) {
            $reservation = Reservation::find()->id($id)->one();
            if (!$reservation) {
                throw new NotFoundHttpException('Booking not found');
            }
        } else {
            $reservation = new Reservation();
        }

        // Populate element properties
        $reservation->userName = $request->getRequiredBodyParam('userName');
        $reservation->userEmail = $request->getRequiredBodyParam('userEmail');
        $reservation->userPhone = $request->getBodyParam('userPhone');
        $reservation->bookingDate = $request->getRequiredBodyParam('bookingDate');
        $reservation->startTime = $request->getRequiredBodyParam('startTime');
        $reservation->endTime = $request->getRequiredBodyParam('endTime');
        $reservation->status = $request->getRequiredBodyParam('status');
        $reservation->notes = $request->getBodyParam('notes');
        $reservation->quantity = (int)($request->getBodyParam('quantity') ?? 1);

        if (!Craft::$app->elements->saveElement($reservation)) {
            Craft::$app->session->setError('Unable to save booking.');
            
            // Load variation if exists
            $variation = null;
            if ($reservation->variationId) {
                $variation = BookingVariation::find()->id($reservation->variationId)->one();
            }
            
            return $this->renderTemplate('booking/bookings/edit', [
                'reservation' => $reservation,
                'variation' => $variation,
                'statuses' => Reservation::getStatuses(),
            ]);
        }

        Craft::$app->session->setNotice('Booking saved successfully.');
        return $this->redirect('booking/bookings/' . $reservation->id);
    }

    /**
     * Delete booking
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $reservation = Reservation::find()->id($id)->one();

        if (!$reservation) {
            throw new NotFoundHttpException('Booking not found');
        }

        if (Craft::$app->elements->deleteElement($reservation)) {
            Craft::$app->session->setNotice('Booking deleted successfully.');
        } else {
            Craft::$app->session->setError('Unable to delete booking.');
        }

        return $this->redirect('booking/bookings');
    }

    /**
     * Update booking status
     */
    public function actionUpdateStatus(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $status = Craft::$app->request->getRequiredBodyParam('status');

        try {
            $reservation = $this->bookingService->updateReservation($id, ['status' => $status]);

            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'Status updated successfully'
                ]);
            } else {
                Craft::$app->session->setNotice('Status updated successfully.');
            }

        } catch (BookingNotFoundException $e) {
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Booking not found'
                ]);
            } else {
                Craft::$app->session->setError('Booking not found.');
            }

        } catch (BookingValidationException | BookingException $e) {
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Unable to update status: ' . $e->getMessage()
                ]);
            } else {
                Craft::$app->session->setError('Unable to update status.');
            }
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Resend confirmation email
     */
    public function actionResendConfirmation(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $reservation = $this->bookingService->getReservationById($id);

        if (!$reservation) {
            throw new NotFoundHttpException('Booking not found');
        }

        $success = $this->bookingService->sendBookingConfirmation($reservation);

        if ($success) {
            Craft::$app->session->setNotice('Confirmation email sent successfully.');
        } else {
            Craft::$app->session->setError('Unable to send confirmation email.');
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Export bookings to CSV
     */
    public function actionExport(): Response
    {
        $request = Craft::$app->request;
        
        $criteria = [
            'status' => $request->getParam('status'),
            'dateFrom' => $request->getParam('dateFrom'),
            'dateTo' => $request->getParam('dateTo'),
            'userEmail' => $request->getParam('userEmail'),
        ];

        // Build query directly to support streaming
        $query = Reservation::find();
        
        if (!empty($criteria['status'])) {
            $query->status($criteria['status']);
        }
        if (!empty($criteria['dateFrom'])) {
            $query->andWhere(['>=', 'bookings_reservations.bookingDate', $criteria['dateFrom']]);
        }
        if (!empty($criteria['dateTo'])) {
            $query->andWhere(['<=', 'bookings_reservations.bookingDate', $criteria['dateTo']]);
        }
        if (!empty($criteria['userEmail'])) {
            $query->userEmail($criteria['userEmail']);
        }
        
        $query->orderBy(['bookingDate' => SORT_DESC, 'startTime' => SORT_DESC]);

        $filename = 'bookings-' . date('Y-m-d') . '.csv';

        // Callback for streaming
        $callback = function() use ($query) {
            $handle = fopen('php://output', 'w');
            // Add BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            
            // Add headers
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Date', 'Start Time', 'End Time', 'Status', 'Notes', 'Created']);

            foreach ($query->each() as $reservation) {
                fputcsv($handle, [
                    $reservation->id,
                    $reservation->userName,
                    $reservation->userEmail,
                    $reservation->userPhone ?: '',
                    $reservation->bookingDate,
                    $reservation->startTime,
                    $reservation->endTime,
                    $reservation->getStatusLabel(),
                    $reservation->notes ?: '',
                    $reservation->dateCreated ? $reservation->dateCreated->format('Y-m-d H:i:s') : ''
                ]);
            }
            fclose($handle);
        };

        return $this->response->sendStreamAsFile($filename, $callback, [
            'mimeType' => 'text/csv',
        ]);
    }
}
