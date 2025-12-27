<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;
use craft\models\Structure;

/**
 * Add capacity to schedules and structure support to services
 *
 * Schedule Capacity:
 * - capacity: Number of people per booking slot (e.g., 4 people per escape room)
 * - simultaneousSlots: Number of parallel resources (e.g., 4 escape rooms)
 * - Total spots = capacity × simultaneousSlots
 *
 * Service Structure:
 * - Services can now be hierarchical (parent/child relationships)
 * - Uses Craft's native Structure system for efficient tree queries
 */
class m251227_000000_add_capacity_and_service_structure extends Migration
{
    public function safeUp(): bool
    {
        // =====================================================
        // Part 1: Add capacity fields to schedules
        // =====================================================

        if (!$this->db->columnExists('{{%booked_schedules}}', 'capacity')) {
            $this->addColumn(
                '{{%booked_schedules}}',
                'capacity',
                $this->integer()->notNull()->defaultValue(1)->after('endTime')
            );
        }

        if (!$this->db->columnExists('{{%booked_schedules}}', 'simultaneousSlots')) {
            $this->addColumn(
                '{{%booked_schedules}}',
                'simultaneousSlots',
                $this->integer()->notNull()->defaultValue(1)->after('capacity')
            );
        }

        // Add index for capacity queries
        $this->createIndex(
            'idx_booked_schedules_capacity',
            '{{%booked_schedules}}',
            ['capacity', 'simultaneousSlots']
        );

        // =====================================================
        // Part 2: Create structure for hierarchical services
        // =====================================================

        // Create the service structure (used for parent/child relationships)
        $structure = new Structure();
        $structure->maxLevels = 3; // Allow up to 3 levels of nesting

        if (Craft::$app->getStructures()->saveStructure($structure)) {
            // Store the structure ID in plugin settings or project config
            Craft::$app->getProjectConfig()->set('plugins.booked.serviceStructureId', $structure->id);
            Craft::info("Created service structure with ID: {$structure->id}", __METHOD__);
        }

        Craft::info('Added capacity fields to schedules and created service structure', __METHOD__);

        return true;
    }

    public function safeDown(): bool
    {
        // Remove capacity columns
        if ($this->db->columnExists('{{%booked_schedules}}', 'capacity')) {
            $this->dropColumn('{{%booked_schedules}}', 'capacity');
        }

        if ($this->db->columnExists('{{%booked_schedules}}', 'simultaneousSlots')) {
            $this->dropColumn('{{%booked_schedules}}', 'simultaneousSlots');
        }

        // Remove the structure
        $structureId = Craft::$app->getProjectConfig()->get('plugins.booked.serviceStructureId');
        if ($structureId) {
            Craft::$app->getStructures()->deleteStructureById($structureId);
            Craft::$app->getProjectConfig()->remove('plugins.booked.serviceStructureId');
        }

        return true;
    }
}
