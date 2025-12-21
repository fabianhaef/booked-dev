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
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('booked_employees');

        $this->query->select([
            'booked_employees.userId',
            'booked_employees.locationId',
            'booked_employees.bio',
            'booked_employees.specialties',
        ]);

        if ($this->userId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_employees.userId', $this->userId));
        }

        if ($this->locationId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_employees.locationId', $this->locationId));
        }

        return parent::beforePrepare();
    }
}

