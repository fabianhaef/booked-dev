<?php

/**
 * Booked plugin for Craft CMS 5.x
 *
 * A comprehensive booking system for Craft CMS 
 *
 * @link      https://fabianhaefliger.ch
 * @copyright Copyright (c) 2025
 */

namespace fabian\booked;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use craft\commerce\elements\Order;
use craft\commerce\services\Orders;
use yii\base\Event;

/**
 * Booked plugin
 *
 * @method static Booked getInstance()
 */
class Booked extends Plugin
{
    /**
     * @var self|null
     */
    private static ?self $plugin = null;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSection = true; // Enable CP section for element management

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        parent::init();

        // Register plugin alias
        Craft::setAlias('@booked', $this->getBasePath());

        // Register controllers explicitly
        $this->controllerNamespace = 'fabian\\booked\\controllers';

        // Register template roots
        $this->registerTemplateRoots();

        // Register services
        $this->registerServices();
        $this->registerElementTypes();
        $this->registerCpRoutes();
        $this->registerSiteRoutes();
        $this->registerPermissions();
        $this->registerTemplateVariable();
        $this->registerCommerceListeners();
        $this->registerProjectConfigListeners();
        $this->registerGraphQl();
    }

    /**
     * Get the plugin's name
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Booked');
    }

    /**
     * Get the plugin's description
     */
    public static function description(): string
    {
        return Craft::t('booked', 'A comprehensive booking system for Craft CMS');
    }

    /**
     * Get plugin instance
     */
    public static function getInstance(): self
    {
        if (self::$plugin === null) {
            self::$plugin = parent::getInstance();
        }

        return self::$plugin;
    }

    /**
     * Register template roots
     */
    private function registerTemplateRoots(): void
    {
        // Register template root for CP templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['booked'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            }
        );

        // Register template root for site templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['booked'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            }
        );
    }

    /**
     * Register services
     */
    private function registerServices(): void
    {
        $this->setComponents([
            'availability' => \fabian\booked\services\AvailabilityService::class,
            'availabilityCache' => \fabian\booked\services\AvailabilityCacheService::class,
            'performanceCache' => \fabian\booked\services\PerformanceCacheService::class,
            'booking' => \fabian\booked\services\BookingService::class,
            'blackoutDate' => \fabian\booked\services\BlackoutDateService::class,
            'softLock' => \fabian\booked\services\SoftLockService::class,
            'commerce' => \fabian\booked\services\CommerceService::class,
            'calendarSync' => \fabian\booked\services\CalendarSyncService::class,
            'virtualMeeting' => \fabian\booked\services\VirtualMeetingService::class,
            'reminder' => \fabian\booked\services\ReminderService::class,
            'recurrence' => \fabian\booked\services\RecurrenceService::class,
            'timezone' => \fabian\booked\services\TimezoneService::class,
            'emailRender' => \fabian\booked\services\EmailRenderService::class,
            'serviceExtra' => \fabian\booked\services\ServiceExtraService::class,
            'sequentialBooking' => \fabian\booked\services\SequentialBookingService::class,
        ]);
    }

    /**
     * Get the reminder service
     */
    public function getReminder(): \fabian\booked\services\ReminderService
    {
        return $this->get('reminder');
    }

    public function getAvailability(): \fabian\booked\services\AvailabilityService
    {
        return $this->get('availability');
    }

    public function getAvailabilityCache(): \fabian\booked\services\AvailabilityCacheService
    {
        return $this->get('availabilityCache');
    }

    public function getBooking(): \fabian\booked\services\BookingService
    {
        return $this->get('booking');
    }

    public function getBlackoutDate(): \fabian\booked\services\BlackoutDateService
    {
        return $this->get('blackoutDate');
    }

    public function getSoftLock(): \fabian\booked\services\SoftLockService
    {
        return $this->get('softLock');
    }

    public function getCalendarSync(): \fabian\booked\services\CalendarSyncService
    {
        return $this->get('calendarSync');
    }

    public function getVirtualMeeting(): \fabian\booked\services\VirtualMeetingService
    {
        return $this->get('virtualMeeting');
    }

    public function getEmailRender(): \fabian\booked\services\EmailRenderService
    {
        return $this->get('emailRender');
    }

    /**
     * Check if Craft Commerce is installed and enabled
     */
    public function isCommerceEnabled(): bool
    {
        return Craft::$app->plugins->isPluginEnabled('commerce');
    }

    /**
     * Register commerce event listeners
     */
    private function registerCommerceListeners(): void
    {
        if ($this->isCommerceEnabled()) {
            Event::on(
                Order::class,
                Order::EVENT_AFTER_COMPLETE_ORDER,
                function(Event $event) {
                    /** @var Order $order */
                    $order = $event->sender;
                    $reservation = $this->commerce->getReservationByOrderId($order->id);
                    if ($reservation) {
                        $reservation->status = \fabian\booked\records\ReservationRecord::STATUS_CONFIRMED;
                        Craft::$app->elements->saveElement($reservation);
                        Craft::info("Reservation #{$reservation->id} confirmed via Order #{$order->id}", __METHOD__);
                    }
                }
            );
        }
    }

    /**
     * Register element types
     */
    private function registerElementTypes(): void
    {
        Event::on(
            \craft\services\Elements::class,
            \craft\services\Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(\craft\events\RegisterComponentTypesEvent $event) {
                // Phase 1.2 - Core element types
                $event->types[] = \fabian\booked\elements\Service::class;
                $event->types[] = \fabian\booked\elements\ServiceExtra::class;
                $event->types[] = \fabian\booked\elements\Employee::class;
                $event->types[] = \fabian\booked\elements\Location::class;
                $event->types[] = \fabian\booked\elements\Schedule::class;
                $event->types[] = \fabian\booked\elements\BookingSequence::class;
            }
        );
    }

    /**
     * Register CP routes
     */
    private function registerCpRoutes(): void
    {
        Event::on(
            \craft\web\UrlManager::class,
            \craft\web\UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(\craft\events\RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    // Default redirect to dashboard
                    'booked' => 'booked/cp/dashboard/index',

                    // Dashboard
                    'booked/dashboard' => 'booked/cp/dashboard/index',

                    // Calendar Views
                    'booked/calendar-view/month' => 'booked/cp/calendar-view/month',
                    'booked/calendar-view/week' => 'booked/cp/calendar-view/week',
                    'booked/calendar-view/day' => 'booked/cp/calendar-view/day',
                    'booked/calendar-view/reschedule' => 'booked/cp/calendar-view/reschedule',

                    // Reports
                    'booked/reports' => 'booked/cp/reports/index',
                    'booked/reports/revenue' => 'booked/cp/reports/revenue',
                    'booked/reports/by-service' => 'booked/cp/reports/by-service',
                    'booked/reports/by-employee' => 'booked/cp/reports/by-employee',
                    'booked/reports/cancellations' => 'booked/cp/reports/cancellations',
                    'booked/reports/peak-hours' => 'booked/cp/reports/peak-hours',
                    'booked/reports/no-shows' => 'booked/cp/reports/no-shows',
                    'booked/reports/lead-time' => 'booked/cp/reports/lead-time',
                    'booked/reports/by-day-of-week' => 'booked/cp/reports/by-day-of-week',
                    'booked/reports/retention' => 'booked/cp/reports/retention',
                    'booked/reports/export-csv' => 'booked/cp/reports/export-csv',

                    // Phase 1.3 - Core element management
                    'booked/services' => 'booked/cp/services/index',
                    'booked/services/new' => 'booked/cp/services/edit',
                    'booked/services/<id:\d+>' => 'booked/cp/services/edit',

                    'booked/employees' => 'booked/cp/employees/index',
                    'booked/employees/new' => 'booked/cp/employees/edit',
                    'booked/employees/<id:\d+>' => 'booked/cp/employees/edit',

                    'booked/locations' => 'booked/cp/locations/index',
                    'booked/locations/new' => 'booked/cp/locations/edit',
                    'booked/locations/<id:\d+>' => 'booked/cp/locations/edit',

                    'booked/blackout-dates' => 'booked/cp/blackout-dates/index',
                    'booked/blackout-dates/new' => 'booked/cp/blackout-dates/new',
                    'booked/blackout-dates/<id:\d+>' => 'booked/cp/blackout-dates/edit',

                    'booked/schedules' => 'booked/cp/schedules/index',
                    'booked/schedules/new' => 'booked/cp/schedules/edit',
                    'booked/schedules/<id:\d+>' => 'booked/cp/schedules/edit',

                    // Service Extras (Phase 5.4)
                    'booked/service-extras' => 'booked/cp/service-extra/index',
                    'booked/service-extras/new' => 'booked/cp/service-extra/new',
                    'booked/service-extras/<id:\d+>' => 'booked/cp/service-extra/edit',

                    // Bookings
                    'booked/bookings' => 'booked/cp/bookings/index',
                    'booked/bookings/new' => 'booked/cp/bookings/edit',
                    'booked/bookings/<id:\d+>' => 'booked/cp/bookings/edit',
                    'booked/bookings/<id:\d+>/view' => 'booked/cp/bookings/view',
                    'booked/bookings/export' => 'booked/cp/bookings/export',

                    // Sequential Booking Sequences - redirect to bookings tab
                    'booked/sequences' => ['template' => '_layouts/redirect', 'params' => ['url' => 'booked/bookings?tab=sequential']],
                    'booked/sequences/<id:\d+>' => 'booked/cp/sequences/view',

                    // Settings - with sidebar navigation
                    'booked/settings' => 'booked/cp/settings/general',
                    'booked/settings/general' => 'booked/cp/settings/general',
                    'booked/settings/calendar' => 'booked/cp/settings/calendar',
                    'booked/settings/meetings' => 'booked/cp/settings/meetings',
                    'booked/settings/notifications' => 'booked/cp/settings/notifications',
                    'booked/settings/booking-fields' => 'booked/cp/settings/booking-fields',
                    'booked/settings/commerce' => 'booked/cp/settings/commerce',
                    'booked/settings/frontend' => 'booked/cp/settings/frontend',

                    // Calendar Sync (OAuth)
                    'booked/calendar/connect' => 'booked/cp/calendar/connect',
                    'booked/calendar/callback' => 'booked/cp/calendar/callback',
                ]);
            }
        );
    }

    /**
     * Register site routes
     */
    private function registerSiteRoutes(): void
    {
        Event::on(
            \craft\web\UrlManager::class,
            \craft\web\UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(\craft\events\RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    'booking/manage/<token:[^\/]+>' => 'booked/booking/manage-booking',
                    'booking/cancel/<token:[^\/]+>' => 'booked/booking/cancel-booking-by-token',
                ]);
            }
        );
    }

    /**
     * Register custom user permissions
     */
    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('booked', 'Booked'),
                    'permissions' => [
                        'booked-manageSettings' => ['label' => Craft::t('booked', 'Manage Settings')],
                        'booked-manageServices' => ['label' => Craft::t('booked', 'Manage Services')],
                        'booked-manageEmployees' => ['label' => Craft::t('booked', 'Manage Employees')],
                        'booked-manageLocations' => ['label' => Craft::t('booked', 'Manage Locations')],
                        'booked-manageBookings' => ['label' => Craft::t('booked', 'Manage Bookings')],
                        'booked-manageAvailability' => ['label' => Craft::t('booked', 'Manage Availability')],
                    ],
                ];
            }
        );
    }

    /**
     * Get CP nav item
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['icon'] = '@booked/icon.svg';
        // Main plugin URL - use base 'booked' to keep nav open across all sections
        $item['url'] = 'booked';
        $item['subnav'] = [
            'calendar' => ['label' => Craft::t('booked', 'Calendar'), 'url' => 'booked/calendar-view/month'],
            'bookings' => ['label' => Craft::t('booked', 'Bookings'), 'url' => 'booked/bookings'],
            'services' => ['label' => Craft::t('booked', 'Services'), 'url' => 'booked/services'],
            'service-extras' => ['label' => Craft::t('booked', 'Service Extras'), 'url' => 'booked/service-extras'],
            'employees' => ['label' => Craft::t('booked', 'Employees'), 'url' => 'booked/employees'],
            'locations' => ['label' => Craft::t('booked', 'Locations'), 'url' => 'booked/locations'],
            'schedules' => ['label' => Craft::t('booked', 'Schedules'), 'url' => 'booked/schedules'],
            'blackout-dates' => ['label' => Craft::t('booked', 'Blackout Dates'), 'url' => 'booked/blackout-dates'],
            'settings' => ['label' => Craft::t('booked', 'Settings'), 'url' => 'booked/settings'],
        ];
        return $item;
    }

    /**
     * Settings model
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new \fabian\booked\models\Settings();
    }

    /**
     * Settings HTML - redirect to custom settings page
     */
    protected function settingsHtml(): string
    {
        // Redirect to our custom settings page instead of using default
        return Craft::$app->getView()->renderTemplate(
            'booked/settings/index',
            ['settings' => $this->getSettings()]
        );
    }

    /**
     * Register template variables
     */
    /**
     * Register template variable
     */
    private function registerTemplateVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('booked', \fabian\booked\variables\BookingVariable::class);
                // Also support legacy 'booking' handle
                $variable->set('booking', \fabian\booked\variables\BookingVariable::class);
            }
        );
    }

    /**
     * Register Project Config event listeners
     * Handles synchronization of plugin settings across environments
     */
    private function registerProjectConfigListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        // Listen for settings changes in Project Config
        $projectConfigService
            ->onAdd('plugins.booked.settings', [$this, 'handleChangedSettings'])
            ->onUpdate('plugins.booked.settings', [$this, 'handleChangedSettings'])
            ->onRemove('plugins.booked.settings', [$this, 'handleRemovedSettings']);

        // Listen for plugin install/uninstall to sync settings
        Event::on(
            \craft\services\Plugins::class,
            \craft\services\Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(\craft\events\PluginEvent $event) {
                if ($event->plugin === $this) {
                    $this->syncSettingsToProjectConfig();
                }
            }
        );

        Event::on(
            \craft\services\Plugins::class,
            \craft\services\Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS,
            function(\craft\events\PluginEvent $event) {
                if ($event->plugin === $this) {
                    $this->syncSettingsToProjectConfig();
                }
            }
        );
    }

    /**
     * Handle changed settings from Project Config
     *
     * @param \craft\events\ConfigEvent $event
     */
    public function handleChangedSettings(\craft\events\ConfigEvent $event): void
    {
        $settings = $this->getSettings();
        $data = $event->newValue;

        if (!is_array($data)) {
            return;
        }

        // Apply non-sensitive settings from Project Config
        foreach ($data as $key => $value) {
            if (property_exists($settings, $key) && !in_array($key, $settings->getProjectConfigExcludedAttributes())) {
                $settings->$key = $value;
            }
        }

        // Save to database
        $pluginsService = Craft::$app->getPlugins();
        $pluginsService->savePluginSettings($this, $settings->toArray());
    }

    /**
     * Handle removed settings from Project Config
     *
     * @param \craft\events\ConfigEvent $event
     */
    public function handleRemovedSettings(\craft\events\ConfigEvent $event): void
    {
        // Reset to default settings
        $settings = new \fabian\booked\models\Settings();
        $pluginsService = Craft::$app->getPlugins();
        $pluginsService->savePluginSettings($this, $settings->toArray());
    }

    /**
     * Sync current settings to Project Config
     * Called after settings are saved in the CP
     */
    private function syncSettingsToProjectConfig(): void
    {
        $settings = $this->getSettings();
        $projectConfigService = Craft::$app->getProjectConfig();

        // Get settings without sensitive values
        $configData = $settings->getProjectConfigAttributes();

        // Save to Project Config (which will be written to YAML files)
        $projectConfigService->set('plugins.booked.settings', $configData);
    }

    /**
     * Get the service extra service
     */
    public function getServiceExtra(): \fabian\booked\services\ServiceExtraService
    {
        return $this->get('serviceExtra');
    }

    /**
     * Get the sequential booking service
     */
    public function getSequentialBooking(): \fabian\booked\services\SequentialBookingService
    {
        return $this->get('sequentialBooking');
    }

    /**
     * Register GraphQL queries and types
     */
    private function registerGraphQl(): void
    {
        Event::on(
            \craft\services\Gql::class,
            \craft\services\Gql::EVENT_REGISTER_GQL_QUERIES,
            function(\craft\events\RegisterGqlQueriesEvent $event) {
                $event->queries = array_merge(
                    $event->queries,
                    \fabian\booked\gql\queries\ServiceExtrasQuery::getQueries()
                );
            }
        );
    }
}
