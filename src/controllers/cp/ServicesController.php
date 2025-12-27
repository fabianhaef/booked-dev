<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Service;
use fabian\booked\elements\ServiceExtra;
use fabian\booked\Booked;
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
     * Edit a service
     */
    public function actionEdit(?int $id = null, ?Service $service = null): Response
    {
        if ($service === null) {
            if ($id !== null) {
                $service = Service::find()
                    ->id($id)
                    ->siteId('*')
                    ->status(null)
                    ->one();

                if (!$service) {
                    throw new NotFoundHttpException('Service not found');
                }
            } else {
                $service = new Service();
                $service->siteId = Craft::$app->getSites()->getCurrentSite()->id;
            }
        }

        $isNew = !$service->id;

        // Get available service extras
        $serviceExtras = ServiceExtra::find()
            ->status('enabled')
            ->orderBy('title')
            ->all();

        // Get currently assigned extras for this service
        $assignedExtras = [];
        if (!$isNew) {
            $assignedExtras = Booked::getInstance()->serviceExtra->getExtrasForService($service->id);
            $assignedExtras = array_map(fn($extra) => $extra->id, $assignedExtras);
        }

        return $this->renderTemplate('booked/services/_edit', [
            'service' => $service,
            'isNew' => $isNew,
            'title' => $isNew ? Craft::t('booked', 'New Service') : $service->title,
            'serviceExtras' => $serviceExtras,
            'assignedExtras' => $assignedExtras,
        ]);
    }

    /**
     * Save a service
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id');

        if ($id) {
            $service = Service::find()
                ->id($id)
                ->siteId('*')
                ->status(null)
                ->one();

            if (!$service) {
                throw new NotFoundHttpException('Service not found');
            }
        } else {
            $service = new Service();
            $service->siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        // Set service attributes
        $service->title = $request->getBodyParam('title');
        $service->slug = $request->getBodyParam('slug');
        $service->enabled = (bool)$request->getBodyParam('enabled', true);
        $service->duration = $request->getBodyParam('duration') ?: null;
        $service->bufferBefore = $request->getBodyParam('bufferBefore') ?: null;
        $service->bufferAfter = $request->getBodyParam('bufferAfter') ?: null;
        $service->price = $request->getBodyParam('price') ?: null;
        $service->virtualMeetingProvider = $request->getBodyParam('virtualMeetingProvider') ?: null;
        $service->minTimeBeforeBooking = $request->getBodyParam('minTimeBeforeBooking') ?: null;
        $service->minTimeBeforeCanceling = $request->getBodyParam('minTimeBeforeCanceling') ?: null;
        $service->finalStepUrl = $request->getBodyParam('finalStepUrl') ?: null;

        // Save the service
        if (!Craft::$app->getElements()->saveElement($service)) {
            Craft::$app->getSession()->setError(Craft::t('booked', 'Couldn\'t save service.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'service' => $service,
            ]);

            return null;
        }

        // Save service extras assignments
        $selectedExtras = $request->getBodyParam('extras', []);
        if (is_array($selectedExtras)) {
            $selectedExtras = array_map('intval', array_filter($selectedExtras));
            Booked::getInstance()->serviceExtra->setExtrasForService($service->id, $selectedExtras);
        }

        Craft::$app->getSession()->setNotice(Craft::t('booked', 'Service saved.'));

        return $this->redirectToPostedUrl($service);
    }

    /**
     * Delete a service
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        $service = Service::find()
            ->id($id)
            ->siteId('*')
            ->status(null)
            ->one();

        if (!$service) {
            throw new NotFoundHttpException('Service not found');
        }

        if (!Craft::$app->getElements()->deleteElement($service)) {
            return $this->asJson(['success' => false]);
        }

        return $this->asJson(['success' => true]);
    }
}
