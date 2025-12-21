<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * EmployeeQuery defines the condition builder for Employee elements
 *
 * @method \fabian\booked\elements\Employee[]|array all($db = null)
 * @method \fabian\booked\elements\Employee|array|null one($db = null)
 * @method \fabian\booked\elements\Employee|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class EmployeeQuery extends ElementQuery
{
    /**
     * @var bool|null Whether to only return enabled employees
     */
    public $enabled;

    public ?int $userId = null;
    public ?int $locationId = null;

    /**
     * Filter by user ID
     */
    public function userId(?int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Filter by location ID
     */
    public function locationId(?int $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    /**
     * Narrows the query results to only enabled employees
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

        $this->joinElementTable('booked_employees');

        $this->query->select([
            'booked_employees.userId',
            'booked_employees.locationId',
        ]);

        if ($this->userId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_employees.userId', $this->userId));
        }

        if ($this->locationId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_employees.locationId', $this->locationId));
        }

        // Handle the 'enabled' parameter
        if ($this->enabled !== null) {
            $this->subQuery->andWhere(Db::parseParam('elements.enabled', (int)$this->enabled));
        }

        return true;
    }
}

