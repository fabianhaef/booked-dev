<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\helpers\StringHelper;

/**
 * Convert existing reservations to Craft elements
 */
class m241109_100000_convert_reservations_to_elements extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get all existing reservations
        $reservations = (new \yii\db\Query())
            ->select(['*'])
            ->from('{{%bookings_reservations}}')
            ->all();

        echo "Found " . count($reservations) . " existing reservations to convert.\n";

        foreach ($reservations as $reservation) {
            // Check if this reservation already has an element entry
            $existingElement = (new \yii\db\Query())
                ->select(['id'])
                ->from(Table::ELEMENTS)
                ->where(['id' => $reservation['id']])
                ->exists();

            if ($existingElement) {
                echo "  Reservation #{$reservation['id']} already has an element entry, skipping...\n";
                continue;
            }

            // Insert into elements table
            $this->insert(Table::ELEMENTS, [
                'id' => $reservation['id'],
                'canonicalId' => $reservation['id'],
                'draftId' => null,
                'revisionId' => null,
                'fieldLayoutId' => null,
                'type' => 'modules\\booking\\elements\\Reservation',
                'enabled' => $reservation['status'] !== 'cancelled',
                'archived' => false,
                'dateCreated' => $reservation['dateCreated'],
                'dateUpdated' => $reservation['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ]);

            // Insert into elements_sites table (single-site for now)
            $siteId = Craft::$app->sites->getPrimarySite()->id;
            $this->insert(Table::ELEMENTS_SITES, [
                'elementId' => $reservation['id'],
                'siteId' => $siteId,
                'slug' => 'reservation-' . $reservation['id'],
                'uri' => null,
                'enabled' => $reservation['status'] !== 'cancelled',
                'dateCreated' => $reservation['dateCreated'],
                'dateUpdated' => $reservation['dateUpdated'],
                'uid' => StringHelper::UUID(),
            ]);

            echo "  ✓ Converted reservation #{$reservation['id']} to element\n";
        }

        echo "✓ All reservations converted successfully!\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "Converting reservations back to non-elements...\n";

        // Get all reservation elements
        $elementIds = (new \yii\db\Query())
            ->select(['id'])
            ->from(Table::ELEMENTS)
            ->where(['type' => 'modules\\booking\\elements\\Reservation'])
            ->column();

        if (!empty($elementIds)) {
            // Delete from elements_sites
            $this->delete(Table::ELEMENTS_SITES, ['elementId' => $elementIds]);

            // Delete from elements
            $this->delete(Table::ELEMENTS, ['id' => $elementIds]);

            echo "✓ Removed " . count($elementIds) . " reservation elements\n";
        }

        return true;
    }
}
