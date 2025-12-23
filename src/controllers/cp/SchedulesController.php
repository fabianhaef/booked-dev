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

        // Days of week for checkboxes (1 = Monday, 7 = Sunday)
        $daysOfWeek = [
            1 => Craft::t('booked', 'Monday'),
            2 => Craft::t('booked', 'Tuesday'),
            3 => Craft::t('booked', 'Wednesday'),
            4 => Craft::t('booked', 'Thursday'),
            5 => Craft::t('booked', 'Friday'),
            6 => Craft::t('booked', 'Saturday'),
            7 => Craft::t('booked', 'Sunday'),
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

        // Set title
        $title = $request->getBodyParam('title');
        $schedule->title = $title === '' ? null : $title;

        // Set custom attributes - convert strings to proper types
        $employeeIds = $request->getBodyParam('employeeIds');
        if (is_array($employeeIds)) {
            $schedule->employeeIds = array_map('intval', array_filter($employeeIds));
        } else {
            $schedule->employeeIds = [];
        }

        // For backward compatibility, also support single employeeId
        $employeeId = $request->getBodyParam('employeeId');
        $schedule->employeeId = $employeeId === '' || $employeeId === null ? null : (int)$employeeId;

        // Handle multiple days of week
        // Debug: Log ALL body params to see the structure
        \Craft::info('ALL body params: ' . print_r($request->getBodyParams(), true), 'booked');

        $daysOfWeek = $request->getBodyParam('daysOfWeek');

        // Debug: Log what we received
        \Craft::info('daysOfWeek received (type: ' . gettype($daysOfWeek) . '): ' . print_r($daysOfWeek, true), 'booked');

        if (is_array($daysOfWeek)) {
            // Remove empty string values that checkboxes send
            $filtered = array_filter($daysOfWeek, function($val) {
                return $val !== '' && $val !== null && $val !== false;
            });
            // Convert to integers and re-index array
            $schedule->daysOfWeek = array_values(array_map('intval', $filtered));

            \Craft::info('daysOfWeek after processing: ' . print_r($schedule->daysOfWeek, true), 'booked');
        } else {
            \Craft::info('daysOfWeek is NOT an array!', 'booked');
            $schedule->daysOfWeek = [];
        }

        // For backward compatibility, also support single dayOfWeek
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
        return $this->redirect('booked/schedules');
    }
}
