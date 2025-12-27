<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Simplify Schedule Relationships Migration
 *
 * This migration simplifies the relationship model by:
 * 1. Adding serviceId, employeeId, locationId directly to schedules table
 * 2. Migrating data from junction tables
 * 3. Removing junction tables (booked_schedule_employees, booked_employees_services)
 * 4. Removing locationId from employees table
 *
 * New model:
 * Schedule -> Service (required)
 * Schedule -> Employee (optional)
 * Schedule -> Location (optional)
 */
class m251226_200000_simplify_schedule_relationships extends Migration
{
    public function safeUp(): bool
    {
        // Step 1: Add new columns to booked_schedules
        if (!$this->db->columnExists('{{%booked_schedules}}', 'serviceId')) {
            $this->addColumn('{{%booked_schedules}}', 'serviceId', $this->integer()->after('endTime'));
        }

        if (!$this->db->columnExists('{{%booked_schedules}}', 'employeeId')) {
            $this->addColumn('{{%booked_schedules}}', 'employeeId', $this->integer()->after('serviceId'));
        }

        if (!$this->db->columnExists('{{%booked_schedules}}', 'locationId')) {
            $this->addColumn('{{%booked_schedules}}', 'locationId', $this->integer()->after('employeeId'));
        }

        // Step 2: Migrate data from junction tables
        // Get all schedule-employee relationships
        if ($this->db->tableExists('{{%booked_schedule_employees}}')) {
            $scheduleEmployees = (new \craft\db\Query())
                ->select(['scheduleId', 'employeeId'])
                ->from('{{%booked_schedule_employees}}')
                ->all();

            foreach ($scheduleEmployees as $row) {
                // Update schedule with first employee (simplified model uses single employee per schedule)
                $this->update('{{%booked_schedules}}',
                    ['employeeId' => $row['employeeId']],
                    ['id' => $row['scheduleId'], 'employeeId' => null]
                );
            }
        }

        // Get employee-service relationships and apply to schedules
        if ($this->db->tableExists('{{%booked_employees_services}}')) {
            $employeeServices = (new \craft\db\Query())
                ->select(['employeeId', 'serviceId'])
                ->from('{{%booked_employees_services}}')
                ->all();

            // Create a map of employee -> services
            $employeeServiceMap = [];
            foreach ($employeeServices as $row) {
                $employeeServiceMap[$row['employeeId']][] = $row['serviceId'];
            }

            // Update schedules with serviceId based on their employee
            $schedules = (new \craft\db\Query())
                ->select(['id', 'employeeId'])
                ->from('{{%booked_schedules}}')
                ->where(['not', ['employeeId' => null]])
                ->all();

            foreach ($schedules as $schedule) {
                if (isset($employeeServiceMap[$schedule['employeeId']])) {
                    // Use first service for this employee
                    $serviceId = $employeeServiceMap[$schedule['employeeId']][0];
                    $this->update('{{%booked_schedules}}',
                        ['serviceId' => $serviceId],
                        ['id' => $schedule['id']]
                    );
                }
            }
        }

        // Step 3: Migrate locationId from employees to schedules
        if ($this->db->tableExists('{{%booked_employees}}') && $this->db->columnExists('{{%booked_employees}}', 'locationId')) {
            $employees = (new \craft\db\Query())
                ->select(['id', 'locationId'])
                ->from('{{%booked_employees}}')
                ->where(['not', ['locationId' => null]])
                ->all();

            foreach ($employees as $employee) {
                // Update all schedules for this employee with their location
                $this->update('{{%booked_schedules}}',
                    ['locationId' => $employee['locationId']],
                    ['employeeId' => $employee['id'], 'locationId' => null]
                );
            }
        }

        // Step 4: Add foreign keys
        // Service FK
        $this->createIndex(
            'idx_booked_schedules_serviceId',
            '{{%booked_schedules}}',
            'serviceId'
        );

        // Check if booked_services table exists before adding FK
        if ($this->db->tableExists('{{%booked_services}}')) {
            $this->addForeignKey(
                'fk_booked_schedules_serviceId',
                '{{%booked_schedules}}',
                'serviceId',
                '{{%booked_services}}',
                'id',
                'SET NULL'
            );
        }

        // Employee FK
        $this->createIndex(
            'idx_booked_schedules_employeeId',
            '{{%booked_schedules}}',
            'employeeId'
        );

        if ($this->db->tableExists('{{%booked_employees}}')) {
            $this->addForeignKey(
                'fk_booked_schedules_employeeId',
                '{{%booked_schedules}}',
                'employeeId',
                '{{%booked_employees}}',
                'id',
                'SET NULL'
            );
        }

        // Location FK
        $this->createIndex(
            'idx_booked_schedules_locationId',
            '{{%booked_schedules}}',
            'locationId'
        );

        if ($this->db->tableExists('{{%booked_locations}}')) {
            $this->addForeignKey(
                'fk_booked_schedules_locationId',
                '{{%booked_schedules}}',
                'locationId',
                '{{%booked_locations}}',
                'id',
                'SET NULL'
            );
        }

        // Step 5: Drop junction tables (they're no longer needed)
        if ($this->db->tableExists('{{%booked_schedule_employees}}')) {
            $this->dropTableIfExists('{{%booked_schedule_employees}}');
        }

        if ($this->db->tableExists('{{%booked_employees_services}}')) {
            $this->dropTableIfExists('{{%booked_employees_services}}');
        }

        // Step 6: Remove locationId from employees table (location is now on schedule)
        // Note: We keep locationId on employees for backward compatibility. It won't be used
        // but removing it requires dropping auto-generated FKs which can be complex.
        // Future cleanup migration can handle this if needed.

        Craft::info('Simplified schedule relationships - migration complete', __METHOD__);

        return true;
    }

    public function safeDown(): bool
    {
        // This migration is not reversible as it removes data
        echo "This migration cannot be reverted.\n";
        return false;
    }
}
