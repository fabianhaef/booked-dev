<?php

namespace fabian\booked\controllers\cp;

use Craft;
use craft\models\FieldLayout;
use craft\web\Controller;
use craft\web\Response;
use fabian\booked\models\Settings;

/**
 * CP Settings Controller - Handles Control Panel settings management
 */
class SettingsController extends Controller
{
    /**
     * Field Layouts settings page
     */
    public function actionFieldLayouts(): Response
    {
        $settings = Settings::loadSettings();
        $fieldsService = Craft::$app->getFields();

        // Get or create field layouts for each element type
        $employeeFieldLayout = $settings->getEmployeeFieldLayout();
        if (!$employeeFieldLayout) {
            $employeeFieldLayout = new FieldLayout(['type' => \fabian\booked\elements\Employee::class]);
        }

        $serviceFieldLayout = $settings->getServiceFieldLayout();
        if (!$serviceFieldLayout) {
            $serviceFieldLayout = new FieldLayout(['type' => \fabian\booked\elements\Service::class]);
        }

        $locationFieldLayout = $settings->getLocationFieldLayout();
        if (!$locationFieldLayout) {
            $locationFieldLayout = new FieldLayout(['type' => \fabian\booked\elements\Location::class]);
        }

        return $this->renderTemplate('booked/settings/field-layouts', [
            'selectedSubnavItem' => 'field-layouts',
            'settings' => $settings,
            'employeeFieldLayout' => $employeeFieldLayout,
            'serviceFieldLayout' => $serviceFieldLayout,
            'locationFieldLayout' => $locationFieldLayout,
        ]);
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

        // Get the section from redirect URL or referrer
        $redirectUrl = $request->getBodyParam('redirect');
        $section = 'field-layouts'; // default
        if ($redirectUrl) {
            if (strpos($redirectUrl, '/general') !== false) {
                $section = 'general';
            } elseif (strpos($redirectUrl, '/calendar') !== false) {
                $section = 'calendar';
            } elseif (strpos($redirectUrl, '/meetings') !== false) {
                $section = 'meetings';
            } elseif (strpos($redirectUrl, '/notifications') !== false) {
                $section = 'notifications';
            } elseif (strpos($redirectUrl, '/commerce') !== false) {
                $section = 'commerce';
            } elseif (strpos($redirectUrl, '/frontend') !== false) {
                $section = 'frontend';
            }
        }

        // Handle field layout saving (only for field-layouts section)
        if ($section === 'field-layouts') {
            $fieldsService = Craft::$app->getFields();
            
            // Save Employee field layout
            $employeeFieldLayout = $this->saveFieldLayout(
                $request->getBodyParam('employeeFieldLayout'),
                $settings->employeeFieldLayoutId,
                'Employee'
            );
            $settings->employeeFieldLayoutId = $employeeFieldLayout?->id;

            // Save Service field layout
            $serviceFieldLayout = $this->saveFieldLayout(
                $request->getBodyParam('serviceFieldLayout'),
                $settings->serviceFieldLayoutId,
                'Service'
            );
            $settings->serviceFieldLayoutId = $serviceFieldLayout?->id;

            // Save Location field layout
            $locationFieldLayout = $this->saveFieldLayout(
                $request->getBodyParam('locationFieldLayout'),
                $settings->locationFieldLayoutId,
                'Location'
            );
            $settings->locationFieldLayoutId = $locationFieldLayout?->id;
        }

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

    /**
     * Save a field layout from POST data
     *
     * @param array|null $fieldLayoutData
     * @param int|null $existingLayoutId
     * @param string $type
     * @return FieldLayout|null
     */
    private function saveFieldLayout(?array $fieldLayoutData, ?int $existingLayoutId, string $type): ?FieldLayout
    {
        $fieldsService = Craft::$app->getFields();
        
        // Get or create field layout
        if ($existingLayoutId) {
            $fieldLayout = $fieldsService->getLayoutById($existingLayoutId);
            if (!$fieldLayout) {
                $fieldLayout = new FieldLayout(['type' => "fabian\\booked\\elements\\{$type}"]);
            }
        } else {
            $fieldLayout = new FieldLayout(['type' => "fabian\\booked\\elements\\{$type}"]);
        }

        // If field layout data is provided, set it
        if ($fieldLayoutData !== null) {
            $fieldLayout->setTabs($fieldLayoutData);
        }

        // Save the field layout (even if empty, to allow clearing)
        if (!$fieldsService->saveLayout($fieldLayout)) {
            Craft::error("Failed to save {$type} field layout: " . implode(', ', $fieldLayout->getFirstErrors()), __METHOD__);
            return null;
        }

        // If layout is empty, return null (but layout is saved for future use)
        if (empty($fieldLayout->getTabs())) {
            return $fieldLayout; // Return it anyway so we have the ID
        }

        return $fieldLayout;
    }
}
