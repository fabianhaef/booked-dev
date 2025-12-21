<?php

namespace fabian\booked\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use fabian\booked\elements\db\EmployeeQuery;
use fabian\booked\records\EmployeeRecord;

/**
 * Employee Element
 *
 * @property int|null $userId Foreign key to User element
 * @property int|null $locationId Foreign key to Location element
 * @property string|null $bio Employee biography
 * @property string|null $specialties Employee specialties
 */
class Employee extends Element
{
    public ?int $userId = null;
    public ?int $locationId = null;
    public ?string $bio = null;
    public ?string $specialties = null;

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
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['userId', 'locationId'], 'integer'],
            [['bio', 'specialties'], 'string'],
        ]);
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
        $record->bio = $this->bio;
        $record->specialties = $this->specialties;

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
        if ($this->_location === null && $this->locationId) {
            $this->_location = Location::findOne($this->locationId);
        }
        return $this->_location;
    }
}

