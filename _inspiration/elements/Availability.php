<?php

namespace modules\booking\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use modules\booking\elements\db\AvailabilityQuery;
use modules\booking\records\AvailabilityRecord;
use modules\booking\records\EventDateRecord;
use modules\booking\models\EventDate;

/**
 * Availability Element
 *
 * @property int|null $dayOfWeek
 * @property string|null $startTime
 * @property string|null $endTime
 * @property bool $isActive
 * @property string $availabilityType
 * @property string|null $description
 * @property string $sourceType
 * @property int|null $sourceId
 * @property string|null $sourceHandle
 */
class Availability extends Element
{
    public ?int $dayOfWeek = null;
    public ?string $startTime = null;
    public ?string $endTime = null;
    public bool $isActive = true;
    public string $availabilityType = 'recurring';
    public ?string $description = null;
    public string $sourceType = 'section';
    public ?int $sourceId = null;
    public ?string $sourceHandle = null;

    private array $_eventDates = [];
    private ?array $_variations = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Verfügbarkeit';
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return 'Verfügbarkeiten';
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'availability';
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
        return new AvailabilityQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => 'Alle Verfügbarkeiten',
                'defaultSort' => ['dateCreated', 'desc'],
                'type' => 'native',
            ],
            [
                'heading' => 'Typ',
            ],
            [
                'key' => 'recurring',
                'label' => 'Wiederkehrend',
                'criteria' => ['availabilityType' => 'recurring'],
                'defaultSort' => ['dayOfWeek', 'asc'],
                'type' => 'native',
            ],
            [
                'key' => 'event',
                'label' => 'Event',
                'criteria' => ['availabilityType' => 'event'],
                'defaultSort' => ['dateCreated', 'desc'],
                'type' => 'native',
            ],
            [
                'heading' => 'Status',
            ],
            [
                'key' => 'active',
                'label' => 'Aktiv',
                'criteria' => ['isActive' => true],
                'defaultSort' => ['dateCreated', 'desc'],
                'type' => 'native',
            ],
            [
                'key' => 'inactive',
                'label' => 'Inaktiv',
                'criteria' => ['isActive' => false],
                'defaultSort' => ['dateCreated', 'desc'],
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
            'title' => ['label' => 'Titel'],
            'availabilityType' => ['label' => 'Typ'],
            'dayOrDate' => ['label' => 'Tag/Datum'],
            'timeRange' => ['label' => 'Zeitbereich'],
            'variations' => ['label' => 'Varianten'],
            'description' => ['label' => 'Beschreibung'],
            'sourceName' => ['label' => 'Quelle'],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['id', 'title', 'availabilityType', 'dayOrDate', 'timeRange', 'variations'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => 'Typ',
                'orderBy' => 'bookings_availability.availabilityType',
                'attribute' => 'availabilityType',
            ],
            [
                'label' => 'Tag',
                'orderBy' => 'bookings_availability.dayOfWeek',
                'attribute' => 'dayOfWeek',
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
                // Make ID clickable to edit the availability
                if ($this->getCpEditUrl()) {
                    return Html::a('#' . $this->id, $this->getCpEditUrl());
                }
                return '#' . $this->id;

            case 'availabilityType':
                return Html::tag('span', $this->availabilityType === 'event' ? 'Event' : 'Wiederkehrend', ['class' => 'light']);

            case 'dayOrDate':
                if ($this->availabilityType === 'event') {
                    $eventDates = $this->getEventDates();
                    if (count($eventDates) > 0) {
                        $html = Html::tag('strong', count($eventDates) . ' Termin' . (count($eventDates) != 1 ? 'e' : ''));
                        $dateList = [];
                        foreach (array_slice($eventDates, 0, 3) as $eventDate) {
                            $dateList[] = $eventDate->getFormattedDate();
                        }
                        $html .= Html::tag('br') . Html::tag('span', implode(', ', $dateList), ['class' => 'light']);
                        if (count($eventDates) > 3) {
                            $html .= Html::tag('span', '...', ['class' => 'light']);
                        }
                        return $html;
                    }
                    return Html::tag('span', 'Keine Termine', ['class' => 'light']);
                }
                return Html::tag('strong', $this->getDayName());

            case 'timeRange':
                if ($this->availabilityType === 'event') {
                    $eventDates = $this->getEventDates();
                    if (count($eventDates) > 0) {
                        $html = '';
                        foreach (array_slice($eventDates, 0, 2) as $i => $eventDate) {
                            if ($i > 0) $html .= Html::tag('br');
                            $html .= Html::tag('span', $eventDate->getFormattedTimeRange(), ['class' => 'light']);
                        }
                        if (count($eventDates) > 2) {
                            $html .= Html::tag('br') . Html::tag('span', '+' . (count($eventDates) - 2) . ' weitere', ['class' => 'light']);
                        }
                        return $html;
                    }
                    return '–';
                }
                return Html::encode($this->getFormattedTimeRange());

            case 'variations':
                $variations = $this->getVariations();
                if (empty($variations)) {
                    return Html::tag('span', 'Keine Varianten', ['class' => 'light']);
                }
                $html = Html::tag('strong', count($variations) . ' Variante' . (count($variations) != 1 ? 'n' : ''));
                $nameList = [];
                foreach (array_slice($variations, 0, 2) as $variation) {
                    $nameList[] = $variation->title;
                }
                $html .= Html::tag('br') . Html::tag('span', implode(', ', $nameList), ['class' => 'light']);
                if (count($variations) > 2) {
                    $html .= Html::tag('span', '...', ['class' => 'light']);
                }
                return $html;

            case 'description':
                return $this->description ? Html::encode($this->description) : Html::tag('span', '–', ['class' => 'light']);

            case 'sourceName':
                $sourceName = $this->getSourceName();
                $sourceTypeLabel = $this->sourceType == 'entry' ? 'Eintrag' : 'Sektion';
                return Html::tag('div',
                    Html::tag('strong', Html::encode($sourceName)) .
                    Html::tag('br') .
                    Html::tag('span', Html::encode($sourceTypeLabel), ['class' => 'light', 'style' => 'font-size: 11px;'])
                );
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booking/availability/edit/' . $this->id);
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
            [['sourceType', 'availabilityType'], 'required'],
            [['dayOfWeek', 'sourceId'], 'integer'],
            [['dayOfWeek'], 'integer', 'min' => 0, 'max' => 6],
            [['startTime', 'endTime'], 'match', 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/'],
            [['isActive'], 'boolean'],
            [['availabilityType'], 'in', 'range' => ['recurring', 'event']],
            [['sourceType'], 'in', 'range' => ['entry', 'section']],
            [['sourceHandle'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['dayOfWeek', 'startTime', 'endTime'], 'required', 'when' => function($model) {
                return $model->availabilityType === 'recurring';
            }],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = AvailabilityRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid availability ID: ' . $this->id);
            }
        } else {
            $record = new AvailabilityRecord();
            $record->id = (int)$this->id;
        }

        $record->dayOfWeek = $this->dayOfWeek;
        $record->startTime = $this->startTime;
        $record->endTime = $this->endTime;
        $record->isActive = $this->isActive;
        $record->availabilityType = $this->availabilityType;
        $record->description = $this->description;
        $record->sourceType = $this->sourceType;
        $record->sourceId = $this->sourceId;
        $record->sourceHandle = $this->sourceHandle;

        $record->save(false);

        // Save event dates if this is an event-type availability
        if ($this->availabilityType === 'event' && !empty($this->_eventDates)) {
            EventDateRecord::deleteAll(['availabilityId' => $this->id]);

            foreach ($this->_eventDates as $eventDate) {
                $eventDate->availabilityId = $this->id;
                $eventDate->save();
            }
        }

        // Save variations
        $this->saveVariations();

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        // Delete event dates first
        EventDateRecord::deleteAll(['availabilityId' => $this->id]);

        // Delete variation relationships
        Craft::$app->db->createCommand()
            ->delete('{{%bookings_availability_variations}}', ['availabilityId' => $this->id])
            ->execute();

        // Delete the availability record
        $record = AvailabilityRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    // === Business Logic Methods ===

    /**
     * Get event dates
     */
    public function getEventDates(): array
    {
        if ($this->availabilityType !== 'event' || !$this->id) {
            return [];
        }

        if (empty($this->_eventDates)) {
            $records = EventDateRecord::find()
                ->where(['availabilityId' => $this->id])
                ->orderBy(['eventDate' => SORT_ASC, 'startTime' => SORT_ASC])
                ->all();

            foreach ($records as $record) {
                $this->_eventDates[] = EventDate::fromRecord($record);
            }
        }

        return $this->_eventDates;
    }

    /**
     * Set event dates
     */
    public function setEventDates(array $eventDates): void
    {
        $this->_eventDates = $eventDates;
    }

    /**
     * Get day name from dayOfWeek number
     */
    public function getDayName(): string
    {
        $days = [
            0 => 'Sonntag',
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag'
        ];

        return $days[$this->dayOfWeek] ?? 'Unbekannt';
    }

    /**
     * Get all available days (starting with Monday)
     */
    public static function getDays(): array
    {
        return [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            0 => 'Sonntag',
        ];
    }

    /**
     * Get source name (entry title or section name)
     */
    public function getSourceName(): string
    {
        if ($this->sourceType === 'entry' && $this->sourceId) {
            $entry = Craft::$app->entries->getEntryById($this->sourceId);
            return $entry ? $entry->title : 'Entry #' . $this->sourceId;
        }

        if ($this->sourceType === 'section' && $this->sourceHandle) {
            $section = Craft::$app->sections->getSectionByHandle($this->sourceHandle);
            return $section ? $section->name : $this->sourceHandle;
        }

        if ($this->sourceType === 'section' && $this->sourceId) {
            $section = Craft::$app->sections->getSectionById($this->sourceId);
            return $section ? $section->name : 'Section #' . $this->sourceId;
        }

        return 'Unknown';
    }

    /**
     * Get formatted time range
     */
    public function getFormattedTimeRange(): string
    {
        if (empty($this->startTime) || empty($this->endTime)) {
            return '';
        }

        $start = \DateTime::createFromFormat('H:i:s', $this->startTime)
            ?: \DateTime::createFromFormat('H:i', $this->startTime);
        $end = \DateTime::createFromFormat('H:i:s', $this->endTime)
            ?: \DateTime::createFromFormat('H:i', $this->endTime);

        if (!$start || !$end) {
            return $this->startTime . ' - ' . $this->endTime;
        }

        return $start->format('H:i') . ' - ' . $end->format('H:i');
    }

    /**
     * Get related variations
     *
     * @return BookingVariation[]
     */
    public function getVariations(): array
    {
        if ($this->_variations === null) {
            if (!$this->id) {
                $this->_variations = [];
            } else {
                // Load variations from junction table
                $variationIds = (new \craft\db\Query())
                    ->select(['variationId'])
                    ->from('{{%bookings_availability_variations}}')
                    ->where(['availabilityId' => $this->id])
                    ->column();

                if (empty($variationIds)) {
                    $this->_variations = [];
                } else {
                    $this->_variations = BookingVariation::find()
                        ->id($variationIds)
                        ->all();
                }
            }
        }

        return $this->_variations;
    }

    /**
     * Set variations (expects array of variation IDs)
     *
     * @param array $variationIds Array of variation IDs
     */
    public function setVariationIds(array $variationIds): void
    {
        // Load the actual variation elements
        if (empty($variationIds)) {
            $this->_variations = [];
        } else {
            $this->_variations = BookingVariation::find()
                ->id($variationIds)
                ->all();
        }
    }

    /**
     * Get variation IDs
     *
     * @return int[]
     */
    public function getVariationIds(): array
    {
        $variations = $this->getVariations();
        return array_map(fn($v) => $v->id, $variations);
    }

    /**
     * Save variations to junction table
     */
    protected function saveVariations(): void
    {
        if ($this->_variations === null) {
            return;
        }

        // Delete existing relationships
        Craft::$app->db->createCommand()
            ->delete('{{%bookings_availability_variations}}', ['availabilityId' => $this->id])
            ->execute();

        // Insert new relationships
        foreach ($this->_variations as $variation) {
            Craft::$app->db->createCommand()
                ->insert('{{%bookings_availability_variations}}', [
                    'availabilityId' => $this->id,
                    'variationId' => $variation->id,
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'dateUpdated' => date('Y-m-d H:i:s'),
                    'uid' => \craft\helpers\StringHelper::UUID(),
                ])
                ->execute();
        }
    }
}
