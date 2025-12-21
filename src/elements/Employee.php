<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\EmployeeQuery;
use fabian\booked\elements\Location;
use fabian\booked\records\EmployeeRecord;

/**
 * Employee Element
 *
 * @property int|null $userId Foreign key to User element
 * @property int|null $locationId Foreign key to Location element
 */
class Employee extends Element
{
    public ?int $userId = null;
    public ?int $locationId = null;

    private ?User $_user = null;
    private ?Location $_location = null;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('booked', 'Employee');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'Employees');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'employee';
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
    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        // Get field layout from plugin settings
        $settings = \fabian\booked\models\Settings::loadSettings();
        return $settings->getEmployeeFieldLayout() ?? parent::getFieldLayout();
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
        return new EmployeeQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('booked', 'All Employees'),
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
            'user' => ['label' => Craft::t('booked', 'User')],
            'location' => ['label' => Craft::t('booked', 'Location')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'user', 'location'];
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
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'user') {
            // Get all user IDs
            $userIds = array_filter(array_map(fn($element) => $element->userId, $sourceElements));
            
            if (empty($userIds)) {
                return [];
            }

            // Load all users
            $users = \craft\elements\User::find()
                ->id($userIds)
                ->indexBy('id')
                ->all();

            // Map elements to their users
            $map = [];
            foreach ($sourceElements as $element) {
                $map[$element->id] = $users[$element->userId] ?? null;
            }

            return $map;
        }

        if ($handle === 'location') {
            // Get all location IDs
            $locationIds = array_filter(array_map(fn($element) => $element->locationId, $sourceElements));
            
            if (empty($locationIds)) {
                return [];
            }

            // Load all locations
            $locations = Location::find()
                ->id($locationIds)
                ->indexBy('id')
                ->all();

            // Map elements to their locations
            $map = [];
            foreach ($sourceElements as $element) {
                $map[$element->id] = $locations[$element->locationId] ?? null;
            }

            return $map;
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'user':
                $user = $this->getUser();
                if ($user) {
                    return Html::encode($user->getName());
                }
                return Html::tag('span', '–', ['class' => 'light']);

            case 'location':
                $location = $this->getLocation();
                if ($location) {
                    return Html::encode($location->title);
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
        return UrlHelper::cpUrl('booked/employees/' . $this->id);
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
            [['userId', 'locationId'], 'integer'],
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
            $record = EmployeeRecord::findOne($this->id);
            if (!$record) {
                throw new \Exception('Invalid employee ID: ' . $this->id);
            }
        } else {
            $record = new EmployeeRecord();
            $record->id = (int)$this->id;
        }

        $record->userId = $this->userId;
        $record->locationId = $this->locationId;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): void
    {
        $record = EmployeeRecord::findOne($this->id);
        if ($record) {
            $record->delete();
        }

        parent::afterDelete();
    }

    /**
     * Get the associated User element
     */
    public function getUser(): ?User
    {
        // Check if eager loaded
        $eagerLoaded = $this->getEagerLoadedElements('user');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_user === null && $this->userId) {
            $this->_user = Craft::$app->users->getUserById($this->userId);
        }
        return $this->_user;
    }

    /**
     * Get the associated Location element
     */
    public function getLocation(): ?Location
    {
        // Check if eager loaded
        $eagerLoaded = $this->getEagerLoadedElements('location');
        if ($eagerLoaded !== null) {
            return $eagerLoaded;
        }

        if ($this->_location === null && $this->locationId) {
            $this->_location = Location::findOne($this->locationId);
        }
        return $this->_location;
    }
}

