<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
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
 * @property string|null $description
 */
class ServiceExtra extends Element
{
    public float $price = 0.0;
    public int $duration = 0;
    public int $maxQuantity = 1;
    public bool $isRequired = false;
    public ?string $description = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Add-On');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Add-Ons');
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
        return new ServiceExtraQuery(static::class);
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
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Add-Ons'),
                'defaultSort' => ['title', 'asc'],
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
            'price' => ['label' => Craft::t('booked', 'Price')],
            'duration' => ['label' => Craft::t('booked', 'Duration')],
            'maxQuantity' => ['label' => Craft::t('booked', 'Max Qty')],
            'isRequired' => ['label' => Craft::t('booked', 'Required')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['price', 'duration', 'maxQuantity', 'isRequired'];
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
            // Title is now stored in content table (handled by parent)
            // Enabled is now stored in elements table (handled by parent)
            $record->price = $this->price;
            $record->duration = $this->duration;
            $record->maxQuantity = $this->maxQuantity;
            $record->isRequired = $this->isRequired;
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
