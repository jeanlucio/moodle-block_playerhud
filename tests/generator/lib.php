<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat data generator for PlayerHUD.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator class for PlayerHUD.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_playerhud_generator extends component_generator_base {
    /**
     * Creates a dummy item for Behat tests.
     *
     * @param array|stdClass $record Data to insert.
     * @return stdClass The created record.
     */
    public function create_item($record): \stdClass {
        global $DB;

        $record = (array)$record;
        $record['timecreated'] = time();
        $record['timemodified'] = time();

        $record['xp'] = $record['xp'] ?? 100;
        $record['enabled'] = $record['enabled'] ?? 1;
        $record['secret'] = $record['secret'] ?? 0;
        $record['description'] = $record['description'] ?? 'Behat Item';
        $record['image'] = $record['image'] ?? '🧪';
        $record['required_class_id'] = $record['required_class_id'] ?? '0';
        $record['tradable'] = $record['tradable'] ?? 1;

        $record['id'] = $DB->insert_record('block_playerhud_items', $record);
        return (object)$record;
    }
}
