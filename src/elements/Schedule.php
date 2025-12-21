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
 * @property int|null $employeeId Foreign key to Employee element
 * @property int|null $dayOfWeek Day of week (0 = Sunday, 6 = Saturday)
 * @property string|null $startTime Start time (H:i format)
 * @property string|null $endTime End time (H:i format)
 */
class Schedule extends Element
{
    public ?int $employeeId = null;
    public ?int $dayOfWeek = null;
    public ?string $startTime = null;
    public ?string $endTime = null;

    private ?Employee $_employee = null;

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
            // Get all employee IDs
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

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'employee':
                $employee = $this->getEmployee();
                if ($employee) {
                    return Html::encode($employee->title);
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
            [['employeeId', 'dayOfWeek'], 'required'],
            [['employeeId', 'dayOfWeek'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 0, 'max' => 6],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
        ]);
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

        $record->employeeId = $this->employeeId;
        $record->dayOfWeek = $this->dayOfWeek;
        $record->startTime = $this->startTime;
        $record->endTime = $this->endTime;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        $record = ScheduleRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    /**
     * Get the associated Employee element
     */
    public function getEmployee(): ?Employee
    {
        // Check if eager loaded
        $eagerLoaded = $this->getEagerLoadedElements('employee');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_employee === null && $this->employeeId) {
            $this->_employee = Employee::findOne($this->employeeId);
        }
        return $this->_employee;
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

