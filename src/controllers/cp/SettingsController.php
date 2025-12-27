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
