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
    /**
     * @var bool|null Whether to only return enabled services
     */
    public $enabled;

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
     * Narrows the query results to only enabled services
     *
     * @param bool|null $value The property value
     * @return static self reference
     */
    public function enabled($value = true): static
    {
        $this->enabled = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // Call parent first to initialize the base query
        if (!parent::beforePrepare()) {
            return false;
        }

        $this->joinElementTable('booked_services');

        $this->query->addSelect([
            'booked_services.duration',
            'booked_services.bufferBefore',
            'booked_services.bufferAfter',
            'booked_services.price',
            'booked_services.virtualMeetingProvider',
        ]);

        if ($this->duration !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_services.duration', $this->duration));
        }

        if ($this->price !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_services.price', $this->price));
        }

        // Handle the 'enabled' parameter
        if ($this->enabled !== null) {
            $this->subQuery->andWhere(Db::parseParam('elements.enabled', (int)$this->enabled));
        }

        return true;
    }
}

