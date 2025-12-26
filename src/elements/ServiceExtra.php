<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\ServiceExtraQuery;
use fabian\booked\records\ServiceExtraRecord;

/**
 * ServiceExtra Element
 *
 * Represents a service extra/add-on that can be added to bookings.
 * Examples:
 * - Extended session time (+30 minutes)
 * - Premium products upgrade
 * - Refreshments package
 * - Take-home kit
 * - Priority scheduling
 *
 * @property float $price
 * @property int $duration Additional duration in minutes
 * @property int $maxQuantity Maximum quantity allowed per booking
 * @property bool $isRequired Whether this extra must be selected
 * @property int $sortOrder Display order
 * @property string|null $description
 */
class ServiceExtra extends Element
{
    public float $price = 0.0;
    public int $duration = 0;
    public int $maxQuantity = 1;
    public bool $isRequired = false;
    public int $sortOrder = 0;
    public ?string $description = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Service Extra');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Service Extras');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'serviceExtra';
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
        return false; // Service extras don't have public URLs
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
            'enabled' => Craft::t('booked', 'Enabled'),
            'disabled' => Craft::t('booked', 'Disabled'),
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
        return new ServiceExtraQuery(static::class);
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
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Extras'),
                'defaultSort' => ['sortOrder', 'asc'],
            ],
            [
                'heading' => Craft::t('booked', 'Status'),
            ],
            [
                'key' => 'enabled',
                'label' => Craft::t('booked', 'Enabled'),
                'criteria' => ['status' => 'enabled'],
            ],
            [
                'key' => 'disabled',
                'label' => Craft::t('booked', 'Disabled'),
                'criteria' => ['status' => 'disabled'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('app', 'Name')],
            'price' => ['label' => Craft::t('booked', 'Price')],
            'duration' => ['label' => Craft::t('booked', 'Duration')],
            'maxQuantity' => ['label' => Craft::t('booked', 'Max Qty')],
            'isRequired' => ['label' => Craft::t('booked', 'Required')],
            'sortOrder' => ['label' => Craft::t('booked', 'Sort Order')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'price', 'duration', 'maxQuantity', 'isRequired', 'sortOrder'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'description'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Name'),
            'sortOrder' => Craft::t('booked', 'Sort Order'),
            'price' => Craft::t('booked', 'Price'),
            'duration' => Craft::t('booked', 'Duration'),
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
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['price'], 'number', 'min' => 0],
            [['duration'], 'integer', 'min' => 0],
            [['maxQuantity'], 'integer', 'min' => 1],
            [['isRequired'], 'boolean'],
            [['sortOrder'], 'integer'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new ServiceExtraRecord();
            $record->id = $this->id;
        } else {
            $record = ServiceExtraRecord::findOne($this->id);
        }

        if ($record) {
            $record->price = $this->price;
            $record->duration = $this->duration;
            $record->maxQuantity = $this->maxQuantity;
            $record->isRequired = $this->isRequired;
            $record->sortOrder = $this->sortOrder;
            $record->description = $this->description;
            $record->save(false);
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        return null;
    }

    /**
     * Get the CP edit URL
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booked/service-extras/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'price':
                return Craft::$app->formatter->asCurrency($this->price);
            case 'duration':
                return $this->duration > 0 ? "+{$this->duration} min" : '-';
            case 'isRequired':
                return $this->isRequired ? '<span class="status green"></span>' : '';
        }

        return parent::tableAttributeHtml($attribute);
    }
}
