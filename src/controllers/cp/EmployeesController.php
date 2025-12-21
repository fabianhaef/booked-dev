<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Employee;
use fabian\booked\elements\Location;
use yii\web\NotFoundHttpException;

/**
 * CP Employees Controller - Handles Control Panel employee management
 */
class EmployeesController extends Controller
{
    /**
     * Employees index page - using element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/employees/_index', [
            'title' => Craft::t('booked', 'Employees'),
        ]);
    }

    /**
     * Edit employee
     */
    public function actionEdit(int $id = null): Response
    {
        if ($id) {
            $employee = Employee::find()->id($id)->one();
            if (!$employee) {
                throw new NotFoundHttpException('Employee not found');
            }
        } else {
            $employee = new Employee();
        }

        // Get available users and locations for dropdowns
        $users = User::find()->all();
        $locations = Location::find()->enabled()->all();

        return $this->renderTemplate('booked/employees/edit', [
            'employee' => $employee,
            'users' => $users,
            'locations' => $locations,
        ]);
    }

    /**
     * Save employee
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('elementId') ?? $request->getBodyParam('id');

        if ($id) {
            $employee = Employee::find()->id($id)->one();
            if (!$employee) {
                throw new NotFoundHttpException('Employee not found');
            }
        } else {
            $employee = new Employee();
        }

        // Set element attributes
        $employee->title = $request->getBodyParam('title');
        // Slug is auto-generated from title in beforeSave(), but allow manual override
        $slug = $request->getBodyParam('slug');
        if ($slug !== null && $slug !== '') {
            $employee->slug = $slug;
        }
        $employee->enabled = (bool)$request->getBodyParam('enabled', true);

        // Set custom attributes - convert strings to proper types
        $userId = $request->getBodyParam('userId');
        $employee->userId = $userId === '' || $userId === null ? null : (int)$userId;
        
        $locationId = $request->getBodyParam('locationId');
        $employee->locationId = $locationId === '' || $locationId === null ? null : (int)$locationId;

        // Set field layout content
        $fieldsLocation = $request->getBodyParam('fieldsLocation', 'fields');
        $employee->setFieldValuesFromRequest($fieldsLocation);

        if (!Craft::$app->elements->saveElement($employee)) {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save employee.'));
            Craft::$app->urlManager->setRouteParams([
                'employee' => $employee,
            ]);
            return null;
        }

        Craft::$app->session->setNotice(Craft::t('booked', 'Employee saved.'));
        return $this->redirectToPostedUrl($employee);
    }
}

