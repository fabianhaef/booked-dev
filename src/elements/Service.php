<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\actions\Delete;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use fabian\booked\elements\conditions\ServiceCondition;
use fabian\booked\elements\db\ServiceQuery;
use fabian\booked\records\ServiceRecord;

/**
 * Service Element
 *
 * Supports hierarchical structure for organizing services into categories/groups.
 * Supports custom field layouts for developer flexibility.
 *
 * @property int|null $duration Duration in minutes
 * @property int|null $bufferBefore Buffer time before service in minutes
 * @property int|null $bufferAfter Buffer time after service in minutes
 * @property float|null $price Service price
 * @property string|null $virtualMeetingProvider Virtual meeting provider (zoom, google, none)
 * @property int|null $minTimeBeforeBooking Minimum minutes before booking
 * @property int|null $minTimeBeforeCanceling Minimum minutes before canceling
 * @property string|null $finalStepUrl URL to redirect after booking
 */
class Service extends Element
{
    // Core service properties
    public ?int $duration = null;
    public ?int $bufferBefore = null;
    public ?int $bufferAfter = null;
    public ?float $price = null;
    public ?string $virtualMeetingProvider = null;

    // Booking configuration (null = use global defaults)
    public ?int $minTimeBeforeBooking = null;
    public ?int $minTimeBeforeCanceling = null;
    public ?string $finalStepUrl = null;

    /**
     * @var int|null The parent service ID (for hierarchical structure)
     */
    public ?int $parentId = null;

    /**
     * @var Service|null Cached parent service
     */
    private ?Service $_parent = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Service');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Services');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'service';
    }

    /**
     * @inheritdoc
     * @return ServiceCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ServiceCondition::class, [static::class]);
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
        return true;
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
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * Get the service structure ID from project config
     */
    public static function getStructureId(): ?int
    {
        return Craft::$app->getProjectConfig()->get('plugins.booked.serviceStructureId');
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
        return new ServiceQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        $structureId = static::getStructureId();

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Services'),
            ],
        ];

        // If structure exists, enable structure mode for drag-and-drop
        if ($structureId) {
            $sources[0]['structureId'] = $structureId;
            $sources[0]['structureEditable'] = true;
        } else {
            $sources[0]['defaultSort'] = ['title', 'asc'];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('app', 'Title')],
            'parent' => ['label' => Craft::t('booked', 'Parent')],
            'duration' => ['label' => Craft::t('booked', 'Duration')],
            'price' => ['label' => Craft::t('booked', 'Price')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'duration', 'price'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('app', 'Title'),
                'orderBy' => 'elements_sites.title',
                'attribute' => 'title',
            ],
            [
                'label' => Craft::t('booked', 'Duration'),
                'orderBy' => 'booked_services.duration',
                'attribute' => 'duration',
            ],
            [
                'label' => Craft::t('booked', 'Price'),
                'orderBy' => 'booked_services.price',
                'attribute' => 'price',
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
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'duration':
                if ($this->duration !== null) {
                    return Html::encode($this->duration . ' ' . Craft::t('booked', 'min'));
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'price':
                if ($this->price !== null) {
                    return Craft::$app->formatter->asCurrency($this->price);
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'parent':
                $parent = $this->getParent();
                if ($parent) {
                    return Html::encode($parent->title);
                }
                return Html::tag('span', '–', ['class' => 'light']);
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return sprintf('booked/services/%s', $this->getCanonicalId());
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
            [['duration', 'bufferBefore', 'bufferAfter'], 'integer', 'min' => 0],
            [['price'], 'number', 'min' => 0],
            [['virtualMeetingProvider'], 'string'],
            [['minTimeBeforeBooking', 'minTimeBeforeCanceling'], 'integer', 'min' => 0],
            [['finalStepUrl'], 'string', 'max' => 500],
            [['finalStepUrl'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Auto-generate slug from title if not provided
        if (!$this->slug && $this->title) {
            $this->slug = $this->generateSlugFromTitle($this->title);
        }

        // Set the structure ID for hierarchical support
        $structureId = static::getStructureId();
        if ($structureId) {
            $this->structureId = $structureId;
        }

        return parent::beforeSave($isNew);
    }

    /**
     * Generate a unique slug from the title
     *
     * @param string $title
     * @return string
     */
    private function generateSlugFromTitle(string $title): string
    {
        $slug = ElementHelper::generateSlug($title);
        
        // Ensure uniqueness
        $baseSlug = $slug;
        $increment = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $increment;
            $increment++;
        }
        
        return $slug;
    }

    /**
     * Check if a slug already exists
     *
     * @param string $slug
     * @return bool
     */
    private function slugExists(string $slug): bool
    {
        $query = static::find()
            ->slug($slug)
            ->siteId($this->siteId);
        
        if ($this->id) {
            $query->andWhere(['!=', 'elements.id', $this->id]);
        }
        
        return $query->exists();
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            // Always save to record table (including drafts) for proper element querying
            if (!$isNew) {
                $record = ServiceRecord::findOne($this->id);
                if (!$record) {
                    $record = new ServiceRecord();
                    $record->id = (int)$this->id;
                }
            } else {
                $record = new ServiceRecord();
                $record->id = (int)$this->id;
            }

            $record->duration = $this->duration;
            $record->bufferBefore = $this->bufferBefore;
            $record->bufferAfter = $this->bufferAfter;
            $record->price = $this->price;
            $record->virtualMeetingProvider = $this->virtualMeetingProvider;
            $record->minTimeBeforeBooking = $this->minTimeBeforeBooking;
            $record->minTimeBeforeCanceling = $this->minTimeBeforeCanceling;
            $record->finalStepUrl = $this->finalStepUrl;

            $record->save(false);

            // Handle structure positioning for hierarchy (only for new non-draft elements)
            $structureId = static::getStructureId();
            if ($structureId && $isNew) {
                $structuresService = Craft::$app->getStructures();

                if ($this->parentId) {
                    $parent = self::find()->id($this->parentId)->siteId('*')->one();
                    if ($parent) {
                        $structuresService->append($structureId, $this, $parent);
                    } else {
                        $structuresService->appendToRoot($structureId, $this);
                    }
                } else {
                    $structuresService->appendToRoot($structureId, $this);
                }
            }
        }

        parent::afterSave($isNew);
    }

    /**
     * Get the parent service
     */
    public function getParent(): ?Service
    {
        if ($this->_parent !== null) {
            return $this->_parent;
        }

        if ($this->parentId === null) {
            return null;
        }

        $this->_parent = self::find()->id($this->parentId)->siteId('*')->one();
        return $this->_parent;
    }

    /**
     * Set the parent service
     */
    public function setParent(?ElementInterface $parent): void
    {
        $this->_parent = $parent instanceof Service ? $parent : null;
        $this->parentId = $parent?->id;
    }

    /**
     * Get child services
     * @return ElementQueryInterface|\craft\elements\ElementCollection
     */
    public function getChildren(): ElementQueryInterface|\craft\elements\ElementCollection
    {
        $structureId = static::getStructureId();
        if (!$structureId) {
            return self::find()->id(null); // Return empty query
        }

        return self::find()
            ->structureId($structureId)
            ->descendantOf($this)
            ->descendantDist(1); // Direct children only
    }

    /**
     * Check if this service has children
     */
    public function hasChildren(): bool
    {
        return $this->getChildren()->exists();
    }

    /**
     * Check if this is a root-level service (no parent)
     */
    public function isRoot(): bool
    {
        return $this->parentId === null && ($this->level ?? 1) === 1;
    }

    /**
     * @inheritdoc
     */
    public function afterMoveInStructure(int $structureId): void
    {
        // Update parentId based on new structure position
        $parent = $this->getParentUri() ? self::find()
            ->structureId($structureId)
            ->ancestorOf($this)
            ->ancestorDist(1)
            ->siteId($this->siteId)
            ->status(null)
            ->one() : null;

        $this->parentId = $parent?->id;
        $this->_parent = $parent;

        parent::afterMoveInStructure($structureId);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        $record = ServiceRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }
}

