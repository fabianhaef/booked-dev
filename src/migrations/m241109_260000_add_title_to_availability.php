<?php

namespace fabian\booked\migrations;

use Craft;
use craft\db\Migration;

/**
 * Add title support to Availability elements
 */
class m241109_260000_add_title_to_availability extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Generate titles for existing availabilities based on their type and properties
        $availabilities = $this->db->createCommand("
            SELECT a.id, a.availabilityType, a.dayOfWeek, a.startTime, a.endTime, a.description
            FROM {{%bookings_availability}} a
        ")->queryAll();

        $days = [
            0 => 'Sonntag',
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag'
        ];

        foreach ($availabilities as $availability) {
            $title = '';

            if ($availability['availabilityType'] === 'recurring') {
                // Generate title like "Montag 09:00-17:00"
                $dayName = $days[$availability['dayOfWeek']] ?? 'Unbekannt';
                $startTime = substr($availability['startTime'], 0, 5); // HH:MM
                $endTime = substr($availability['endTime'], 0, 5); // HH:MM
                $title = $dayName . ' ' . $startTime . '-' . $endTime;
            } else {
                // For events, use description or generic title
                if (!empty($availability['description'])) {
                    $title = substr($availability['description'], 0, 50);
                } else {
                    $title = 'Event-Verfügbarkeit';
                }
            }

            // Update the title in elements_sites
            $this->update(
                '{{%elements_sites}}',
                ['title' => $title],
                ['elementId' => $availability['id']]
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Titles are stored in elements_sites, so no need to do anything on down
        return true;
    }
}
