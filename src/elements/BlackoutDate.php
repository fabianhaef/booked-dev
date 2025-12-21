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
 * @property string $startDate
 * @property string $endDate
 * @property bool $isActive
 */
class BlackoutDate extends Element
{
    public string $startDate = '';
    public string $endDate = '';
    public bool $isActive = true;

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
            'duration' => ['label' => 'Dauer'],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['id', 'title', 'dateRange', 'duration'];
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
        return UrlHelper::cpUrl('booking/blackout-dates/edit/' . $this->id);
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

        $record->startDate = $this->startDate;
        $record->endDate = $this->endDate;
        $record->isActive = $this->isActive;

        $record->save(false);

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
}
