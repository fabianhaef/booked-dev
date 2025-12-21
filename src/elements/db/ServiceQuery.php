<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * ServiceQuery defines the condition builder for Service elements
 *
 * @method \fabian\booked\elements\Service[]|array all($db = null)
 * @method \fabian\booked\elements\Service|array|null one($db = null)
 * @method \fabian\booked\elements\Service|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class ServiceQuery extends ElementQuery
{
    public ?int $duration = null;
    public ?float $price = null;

    /**
     * Filter by duration
     */
    public function duration(?int $value): static
    {
        $this->duration = $value;
        return $this;
    }

    /**
     * Filter by price
     */
    public function price(?float $value): static
    {
        $this->price = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('booked_services');

        $this->query->select([
            'booked_services.duration',
            'booked_services.bufferBefore',
            'booked_services.bufferAfter',
            'booked_services.price',
        ]);

        if ($this->duration !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_services.duration', $this->duration));
        }

        if ($this->price !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_services.price', $this->price));
        }

        return parent::beforePrepare();
    }
}

