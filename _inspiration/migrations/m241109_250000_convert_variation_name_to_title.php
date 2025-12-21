<?php

namespace modules\booking\migrations;

use Craft;
use craft\db\Migration;

/**
 * Convert variation name to use Craft's native title field
 */
class m241109_250000_convert_variation_name_to_title extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Copy existing names to the title field in elements_sites
        $variations = $this->db->createCommand("
            SELECT v.id, v.name
            FROM {{%bookings_variations}} v
        ")->queryAll();

        foreach ($variations as $variation) {
            // Update the title in elements_sites
            $this->update(
                '{{%elements_sites}}',
                ['title' => $variation['name']],
                ['elementId' => $variation['id']]
            );
        }

        // Drop the name column from variations table
        if ($this->db->columnExists('{{%bookings_variations}}', 'name')) {
            $this->dropColumn('{{%bookings_variations}}', 'name');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Re-add name column
        $this->addColumn('{{%bookings_variations}}', 'name', $this->string(255)->notNull()->after('id'));

        // Copy titles back to name column
        $variations = $this->db->createCommand("
            SELECT e.id, es.title
            FROM {{%elements}} e
            INNER JOIN {{%elements_sites}} es ON es.elementId = e.id
            INNER JOIN {{%bookings_variations}} v ON v.id = e.id
        ")->queryAll();

        foreach ($variations as $variation) {
            $this->update(
                '{{%bookings_variations}}',
                ['name' => $variation['title']],
                ['id' => $variation['id']]
            );
        }

        return true;
    }
}
