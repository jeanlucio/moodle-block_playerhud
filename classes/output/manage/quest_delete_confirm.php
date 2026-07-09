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
 * Builds the template context for the quest-deletion confirmation screen.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

/**
 * Prepares the confirmation context shown before deleting quests that have already been
 * claimed by students, so a teacher sees the XP impact before confirming.
 *
 * Pure builder: it receives already-resolved values (no DB, no URL building) and returns the
 * array consumed by the manage_quest_delete_confirm template. Mirrors item_delete_confirm,
 * minus the trade-impact section (quests have no equivalent concept).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quest_delete_confirm {
    /**
     * Builds the template context for the confirmation screen.
     *
     * @param string $heading Pre-formatted heading (quest name, or a label for bulk).
     * @param \stdClass $xpimpact {studentcount, totalxp} from quests::find_xp_impact().
     * @param bool $isbulk Whether this confirms a bulk deletion.
     * @param int[] $ids The quest IDs involved (one for single, many for bulk).
     * @param array $urls URL strings keyed 'form', 'cancel' and (single only) 'toggle'.
     * @param string $sort Current sort column, carried through the form.
     * @param string $dir Current sort direction, carried through the form.
     * @return array The template context.
     */
    public static function build_context(
        string $heading,
        \stdClass $xpimpact,
        bool $isbulk,
        array $ids,
        array $urls,
        string $sort,
        string $dir
    ): array {
        // Only the bulk path posts the id list; the single path posts questid.
        $bulkids = [];
        if ($isbulk) {
            foreach ($ids as $id) {
                $bulkids[] = ['id' => $id];
            }
        }

        $hasxpimpact = $xpimpact->studentcount > 0;
        $xpwarning = $hasxpimpact
            ? get_string('quest_delete_xp_impact', 'block_playerhud', (object) [
                'students' => $xpimpact->studentcount,
                'xp'       => $xpimpact->totalxp,
            ])
            : '';
        $hasdisablelink = $hasxpimpact && !$isbulk && !empty($urls['toggle']);

        return [
            'heading'                => $heading,
            'has_xp_impact'          => $hasxpimpact,
            'xp_impact_warning'      => $xpwarning,
            'has_disable_link'       => $hasdisablelink,
            'disable_url'            => $urls['toggle'] ?? '',
            'str_disable_suggestion' => get_string('quest_delete_disable_suggestion', 'block_playerhud'),
            'str_disable_instead'    => get_string('quest_delete_disable_instead', 'block_playerhud'),
            'form_action'            => $urls['form'],
            'sesskey'                => sesskey(),
            'action'                 => $isbulk ? 'bulk_delete_quests_force' : 'delete_quest_force',
            'is_bulk'                => $isbulk,
            'quest_id'               => $isbulk ? 0 : ($ids[0] ?? 0),
            'bulk_ids'               => $bulkids,
            'sort'                   => $sort,
            'dir'                    => $dir,
            'cancel_url'             => $urls['cancel'],
            'str_cancel'             => get_string('cancel'),
            'confirm_label'          => get_string('quest_delete_confirm_simple', 'block_playerhud'),
        ];
    }
}
