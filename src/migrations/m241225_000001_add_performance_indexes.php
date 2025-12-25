<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241225_000001_add_performance_indexes migration.
 *
 * Adds database indexes to optimize query performance for:
 * - Availability lookups (employee + date + time)
 * - Status filtering
 * - Date range queries
 * - Foreign key JOINs
 */
class m241225_000001_add_performance_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $tableName = '{{%bookings_reservations}}';

        // Check if table exists
        if (!$this->db->tableExists($tableName)) {
            return true; // Skip if table doesn't exist yet
        }

        // Index 1: employeeId (for filtering by employee)
        $this->createIndexIfMissing($tableName, 'employeeId');

        // Index 2: bookingDate (for date filtering and range queries)
        $this->createIndexIfMissing($tableName, 'bookingDate');

        // Index 3: status (for filtering by confirmed/pending/cancelled)
        $this->createIndexIfMissing($tableName, 'status');

        // Index 4: serviceId (for service filtering)
        $this->createIndexIfMissing($tableName, 'serviceId');

        // Index 5: Composite index for availability checks (most common query)
        $this->createIndexIfMissing($tableName, ['employeeId', 'bookingDate', 'startTime']);

        // Index 6: Composite covering index for availability with status
        $this->createIndexIfMissing($tableName, ['employeeId', 'bookingDate', 'status']);

        // Index 7: Date + Status for dashboard queries
        $this->createIndexIfMissing($tableName, ['bookingDate', 'status']);

        // Index 8: Service + Date for capacity checks
        $this->createIndexIfMissing($tableName, ['serviceId', 'bookingDate', 'startTime']);

        // Index 9: userEmail (for finding user's reservations)
        $this->createIndexIfMissing($tableName, 'userEmail');

        // Index 10: confirmationToken (for reservation verification)
        $this->createIndexIfMissing($tableName, 'confirmationToken');

        Craft::info('Performance indexes added to bookings_reservations table', __METHOD__);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $tableName = '{{%bookings_reservations}}';

        // Drop indexes in reverse order
        $indexes = [
            'idx_reservations_confirmation_token',
            'idx_reservations_user_email',
            'idx_reservations_service_date_time',
            'idx_reservations_date_status',
            'idx_reservations_employee_date_status',
            'idx_reservations_availability_check',
            'idx_reservations_service_id',
            'idx_reservations_status',
            'idx_reservations_booking_date',
            'idx_reservations_employee_id',
        ];

        foreach ($indexes as $indexName) {
            if ($this->db->tableExists($tableName)) {
                try {
                    $this->dropIndexIfExists($tableName, $indexName);
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
            }
        }

        Craft::info('Performance indexes removed from bookings_reservations table', __METHOD__);

        return true;
    }
}
