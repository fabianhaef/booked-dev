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
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\View;
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
        // $this->registerTemplateVariable();
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
            // TODO: Phase 2 - Add more services
            // 'booking' => \fabian\booked\services\BookingService::class,
            // 'blackoutDate' => \fabian\booked\services\BlackoutDateService::class,
        ]);
    }

    public function getAvailability(): \fabian\booked\services\AvailabilityService
    {
        return $this->get('availability');
    }

    public function getAvailabilityCache(): \fabian\booked\services\AvailabilityCacheService
    {
        return $this->get('availabilityCache');
    }

    // TODO: Phase 2 - Add more service getters
    // public function getBooking(): \fabian\booked\services\BookingService
    // {
    //     return $this->get('booking');
    // }
    //
    // public function getBlackoutDate(): \fabian\booked\services\BlackoutDateService
    // {
    //     return $this->get('blackoutDate');
    // }

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

                // TODO: Phase 2 - Add Reservation, Availability, BookingVariation, BlackoutDate elements
                // $event->types[] = \fabian\booked\elements\Reservation::class;
                // $event->types[] = \fabian\booked\elements\Availability::class;
                // $event->types[] = \fabian\booked\elements\BookingVariation::class;
                // $event->types[] = \fabian\booked\elements\BlackoutDate::class;
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
                    
                    // Settings
                    'booked/settings' => 'booked/cp/settings/index',
                    
                    // TODO: Phase 2 - Add bookings, availability routes
                    // 'booked/bookings' => 'booked/cp/bookings/index',
                    // 'booked/availability' => 'booked/cp/availability/index',
                ]);
            }
        );
    }

    /*
     * Register site routes
     *
    private function registerSiteRoutes(): void
    {
        Event::on(
            \craft\web\UrlManager::class,
            \craft\web\UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(\craft\events\RegisterUrlRulesEvent $event) {
                $event->rules['POST booked/create-booking'] = 'booked/booking/create-booking';
                // ... more routes
            }
        );
    }
    */

    /*
     * Register template variable
     *
    private function registerTemplateVariable(): void
    {
        Event::on(
            \craft\web\twig\variables\CraftVariable::class,
            \craft\web\twig\variables\CraftVariable::EVENT_INIT,
            function(\yii\base\Event $event) {
                $variable = $event->sender;
                $variable->set('booked', \fabian\booked\variables\BookingVariable::class);
                $variable->set('booking', \fabian\booked\variables\BookingVariable::class); // backward compat
            }
        );
    }
    */

    /**
     * Get CP nav item
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['icon'] = '@booked/icon.svg';
        $item['url'] = 'booked/services';
        $item['subnav'] = [
            'services' => ['label' => Craft::t('booked', 'Services'), 'url' => 'booked/services'],
            'employees' => ['label' => Craft::t('booked', 'Employees'), 'url' => 'booked/employees'],
            'locations' => ['label' => Craft::t('booked', 'Locations'), 'url' => 'booked/locations'],
            'schedules' => ['label' => Craft::t('booked', 'Schedules'), 'url' => 'booked/schedules'],
            'settings' => ['label' => Craft::t('booked', 'Settings'), 'url' => 'booked/settings'],
            // TODO: Phase 2 - Add bookings, availability to nav
            // 'bookings' => ['label' => Craft::t('booked', 'Bookings'), 'url' => 'booked/bookings'],
            // 'availability' => ['label' => Craft::t('booked', 'Availability'), 'url' => 'booked/availability'],
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
}
