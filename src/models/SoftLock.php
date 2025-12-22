<?php

namespace fabian\booked\models;

use craft\base\Model;

/**
 * SoftLock Model
 */
class SoftLock extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null
     */
    public ?string $token = null;

    /**
     * @var int|null
     */
    public ?int $serviceId = null;

    /**
     * @var int|null
     */
    public ?int $employeeId = null;

    /**
     * @var int|null
     */
    public ?int $locationId = null;

    /**
     * @var string|null
     */
    public ?string $date = null;

    /**
     * @var string|null
     */
    public ?string $startTime = null;

    /**
     * @var string|null
     */
    public ?string $endTime = null;

    /**
     * @var string|null
     */
    public ?string $expiresAt = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['token', 'serviceId', 'date', 'startTime', 'endTime', 'expiresAt'], 'required'],
            [['id', 'serviceId', 'employeeId', 'locationId'], 'integer'],
            [['token', 'date', 'startTime', 'endTime'], 'string'],
            [['expiresAt'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
        ];
    }
}

