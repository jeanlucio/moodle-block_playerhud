<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Controller for persisting the AI/heuristic quest and trade suggestions.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

/**
 * Persists the suggestions a teacher ticked on the quest/trade suggestion forms.
 *
 * A suggestion is selected when the submitted form data carries a truthy
 * 'sug_<uid>' field for it.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggestions {
    /**
     * Inserts the quest suggestions selected in the form.
     *
     * @param int $instanceid The owning block instance ID.
     * @param array $suggestions The suggestion list, each entry carrying a 'uid'.
     * @param \stdClass $formdata Submitted form data; a ticked suggestion has a
     *        truthy 'sug_<uid>' field.
     * @return int The number of quests created.
     */
    public static function save_quest_suggestions(int $instanceid, array $suggestions, \stdClass $formdata): int {
        global $DB;

        $records = [];
        foreach ($suggestions as $sug) {
            $field = 'sug_' . $sug['uid'];
            if (!empty($formdata->$field)) {
                $records[] = \block_playerhud\quest::build_record_from_suggestion($instanceid, $sug);
            }
        }

        if (!empty($records)) {
            $DB->insert_records('block_playerhud_quests', $records);
        }

        return count($records);
    }

    /**
     * Creates the trade suggestions selected in the form, in one transaction.
     *
     * @param int $instanceid The owning block instance ID.
     * @param array $suggestions The suggestion list, each entry carrying a 'uid'.
     * @param \stdClass $formdata Submitted form data; a ticked suggestion has a
     *        truthy 'sug_<uid>' field.
     * @return int The number of trades created.
     */
    public static function save_trade_suggestions(int $instanceid, array $suggestions, \stdClass $formdata): int {
        global $DB;

        $count = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($suggestions as $sug) {
            $field = 'sug_' . $sug['uid'];
            if (empty($formdata->$field)) {
                continue;
            }
            \block_playerhud\game::create_trade_from_suggestion($instanceid, $sug);
            $count++;
        }
        $transaction->allow_commit();

        return $count;
    }
}
