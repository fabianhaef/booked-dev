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
use fabian\booked\records\ScheduleEmployeeRecord;

/**
 * Schedule Element
 *
 * @property int|null $employeeId Foreign key to Employee element (deprecated, use employeeIds)
 * @property array $employeeIds Array of employee IDs assigned to this schedule
 * @property int|null $dayOfWeek Day of week (0 = Sunday, 6 = Saturday)
 * @property string|null $startTime Start time (H:i format)
 * @property string|null $endTime End time (H:i format)
 */
class Schedule extends Element
{
    public ?int $employeeId = null;
    public array $employeeIds = [];
    public ?int $dayOfWeek = null;
    public ?string $startTime = null;
    public ?string $endTime = null;

    private ?Employee $_employee = null;
    private ?array $_employees = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Load employeeIds from junction table if not already set
        if ($this->id && empty($this->employeeIds)) {
            $junctionRecords = ScheduleEmployeeRecord::find()
                ->where(['scheduleId' => $this->id])
                ->all();

            $this->employeeIds = array_map(fn($record) => $record->employeeId, $junctionRecords);
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
        return false;
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
            'employee' => ['label' => Craft::t('booked', 'Employee')],
            'dayOfWeek' => ['label' => Craft::t('booked', 'Day')],
            'timeRange' => ['label' => Craft::t('booked', 'Time')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['employee', 'dayOfWeek', 'timeRange'];
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
        if ($handle === 'employee') {
            // Get all employee IDs (support legacy single employee)
            $employeeIds = array_filter(array_map(fn($element) => $element->employeeId, $sourceElements));

            if (empty($employeeIds)) {
                return [];
            }

            // Load all employees
            $employees = Employee::find()
                ->id($employeeIds)
                ->indexBy('id')
                ->all();

            // Map elements to their employees
            $map = [];
            foreach ($sourceElements as $element) {
                $map[$element->id] = $employees[$element->employeeId] ?? null;
            }

            return $map;
        }

        if ($handle === 'employees') {
            // Get all schedule IDs
            $scheduleIds = array_map(fn($element) => $element->id, $sourceElements);

            if (empty($scheduleIds)) {
                return [];
            }

            // Load junction records
            $junctionRecords = ScheduleEmployeeRecord::find()
                ->where(['scheduleId' => $scheduleIds])
                ->all();

            // Get all unique employee IDs
            $employeeIds = array_unique(array_map(fn($record) => $record->employeeId, $junctionRecords));

            if (empty($employeeIds)) {
                return [];
            }

            // Load all employees
            $employees = Employee::find()
                ->id($employeeIds)
                ->indexBy('id')
                ->all();

            // Build map of schedule ID => [employees]
            $map = [];
            foreach ($junctionRecords as $record) {
                if (!isset($map[$record->scheduleId])) {
                    $map[$record->scheduleId] = [];
                }
                if (isset($employees[$record->employeeId])) {
                    $map[$record->scheduleId][] = $employees[$record->employeeId];
                }
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
            case 'employee':
                $employees = $this->getEmployees();
                if (!empty($employees)) {
                    $names = array_map(fn($emp) => Html::encode($emp->title), $employees);
                    return implode(', ', $names);
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'dayOfWeek':
                return Html::encode($this->getDayName());

            case 'timeRange':
                if ($this->startTime && $this->endTime) {
                    return Html::encode($this->startTime . ' - ' . $this->endTime);
                }
                return Html::tag('span', '–', ['class' => 'light']);
        }

        return parent::attributeHtml($attribute);
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
            [['dayOfWeek'], 'required'],
            [['employeeId', 'dayOfWeek'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 0, 'max' => 6],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['employeeIds'], 'validateEmployeeIds'],
        ]);
    }

    /**
     * Validate that at least one employee is assigned
     */
    public function validateEmployeeIds(): void
    {
        if (empty($this->employeeIds) && !$this->employeeId) {
            $this->addError('employeeIds', Craft::t('booked', 'At least one employee must be assigned.'));
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

        // For backward compatibility, keep employeeId if set
        $record->employeeId = $this->employeeId;
        $record->dayOfWeek = $this->dayOfWeek;
        $record->startTime = $this->startTime;
        $record->endTime = $this->endTime;

        $record->save(false);

        // Save employee relationships through junction table
        if (!empty($this->employeeIds)) {
            // Delete existing relationships
            ScheduleEmployeeRecord::deleteAll(['scheduleId' => $this->id]);

            // Create new relationships
            foreach ($this->employeeIds as $employeeId) {
                $junction = new ScheduleEmployeeRecord();
                $junction->scheduleId = (int)$this->id;
                $junction->employeeId = (int)$employeeId;
                $junction->save(false);
            }
        }

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

        // Delete employee relationships
        ScheduleEmployeeRecord::deleteAll(['scheduleId' => $this->id]);

        parent::afterDelete();
    }

    /**
     * Get the associated Employee element (legacy support for single employee)
     */
    public function getEmployee(): ?Employee
    {
        // Check if eager loaded
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
     * Get all associated Employee elements
     *
     * @return Employee[]
     */
    public function getEmployees(): array
    {
        // Check if eager loaded
        $eagerLoaded = $this->getEagerLoadedElements('employees');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_employees === null) {
            // Load from junction table
            $junctionRecords = ScheduleEmployeeRecord::find()
                ->where(['scheduleId' => $this->id])
                ->all();

            $employeeIds = array_map(fn($record) => $record->employeeId, $junctionRecords);

            if (!empty($employeeIds)) {
                $this->_employees = Employee::find()
                    ->id($employeeIds)
                    ->siteId('*')
                    ->all();
            } else {
                $this->_employees = [];
            }
        }

        return $this->_employees;
    }

    /**
     * Get day name from dayOfWeek number
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
}

