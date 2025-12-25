<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\BlackoutDate;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use fabian\booked\services\BlackoutDateService;
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
        $this->blackoutDateService = Booked::getInstance()->blackoutDate;
    }

    /**
     * List all blackout dates
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/blackout-dates/_index', [
            'title' => 'Ausfalltage',
        ]);
    }

    /**
     * Create new blackout date form
     */
    public function actionNew(): Response
    {
        return $this->renderTemplate('booked/blackout-dates/edit', [
            'blackoutDate' => null,
            'title' => 'New Blackout Date',
            'locations' => Location::find()->all(),
            'employees' => Employee::find()->all(),
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

        return $this->renderTemplate('booked/blackout-dates/edit', [
            'blackoutDate' => $blackoutDate,
            'title' => 'Edit Blackout Date',
            'locations' => Location::find()->all(),
            'employees' => Employee::find()->all(),
        ]);
    }

    /**
     * Save blackout date
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('id') ?: $request->getBodyParam('elementId');

        if ($id) {
            $blackoutDate = BlackoutDate::find()->id($id)->one();
            if (!$blackoutDate) {
                throw new NotFoundHttpException('Blackout date not found');
            }
        } else {
            $blackoutDate = new BlackoutDate();
        }

        $blackoutDate->title = $request->getRequiredBodyParam('title');

        // Handle date fields - use DateTimeHelper to convert from any format to Y-m-d
        $startDateValue = $request->getRequiredBodyParam('startDate');
        $startDateTime = DateTimeHelper::toDateTime($startDateValue);
        $blackoutDate->startDate = $startDateTime ? $startDateTime->format('Y-m-d') : '';

        $endDateValue = $request->getRequiredBodyParam('endDate');
        $endDateTime = DateTimeHelper::toDateTime($endDateValue);
        $blackoutDate->endDate = $endDateTime ? $endDateTime->format('Y-m-d') : '';

        $blackoutDate->locationId = $request->getBodyParam('locationId') ?: null;
        $blackoutDate->employeeId = $request->getBodyParam('employeeId') ?: null;
        $blackoutDate->isActive = (bool) $request->getBodyParam('isActive', true);

        if (!Craft::$app->elements->saveElement($blackoutDate)) {
            Craft::$app->session->setError('Could not save blackout date.');
            return $this->renderTemplate('booked/blackout-dates/edit', [
                'blackoutDate' => $blackoutDate,
                'title' => $id ? 'Edit Blackout Date' : 'New Blackout Date',
                'locations' => Location::find()->all(),
                'employees' => Employee::find()->all(),
            ]);
        }

        Craft::$app->session->setNotice('Blackout date saved successfully.');
        return $this->redirect('booked/blackout-dates');
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
