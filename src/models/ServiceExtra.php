<?php

namespace fabian\booked\models;

use craft\base\Model;
use fabian\booked\records\ServiceExtraRecord;

/**
 * ServiceExtra Model
 *
 * Represents a service extra/add-on that can be added to bookings.
 * Examples:
 * - Extended session time (+30 minutes)
 * - Premium products upgrade
 * - Refreshments package
 * - Take-home kit
 * - Priority scheduling
 *
 * @property int|null $id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property int $duration Additional duration in minutes
 * @property int $maxQuantity Maximum quantity allowed per booking
 * @property bool $isRequired Whether this extra must be selected
 * @property int $sortOrder Display order
 * @property bool $enabled Whether this extra is currently available
 */
class ServiceExtra extends Model
{
    public ?int $id = null;
    public string $name = '';
    public ?string $description = null;
    public float $price = 0.0;
    public int $duration = 0;
    public int $maxQuantity = 1;
    public bool $isRequired = false;
    public int $sortOrder = 0;
    public bool $enabled = true;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['price'], 'number', 'min' => 0],
            [['duration'], 'integer', 'min' => 0],
            [['maxQuantity'], 'integer', 'min' => 1],
            [['isRequired', 'enabled'], 'boolean'],
            [['sortOrder'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'name' => 'Name',
            'description' => 'Description',
            'price' => 'Price',
            'duration' => 'Additional Duration (minutes)',
            'maxQuantity' => 'Max Quantity Per Booking',
            'isRequired' => 'Required',
            'sortOrder' => 'Sort Order',
            'enabled' => 'Enabled',
        ];
    }

    /**
     * Save the service extra
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $record = $this->id ? ServiceExtraRecord::findOne($this->id) : new ServiceExtraRecord();

        if (!$record) {
            return false;
        }

        $record->name = $this->name;
        $record->description = $this->description;
        $record->price = $this->price;
        $record->duration = $this->duration;
        $record->maxQuantity = $this->maxQuantity;
        $record->isRequired = $this->isRequired;
        $record->sortOrder = $this->sortOrder;
        $record->enabled = $this->enabled;

        if ($record->save()) {
            $this->id = $record->id;
            return true;
        }

        return false;
    }

    /**
     * Get total price for a given quantity
     */
    public function getTotalPrice(int $quantity = 1): float
    {
        return $this->price * min($quantity, $this->maxQuantity);
    }

    /**
     * Get total additional duration for a given quantity
     */
    public function getTotalDuration(int $quantity = 1): int
    {
        return $this->duration * min($quantity, $this->maxQuantity);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice(): string
    {
        return \Craft::$app->formatter->asCurrency($this->price);
    }

    /**
     * Check if quantity is valid
     */
    public function isValidQuantity(int $quantity): bool
    {
        return $quantity >= 1 && $quantity <= $this->maxQuantity;
    }

    /**
     * Create from record
     */
    public static function fromRecord(ServiceExtraRecord $record): self
    {
        $model = new self();
        $model->id = $record->id;
        $model->name = $record->name;
        $model->description = $record->description;
        $model->price = (float)$record->price;
        $model->duration = $record->duration;
        $model->maxQuantity = $record->maxQuantity;
        $model->isRequired = (bool)$record->isRequired;
        $model->sortOrder = $record->sortOrder;
        $model->enabled = (bool)$record->enabled;

        return $model;
    }
}
