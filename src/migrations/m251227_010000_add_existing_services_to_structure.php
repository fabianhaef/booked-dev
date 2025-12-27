<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use fabian\booked\elements\Service;

/**
 * Add existing services to the structure for hierarchical support
 */
class m251227_010000_add_existing_services_to_structure extends Migration
{
    public function safeUp(): bool
    {
        $structureId = Craft::$app->getProjectConfig()->get('plugins.booked.serviceStructureId');

        if (!$structureId) {
            Craft::warning('No service structure ID found. Skipping migration.', __METHOD__);
            return true;
        }

        $structuresService = Craft::$app->getStructures();

        // Get all service element IDs
        $serviceIds = (new Query())
            ->select(['id'])
            ->from('{{%elements}}')
            ->where(['type' => Service::class])
            ->andWhere(['dateDeleted' => null])
            ->column();

        Craft::info('Found ' . count($serviceIds) . ' services to add to structure', __METHOD__);

        foreach ($serviceIds as $serviceId) {
            // Check if already in structure
            $inStructure = (new Query())
                ->from('{{%structureelements}}')
                ->where(['structureId' => $structureId, 'elementId' => $serviceId])
                ->exists();

            if (!$inStructure) {
                $service = Service::find()->id($serviceId)->siteId('*')->status(null)->one();
                if ($service) {
                    Craft::info("Adding service {$service->id} ({$service->title}) to structure", __METHOD__);
                    $structuresService->appendToRoot($structureId, $service);
                }
            }
        }

        Craft::info('Finished adding services to structure', __METHOD__);
        return true;
    }

    public function safeDown(): bool
    {
        // We don't remove services from structure on rollback
        // as that could break the hierarchy
        return true;
    }
}
