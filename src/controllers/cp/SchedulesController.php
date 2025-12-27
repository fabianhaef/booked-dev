<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use fabian\booked\elements\Schedule;
use fabian\booked\elements\Service;
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
     *
     * Simplified model: Schedule has direct FK to Service, Employee, Location
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

        // Get available services for dropdown
        $services = Service::find()->enabled()->all();

        // Get available employees for dropdown
        $employees = Employee::find()->enabled()->all();

        // Get available locations for dropdown
        $locations = Location::find()->enabled()->all();

        // Days of week for checkboxes (1 = Monday, 7 = Sunday)
        $daysOfWeek = [
            ['value' => 1, 'label' => Craft::t('booked', 'Monday')],
            ['value' => 2, 'label' => Craft::t('booked', 'Tuesday')],
            ['value' => 3, 'label' => Craft::t('booked', 'Wednesday')],
            ['value' => 4, 'label' => Craft::t('booked', 'Thursday')],
            ['value' => 5, 'label' => Craft::t('booked', 'Friday')],
            ['value' => 6, 'label' => Craft::t('booked', 'Saturday')],
            ['value' => 7, 'label' => Craft::t('booked', 'Sunday')],
        ];

        return $this->renderTemplate('booked/schedules/edit', [
            'schedule' => $schedule,
            'services' => $services,
            'employees' => $employees,
            'locations' => $locations,
            'daysOfWeek' => $daysOfWeek,
        ]);
    }

    /**
     * Save schedule
     *
     * Simplified model: Schedule has direct FK to Service, Employee, Location
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

        // Set direct FK relationships (simplified model)
        // Element select fields return arrays, so we need to extract the first element
        $serviceId = $request->getBodyParam('serviceId');
        if (is_array($serviceId)) {
            $serviceId = $serviceId[0] ?? null;
        }
        $schedule->serviceId = $serviceId === '' || $serviceId === null ? null : (int)$serviceId;

        $employeeId = $request->getBodyParam('employeeId');
        if (is_array($employeeId)) {
            $employeeId = $employeeId[0] ?? null;
        }
        $schedule->employeeId = $employeeId === '' || $employeeId === null ? null : (int)$employeeId;

        $locationId = $request->getBodyParam('locationId');
        if (is_array($locationId)) {
            $locationId = $locationId[0] ?? null;
        }
        $schedule->locationId = $locationId === '' || $locationId === null ? null : (int)$locationId;

        // Handle multiple days of week
        $daysOfWeek = $request->getBodyParam('daysOfWeek');

        if (is_array($daysOfWeek)) {
            // Remove empty string values that checkboxes send
            $filtered = array_filter($daysOfWeek, function($val) {
                return $val !== '' && $val !== null && $val !== false;
            });
            // Convert to integers and re-index array
            $schedule->daysOfWeek = array_values(array_map('intval', $filtered));
        } else {
            $schedule->daysOfWeek = [];
        }

        // For backward compatibility, also support single dayOfWeek
        $dayOfWeek = $request->getBodyParam('dayOfWeek');
        $schedule->dayOfWeek = $dayOfWeek === '' || $dayOfWeek === null ? null : (int)$dayOfWeek;

        $startTime = $request->getBodyParam('startTime');
        $schedule->startTime = $startTime === '' ? null : $startTime;

        $endTime = $request->getBodyParam('endTime');
        $schedule->endTime = $endTime === '' ? null : $endTime;

        // Capacity fields
        $capacity = $request->getBodyParam('capacity');
        $schedule->capacity = $capacity === '' || $capacity === null ? 1 : (int)$capacity;

        $simultaneousSlots = $request->getBodyParam('simultaneousSlots');
        $schedule->simultaneousSlots = $simultaneousSlots === '' || $simultaneousSlots === null ? 1 : (int)$simultaneousSlots;

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
