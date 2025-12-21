<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\BookingVariationQuery;
use fabian\booked\elements\Reservation;
use fabian\booked\records\BookingVariationRecord;

/**
 * BookingVariation Element
 *
 * @property string|null $description
 * @property int|null $slotDurationMinutes
 * @property int|null $bufferMinutes
 * @property bool $isActive
 * @property int $maxCapacity
 * @property bool $allowQuantitySelection
 */
class BookingVariation extends Element
{
    public ?string $description = null;
    public ?int $slotDurationMinutes = null;
    public ?int $bufferMinutes = null;
    public bool $isActive = true;
    public int $maxCapacity = 1;
    public bool $allowQuantitySelection = false;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Buchungsvariante';
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return 'Buchungsvarianten';
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'bookingVariation';
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(?string $source = null): array
    {
        return [
            Duplicate::class,
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
        return new BookingVariationQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => 'Alle Varianten',
                'defaultSort' => ['name', 'asc'],
                'type' => 'native',
            ],
            [
                'heading' => 'Status',
            ],
            [
                'key' => 'active',
                'label' => 'Aktiv',
                'criteria' => ['isActive' => true],
                'defaultSort' => ['name', 'asc'],
                'type' => 'native',
            ],
            [
                'key' => 'inactive',
                'label' => 'Inaktiv',
                'criteria' => ['isActive' => false],
                'defaultSort' => ['name', 'asc'],
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
            'slotDurationMinutes' => ['label' => 'Dauer'],
            'bufferMinutes' => ['label' => 'Puffer'],
            'maxCapacity' => ['label' => 'Kapazität'],
            'dateCreated' => ['label' => 'Erstellt'],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['id', 'title', 'slotDurationMinutes', 'bufferMinutes', 'maxCapacity'];
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
                // Make ID clickable to edit the variation
                if ($this->getCpEditUrl()) {
                    return Html::a('#' . $this->id, $this->getCpEditUrl());
                }
                return '#' . $this->id;

            case 'slotDurationMinutes':
                if ($this->slotDurationMinutes !== null) {
                    return Html::encode($this->slotDurationMinutes . ' Min.');
                }
                return Html::tag('span', 'Standard', ['class' => 'light']);

            case 'bufferMinutes':
                if ($this->bufferMinutes !== null) {
                    return Html::encode($this->bufferMinutes . ' Min.');
                }
                return Html::tag('span', 'Standard', ['class' => 'light']);

            case 'maxCapacity':
                $suffix = $this->maxCapacity === 1 ? ' Person' : ' Personen';
                return Html::encode($this->maxCapacity . $suffix);
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booking/variations/edit/' . $this->id);
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
    public function canDuplicate(\craft\elements\User $user): bool
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
            [['description'], 'string'],
            [['slotDurationMinutes', 'bufferMinutes'], 'integer', 'min' => 0],
            [['maxCapacity'], 'integer', 'min' => 1],
            [['maxCapacity'], 'required'],
            [['isActive', 'allowQuantitySelection'], 'boolean'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = BookingVariationRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid variation ID: ' . $this->id);
            }
        } else {
            $record = new BookingVariationRecord();
            $record->id = (int)$this->id;
        }

        $record->description = $this->description;
        $record->slotDurationMinutes = $this->slotDurationMinutes;
        $record->bufferMinutes = $this->bufferMinutes;
        $record->maxCapacity = $this->maxCapacity;
        $record->allowQuantitySelection = $this->allowQuantitySelection;
        $record->isActive = $this->isActive;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // Delete the variation record
        $record = BookingVariationRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    /**
     * @inheritdoc
     */
    public function afterPropagate(bool $isNew): void
    {
        parent::afterPropagate($isNew);

        // If this is a duplicate, append "Kopie" to the title
        if ($isNew && $this->duplicateOf) {
            $this->title = $this->title . ' (Kopie)';
            Craft::$app->elements->saveElement($this, false);
        }
    }

    /**
     * Get remaining capacity for a specific time slot
     *
     * Calculates how many spots are still available by subtracting
     * the total booked quantity from the maximum capacity.
     *
     * All times are stored in Europe/Zurich timezone (no UTC conversion).
     *
     * @param string $date Date in Y-m-d format
     * @param string $startTime Time in H:i or H:i:s format
     * @param string $endTime Time in H:i or H:i:s format
     * @param int|null $excludeReservationId Optional reservation ID to exclude (for updates)
     * @return int Number of spots remaining (0 if fully booked)
     */
    public function getRemainingCapacity(
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeReservationId = null
    ): int {
        // Normalize times to H:i:s format
        $queryStartTime = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
        $queryEndTime = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;

        // Use a direct database query for more reliable capacity checking
        // Times are stored in Europe/Zurich format (no UTC conversion needed)
        $query = (new \craft\db\Query())
            ->select(['SUM([[quantity]])'])
            ->from(['{{%bookings_reservations}}'])
            ->where([
                'variationId' => $this->id,
                'bookingDate' => $date,
            ])
            ->andWhere(['in', 'status', ['confirmed', 'pending']])
            // Check for time overlap: (start < reqEnd) AND (end > reqStart)
            ->andWhere(['<', 'startTime', $queryEndTime])
            ->andWhere(['>', 'endTime', $queryStartTime]);

        // Exclude specific reservation if updating
        if ($excludeReservationId !== null) {
            $query->andWhere(['not', ['id' => $excludeReservationId]]);
        }

        // Get the sum (returns null if no results, so default to 0)
        $bookedQuantity = (int) $query->scalar();

        return max(0, $this->maxCapacity - $bookedQuantity);
    }

}
