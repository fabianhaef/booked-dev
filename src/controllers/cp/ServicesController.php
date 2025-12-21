<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Service;
use yii\web\NotFoundHttpException;

/**
 * CP Services Controller - Handles Control Panel service management
 */
class ServicesController extends Controller
{
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

        // Set field layout content
        $fieldsLocation = $request->getBodyParam('fieldsLocation', 'fields');
        $service->setFieldValuesFromRequest($fieldsLocation);

        if (!Craft::$app->elements->saveElement($service)) {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save service.'));
            Craft::$app->urlManager->setRouteParams([
                'service' => $service,
            ]);
            return null;
        }

        Craft::$app->session->setNotice(Craft::t('booked', 'Service saved.'));
        return $this->redirectToPostedUrl($service);
    }
}

