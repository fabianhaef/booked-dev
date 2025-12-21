<?php

namespace modules\booking\controllers\cp;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use modules\booking\models\Settings;

/**
 * CP Settings Controller - Handles Control Panel settings management
 */
class SettingsController extends Controller
{
    /**
     * Settings page
     */
    public function actionIndex(): Response
    {
        $settings = Settings::loadSettings();

        return $this->renderTemplate('booking/settings/index', [
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

        // Basic contact settings
        $settings->ownerEmail = $request->getRequiredBodyParam('ownerEmail');
        $settings->ownerName = $request->getRequiredBodyParam('ownerName');

        // Owner notification settings
        $settings->ownerNotificationEnabled = (bool)$request->getBodyParam('ownerNotificationEnabled');
        $settings->ownerNotificationSubject = $request->getBodyParam('ownerNotificationSubject');

        // Payment QR code asset
        $paymentQrAssetId = $request->getBodyParam('paymentQrAssetId');
        $settings->paymentQrAssetId = is_array($paymentQrAssetId) ? ($paymentQrAssetId[0] ?? null) : $paymentQrAssetId;

        if ($settings->save()) {
            Craft::$app->session->setNotice('Einstellungen wurden gespeichert.');
        } else {
            Craft::$app->session->setError('Einstellungen konnten nicht gespeichert werden.');
        }

        return $this->renderTemplate('booking/settings/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Test email settings
     */
    public function actionTestEmail(): Response
    {
        $this->requirePostRequest();

        $settings = Settings::loadSettings();
        
        try {
            $message = Craft::$app->mailer->compose()
                ->setTo($settings->ownerEmail)
                ->setFrom([Craft::$app->systemSettings->getSetting('email', 'fromEmail') => $settings->ownerName])
                ->setSubject('Booking System Test Email')
                ->setTextBody('This is a test email from your booking system. Email settings are working correctly.');

            $sent = $message->send();

            if ($sent) {
                Craft::$app->session->setNotice('Test email sent successfully.');
            } else {
                Craft::$app->session->setError('Failed to send test email.');
            }
        } catch (\Exception $e) {
            Craft::$app->session->setError('Email error: ' . $e->getMessage());
        }

        return $this->redirectToPostedUrl();
    }
}
