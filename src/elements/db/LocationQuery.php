<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * LocationQuery defines the condition builder for Location elements
 *
 * @method \fabian\booked\elements\Location[]|array all($db = null)
 * @method \fabian\booked\elements\Location|array|null one($db = null)
 * @method \fabian\booked\elements\Location|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class LocationQuery extends ElementQuery
{
    public ?string $timezone = null;

    /**
     * Filter by timezone
     */
    public function timezone(?string $value): static
    {
        $this->timezone = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('booked_locations');

        $this->query->select([
            'booked_locations.address',
            'booked_locations.timezone',
            'booked_locations.contactInfo',
        ]);

        if ($this->timezone !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_locations.timezone', $this->timezone));
        }

        return parent::beforePrepare();
    }
}

