<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\models\Settings;

/**
 * CP Settings Controller - Handles Control Panel settings management
 */
class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('booked-manageSettings');

        return true;
    }

    /**
     * General settings page
     */
    public function actionGeneral(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booked/settings/general', [
            'selectedSubnavItem' => 'general',
            'settings' => $settings,
        ]);
    }

    /**
     * Calendar settings page
     */
    public function actionCalendar(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booked/settings/calendar', [
            'selectedSubnavItem' => 'calendar',
            'settings' => $settings,
        ]);
    }

    /**
     * Virtual Meetings settings page
     */
    public function actionMeetings(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booked/settings/meetings', [
            'selectedSubnavItem' => 'meetings',
            'settings' => $settings,
        ]);
    }

    /**
     * Notifications settings page
     */
    public function actionNotifications(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booked/settings/notifications', [
            'selectedSubnavItem' => 'notifications',
            'settings' => $settings,
        ]);
    }

    /**
     * Commerce settings page
     */
    public function actionCommerce(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booked/settings/commerce', [
            'selectedSubnavItem' => 'commerce',
            'settings' => $settings,
        ]);
    }

    /**
     * Frontend settings page
     */
    public function actionFrontend(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booked/settings/frontend', [
            'selectedSubnavItem' => 'frontend',
            'settings' => $settings,
        ]);
    }

    /**
     * Booking Fields settings page
     */
    public function actionBookingFields(): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(\fabian\booked\elements\Reservation::class);

        return $this->renderTemplate('booked/settings/booking-fields', [
            'selectedSubnavItem' => 'booking-fields',
            'fieldLayout' => $fieldLayout,
        ]);
    }

    /**
     * Save booking field layout
     */
    public function actionSaveBookingFields(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = \fabian\booked\elements\Reservation::class;

        if (!Craft::$app->getFields()->saveLayout($fieldLayout)) {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save booking fields.'));
            return null;
        }

        Craft::$app->session->setNotice(Craft::t('booked', 'Booking fields saved.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * Save settings
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->request;
        $settings = Settings::loadSettings();

        // Load all settings from POST data
        $postedSettings = $request->getBodyParam('settings', []);
        
        // Set all attributes from POST data
        $settings->setAttributes($postedSettings, false);

        // Validate and save
        if ($settings->validate() && $settings->save()) {
            Craft::$app->session->setNotice(Craft::t('booked', 'Settings saved.'));
        } else {
            Craft::$app->session->setError(Craft::t('booked', 'Couldn\'t save settings.'));
            if ($settings->hasErrors()) {
                Craft::$app->session->setError(Craft::t('booked', 'Validation errors: {errors}', [
                    'errors' => implode(', ', $settings->getFirstErrors())
                ]));
            }
        }

        return $this->redirectToPostedUrl();
    }
}
