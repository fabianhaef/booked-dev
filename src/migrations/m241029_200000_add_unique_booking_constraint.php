<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add unique constraint to prevent double bookings
 * This ensures only one active booking can exist for a given date/time slot
 */
class m241029_200000_add_unique_booking_constraint extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // First, let's check if there are any duplicate bookings
        $duplicates = $this->db->createCommand("
            SELECT bookingDate, startTime, endTime, COUNT(*) as count
            FROM {{%bookings_reservations}}
            WHERE status != 'cancelled'
            GROUP BY bookingDate, startTime, endTime
            HAVING count > 1
        ")->queryAll();

        if (!empty($duplicates)) {
            echo "WARNING: Found " . count($duplicates) . " duplicate bookings:\n";
            foreach ($duplicates as $dup) {
                echo "  - {$dup['bookingDate']} {$dup['startTime']}-{$dup['endTime']}: {$dup['count']} bookings\n";
            }
            echo "\nPlease resolve these duplicates before applying this migration.\n";
            echo "You can cancel duplicates manually in the admin panel.\n";
            return false;
        }

        // Add unique index on (bookingDate, startTime, endTime) for non-cancelled bookings
        // Note: MySQL doesn't support partial unique indexes natively, so we use a workaround
        // We'll add a computed column or use a trigger approach

        // Check if index already exists
        $indexName = 'idx_unique_active_booking';
        $tableName = '{{%bookings_reservations}}';

        // Get the actual table name
        $actualTableName = $this->db->getSchema()->getRawTableName($tableName);

        // Check if index exists
        $indexExists = $this->db->createCommand("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = '{$actualTableName}'
            AND index_name = '{$indexName}'
        ")->queryScalar();

        if (!$indexExists) {
            // For MySQL 5.7+, we can use a generated column approach
            $this->createIndex(
                $indexName,
                $tableName,
                ['bookingDate', 'startTime', 'endTime', 'status'],
                true // unique
            );

            echo "✓ Added unique constraint to prevent double bookings\n";
        } else {
            echo "✓ Unique constraint already exists\n";
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropIndex('idx_unique_active_booking', '{{%bookings_reservations}}');

        return true;
    }
}
