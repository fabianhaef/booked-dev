<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use fabian\booked\Booked;
use fabian\booked\models\ServiceExtra;
use yii\web\Response;
use yii\web\NotFoundHttpException;

/**
 * Service Extra Controller
 *
 * Handles CRUD operations for service extras in the Control Panel
 */
class ServiceExtraController extends Controller
{
    /**
     * List all service extras
     */
    public function actionIndex(): Response
    {
        $extras = Booked::getInstance()->serviceExtra->getAllExtras();

        return $this->renderTemplate('booked/service-extras/index', [
            'extras' => $extras,
        ]);
    }

    /**
     * Create a new service extra
     */
    public function actionNew(): Response
    {
        $extra = new ServiceExtra();
        $services = \fabian\booked\elements\Service::find()->all();

        return $this->renderTemplate('booked/service-extras/edit', [
            'extra' => $extra,
            'services' => $services,
            'isNew' => true,
        ]);
    }

    /**
     * Edit an existing service extra
     */
    public function actionEdit(int $id): Response
    {
        $extra = Booked::getInstance()->serviceExtra->getExtraById($id);

        if (!$extra) {
            throw new NotFoundHttpException('Service extra not found');
        }

        $services = \fabian\booked\elements\Service::find()->all();

        // Get services this extra is assigned to
        $assignedServiceIds = \fabian\booked\records\ServiceExtraServiceRecord::find()
            ->select('serviceId')
            ->where(['extraId' => $id])
            ->column();

        return $this->renderTemplate('booked/service-extras/edit', [
            'extra' => $extra,
            'services' => $services,
            'assignedServiceIds' => $assignedServiceIds,
            'isNew' => false,
        ]);
    }

    /**
     * Save a service extra
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('id');

        if ($id) {
            $extra = Booked::getInstance()->serviceExtra->getExtraById($id);
            if (!$extra) {
                throw new NotFoundHttpException('Service extra not found');
            }
        } else {
            $extra = new ServiceExtra();
        }

        // Populate the model
        $extra->name = $request->getBodyParam('name');
        $extra->description = $request->getBodyParam('description');
        $extra->price = (float)$request->getBodyParam('price', 0);
        $extra->duration = (int)$request->getBodyParam('duration', 0);
        $extra->maxQuantity = (int)$request->getBodyParam('maxQuantity', 1);
        $extra->isRequired = (bool)$request->getBodyParam('isRequired', false);
        $extra->sortOrder = (int)$request->getBodyParam('sortOrder', 0);
        $extra->enabled = (bool)$request->getBodyParam('enabled', true);

        // Validate and save
        if (!$extra->validate()) {
            Craft::$app->getSession()->setError('Could not save service extra.');

            return $this->renderTemplate('booked/service-extras/edit', [
                'extra' => $extra,
                'services' => \fabian\booked\elements\Service::find()->all(),
                'isNew' => !$id,
            ]);
        }

        if (!Booked::getInstance()->serviceExtra->saveExtra($extra)) {
            Craft::$app->getSession()->setError('Could not save service extra.');

            return $this->renderTemplate('booked/service-extras/edit', [
                'extra' => $extra,
                'services' => \fabian\booked\elements\Service::find()->all(),
                'isNew' => !$id,
            ]);
        }

        // Save service assignments
        $assignedServices = $request->getBodyParam('services', []);
        if (is_array($assignedServices)) {
            Booked::getInstance()->serviceExtra->setServicesForExtra($extra->id, $assignedServices);
        } else {
            // Clear all service assignments if none selected
            Booked::getInstance()->serviceExtra->setServicesForExtra($extra->id, []);
        }

        Craft::$app->getSession()->setNotice('Service extra saved.');

        return $this->redirectToPostedUrl($extra);
    }

    /**
     * Delete a service extra
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (!Booked::getInstance()->serviceExtra->deleteExtra($id)) {
            Craft::$app->getSession()->setError('Could not delete service extra.');
        } else {
            Craft::$app->getSession()->setNotice('Service extra deleted.');
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Get extras for a service (AJAX endpoint for frontend)
     */
    public function actionGetForService(): Response
    {
        $this->requireAcceptsJson();

        $serviceId = Craft::$app->getRequest()->getQueryParam('serviceId');

        if (!$serviceId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Service ID is required',
            ]);
        }

        $extras = Booked::getInstance()->serviceExtra->getExtrasForService((int)$serviceId);

        // Convert to array for JSON
        $extrasArray = array_map(function($extra) {
            return [
                'id' => $extra->id,
                'name' => $extra->name,
                'description' => $extra->description,
                'price' => $extra->price,
                'duration' => $extra->duration,
                'maxQuantity' => $extra->maxQuantity,
                'isRequired' => $extra->isRequired,
                'enabled' => $extra->enabled,
            ];
        }, $extras);

        return $this->asJson([
            'success' => true,
            'extras' => $extrasArray,
        ]);
    }

    /**
     * Reorder service extras
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');

        foreach ($ids as $order => $id) {
            $extra = Booked::getInstance()->serviceExtra->getExtraById($id);
            if ($extra) {
                $extra->sortOrder = $order;
                Booked::getInstance()->serviceExtra->saveExtra($extra);
            }
        }

        return $this->asJson(['success' => true]);
    }
}
