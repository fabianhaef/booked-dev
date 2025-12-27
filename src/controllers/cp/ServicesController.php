<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\Booked;
use fabian\booked\elements\Service;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * CP Services Controller - Handles Control Panel service management
 */
class ServicesController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('booked-manageServices');

        return true;
    }

    /**
     * Services index page - using element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/services/_index', [
            'title' => Craft::t('booked', 'Services'),
        ]);
    }

    /**
     * Edit service
     */
    public function actionEdit(int $id = null): Response
    {
        if ($id) {
            $service = Service::find()->id($id)->one();
            if (!$service) {
                throw new NotFoundHttpException('Service not found');
            }
        } else {
            $service = new Service();
            $service->siteId = Craft::$app->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;
        }

        return $this->renderTemplate('booked/services/edit', [
            'service' => $service,
        ]);
    }

    /**
     * Save service
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('elementId') ?? $request->getBodyParam('id');

        if ($id) {
            $service = Service::find()->id($id)->one();
            if (!$service) {
                throw new NotFoundHttpException('Service not found');
            }
        } else {
            $service = new Service();
        }

        // Set element attributes
        $service->title = $request->getBodyParam('title');
        // Slug is auto-generated from title in beforeSave(), but allow manual override
        $slug = $request->getBodyParam('slug');
        if ($slug !== null && $slug !== '') {
            $service->slug = $slug;
        }
        $service->enabled = (bool)$request->getBodyParam('enabled', true);

        // Set custom attributes - convert strings to proper types
        $duration = $request->getBodyParam('duration');
        $service->duration = $duration === '' || $duration === null ? null : (int)$duration;
        
        $bufferBefore = $request->getBodyParam('bufferBefore');
        $service->bufferBefore = $bufferBefore === '' || $bufferBefore === null ? null : (int)$bufferBefore;
        
        $bufferAfter = $request->getBodyParam('bufferAfter');
        $service->bufferAfter = $bufferAfter === '' || $bufferAfter === null ? null : (int)$bufferAfter;
        
        $price = $request->getBodyParam('price');
        $service->price = $price === '' || $price === null ? null : (float)$price;

        $service->virtualMeetingProvider = $request->getBodyParam('virtualMeetingProvider');

        // Booking configuration fields
        $minTimeBeforeBooking = $request->getBodyParam('minTimeBeforeBooking');
        $service->minTimeBeforeBooking = $minTimeBeforeBooking === '' || $minTimeBeforeBooking === null ? null : (int)$minTimeBeforeBooking;

        $minTimeBeforeCanceling = $request->getBodyParam('minTimeBeforeCanceling');
        $service->minTimeBeforeCanceling = $minTimeBeforeCanceling === '' || $minTimeBeforeCanceling === null ? null : (int)$minTimeBeforeCanceling;

        $finalStepUrl = $request->getBodyParam('finalStepUrl');
        $service->finalStepUrl = $finalStepUrl === '' ? null : $finalStepUrl;

        // Handle parent service for hierarchy
        $parentId = $request->getBodyParam('parentId');
        $service->parentId = $parentId === '' || $parentId === null ? null : (int)$parentId;

        // Set field values from field layout
        $service->setFieldValuesFromRequest('fields');

        if (!Craft::$app->elements->saveElement($service)) {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save service.'));
            Craft::$app->urlManager->setRouteParams([
                'service' => $service,
            ]);
            return null;
        }

        // Save service extras assignments
        $selectedExtras = $request->getBodyParam('extras', []);
        if (is_array($selectedExtras) && !empty($selectedExtras)) {
            // Convert array values to integers
            $selectedExtras = array_map('intval', $selectedExtras);
            Booked::getInstance()->serviceExtra->setExtrasForService($service->id, $selectedExtras);
        } else {
            // If no extras selected, clear all assignments for this service
            Booked::getInstance()->serviceExtra->setExtrasForService($service->id, []);
        }

        Craft::$app->session->setNotice(Craft::t('booked', 'Service saved.'));
        return $this->redirect('booked/services');
    }
}
