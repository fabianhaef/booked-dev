<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\BookingSequence;
use yii\web\NotFoundHttpException;

/**
 * CP Sequences Controller - Handles Control Panel sequence management
 */
class SequencesController extends Controller
{
    /**
     * Sequences index page - using element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/sequences/_index', [
            'title' => 'Sequential Bookings',
        ]);
    }

    /**
     * View sequence details
     */
    public function actionView(int $id): Response
    {
        $sequence = BookingSequence::findOne($id);

        if (!$sequence) {
            throw new NotFoundHttpException('Booking sequence not found');
        }

        // Get all reservations in the sequence
        $items = $sequence->getItems();

        return $this->renderTemplate('booked/sequences/view', [
            'sequence' => $sequence,
            'items' => $items,
        ]);
    }

    /**
     * Cancel sequence (AJAX)
     */
    public function actionCancel(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $sequence = BookingSequence::findOne($id);

        if (!$sequence) {
            return $this->asJson([
                'success' => false,
                'message' => 'Booking sequence not found'
            ]);
        }

        if ($sequence->cancel()) {
            return $this->asJson([
                'success' => true,
                'message' => 'Booking sequence cancelled successfully'
            ]);
        } else {
            return $this->asJson([
                'success' => false,
                'message' => 'Failed to cancel booking sequence'
            ]);
        }
    }

    /**
     * Confirm sequence (AJAX)
     */
    public function actionConfirm(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $sequence = BookingSequence::findOne($id);

        if (!$sequence) {
            return $this->asJson([
                'success' => false,
                'message' => 'Booking sequence not found'
            ]);
        }

        if ($sequence->confirm()) {
            return $this->asJson([
                'success' => true,
                'message' => 'Booking sequence confirmed successfully'
            ]);
        } else {
            return $this->asJson([
                'success' => false,
                'message' => 'Failed to confirm booking sequence'
            ]);
        }
    }

    /**
     * Delete sequence
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $sequence = BookingSequence::findOne($id);

        if (!$sequence) {
            throw new NotFoundHttpException('Booking sequence not found');
        }

        if (Craft::$app->elements->deleteElement($sequence)) {
            Craft::$app->session->setNotice('Booking sequence deleted successfully.');
        } else {
            Craft::$app->session->setError('Failed to delete booking sequence.');
        }

        return $this->redirect('booked/sequences');
    }
}
