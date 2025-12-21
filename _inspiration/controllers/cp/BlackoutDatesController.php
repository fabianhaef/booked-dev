<?php

namespace modules\booking\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use modules\booking\BookingModule;
use modules\booking\elements\BlackoutDate;
use modules\booking\services\BlackoutDateService;
use yii\web\NotFoundHttpException;

/**
 * Blackout Dates Controller - Handles control panel CRUD operations for blackout dates
 */
class BlackoutDatesController extends Controller
{
    private BlackoutDateService $blackoutDateService;

    public function init(): void
    {
        parent::init();
        $this->blackoutDateService = BookingModule::getInstance()->blackoutDate;
    }

    /**
     * List all blackout dates
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booking/blackout-dates/_index', [
            'title' => 'Ausfalltage',
        ]);
    }

    /**
     * Create new blackout date form
     */
    public function actionNew(): Response
    {
        return $this->renderTemplate('booking/blackout-dates/edit', [
            'blackoutDate' => null,
            'title' => 'New Blackout Date'
        ]);
    }

    /**
     * Edit existing blackout date form
     */
    public function actionEdit(int $id): Response
    {
        $blackoutDate = BlackoutDate::find()->id($id)->one();

        if (!$blackoutDate) {
            throw new NotFoundHttpException('Blackout date not found');
        }

        return $this->renderTemplate('booking/blackout-dates/edit', [
            'blackoutDate' => $blackoutDate,
            'title' => 'Edit Blackout Date'
        ]);
    }

    /**
     * Save blackout date
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('id');

        if ($id) {
            $blackoutDate = BlackoutDate::find()->id($id)->one();
            if (!$blackoutDate) {
                throw new NotFoundHttpException('Blackout date not found');
            }
        } else {
            $blackoutDate = new BlackoutDate();
        }

        $blackoutDate->title = $request->getRequiredBodyParam('title');
        $blackoutDate->startDate = $request->getRequiredBodyParam('startDate');
        $blackoutDate->endDate = $request->getRequiredBodyParam('endDate');
        $blackoutDate->isActive = (bool) $request->getBodyParam('isActive', true);

        if (!Craft::$app->elements->saveElement($blackoutDate)) {
            Craft::$app->session->setError('Could not save blackout date.');
            return $this->renderTemplate('booking/blackout-dates/edit', [
                'blackoutDate' => $blackoutDate,
                'title' => $id ? 'Edit Blackout Date' : 'New Blackout Date'
            ]);
        }

        Craft::$app->session->setNotice('Blackout date saved successfully.');
        return $this->redirectToPostedUrl($blackoutDate);
    }

    /**
     * Delete blackout date
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->request->getRequiredBodyParam('id');
        $blackoutDate = BlackoutDate::find()->id($id)->one();

        if (!$blackoutDate) {
            throw new NotFoundHttpException('Blackout date not found');
        }

        if (Craft::$app->elements->deleteElement($blackoutDate)) {
            Craft::$app->session->setNotice('Blackout date deleted successfully.');
        } else {
            Craft::$app->session->setError('Could not delete blackout date.');
        }

        return $this->redirectToPostedUrl();
    }
}
