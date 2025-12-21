<?php

namespace fabian\booked\migrations;

use craft\db\Migration;

/**
 * Remove bio and specialties columns from booked_employees table
 */
class m250101_000001_remove_employee_bio_specialties extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Drop bio column if it exists
        if ($this->db->tableExists('{{%booked_employees}}')) {
            if ($this->db->columnExists('{{%booked_employees}}', 'bio')) {
                $this->dropColumn('{{%booked_employees}}', 'bio');
            }

            // Drop specialties column if it exists
            if ($this->db->columnExists('{{%booked_employees}}', 'specialties')) {
                $this->dropColumn('{{%booked_employees}}', 'specialties');
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Re-add columns if rolling back
        if ($this->db->tableExists('{{%booked_employees}}')) {
            if (!$this->db->columnExists('{{%booked_employees}}', 'bio')) {
                $this->addColumn('{{%booked_employees}}', 'bio', $this->text());
            }

            if (!$this->db->columnExists('{{%booked_employees}}', 'specialties')) {
                $this->addColumn('{{%booked_employees}}', 'specialties', $this->text());
            }
        }

        return true;
    }
}

