<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\ServiceQuery;
use fabian\booked\records\ServiceRecord;

/**
 * Service Element
 *
 * @property int|null $duration Duration in minutes
 * @property int|null $bufferBefore Buffer time before service in minutes
 * @property int|null $bufferAfter Buffer time after service in minutes
 * @property float|null $price Service price
 * @property string|null $virtualMeetingProvider Virtual meeting provider (zoom, google, none)
 */
class Service extends Element
{
    public ?int $duration = null;
    public ?int $bufferBefore = null;
    public ?int $bufferAfter = null;
    public ?float $price = null;
    public ?string $virtualMeetingProvider = null;

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
        return false; // No field layouts
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        return null;
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
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Services'),
                'defaultSort' => ['title', 'asc'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('app', 'Title')],
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
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booked/services/' . $this->id);
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
        if (!$isNew) {
            $record = ServiceRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid service ID: ' . $this->id);
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

        $record->save(false);

        parent::afterSave($isNew);
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

