<?php

/**
 * Booked plugin for Craft CMS 5.x
 *
 * A comprehensive booking system for Craft CMS 
 *
 * @link      https://zeix.com
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
        $this->registerPermissions();
        $this->registerTemplateVariable();
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
            'booking' => \fabian\booked\services\BookingService::class,
            'blackoutDate' => \fabian\booked\services\BlackoutDateService::class,
            'softLock' => \fabian\booked\services\SoftLockService::class,
            'calendarSync' => \fabian\booked\services\CalendarSyncService::class,
            'virtualMeeting' => \fabian\booked\services\VirtualMeetingService::class,
            'reminder' => \fabian\booked\services\ReminderService::class,
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
                $event->types[] = \fabian\booked\elements\Employee::class;
                $event->types[] = \fabian\booked\elements\Location::class;
                $event->types[] = \fabian\booked\elements\Schedule::class;
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
                    // Default redirect to services
                    'booked' => 'booked/cp/services/index',
                    
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
                    
                    'booked/schedules' => 'booked/cp/schedules/index',
                    'booked/schedules/new' => 'booked/cp/schedules/edit',
                    'booked/schedules/<id:\d+>' => 'booked/cp/schedules/edit',
                    
                    // Settings - with sidebar navigation
                    'booked/settings' => 'booked/cp/settings/general',
                    'booked/settings/general' => 'booked/cp/settings/general',
                    'booked/settings/calendar' => 'booked/cp/settings/calendar',
                    'booked/settings/meetings' => 'booked/cp/settings/meetings',
                    'booked/settings/notifications' => 'booked/cp/settings/notifications',
                    'booked/settings/booking-fields' => 'booked/cp/settings/booking-fields',
                    'booked/settings/commerce' => 'booked/cp/settings/commerce',
                    'booked/settings/frontend' => 'booked/cp/settings/frontend',
                    
                    // Calendar Sync
                    'booked/calendar/connect' => 'booked/cp/calendar/connect',
                    'booked/calendar/callback' => 'booked/cp/calendar/callback',
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
        // Use base URL so nav stays open for all subnav items
        $item['url'] = 'booked';
        $item['subnav'] = [
            'services' => ['label' => Craft::t('booked', 'Services'), 'url' => 'booked/services'],
            'employees' => ['label' => Craft::t('booked', 'Employees'), 'url' => 'booked/employees'],
            'locations' => ['label' => Craft::t('booked', 'Locations'), 'url' => 'booked/locations'],
            'schedules' => ['label' => Craft::t('booked', 'Schedules'), 'url' => 'booked/schedules'],
            'settings' => [
                'label' => Craft::t('booked', 'Settings'),
                'url' => 'booked/settings',
                'subnav' => [
                    'general' => ['label' => Craft::t('booked', 'General'), 'url' => 'booked/settings/general'],
                    'calendar' => ['label' => Craft::t('booked', 'Calendar'), 'url' => 'booked/settings/calendar'],
                    'meetings' => ['label' => Craft::t('booked', 'Virtual Meetings'), 'url' => 'booked/settings/meetings'],
                    'notifications' => ['label' => Craft::t('booked', 'Notifications'), 'url' => 'booked/settings/notifications'],
                    'booking-fields' => ['label' => Craft::t('booked', 'Booking Fields'), 'url' => 'booked/settings/booking-fields'],
                    'commerce' => ['label' => Craft::t('booked', 'Commerce'), 'url' => 'booked/settings/commerce'],
                    'frontend' => ['label' => Craft::t('booked', 'Frontend'), 'url' => 'booked/settings/frontend'],
                ],
            ],
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
    private function registerTemplateVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('booked', \fabian\booked\variables\BookingVariable::class);
            }
        );
    }
}
