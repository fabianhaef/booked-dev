<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\BlackoutDateQuery;
use fabian\booked\records\BlackoutDateRecord;

/**
 * BlackoutDate Element
 *
 * Represents a period when bookings should be blocked, either globally or for specific
 * locations and/or employees.
 *
 * SCOPING LOGIC:
 * --------------
 * BlackoutDate uses flexible scoping to control which bookings are affected:
 *
 * 1. Global Blackout (empty locationIds AND empty employeeIds):
 *    - Blocks ALL bookings across ALL locations and ALL employees
 *    - Use case: Public holidays, company-wide closures
 *
 * 2. Location-specific Blackout (locationIds specified, employeeIds empty):
 *    - Blocks ALL bookings at the specified location(s)
 *    - Affects ALL employees working at those locations
 *    - Use case: Location maintenance, facility closure
 *
 * 3. Employee-specific Blackout (employeeIds specified, locationIds empty):
 *    - Blocks bookings for the specified employee(s)
 *    - Affects these employees across ALL locations they work at
 *    - Use case: Employee vacation, sick leave
 *
 * 4. Location AND Employee Blackout (both locationIds AND employeeIds specified):
 *    - Blocks bookings ONLY when BOTH conditions match
 *    - Logic: (employee is in employeeIds) AND (location is in locationIds)
 *    - Use case: Specific employee unavailable at specific location only
 *
 * VALIDATION LOGIC:
 * When checking if a booking should be blocked, the logic is:
 * - If locationIds is empty → applies to all locations
 * - If employeeIds is empty → applies to all employees
 * - If both are specified → BOTH must match to block
 * - If blackout is inactive (isActive=false) → never blocks
 *
 * @property string $startDate
 * @property string $endDate
 * @property array $locationIds Array of Location element IDs (empty = all locations)
 * @property array $employeeIds Array of Employee element IDs (empty = all employees)
 * @property bool $isActive Whether this blackout is currently enforced
 */
class BlackoutDate extends Element
{
    public string $startDate = '';
    public string $endDate = '';
    public array $locationIds = [];
    public array $employeeIds = [];
    public bool $isActive = true;

    private ?array $_locations = null;
    private ?array $_employees = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Ausfalltag';
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return 'Ausfalltage';
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'blackoutDate';
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
            'active' => 'green',
            'inactive' => null,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        return $this->isActive ? 'active' : 'inactive';
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new BlackoutDateQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => 'Alle Ausfalltage',
                'defaultSort' => ['startDate', 'desc'],
                'type' => 'native',
            ],
            [
                'heading' => 'Status',
            ],
            [
                'key' => 'active',
                'label' => 'Aktiv',
                'criteria' => ['isActive' => true],
                'defaultSort' => ['startDate', 'desc'],
                'type' => 'native',
            ],
            [
                'key' => 'inactive',
                'label' => 'Inaktiv',
                'criteria' => ['isActive' => false],
                'defaultSort' => ['startDate', 'desc'],
                'type' => 'native',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'id' => ['label' => 'ID'],
            'title' => ['label' => 'Name'],
            'dateRange' => ['label' => 'Zeitraum'],
            'location' => ['label' => 'Location'],
            'employee' => ['label' => 'Employee'],
            'duration' => ['label' => 'Dauer'],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'dateRange', 'location', 'employee'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => 'Name',
                'orderBy' => 'elements_sites.title',
                'attribute' => 'title',
            ],
            [
                'label' => 'Startdatum',
                'orderBy' => 'bookings_blackout_dates.startDate',
                'attribute' => 'startDate',
            ],
            [
                'label' => 'Erstellt',
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'id':
                // Make ID clickable to edit the blackout date
                if ($this->getCpEditUrl()) {
                    return Html::a('#' . $this->id, $this->getCpEditUrl());
                }
                return '#' . $this->id;

            case 'title':
                // Make title clickable to edit the blackout date
                if ($this->getCpEditUrl()) {
                    return Html::a(Html::encode($this->title ?: 'Unbenannt'), $this->getCpEditUrl());
                }
                return Html::encode($this->title ?: 'Unbenannt');

            case 'dateRange':
                return Html::encode($this->getFormattedDateRange());

            case 'location':
                $locations = $this->getLocations();
                if (empty($locations)) {
                    return Html::tag('span', 'All Locations', ['class' => 'light']);
                }
                $names = array_map(fn($loc) => Html::encode($loc->title), $locations);
                return implode(', ', $names);

            case 'employee':
                $employees = $this->getEmployees();
                if (empty($employees)) {
                    return Html::tag('span', 'All Employees', ['class' => 'light']);
                }
                $names = array_map(fn($emp) => Html::encode($emp->title), $employees);
                return implode(', ', $names);

            case 'duration':
                $days = $this->getDurationDays();
                return Html::encode($days . ' ' . ($days == 1 ? 'Tag' : 'Tage'));
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booked/blackout-dates/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function getSection(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getType(): ?string
    {
        return null;
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
    public function canDelete(\craft\elements\User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(\craft\elements\User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['startDate', 'endDate'], 'required'],
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d'],
            [['isActive'], 'boolean'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterPropagate(bool $isNew): void
    {
        parent::afterPropagate($isNew);

        // Load the relationship IDs after the element is loaded
        if (!$isNew && $this->id) {
            $this->locationIds = (new \craft\db\Query())
                ->select(['locationId'])
                ->from('{{%bookings_blackout_dates_locations}}')
                ->where(['blackoutDateId' => $this->id])
                ->column();

            $this->employeeIds = (new \craft\db\Query())
                ->select(['employeeId'])
                ->from('{{%bookings_blackout_dates_employees}}')
                ->where(['blackoutDateId' => $this->id])
                ->column();
        }
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = BlackoutDateRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid blackout date ID: ' . $this->id);
            }
        } else {
            $record = new BlackoutDateRecord();
            $record->id = (int)$this->id;
        }

        $record->name = $this->title;
        $record->startDate = $this->startDate;
        $record->endDate = $this->endDate;
        $record->isActive = $this->isActive;

        $record->save(false);

        // Save location relationships
        Craft::$app->db->createCommand()
            ->delete('{{%bookings_blackout_dates_locations}}', ['blackoutDateId' => $this->id])
            ->execute();

        if (!empty($this->locationIds)) {
            foreach ($this->locationIds as $locationId) {
                Craft::$app->db->createCommand()->insert('{{%bookings_blackout_dates_locations}}', [
                    'blackoutDateId' => $this->id,
                    'locationId' => $locationId,
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'dateUpdated' => date('Y-m-d H:i:s'),
                    'uid' => \craft\helpers\StringHelper::UUID(),
                ])->execute();
            }
        }

        // Save employee relationships
        Craft::$app->db->createCommand()
            ->delete('{{%bookings_blackout_dates_employees}}', ['blackoutDateId' => $this->id])
            ->execute();

        if (!empty($this->employeeIds)) {
            foreach ($this->employeeIds as $employeeId) {
                Craft::$app->db->createCommand()->insert('{{%bookings_blackout_dates_employees}}', [
                    'blackoutDateId' => $this->id,
                    'employeeId' => $employeeId,
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'dateUpdated' => date('Y-m-d H:i:s'),
                    'uid' => \craft\helpers\StringHelper::UUID(),
                ])->execute();
            }
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // Delete the blackout date record
        $record = BlackoutDateRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    // === Business Logic Methods ===

    /**
     * Get formatted date range
     */
    public function getFormattedDateRange(): string
    {
        $start = \DateTime::createFromFormat('Y-m-d', $this->startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $this->endDate);

        if (!$start || !$end) {
            return $this->startDate . ' - ' . $this->endDate;
        }

        $formatter = new \IntlDateFormatter(
            'de_CH',
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE
        );

        if ($this->startDate === $this->endDate) {
            return $formatter->format($start);
        }

        return $formatter->format($start) . ' - ' . $formatter->format($end);
    }

    /**
     * Get duration in days
     */
    public function getDurationDays(): int
    {
        $start = new \DateTime($this->startDate);
        $end = new \DateTime($this->endDate);
        $diff = $start->diff($end);

        return $diff->days + 1;
    }

    /**
     * Check if blackout date is in the past
     */
    public function isPast(): bool
    {
        $end = new \DateTime($this->endDate);
        $today = new \DateTime('today');

        return $end < $today;
    }

    /**
     * Check if blackout date is currently active
     */
    public function isCurrentlyActive(): bool
    {
        $start = new \DateTime($this->startDate);
        $end = new \DateTime($this->endDate);
        $today = new \DateTime('today');

        return $today >= $start && $today <= $end;
    }

    /**
     * Check if blackout date is in the future
     */
    public function isFuture(): bool
    {
        $start = new \DateTime($this->startDate);
        $today = new \DateTime('today');

        return $start > $today;
    }

    /**
     * Check if a given date falls within this blackout period
     */
    public function coversDate(string $date): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $checkDate = new \DateTime($date);
        $start = new \DateTime($this->startDate);
        $end = new \DateTime($this->endDate);

        return $checkDate >= $start && $checkDate <= $end;
    }

    /**
     * Check if this blackout applies to a specific booking scenario
     *
     * Implements the scoping logic documented in the class header.
     *
     * @param string $date The booking date to check
     * @param int|null $locationId The location ID for the booking (null = check all)
     * @param int|null $employeeId The employee ID for the booking (null = check all)
     * @return bool True if this blackout blocks the booking
     */
    public function appliesToBooking(string $date, ?int $locationId = null, ?int $employeeId = null): bool
    {
        // If blackout is inactive or date doesn't fall within range, it doesn't apply
        if (!$this->coversDate($date)) {
            return false;
        }

        $hasLocationRestriction = !empty($this->locationIds);
        $hasEmployeeRestriction = !empty($this->employeeIds);

        // Case 1: Global blackout (no restrictions) - blocks everything
        if (!$hasLocationRestriction && !$hasEmployeeRestriction) {
            return true;
        }

        // Case 2: Location-only restriction - blocks if location matches (or no location specified)
        if ($hasLocationRestriction && !$hasEmployeeRestriction) {
            return $locationId === null || in_array($locationId, $this->locationIds);
        }

        // Case 3: Employee-only restriction - blocks if employee matches (or no employee specified)
        if (!$hasLocationRestriction && $hasEmployeeRestriction) {
            return $employeeId === null || in_array($employeeId, $this->employeeIds);
        }

        // Case 4: Both location AND employee restrictions - BOTH must match
        if ($hasLocationRestriction && $hasEmployeeRestriction) {
            $locationMatches = $locationId === null || in_array($locationId, $this->locationIds);
            $employeeMatches = $employeeId === null || in_array($employeeId, $this->employeeIds);
            return $locationMatches && $employeeMatches;
        }

        return false;
    }

    /**
     * Get locations associated with this blackout date
     *
     * @return Location[]
     */
    public function getLocations(): array
    {
        if ($this->_locations === null) {
            if (!$this->id) {
                $this->_locations = [];
            } else {
                $locationIds = (new \craft\db\Query())
                    ->select(['locationId'])
                    ->from('{{%bookings_blackout_dates_locations}}')
                    ->where(['blackoutDateId' => $this->id])
                    ->column();

                $this->_locations = $locationIds
                    ? Location::find()->id($locationIds)->all()
                    : [];
            }
        }

        return $this->_locations;
    }

    /**
     * Get employees associated with this blackout date
     *
     * @return Employee[]
     */
    public function getEmployees(): array
    {
        if ($this->_employees === null) {
            if (!$this->id) {
                $this->_employees = [];
            } else {
                $employeeIds = (new \craft\db\Query())
                    ->select(['employeeId'])
                    ->from('{{%bookings_blackout_dates_employees}}')
                    ->where(['blackoutDateId' => $this->id])
                    ->column();

                $this->_employees = $employeeIds
                    ? Employee::find()->id($employeeIds)->all()
                    : [];
            }
        }

        return $this->_employees;
    }
}
