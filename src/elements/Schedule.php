<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\ScheduleQuery;
use fabian\booked\records\ScheduleRecord;

/**
 * Schedule Element
 *
 * Simplified model: Each schedule directly references a service, employee, and location.
 * Capacity model: Each schedule defines capacity for booking management.
 *
 * @property string|null $title Schedule title (e.g., "Morning Shift", "Weekend Hours")
 * @property int|null $serviceId FK to Service element (required)
 * @property int|null $employeeId FK to Employee element (optional)
 * @property int|null $locationId FK to Location element (optional)
 * @property int|null $dayOfWeek Day of week (1-7, Monday=1) - DEPRECATED, use daysOfWeek
 * @property string|array|null $daysOfWeek Array of days (e.g., [1, 2, 5] for Mon, Tue, Fri)
 * @property string|null $startTime Start time (H:i format)
 * @property string|null $endTime End time (H:i format)
 * @property int $capacity Number of people per booking slot (e.g., 4 people per escape room)
 * @property int $simultaneousSlots Number of parallel resources (e.g., 4 escape rooms)
 */
class Schedule extends Element
{
    public ?string $title = null;
    public ?int $serviceId = null;
    public ?int $employeeId = null;
    public ?int $locationId = null;
    public ?int $dayOfWeek = null; // Kept for backward compatibility
    public string|array|null $daysOfWeek = []; // Can be JSON string from DB or array
    public ?string $startTime = null;
    public ?string $endTime = null;
    public int $capacity = 1; // People per slot
    public int $simultaneousSlots = 1; // Parallel resources (rooms, tables, etc.)

    private ?Service $_service = null;
    private ?Employee $_employee = null;
    private ?Location $_location = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Ensure daysOfWeek is an array (handles JSON decoding from database)
        if (is_string($this->daysOfWeek)) {
            $this->daysOfWeek = json_decode($this->daysOfWeek, true) ?? [];
        } elseif (!is_array($this->daysOfWeek)) {
            $this->daysOfWeek = [];
        }

        // Backward compatibility: if dayOfWeek is set but daysOfWeek is empty, use dayOfWeek
        // Convert from old format (0-6) to new format (1-7)
        if (empty($this->daysOfWeek) && $this->dayOfWeek !== null) {
            $oldDay = (int)$this->dayOfWeek;
            // Old: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
            // New: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
            $newDay = $oldDay === 0 ? 7 : $oldDay;
            $this->daysOfWeek = [$newDay];
        }
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Schedule');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Schedules');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'schedule';
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(?string $source = null): array
    {
        return [
            Delete::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return false; // Schedules don't have public URLs
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            'enabled' => 'green',
            'disabled' => null,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        return $this->enabled ? 'enabled' : 'disabled';
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new ScheduleQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Schedules'),
                'defaultSort' => ['dayOfWeek', 'asc'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('booked', 'Title')],
            'service' => ['label' => Craft::t('booked', 'Service')],
            'employee' => ['label' => Craft::t('booked', 'Employee')],
            'location' => ['label' => Craft::t('booked', 'Location')],
            'dayOfWeek' => ['label' => Craft::t('booked', 'Days')],
            'timeRange' => ['label' => Craft::t('booked', 'Time')],
            'capacity' => ['label' => Craft::t('booked', 'Capacity')],
            'totalSpots' => ['label' => Craft::t('booked', 'Total Spots')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'service', 'employee', 'dayOfWeek', 'timeRange', 'capacity'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('booked', 'Day of Week'),
                'orderBy' => 'booked_schedules.dayOfWeek',
                'attribute' => 'dayOfWeek',
            ],
            [
                'label' => Craft::t('booked', 'Start Time'),
                'orderBy' => 'booked_schedules.startTime',
                'attribute' => 'startTime',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'service') {
            $serviceIds = array_filter(array_map(fn($element) => $element->serviceId, $sourceElements));
            if (empty($serviceIds)) {
                return [];
            }

            $services = Service::find()->id($serviceIds)->indexBy('id')->all();

            $map = [];
            foreach ($sourceElements as $element) {
                $map[$element->id] = $services[$element->serviceId] ?? null;
            }
            return $map;
        }

        if ($handle === 'employee') {
            $employeeIds = array_filter(array_map(fn($element) => $element->employeeId, $sourceElements));
            if (empty($employeeIds)) {
                return [];
            }

            $employees = Employee::find()->id($employeeIds)->indexBy('id')->all();

            $map = [];
            foreach ($sourceElements as $element) {
                $map[$element->id] = $employees[$element->employeeId] ?? null;
            }
            return $map;
        }

        if ($handle === 'location') {
            $locationIds = array_filter(array_map(fn($element) => $element->locationId, $sourceElements));
            if (empty($locationIds)) {
                return [];
            }

            $locations = Location::find()->id($locationIds)->indexBy('id')->all();

            $map = [];
            foreach ($sourceElements as $element) {
                $map[$element->id] = $locations[$element->locationId] ?? null;
            }
            return $map;
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'service':
                $service = $this->getService();
                if ($service) {
                    return Html::encode($service->title);
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'employee':
                $employee = $this->getEmployee();
                if ($employee) {
                    return Html::encode($employee->title);
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'location':
                $location = $this->getLocation();
                if ($location) {
                    return Html::encode($location->title);
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'dayOfWeek':
                return Html::encode($this->getDaysName());

            case 'timeRange':
                if ($this->startTime && $this->endTime) {
                    return Html::encode($this->startTime . ' - ' . $this->endTime);
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'capacity':
                return Html::encode($this->capacity . ' × ' . $this->simultaneousSlots);

            case 'totalSpots':
                return Html::encode($this->getTotalSpots());
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * Get total available spots per time slot
     * Total = capacity × simultaneousSlots
     * Example: 4 people × 4 rooms = 16 total spots
     */
    public function getTotalSpots(): int
    {
        return $this->capacity * $this->simultaneousSlots;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booked/schedules/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canView(\craft\elements\User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canSave(\craft\elements\User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDelete(?\craft\elements\User $user = null): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['serviceId', 'daysOfWeek'], 'required'],
            [['daysOfWeek'], 'validateDaysOfWeek'],
            [['serviceId', 'employeeId', 'locationId', 'dayOfWeek'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 1, 'max' => 7],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['title'], 'string', 'max' => 255],
            [['capacity', 'simultaneousSlots'], 'integer', 'min' => 1],
            [['capacity', 'simultaneousSlots'], 'default', 'value' => 1],
        ]);
    }

    /**
     * Validate daysOfWeek array
     */
    public function validateDaysOfWeek(): void
    {
        $days = $this->getDaysOfWeekArray();

        if (empty($days)) {
            $this->addError('daysOfWeek', Craft::t('booked', 'At least one day must be selected.'));
            return;
        }

        // Ensure all values are integers between 1-7 (Monday-Sunday)
        foreach ($days as $day) {
            if (!is_int($day) || $day < 1 || $day > 7) {
                $this->addError('daysOfWeek', Craft::t('booked', 'Invalid day value: {day}', ['day' => $day]));
                return;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = ScheduleRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid schedule ID: ' . $this->id);
            }
        } else {
            $record = new ScheduleRecord();
            $record->id = (int)$this->id;
        }

        // Save direct FK relationships
        $record->title = $this->title;
        $record->serviceId = $this->serviceId;
        $record->employeeId = $this->employeeId;
        $record->locationId = $this->locationId;
        $record->daysOfWeek = !empty($this->daysOfWeek) ? json_encode($this->daysOfWeek) : null;

        // dayOfWeek is kept for backward compatibility queries
        $record->dayOfWeek = $this->dayOfWeek ?? (!empty($this->daysOfWeek) ? $this->daysOfWeek[0] : null);
        $record->startTime = $this->startTime;
        $record->endTime = $this->endTime;

        // Save capacity fields
        $record->capacity = $this->capacity;
        $record->simultaneousSlots = $this->simultaneousSlots;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // Delete schedule record
        $record = ScheduleRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    /**
     * Get the associated Service element
     */
    public function getService(): ?Service
    {
        $eagerLoaded = $this->getEagerLoadedElements('service');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_service === null && $this->serviceId) {
            $this->_service = Service::find()->id($this->serviceId)->siteId('*')->one();
        }
        return $this->_service;
    }

    /**
     * Get the associated Employee element
     */
    public function getEmployee(): ?Employee
    {
        $eagerLoaded = $this->getEagerLoadedElements('employee');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_employee === null && $this->employeeId) {
            $this->_employee = Employee::find()->id($this->employeeId)->siteId('*')->one();
        }
        return $this->_employee;
    }

    /**
     * Get the associated Location element
     */
    public function getLocation(): ?Location
    {
        $eagerLoaded = $this->getEagerLoadedElements('location');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_location === null && $this->locationId) {
            $this->_location = Location::find()->id($this->locationId)->siteId('*')->one();
        }
        return $this->_location;
    }

    /**
     * Get day name from dayOfWeek number (backward compatibility)
     */
    public function getDayName(): string
    {
        $days = [
            0 => Craft::t('booked', 'Sunday'),
            1 => Craft::t('booked', 'Monday'),
            2 => Craft::t('booked', 'Tuesday'),
            3 => Craft::t('booked', 'Wednesday'),
            4 => Craft::t('booked', 'Thursday'),
            5 => Craft::t('booked', 'Friday'),
            6 => Craft::t('booked', 'Saturday'),
        ];

        return $days[$this->dayOfWeek] ?? Craft::t('booked', 'Unknown');
    }

    /**
     * Get daysOfWeek as an array (handles JSON string from database)
     */
    private function getDaysOfWeekArray(): array
    {
        if (is_string($this->daysOfWeek)) {
            return json_decode($this->daysOfWeek, true) ?? [];
        }

        return is_array($this->daysOfWeek) ? $this->daysOfWeek : [];
    }

    /**
     * Get formatted string of multiple days
     * Days: 1 = Monday, 2 = Tuesday, ..., 7 = Sunday
     */
    public function getDaysName(): string
    {
        $days = $this->getDaysOfWeekArray();

        if (empty($days)) {
            return Craft::t('booked', 'No days selected');
        }

        $dayNames = [
            1 => Craft::t('booked', 'Mon'),
            2 => Craft::t('booked', 'Tue'),
            3 => Craft::t('booked', 'Wed'),
            4 => Craft::t('booked', 'Thu'),
            5 => Craft::t('booked', 'Fri'),
            6 => Craft::t('booked', 'Sat'),
            7 => Craft::t('booked', 'Sun'),
        ];

        $names = [];
        foreach ($days as $day) {
            $names[] = $dayNames[$day] ?? $day;
        }

        return implode(', ', $names);
    }
}

