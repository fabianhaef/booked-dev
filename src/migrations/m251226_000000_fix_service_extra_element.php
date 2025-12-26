<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m251226_000000_fix_service_extra_element migration.
 *
 * Fixes the ServiceExtra table to properly link to elements.id instead of using standalone ID.
 * This allows ServiceExtra to function as a proper Craft Element with all benefits:
 * - Element querying
 * - Drafts & revisions
 * - Multi-site support
 * - Element permissions
 * - Search integration
 */
class m251226_000000_fix_service_extra_element extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Step 1: Get all existing service extras before modifying the table
        $existingExtras = (new \yii\db\Query())
            ->select(['id', 'name', 'description', 'price', 'duration', 'maxQuantity', 'isRequired', 'sortOrder', 'enabled', 'dateCreated', 'dateUpdated', 'uid'])
            ->from('{{%booked_service_extras}}')
            ->all();

        echo "Found " . count($existingExtras) . " existing service extras to migrate.\n";

        // Step 2: Drop all foreign keys that reference booked_service_extras.id
        echo "Dropping foreign keys...\n";
        MigrationHelper::dropAllForeignKeysToTable('{{%booked_service_extras}}', $this);

        // Step 3: Remove AUTO_INCREMENT from id column before modifying
        echo "Removing AUTO_INCREMENT from id column...\n";
        $this->alterColumn('{{%booked_service_extras}}', 'id', $this->integer()->notNull());

        // Step 4: Rename the existing 'id' column to 'oldId' temporarily
        echo "Renaming id column...\n";
        $this->renameColumn('{{%booked_service_extras}}', 'id', 'oldId');

        // Step 5: Add new 'id' column that will be a foreign key to elements.id
        echo "Adding new id column as foreign key to elements...\n";
        $this->addColumn('{{%booked_service_extras}}', 'id', $this->integer()->notNull()->after('oldId'));

        // Step 6: Create elements for each existing service extra
        echo "Creating element entries...\n";
        foreach ($existingExtras as $extra) {
            // Insert into elements table
            $this->insert('{{%elements}}', [
                'type' => 'fabian\\booked\\elements\\ServiceExtra',
                'enabled' => (bool)$extra['enabled'],
                'archived' => false,
                'dateCreated' => $extra['dateCreated'],
                'dateUpdated' => $extra['dateUpdated'],
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);

            $elementId = $this->db->getLastInsertID();

            // Insert into elements_sites table
            $this->insert('{{%elements_sites}}', [
                'elementId' => $elementId,
                'siteId' => 1, // Default site
                'slug' => \craft\helpers\StringHelper::toKebabCase($extra['name']),
                'uri' => null,
                'enabled' => true,
                'dateCreated' => $extra['dateCreated'],
                'dateUpdated' => $extra['dateUpdated'],
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);

            // Insert into content table for title
            $this->insert('{{%content}}', [
                'elementId' => $elementId,
                'siteId' => 1,
                'title' => $extra['name'],
                'dateCreated' => $extra['dateCreated'],
                'dateUpdated' => $extra['dateUpdated'],
                'uid' => \craft\helpers\StringHelper::UUID(),
            ]);

            // Update the service_extras table to use the new element ID
            $this->update('{{%booked_service_extras}}', ['id' => $elementId], ['oldId' => $extra['id']]);

            // Update junction tables to point to new element ID
            $this->update('{{%booked_service_extras_services}}', ['extraId' => $elementId], ['extraId' => $extra['id']]);
            $this->update('{{%booked_reservation_extras}}', ['extraId' => $elementId], ['extraId' => $extra['id']]);

            echo "  Migrated: {$extra['name']} (old ID: {$extra['id']} -> new ID: {$elementId})\n";
        }

        // Step 7: Drop the oldId column
        echo "Dropping oldId column...\n";
        $this->dropColumn('{{%booked_service_extras}}', 'oldId');

        // Step 8: Drop the 'name' and 'enabled' columns (now stored in elements/content tables)
        echo "Dropping redundant columns...\n";
        $this->dropColumn('{{%booked_service_extras}}', 'name');
        $this->dropColumn('{{%booked_service_extras}}', 'enabled');

        // Step 9: Add primary key on new id column
        echo "Adding primary key...\n";
        $this->addPrimaryKey('id', '{{%booked_service_extras}}', 'id');

        // Step 10: Add foreign key from booked_service_extras.id to elements.id
        echo "Adding foreign key to elements table...\n";
        $this->addForeignKey(
            null,
            '{{%booked_service_extras}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // Step 11: Re-add foreign keys for junction tables
        echo "Re-adding junction table foreign keys...\n";
        $this->addForeignKey(
            null,
            '{{%booked_service_extras_services}}',
            'extraId',
            '{{%elements}}', // Now points to elements, not booked_service_extras
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%booked_service_extras_services}}',
            'serviceId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%booked_reservation_extras}}',
            'reservationId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%booked_reservation_extras}}',
            'extraId',
            '{{%elements}}', // Now points to elements, not booked_service_extras
            'id',
            'CASCADE',
            'CASCADE'
        );

        echo "ServiceExtra element migration completed successfully!\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "This migration cannot be safely reverted.\n";
        return false;
    }
}
