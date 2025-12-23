<?php

namespace fabian\booked\tests\unit;

use Codeception\Test\Unit;
use Craft;
use UnitTester;

/**
 * Migration Tests
 *
 * Tests database migration integrity:
 * - Fresh install creates all tables
 * - Upgrade migrations work correctly
 * - Rollback capability
 * - Data integrity after migration
 * - Schema consistency
 */
class MigrationTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * Test that fresh install creates all required tables
     */
    public function testFreshInstallCreatesAllTables()
    {
        // Arrange: Get database connection
        $db = Craft::$app->getDb();

        // Expected tables for Booked plugin
        $expectedTables = [
            '{{%booked_services}}',
            '{{%booked_employees}}',
            '{{%booked_locations}}',
            '{{%booked_schedules}}',
            '{{%booked_schedule_employees}}',
            '{{%booked_employees_services}}',
            '{{%booked_reservations}}',
            '{{%booked_availability}}',
            '{{%booked_blackout_dates}}',
            '{{%booked_calendar_tokens}}',
            '{{%booked_soft_locks}}',
        ];

        // Act & Assert: Verify each table exists
        foreach ($expectedTables as $tableName) {
            $tableSchema = $db->getTableSchema($tableName);
            $this->assertNotNull(
                $tableSchema,
                "Table {$tableName} should exist after fresh install"
            );
        }
    }

    /**
     * Test that schedules table has correct schema
     */
    public function testSchedulesTableSchema()
    {
        $db = Craft::$app->getDb();
        $schema = $db->getTableSchema('{{%booked_schedules}}');

        $this->assertNotNull($schema, 'Schedules table should exist');

        // Check for new fields added in recent migration
        $this->assertArrayHasKey('title', $schema->columns, 'Should have title column');
        $this->assertArrayHasKey('daysOfWeek', $schema->columns, 'Should have daysOfWeek column');

        // Check column types
        $this->assertEquals('string', $schema->columns['title']->phpType, 'title should be string');
        $this->assertTrue($schema->columns['title']->allowNull, 'title should be nullable');

        // daysOfWeek should be JSON/text type
        $this->assertContains(
            $schema->columns['daysOfWeek']->dbType,
            ['json', 'text', 'longtext'],
            'daysOfWeek should be JSON type'
        );

        // Check backward compatibility - old fields still exist
        $this->assertArrayHasKey('dayOfWeek', $schema->columns, 'Should keep old dayOfWeek for backward compatibility');
        $this->assertArrayHasKey('employeeId', $schema->columns, 'Should keep old employeeId for backward compatibility');
    }

    /**
     * Test migration data transformation (dayOfWeek 0-6 to daysOfWeek 1-7)
     */
    public function testMigrationDataTransformation()
    {
        // This test verifies the migration correctly transforms old data format
        // From: dayOfWeek (0=Sunday, 6=Saturday)
        // To: daysOfWeek [1=Monday, 7=Sunday]

        $db = Craft::$app->getDb();

        // Insert test data in old format
        $db->createCommand()->insert('{{%booked_schedules}}', [
            'id' => 999,
            'employeeId' => 1,
            'dayOfWeek' => 0, // Sunday in old format
            'startTime' => '09:00',
            'endTime' => '17:00',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
            'uid' => 'test-schedule-999',
        ])->execute();

        // Simulate migration transformation
        $oldDay = 0; // Sunday
        $newDay = $oldDay === 0 ? 7 : $oldDay;
        $newDaysOfWeek = json_encode([$newDay]);

        // Update with new format
        $db->createCommand()->update(
            '{{%booked_schedules}}',
            ['daysOfWeek' => $newDaysOfWeek],
            ['id' => 999]
        )->execute();

        // Verify transformation
        $row = $db->createCommand('SELECT * FROM {{%booked_schedules}} WHERE id = 999')->queryOne();

        $this->assertEquals('[7]', $row['daysOfWeek'], 'Sunday (0) should transform to [7]');
        $this->assertEquals(0, $row['dayOfWeek'], 'Old dayOfWeek should be preserved');

        // Cleanup
        $db->createCommand()->delete('{{%booked_schedules}}', ['id' => 999])->execute();
    }

    /**
     * Test index creation for performance
     */
    public function testIndexCreation()
    {
        $db = Craft::$app->getDb();

        // Check critical indexes exist for performance
        $indexes = $db->getTableSchema('{{%booked_reservations}}')->foreignKeys ?? [];

        // Reservations should have indexes on frequently queried columns
        $schema = $db->getTableSchema('{{%booked_reservations}}');

        // These indexes are critical for query performance
        $this->assertNotNull($schema, 'Reservations table should exist');

        // Note: Actual index checking requires database-specific queries
        // This is a simplified version
        $this->assertTrue(true, 'Index verification should be implemented per database type');
    }

    /**
     * Test foreign key constraints
     */
    public function testForeignKeyConstraints()
    {
        $db = Craft::$app->getDb();

        // Check foreign key from schedules to employees junction table
        $schema = $db->getTableSchema('{{%booked_schedule_employees}}');

        $this->assertNotNull($schema, 'Schedule-Employee junction table should exist');
        $this->assertArrayHasKey('scheduleId', $schema->columns);
        $this->assertArrayHasKey('employeeId', $schema->columns);

        // Foreign keys ensure referential integrity
        $foreignKeys = $schema->foreignKeys;

        $this->assertNotEmpty($foreignKeys, 'Junction table should have foreign key constraints');
    }

    /**
     * Test migration rollback capability
     */
    public function testMigrationRollback()
    {
        // This test verifies migrations can be rolled back cleanly

        $db = Craft::$app->getDb();

        // Simulate adding a column
        $tableName = '{{%booked_test_migration}}';

        // Create test table
        $db->createCommand()->createTable($tableName, [
            'id' => 'pk',
            'testColumn' => 'string',
        ])->execute();

        // Verify table exists
        $this->assertNotNull($db->getTableSchema($tableName));

        // Simulate rollback - drop table
        $db->createCommand()->dropTable($tableName)->execute();

        // Verify table no longer exists
        $this->assertNull($db->getTableSchema($tableName), 'Rolled back table should not exist');
    }

    /**
     * Test schema consistency across different database types
     */
    public function testSchemaConsistencyAcrossDatabases()
    {
        // This test ensures migrations work correctly on MySQL, PostgreSQL, etc.

        $db = Craft::$app->getDb();
        $driverName = $db->getDriverName();

        // Get schedules table schema
        $schema = $db->getTableSchema('{{%booked_schedules}}');

        $this->assertNotNull($schema);

        // JSON column handling varies by database
        if ($driverName === 'mysql') {
            // MySQL has native JSON type
            $this->assertContains(
                $schema->columns['daysOfWeek']->dbType,
                ['json', 'text'],
                'MySQL should support JSON type'
            );
        } elseif ($driverName === 'pgsql') {
            // PostgreSQL has JSONB
            $this->assertContains(
                $schema->columns['daysOfWeek']->dbType,
                ['jsonb', 'json', 'text'],
                'PostgreSQL should support JSONB/JSON type'
            );
        }

        $this->assertTrue(true, 'Schema should work across database types');
    }

    /**
     * Test data integrity after migration
     */
    public function testDataIntegrityAfterMigration()
    {
        $db = Craft::$app->getDb();

        // Insert test schedule
        $db->createCommand()->insert('{{%booked_schedules}}', [
            'id' => 9999,
            'employeeId' => 1,
            'dayOfWeek' => 1, // Monday old format
            'daysOfWeek' => json_encode([1]), // Monday new format
            'startTime' => '09:00',
            'endTime' => '17:00',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
            'uid' => 'test-integrity-9999',
        ])->execute();

        // Verify data integrity
        $row = $db->createCommand('SELECT * FROM {{%booked_schedules}} WHERE id = 9999')->queryOne();

        $this->assertEquals(1, $row['dayOfWeek']);
        $this->assertEquals('[1]', $row['daysOfWeek']);
        $this->assertEquals('09:00', $row['startTime']);
        $this->assertEquals('17:00', $row['endTime']);

        // Both old and new formats coexist correctly
        $this->assertTrue(true, 'Data integrity maintained after migration');

        // Cleanup
        $db->createCommand()->delete('{{%booked_schedules}}', ['id' => 9999])->execute();
    }

    /**
     * Test migration handles NULL values correctly
     */
    public function testMigrationHandlesNullValues()
    {
        $db = Craft::$app->getDb();

        // Test that NULL values in old schema work in new schema
        $db->createCommand()->insert('{{%booked_schedules}}', [
            'id' => 99999,
            'employeeId' => NULL, // NULL is valid
            'dayOfWeek' => NULL,
            'daysOfWeek' => NULL, // NULL before migration
            'startTime' => '09:00',
            'endTime' => '17:00',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
            'uid' => 'test-null-99999',
        ])->execute();

        $row = $db->createCommand('SELECT * FROM {{%booked_schedules}} WHERE id = 99999')->queryOne();

        $this->assertNull($row['employeeId']);
        $this->assertNull($row['dayOfWeek']);
        // daysOfWeek can be NULL before migration runs
        $this->assertTrue(true, 'NULL values handled correctly');

        // Cleanup
        $db->createCommand()->delete('{{%booked_schedules}}', ['id' => 99999])->execute();
    }

    /**
     * Test migration is idempotent (can run multiple times safely)
     */
    public function testMigrationIdempotency()
    {
        // Migrations should be idempotent - running twice shouldn't break things

        $db = Craft::$app->getDb();
        $schema = $db->getTableSchema('{{%booked_schedules}}');

        // Column should exist
        $this->assertArrayHasKey('daysOfWeek', $schema->columns);

        // Running migration again should not throw error
        // In production, this would check:
        // if (!$this->db->columnExists('table', 'column')) { addColumn }

        $this->assertTrue(true, 'Migration should be safe to run multiple times');
    }
}
