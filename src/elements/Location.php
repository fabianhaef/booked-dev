<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\LocationQuery;
use fabian\booked\records\LocationRecord;

use craft\elements\Address;
use craft\elements\db\AddressQuery;
use craft\elements\NestedElementManager;
use craft\enums\PropagationMethod;
use craft\elements\ElementCollection;

/**
 * Location Element
 *
 * @property string|null $timezone Location timezone
 * @property string|null $contactInfo Contact information
 * @property-read Address[]|null $addresses the location’s addresses
 */
class Location extends Element
{
    public ?string $timezone = null;
    public ?string $contactInfo = null;

    private ?NestedElementManager $_addressManager = null;
    private ?ElementCollection $_addresses = null;

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
    public function getAddressManager(): NestedElementManager
    {
        if ($this->_addressManager === null) {
            $this->_addressManager = new NestedElementManager(
                Address::class,
                fn() => $this->createAddressQuery(),
                [
                    'attribute' => 'addresses',
                    'propagationMethod' => PropagationMethod::None,
                ],
            );
        }

        return $this->_addressManager;
    }

    /**
     * Returns the location’s addresses.
     *
     * @return ElementCollection<Address>
     */
    public function getAddresses(): ElementCollection
    {
        if ($this->_addresses === null) {
            $this->_addresses = $this->createAddressQuery()
                ->collect();
        }

        return $this->_addresses;
    }

    /**
     * Sets the location’s addresses.
     *
     * @param Address[]|AddressQuery|ElementCollection|null $addresses
     */
    public function setAddresses(array|AddressQuery|ElementCollection|null $addresses): void
    {
        if ($addresses instanceof AddressQuery) {
            $this->_addresses = null;
        } elseif ($addresses instanceof ElementCollection) {
            $this->_addresses = $addresses;
        } elseif ($addresses === null) {
            $this->_addresses = ElementCollection::make();
        } else {
            $this->_addresses = ElementCollection::make($addresses);
        }
    }

    /**
     * Returns the primary address.
     *
     * @return Address|null
     */
    public function getPrimaryAddress(): ?Address
    {
        return $this->getAddresses()->first();
    }

    /**
     * Creates an address query.
     *
     * @return AddressQuery
     */
    private function createAddressQuery(): AddressQuery
    {
        return Address::find()
            ->owner($this)
            ->orderBy(['id' => SORT_ASC]);
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'address':
                $address = $this->getPrimaryAddress();
                if ($address) {
                    return Craft::$app->getAddresses()->formatAddress($address);
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
            [['timezone', 'contactInfo'], 'string'],
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

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterPropagate(bool $isNew): void
    {
        // Save nested addresses
        $this->getAddressManager()->maintainNestedElements($this, $isNew);

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

        // Delete nested addresses
        $this->getAddressManager()->deleteNestedElements($this);

        parent::afterDelete();
    }
}
