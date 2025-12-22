<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Schedule;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * CP Schedules Controller - Handles Control Panel schedule management
 */
class SchedulesController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('booked-manageAvailability');

        return true;
    }

    /**
     * Schedules index page - using element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/schedules/_index', [
            'title' => Craft::t('booked', 'Schedules'),
        ]);
    }

    /**
     * Edit schedule
     */
    public function actionEdit(int $id = null): Response
    {
        if ($id) {
            $schedule = Schedule::find()->id($id)->one();
            if (!$schedule) {
                throw new NotFoundHttpException('Schedule not found');
            }
        } else {
            $schedule = new Schedule();
        }

        // Get available employees for dropdown
        $employees = Employee::find()->enabled()->all();

        // Days of week for dropdown
        $daysOfWeek = [
            0 => Craft::t('booked', 'Sunday'),
            1 => Craft::t('booked', 'Monday'),
            2 => Craft::t('booked', 'Tuesday'),
            3 => Craft::t('booked', 'Wednesday'),
            4 => Craft::t('booked', 'Thursday'),
            5 => Craft::t('booked', 'Friday'),
            6 => Craft::t('booked', 'Saturday'),
        ];

        return $this->renderTemplate('booked/schedules/edit', [
            'schedule' => $schedule,
            'employees' => $employees,
            'daysOfWeek' => $daysOfWeek,
        ]);
    }

    /**
     * Save schedule
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('elementId') ?? $request->getBodyParam('id');

        if ($id) {
            $schedule = Schedule::find()->id($id)->one();
            if (!$schedule) {
                throw new NotFoundHttpException('Schedule not found');
            }
        } else {
            $schedule = new Schedule();
        }

        // Set element attributes
        $schedule->enabled = (bool)$request->getBodyParam('enabled', true);

        // Set custom attributes - convert strings to proper types
        $employeeId = $request->getBodyParam('employeeId');
        $schedule->employeeId = $employeeId === '' || $employeeId === null ? null : (int)$employeeId;
        
        $dayOfWeek = $request->getBodyParam('dayOfWeek');
        $schedule->dayOfWeek = $dayOfWeek === '' || $dayOfWeek === null ? null : (int)$dayOfWeek;
        
        $startTime = $request->getBodyParam('startTime');
        $schedule->startTime = $startTime === '' ? null : $startTime;
        
        $endTime = $request->getBodyParam('endTime');
        $schedule->endTime = $endTime === '' ? null : $endTime;

        if (!Craft::$app->elements->saveElement($schedule)) {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save schedule.'));
            Craft::$app->urlManager->setRouteParams([
                'schedule' => $schedule,
            ]);
            return null;
        }

        Craft::$app->session->setNotice(Craft::t('booked', 'Schedule saved.'));
        return $this->redirectToPostedUrl($schedule);
    }
}
