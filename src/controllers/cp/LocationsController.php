<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\elements\Location;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * CP Locations Controller - Handles Control Panel location management
 */
class LocationsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('booked-manageLocations');

        return true;
    }

    /**
     * Locations index page - using element index
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('booked/locations/_index', [
            'title' => Craft::t('booked', 'Locations'),
        ]);
    }

    /**
     * Edit location
     */
    public function actionEdit(int $id = null): Response
    {
        if ($id) {
            $location = Location::find()->id($id)->one();
            if (!$location) {
                throw new NotFoundHttpException('Location not found');
            }
        } else {
            $location = new Location();
            $location->siteId = Craft::$app->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;
        }

        return $this->renderTemplate('booked/locations/edit', [
            'location' => $location,
        ]);
    }

    /**
     * Save location
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $id = $request->getBodyParam('elementId') ?? $request->getBodyParam('id');

        if ($id) {
            $location = Location::find()->id($id)->one();
            if (!$location) {
                throw new NotFoundHttpException('Location not found');
            }
        } else {
            $location = new Location();
        }

        // Set element attributes
        $location->title = $request->getBodyParam('title');
        // Slug is auto-generated from title in beforeSave(), but allow manual override
        $slug = $request->getBodyParam('slug');
        if ($slug !== null && $slug !== '') {
            $location->slug = $slug;
        }
        $location->enabled = (bool)$request->getBodyParam('enabled', true);

        // Set custom attributes
        $location->address = $request->getBodyParam('address');
        $location->timezone = $request->getBodyParam('timezone');
        $location->contactInfo = $request->getBodyParam('contactInfo');

        if (!Craft::$app->elements->saveElement($location)) {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save location.'));
            Craft::$app->urlManager->setRouteParams([
                'location' => $location,
            ]);
            return null;
        }

        Craft::$app->session->setNotice(Craft::t('booked', 'Location saved.'));
        return $this->redirectToPostedUrl($location);
    }
}
