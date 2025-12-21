<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\BookingVariation;
use fabian\booked\models\Settings;
use yii\web\NotFoundHttpException;

/**
 * Variations Controller - Handles control panel CRUD operations for booking variations
 */
class VariationsController extends Controller
{
    /**
     * List all variations
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booking/variations/_index', [
            'title' => 'Buchungsvarianten',
        ]);
    }

    /**
     * Create new variation form
     */
    public function actionNew(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booking/variations/edit', [
            'variation' => new BookingVariation(),
            'title' => 'Neue Variante',
            'settings' => $settings,
        ]);
    }

    /**
     * Edit existing variation form
     */
    public function actionEdit(int $id): Response
    {
        $variation = BookingVariation::find()->id($id)->one();

        if (!$variation) {
            throw new NotFoundHttpException('Variation not found');
        }

        $settings = Settings::loadSettings();

        return $this->renderTemplate('booking/variations/edit', [
            'variation' => $variation,
            'title' => 'Variante bearbeiten',
            'settings' => $settings,
        ]);
    }

    /**
     * Save variation
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('id');

        if ($id) {
            $variation = BookingVariation::find()->id($id)->one();
            if (!$variation) {
                throw new NotFoundHttpException('Variation not found');
            }
        } else {
            $variation = new BookingVariation();
        }

        $variation->title = $request->getRequiredBodyParam('title');
        $variation->description = $request->getBodyParam('description', '');
        $variation->isActive = (bool) $request->getBodyParam('isActive', true);

        // Handle optional numeric fields
        $slotDuration = $request->getBodyParam('slotDurationMinutes');
        $bufferMinutes = $request->getBodyParam('bufferMinutes');

        $variation->slotDurationMinutes = $slotDuration !== '' && $slotDuration !== null ? (int)$slotDuration : null;
        $variation->bufferMinutes = $bufferMinutes !== '' && $bufferMinutes !== null ? (int)$bufferMinutes : null;

        // Handle required capacity fields
        $variation->maxCapacity = (int) $request->getRequiredBodyParam('maxCapacity');
        $variation->allowQuantitySelection = (bool) $request->getBodyParam('allowQuantitySelection', false);

        if (!Craft::$app->elements->saveElement($variation)) {
            Craft::$app->session->setError('Could not save variation.');
            $settings = Settings::loadSettings();
            return $this->renderTemplate('booking/variations/edit', [
                'variation' => $variation,
                'title' => $id ? 'Variante bearbeiten' : 'Neue Variante',
                'settings' => $settings,
            ]);
        }

        Craft::$app->session->setNotice('Variation saved successfully.');
        return $this->redirectToPostedUrl($variation);
    }

    /**
     * Delete variation
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $variation = BookingVariation::find()->id($id)->one();

        if (!$variation) {
            throw new NotFoundHttpException('Variation not found');
        }

        if (Craft::$app->elements->deleteElement($variation)) {
            Craft::$app->session->setNotice('Variation deleted successfully.');
        } else {
            Craft::$app->session->setError('Could not delete variation.');
        }

        return $this->redirectToPostedUrl();
    }
}
