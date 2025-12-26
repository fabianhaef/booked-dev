<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use fabian\booked\Booked;
use fabian\booked\elements\ServiceExtra;
use yii\web\Response;
use yii\web\NotFoundHttpException;

/**
 * Service Add-On Controller
 *
 * Handles CRUD operations for service add-ons in the Control Panel
 */
class ServiceExtraController extends Controller
{
    /**
     * List all add-ons - now uses element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/service-extras/_index', [
            'title' => Craft::t('booked', 'Add-Ons'),
        ]);
    }

    /**
     * Create a new add-on
     */
    public function actionNew(): Response
    {
        $extra = new ServiceExtra();
        $extra->siteId = Craft::$app->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;

        return $this->renderTemplate('booked/service-extras/edit', [
            'extra' => $extra,
            'isNew' => true,
        ]);
    }

    /**
     * Edit an existing add-on
     */
    public function actionEdit(int $id): Response
    {
        $extra = ServiceExtra::find()->id($id)->one();

        if (!$extra) {
            throw new NotFoundHttpException('Add-on not found');
        }

        return $this->renderTemplate('booked/service-extras/edit', [
            'extra' => $extra,
            'isNew' => false,
        ]);
    }

    /**
     * Save an add-on
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('elementId') ?? $request->getBodyParam('id');

        if ($id) {
            $extra = ServiceExtra::find()->id($id)->one();
            if (!$extra) {
                throw new NotFoundHttpException('Add-on not found');
            }
        } else {
            $extra = new ServiceExtra();
        }

        // Set element attributes
        $extra->title = $request->getBodyParam('title');
        $extra->enabled = (bool)$request->getBodyParam('enabled', true);

        // Set custom attributes
        $extra->description = $request->getBodyParam('description');
        $extra->price = (float)$request->getBodyParam('price', 0);
        $extra->duration = (int)$request->getBodyParam('duration', 0);
        $extra->maxQuantity = (int)$request->getBodyParam('maxQuantity', 1);

        if (!Craft::$app->elements->saveElement($extra)) {
            Craft::$app->getSession()->setError(Craft::t('booked', 'Couldn\'t save add-on.'));
            Craft::$app->urlManager->setRouteParams([
                'extra' => $extra,
            ]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('booked', 'Add-on saved.'));
        return $this->redirect('booked/service-extras');
    }

    /**
     * Delete an add-on
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $extra = ServiceExtra::find()->id($id)->one();

        if (!$extra) {
            throw new NotFoundHttpException('Add-on not found');
        }

        if (Craft::$app->elements->deleteElement($extra)) {
            Craft::$app->getSession()->setNotice('Add-on deleted.');
        } else {
            Craft::$app->getSession()->setError('Could not delete add-on.');
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Get add-ons for a service (AJAX endpoint for frontend)
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
                'title' => $extra->title,
                'name' => $extra->title,  // Backward compatibility
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
     * Reorder add-ons
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
