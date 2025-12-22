<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\Employee;
use yii\web\NotFoundHttpException;

/**
 * Calendar Controller - Handles OAuth flow for Google and Outlook
 */
class CalendarController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('booked-manageEmployees');

        return true;
    }

    /**
     * Initiate OAuth flow for a provider
     */
    public function actionConnect(int $employeeId, string $provider): Response
    {
        $employee = Employee::find()->id($employeeId)->one();
        if (!$employee) {
            throw new NotFoundHttpException('Employee not found');
        }

        $authUrl = Booked::getInstance()->getCalendarSync()->getAuthUrl($employee, $provider);
        return $this->redirect($authUrl);
    }

    /**
     * Handle OAuth callback
     */
    public function actionCallback(): Response
    {
        $request = Craft::$app->request;
        $state = json_decode(base64_decode($request->getParam('state')), true);
        
        if (!$state || !isset($state['employeeId'], $state['provider'])) {
            Craft::$app->session->setError('Invalid state in OAuth callback');
            return $this->redirect('booked/employees');
        }

        $employeeId = $state['employeeId'];
        $provider = $state['provider'];
        $code = $request->getParam('code');

        if (!$code) {
            Craft::$app->session->setError('No code provided in OAuth callback');
            return $this->redirect('booked/employees/' . $employeeId);
        }

        $employee = Employee::find()->id($employeeId)->one();
        if (!$employee) {
            throw new NotFoundHttpException('Employee not found');
        }

        $success = Booked::getInstance()->getCalendarSync()->handleCallback($employee, $provider, $code);

        if ($success) {
            Craft::$app->session->setNotice(ucfirst($provider) . ' Calendar connected successfully.');
        } else {
            Craft::$app->session->setError('Failed to connect ' . ucfirst($provider) . ' Calendar.');
        }

        return $this->redirect('booked/employees/' . $employeeId);
    }
}

