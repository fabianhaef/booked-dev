<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * ServiceExtraQuery defines the condition builder for ServiceExtra elements
 */
class ServiceExtraQuery extends ElementQuery
{
    public ?float $price = null;
    public ?int $duration = null;
    public ?int $maxQuantity = null;
    public ?bool $isRequired = null;
    public ?int $sortOrder = null;

    public function price(?float $value): static
    {
        $this->price = $value;
        return $this;
    }

    public function duration(?int $value): static
    {
        $this->duration = $value;
        return $this;
    }

    public function maxQuantity(?int $value): static
    {
        $this->maxQuantity = $value;
        return $this;
    }

    public function isRequired(?bool $value): static
    {
        $this->isRequired = $value;
        return $this;
    }

    public function sortOrder(?int $value): static
    {
        $this->sortOrder = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('booked_service_extras');

        $this->query->addSelect([
            'booked_service_extras.price',
            'booked_service_extras.duration',
            'booked_service_extras.maxQuantity',
            'booked_service_extras.isRequired',
            'booked_service_extras.sortOrder',
            'booked_service_extras.description',
        ]);

        if ($this->price !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_service_extras.price', $this->price));
        }

        if ($this->duration !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_service_extras.duration', $this->duration));
        }

        if ($this->maxQuantity !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_service_extras.maxQuantity', $this->maxQuantity));
        }

        if ($this->isRequired !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_service_extras.isRequired', $this->isRequired));
        }

        if ($this->sortOrder !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_service_extras.sortOrder', $this->sortOrder));
        }

        return parent::beforePrepare();
    }
}
