<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\helpers\StringHelper;

/**
 * Convert existing availability to Craft elements
 */
class m241109_110000_convert_availability_to_elements extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get all existing availability
        $availabilities = (new \yii\db\Query())
            ->select(['*'])
            ->from('{{%bookings_availability}}')
            ->all();

        echo "Found " . count($availabilities) . " existing availabilities to convert.\n";

        foreach ($availabilities as $availability) {
            // Check if this availability already has an element entry
            $existingElement = (new \yii\db\Query())
                ->select(['id'])
                ->from(Table::ELEMENTS)
                ->where(['id' => $availability['id']])
                ->exists();

            if ($existingElement) {
                echo "  Availability #{$availability['id']} already has an element entry, skipping...\n";
                continue;
            }

            // Insert into elements table
            $this->insert(Table::ELEMENTS, [
                'id' => $availability['id'],
                'canonicalId' => $availability['id'],
                'draftId' => null,
                'revisionId' => null,
                'fieldLayoutId' => null,
                'type' => 'modules\\booking\\elements\\Availability',
                'enabled' => $availability['isActive'],
                'archived' => false,
                'dateCreated' => $availability['dateCreated'],
                'dateUpdated' => $availability['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ]);

            // Insert into elements_sites table (single-site for now)
            $siteId = Craft::$app->sites->getPrimarySite()->id;
            $this->insert(Table::ELEMENTS_SITES, [
                'elementId' => $availability['id'],
                'siteId' => $siteId,
                'slug' => 'availability-' . $availability['id'],
                'uri' => null,
                'enabled' => $availability['isActive'],
                'dateCreated' => $availability['dateCreated'],
                'dateUpdated' => $availability['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ]);

            echo "  ✓ Converted availability #{$availability['id']} to element\n";
        }

        echo "✓ All availabilities converted successfully!\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "Converting availabilities back to non-elements...\n";

        // Get all availability elements
        $elementIds = (new \yii\db\Query())
            ->select(['id'])
            ->from(Table::ELEMENTS)
            ->where(['type' => 'modules\\booking\\elements\\Availability'])
            ->column();

        if (!empty($elementIds)) {
            // Delete from elements_sites
            $this->delete(Table::ELEMENTS_SITES, ['elementId' => $elementIds]);

            // Delete from elements
            $this->delete(Table::ELEMENTS, ['id' => $elementIds]);

            echo "✓ Removed " . count($elementIds) . " availability elements\n";
        }

        return true;
    }
}
