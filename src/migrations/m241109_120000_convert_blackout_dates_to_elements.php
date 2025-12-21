<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\helpers\StringHelper;

/**
 * Convert existing blackout dates to Craft elements
 */
class m241109_120000_convert_blackout_dates_to_elements extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get all existing blackout dates
        $blackoutDates = (new \yii\db\Query())
            ->select(['*'])
            ->from('{{%bookings_blackout_dates}}')
            ->all();

        echo "Found " . count($blackoutDates) . " existing blackout dates to convert.\n";

        foreach ($blackoutDates as $blackoutDate) {
            // Check if this blackout date already has an element entry
            $existingElement = (new \yii\db\Query())
                ->select(['id'])
                ->from(Table::ELEMENTS)
                ->where(['id' => $blackoutDate['id']])
                ->exists();

            if ($existingElement) {
                echo "  Blackout date #{$blackoutDate['id']} already has an element entry, skipping...\n";
                continue;
            }

            // Insert into elements table
            $this->insert(Table::ELEMENTS, [
                'id' => $blackoutDate['id'],
                'canonicalId' => $blackoutDate['id'],
                'draftId' => null,
                'revisionId' => null,
                'fieldLayoutId' => null,
                'type' => 'modules\\booking\\elements\\BlackoutDate',
                'enabled' => $blackoutDate['isActive'],
                'archived' => false,
                'dateCreated' => $blackoutDate['dateCreated'],
                'dateUpdated' => $blackoutDate['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ]);

            // Insert into elements_sites table (single-site for now)
            $siteId = Craft::$app->sites->getPrimarySite()->id;
            $this->insert(Table::ELEMENTS_SITES, [
                'elementId' => $blackoutDate['id'],
                'siteId' => $siteId,
                'slug' => 'blackout-date-' . $blackoutDate['id'],
                'uri' => null,
                'enabled' => $blackoutDate['isActive'],
                'dateCreated' => $blackoutDate['dateCreated'],
                'dateUpdated' => $blackoutDate['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ]);

            echo "  ✓ Converted blackout date #{$blackoutDate['id']} to element\n";
        }

        echo "✓ All blackout dates converted successfully!\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "Converting blackout dates back to non-elements...\n";

        // Get all blackout date elements
        $elementIds = (new \yii\db\Query())
            ->select(['id'])
            ->from(Table::ELEMENTS)
            ->where(['type' => 'modules\\booking\\elements\\BlackoutDate'])
            ->column();

        if (!empty($elementIds)) {
            // Delete from elements_sites
            $this->delete(Table::ELEMENTS_SITES, ['elementId' => $elementIds]);

            // Delete from elements
            $this->delete(Table::ELEMENTS, ['id' => $elementIds]);

            echo "✓ Removed " . count($elementIds) . " blackout date elements\n";
        }

        return true;
    }
}
