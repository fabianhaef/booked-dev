<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\elements\Address;
use craft\elements\NestedElementManager;
use fabian\booked\elements\db\LocationQuery;
use fabian\booked\records\LocationRecord;

/**
 * Location Element
 *
 * @property string|null $timezone Location timezone
 * @property string|null $contactInfo Contact information
 * @property string|null $addressLine1
 * @property string|null $addressLine2
 * @property string|null $locality
 * @property string|null $administrativeArea
 * @property string|null $postalCode
 * @property string|null $countryCode
 */
class Location extends Element
{
    public ?string $timezone = null;
    public ?string $contactInfo = null;
    public ?string $addressLine1 = null;
    public ?string $addressLine2 = null;
    public ?string $locality = null;
    public ?string $administrativeArea = null;
    public ?string $postalCode = null;
    public ?string $countryCode = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Location');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Locations');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'location';
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
        return new LocationQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Locations'),
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
            'address' => ['label' => Craft::t('app', 'Address')],
            'timezone' => ['label' => Craft::t('booked', 'Timezone')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'address', 'timezone'];
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
                'label' => Craft::t('booked', 'Timezone'),
                'orderBy' => 'booked_locations.timezone',
                'attribute' => 'timezone',
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
            case 'address':
                if ($this->addressLine1) {
                    $parts = array_filter([
                        $this->addressLine1,
                        $this->addressLine2,
                        $this->locality,
                        $this->administrativeArea,
                        $this->postalCode,
                        $this->countryCode
                    ]);
                    return Html::encode(implode(', ', $parts));
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'timezone':
                return $this->timezone ? Html::encode($this->timezone) : Html::tag('span', '–', ['class' => 'light']);
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('booked/locations/' . $this->id);
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
            [['timezone', 'contactInfo', 'addressLine1', 'addressLine2', 'locality', 'administrativeArea', 'postalCode', 'countryCode'], 'string'],
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
     * Returns the address manager for this location.
     *
     * @return NestedElementManager
     */
    public function getAddressManager(): NestedElementManager
    {
        return new NestedElementManager(
            Address::class,
            fn() => Address::find()->ownerId($this->id),
            [
                'attribute' => 'addresses',
            ]
        );
    }

    /**
     * Returns the addresses for this location.
     *
     * @return \craft\elements\ElementCollection
     */
    public function getAddresses(): \craft\elements\ElementCollection
    {
        return $this->getAddressManager()->getElements();
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = LocationRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid location ID: ' . $this->id);
            }
        } else {
            $record = new LocationRecord();
            $record->id = (int)$this->id;
        }

        $record->timezone = $this->timezone;
        $record->contactInfo = $this->contactInfo;
        $record->addressLine1 = $this->addressLine1;
        $record->addressLine2 = $this->addressLine2;
        $record->locality = $this->locality;
        $record->administrativeArea = $this->administrativeArea;
        $record->postalCode = $this->postalCode;
        $record->countryCode = $this->countryCode;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterPropagate(bool $isNew): void
    {
        parent::afterPropagate($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        $record = LocationRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }
}
