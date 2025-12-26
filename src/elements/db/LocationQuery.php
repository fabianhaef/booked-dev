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
    /**
     * @var bool|null Whether to only return enabled locations
     */
    public $enabled;

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
     * Narrows the query results to only enabled locations
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

        $this->joinElementTable('booked_locations');

        $this->query->addSelect([
            'booked_locations.timezone',
            'booked_locations.addressLine1',
            'booked_locations.addressLine2',
            'booked_locations.locality',
            'booked_locations.administrativeArea',
            'booked_locations.postalCode',
            'booked_locations.countryCode',
        ]);

        if ($this->timezone !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_locations.timezone', $this->timezone));
        }

        // Handle the 'enabled' parameter
        if ($this->enabled !== null) {
            $this->subQuery->andWhere(Db::parseParam('elements.enabled', (int)$this->enabled));
        }

        return true;
    }
}

