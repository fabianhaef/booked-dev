<?php

namespace fabian\booked\services;

use Craft;
use craft\base\Component;
use fabian\booked\models\BlackoutDate;
use fabian\booked\records\BlackoutDateRecord;

/**
 * Blackout Date Service
 */
class BlackoutDateService extends Component
{
    /**
     * Get all blackout dates
     */
    public function getAllBlackoutDates(): array
    {
        $records = BlackoutDateRecord::find()
            ->orderBy(['startDate' => SORT_ASC])
            ->all();

        $blackoutDates = [];
        foreach ($records as $record) {
            $blackoutDates[] = BlackoutDate::fromRecord($record);
        }

        return $blackoutDates;
    }

    /**
     * Get active blackout dates
     */
    public function getActiveBlackoutDates(): array
    {
        $records = BlackoutDateRecord::find()
            ->where(['isActive' => true])
            ->orderBy(['startDate' => SORT_ASC])
            ->all();

        $blackoutDates = [];
        foreach ($records as $record) {
            $blackoutDates[] = BlackoutDate::fromRecord($record);
        }

        return $blackoutDates;
    }

    /**
     * Get upcoming blackout dates
     */
    public function getUpcomingBlackoutDates(int $limit = 10): array
    {
        $today = date('Y-m-d');

        $records = BlackoutDateRecord::find()
            ->where(['isActive' => true])
            ->andWhere(['>=', 'endDate', $today])
            ->orderBy(['startDate' => SORT_ASC])
            ->limit($limit)
            ->all();

        $blackoutDates = [];
        foreach ($records as $record) {
            $blackoutDates[] = BlackoutDate::fromRecord($record);
        }

        return $blackoutDates;
    }

    /**
     * Get blackout date by ID
     */
    public function getBlackoutDateById(int $id): ?BlackoutDate
    {
        $record = BlackoutDateRecord::findOne($id);
        if (!$record) {
            return null;
        }

        return BlackoutDate::fromRecord($record);
    }

    /**
     * Create a new blackout date
     */
    public function createBlackoutDate(array $data): ?BlackoutDate
    {
        $blackoutDate = new BlackoutDate();
        $blackoutDate->name = $data['name'] ?? '';
        $blackoutDate->startDate = $data['startDate'] ?? '';
        $blackoutDate->endDate = $data['endDate'] ?? '';
        $blackoutDate->reason = $data['reason'] ?? null;
        $blackoutDate->isActive = $data['isActive'] ?? true;

        if ($blackoutDate->save()) {
            Craft::info('New blackout date created: ' . $blackoutDate->id, __METHOD__);
            return $blackoutDate;
        }

        return null;
    }

    /**
     * Update an existing blackout date
     */
    public function updateBlackoutDate(int $id, array $data): ?BlackoutDate
    {
        $blackoutDate = $this->getBlackoutDateById($id);
        if (!$blackoutDate) {
            return null;
        }

        if (isset($data['name'])) {
            $blackoutDate->name = $data['name'];
        }
        if (isset($data['startDate'])) {
            $blackoutDate->startDate = $data['startDate'];
        }
        if (isset($data['endDate'])) {
            $blackoutDate->endDate = $data['endDate'];
        }
        if (isset($data['reason'])) {
            $blackoutDate->reason = $data['reason'];
        }
        if (isset($data['isActive'])) {
            $blackoutDate->isActive = $data['isActive'];
        }

        if ($blackoutDate->save()) {
            return $blackoutDate;
        }

        return null;
    }

    /**
     * Delete a blackout date
     */
    public function deleteBlackoutDate(int $id): bool
    {
        $blackoutDate = $this->getBlackoutDateById($id);
        if (!$blackoutDate) {
            return false;
        }

        return $blackoutDate->delete();
    }

    /**
     * Check if a date falls within any active blackout period
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $employeeId Optional employee ID
     * @param int|null $locationId Optional location ID
     * @return bool
     */
    public function isDateBlackedOut(string $date, ?int $employeeId = null, ?int $locationId = null): bool
    {
        // Get all active blackout dates that cover this date
        $blackoutIds = BlackoutDateRecord::find()
            ->select(['id'])
            ->where(['isActive' => true])
            ->andWhere(['<=', 'startDate', $date])
            ->andWhere(['>=', 'endDate', $date])
            ->column();

        if (empty($blackoutIds)) {
            return false;
        }

        // Check each blackout to see if it applies to this employee/location
        foreach ($blackoutIds as $blackoutId) {
            // Get locations for this blackout
            $blackoutLocationIds = (new \craft\db\Query())
                ->select(['locationId'])
                ->from('{{%bookings_blackout_dates_locations}}')
                ->where(['blackoutDateId' => $blackoutId])
                ->column();

            // Get employees for this blackout
            $blackoutEmployeeIds = (new \craft\db\Query())
                ->select(['employeeId'])
                ->from('{{%bookings_blackout_dates_employees}}')
                ->where(['blackoutDateId' => $blackoutId])
                ->column();

            // Empty arrays mean "all locations" or "all employees"
            $appliesToAllLocations = empty($blackoutLocationIds);
            $appliesToAllEmployees = empty($blackoutEmployeeIds);

            // Check if this blackout applies
            $locationMatches = $appliesToAllLocations || ($locationId && in_array($locationId, $blackoutLocationIds));
            $employeeMatches = $appliesToAllEmployees || ($employeeId && in_array($employeeId, $blackoutEmployeeIds));

            // If checking specific employee/location, both must match
            // If no employee/location specified, just check if blackout is global
            if ($employeeId !== null || $locationId !== null) {
                if ($locationMatches && $employeeMatches) {
                    Craft::info("Date $date is blacked out (Employee: $employeeId, Location: $locationId)", __METHOD__);
                    return true;
                }
            } else {
                // No filters specified - only match global blackouts
                if ($appliesToAllLocations && $appliesToAllEmployees) {
                    Craft::info("Date $date is blacked out (global)", __METHOD__);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the blackout record query
     * @return \yii\db\ActiveQuery
     */
    protected function getBlackoutQuery()
    {
        return BlackoutDateRecord::find();
    }

    /**
     * Get all blackout dates that affect a date range
     */
    public function getBlackoutsForDateRange(string $startDate, string $endDate): array
    {
        $records = BlackoutDateRecord::find()
            ->where(['isActive' => true])
            ->andWhere([
                'or',
                ['and', ['<=', 'startDate', $endDate], ['>=', 'endDate', $startDate]],
            ])
            ->orderBy(['startDate' => SORT_ASC])
            ->all();

        $blackoutDates = [];
        foreach ($records as $record) {
            $blackoutDates[] = BlackoutDate::fromRecord($record);
        }

        return $blackoutDates;
    }
}
