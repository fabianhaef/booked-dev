<?php

namespace fabian\booked\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * ScheduleQuery defines the condition builder for Schedule elements
 *
 * @method \fabian\booked\elements\Schedule[]|array all($db = null)
 * @method \fabian\booked\elements\Schedule|array|null one($db = null)
 * @method \fabian\booked\elements\Schedule|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class ScheduleQuery extends ElementQuery
{
    /**
     * @var bool|null Whether to only return enabled schedules
     */
    public $enabled;

    public ?int $employeeId = null;
    public ?int $dayOfWeek = null;
    public $serviceId = null;

    /**
     * Filter by employee ID
     */
    public function employeeId(?int $value): static
    {
        $this->employeeId = $value;
        return $this;
    }

    /**
     * Filter by service ID
     */
    public function serviceId($value): static
    {
        $this->serviceId = $value;
        return $this;
    }

    /**
     * Filter by day of week
     */
    public function dayOfWeek(?int $value): static
    {
        $this->dayOfWeek = $value;
        return $this;
    }

    /**
     * Narrows the query results to only enabled schedules
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

        $this->joinElementTable('booked_schedules');

        $this->query->addSelect([
            'booked_schedules.employeeId',
            'booked_schedules.dayOfWeek',
            'booked_schedules.startTime',
            'booked_schedules.endTime',
        ]);

        if ($this->employeeId !== null) {
            $this->subQuery->innerJoin('{{%booked_schedule_employees}} booked_schedule_employees', '[[booked_schedule_employees.scheduleId]] = [[elements.id]]');
            $this->subQuery->andWhere(Db::parseParam('booked_schedule_employees.employeeId', $this->employeeId));
        }

        if ($this->dayOfWeek !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_schedules.dayOfWeek', $this->dayOfWeek));
        }

        if ($this->serviceId !== null) {
            $this->subQuery->innerJoin('{{%booked_employees_services}} booked_employees_services', '[[booked_employees_services.employeeId]] = [[booked_schedule_employees.employeeId]]');
            $this->subQuery->andWhere(Db::parseParam('booked_employees_services.serviceId', $this->serviceId));
        }

        // Handle the 'enabled' parameter
        if ($this->enabled !== null) {
            $this->subQuery->andWhere(Db::parseParam('elements.enabled', (int)$this->enabled));
        }

        return true;
    }
}

