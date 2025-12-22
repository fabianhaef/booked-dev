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
            
            Craft::info('Saving field layouts...', __METHOD__);

            // Save Employee field layout
            $employeeFieldLayout = $this->assembleAndSaveLayout('employeeFieldLayout', \fabian\booked\elements\Employee::class, $settings->employeeFieldLayoutId);
            if ($employeeFieldLayout) {
                $settings->employeeFieldLayoutId = $employeeFieldLayout->id;
                Craft::info('Saved Employee field layout ID: ' . $employeeFieldLayout->id, __METHOD__);
            }

            // Save Service field layout
            $serviceFieldLayout = $this->assembleAndSaveLayout('serviceFieldLayout', \fabian\booked\elements\Service::class, $settings->serviceFieldLayoutId);
            if ($serviceFieldLayout) {
                $settings->serviceFieldLayoutId = $serviceFieldLayout->id;
                Craft::info('Saved Service field layout ID: ' . $serviceFieldLayout->id, __METHOD__);
            }

            // Save Location field layout
            $locationFieldLayout = $this->assembleAndSaveLayout('locationFieldLayout', \fabian\booked\elements\Location::class, $settings->locationFieldLayoutId);
            if ($locationFieldLayout) {
                $settings->locationFieldLayoutId = $locationFieldLayout->id;
                Craft::info('Saved Location field layout ID: ' . $locationFieldLayout->id, __METHOD__);
            }
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
     * Assemble and save a field layout from POST data
     *
     * @param string $namespace
     * @param string $elementType
     * @param int|null $existingLayoutId
     * @return FieldLayout|null
     */
    private function assembleAndSaveLayout(string $namespace, string $elementType, ?int $existingLayoutId = null): ?FieldLayout
    {
        $fieldsService = Craft::$app->getFields();
        $request = Craft::$app->getRequest();

        // Check the raw POST data first to see if this designer actually sent any data
        $postedData = $request->getBodyParam($namespace);
        if (!$postedData || empty($postedData['fieldLayout'])) {
            return null;
        }

        // Check if the JSON actually contains tabs. If it doesn't, it might be an 
        // uninitialized or background designer that shouldn't overwrite our data.
        $json = \craft\helpers\Json::decodeIfJson($postedData['fieldLayout']);
        if (!is_array($json) || !isset($json['tabs'])) {
            Craft::info("Skipping save for field layout namespace '{$namespace}' as it contains no tabs in the POST data.", __METHOD__);
            return null;
        }

        // assembleLayoutFromPost handles the complex structure sent by the designer
        $fieldLayout = $fieldsService->assembleLayoutFromPost($namespace);
        $fieldLayout->type = $elementType;
        
        if ($existingLayoutId) {
            $fieldLayout->id = $existingLayoutId;
        }

        // Save the field layout
        if (!$fieldsService->saveLayout($fieldLayout)) {
            Craft::error("Failed to save field layout for {$elementType}: " . implode(', ', $fieldLayout->getFirstErrors()), __METHOD__);
            return null;
        }

        return $fieldLayout;
    }
}
