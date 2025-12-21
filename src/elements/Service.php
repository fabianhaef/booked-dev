<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
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
 */
class Service extends Element
{
    public ?int $duration = null;
    public ?int $bufferBefore = null;
    public ?int $bufferAfter = null;
    public ?float $price = null;

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
        return true; // Support field layouts
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

