<?php

namespace fabian\booked\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * ScheduleQuery defines the condition builder for Schedule elements
 *
 * Simplified model: schedules have direct FK to service, employee, location
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

    public ?int $serviceId = null;
    public ?int $employeeId = null;
    public ?int $locationId = null;
    public ?int $dayOfWeek = null;

    /**
     * Filter by service ID
     */
    public function serviceId(?int $value): static
    {
        $this->serviceId = $value;
        return $this;
    }

    /**
     * Filter by employee ID
     */
    public function employeeId(?int $value): static
    {
        $this->employeeId = $value;
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
        if (!parent::beforePrepare()) {
            return false;
        }

        $this->joinElementTable('booked_schedules');

        $this->query->addSelect([
            'booked_schedules.title',
            'booked_schedules.serviceId',
            'booked_schedules.employeeId',
            'booked_schedules.locationId',
            'booked_schedules.dayOfWeek',
            'booked_schedules.daysOfWeek',
            'booked_schedules.startTime',
            'booked_schedules.endTime',
        ]);

        // Direct FK filters - no junction tables needed
        if ($this->serviceId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_schedules.serviceId', $this->serviceId));
        }

        if ($this->employeeId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_schedules.employeeId', $this->employeeId));
        }

        if ($this->locationId !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_schedules.locationId', $this->locationId));
        }

        if ($this->dayOfWeek !== null) {
            $this->subQuery->andWhere(Db::parseParam('booked_schedules.dayOfWeek', $this->dayOfWeek));
        }

        if ($this->enabled !== null) {
            $this->subQuery->andWhere(Db::parseParam('elements.enabled', (int)$this->enabled));
        }

        Craft::info("ScheduleQuery: serviceId={$this->serviceId}, employeeId={$this->employeeId}, locationId={$this->locationId}, dayOfWeek={$this->dayOfWeek}", __METHOD__);

        return true;
    }
}
