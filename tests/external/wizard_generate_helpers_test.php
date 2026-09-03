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
 * Tests for wizard_generate's static helper methods (no network involved).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for wizard_generate's pure static helper methods — build_step_types(),
 * compute_shared_xp_shares(), resolve_or_create_progress_item() and
 * resolve_previous_chapter_context() — reached directly rather than through the web service
 * entry point, since none of them call the AI or depend on which entry point (wizard_start,
 * wizard_run_step) drives them.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_generate
 */
final class wizard_generate_helpers_test extends external_base_testcase {
    /**
     * build_step_types() must return exactly one step type per checked module, in the fixed
     * order the browser drives them one at a time, ending with auto_distribute — see
     * wizard_start's step plan.
     */
    public function test_build_step_types_matches_selected_modules_in_order(): void {
        $params = self::wizard_generate_params([
            'include_items' => true,
            'include_ranking' => true,
            'include_avatars' => true,
            'include_missions' => true,
            'distribute_items' => true,
        ]);

        $this->assertSame(
            ['items', 'missions', 'avatars', 'ranking', 'auto_distribute'],
            wizard_generate::build_step_types($params)
        );
    }

    /**
     * The auto_distribute step is skipped when Items is selected but its own distribute
     * checkbox was left off — nothing would be forwarded to it, so it earns no step of its own.
     */
    public function test_build_step_types_skips_auto_distribute_when_items_distribute_is_off(): void {
        $params = self::wizard_generate_params([
            'include_items' => true,
            'distribute_items' => false,
        ]);

        $this->assertSame(['items'], wizard_generate::build_step_types($params));
    }

    /**
     * With every module flag left off, the plan is empty.
     */
    public function test_build_step_types_empty_when_nothing_selected(): void {
        $this->assertSame([], wizard_generate::build_step_types(self::wizard_generate_params([])));
    }

    /**
     * compute_shared_xp_shares() is a no-op — no economy_health() query, empty shares — when
     * neither Items nor Missions is selected, since nothing would consume the shared XP room.
     * Pill/Latepenalty are also excluded here, so their bonus XP is 0 too (unused).
     */
    public function test_compute_shared_xp_shares_empty_when_items_and_missions_excluded(): void {
        [$itemshares, $missionshares, $pillbonus, $latepenaltybonus] = wizard_generate::compute_shared_xp_shares(
            $this->instanceid,
            new \stdClass(),
            self::wizard_generate_params(['include_playercoin' => true])
        );

        $this->assertSame([], $itemshares);
        $this->assertSame([], $missionshares);
        $this->assertSame(0, $pillbonus);
        $this->assertSame(0, $latepenaltybonus);
    }

    /**
     * Without Items/Missions, Pill and Latepenalty keep their own fixed default reward instead
     * of competing for a share of the ceiling — there is no active budget context to reconcile
     * against, and handing either of them the *entire* remaining gap (as a 1-element shared
     * distribution would) would be a wildly disproportionate single-quest reward.
     */
    public function test_compute_shared_xp_shares_pill_and_latepenalty_use_defaults_when_alone(): void {
        [, , $pillbonus, $latepenaltybonus] = wizard_generate::compute_shared_xp_shares(
            $this->instanceid,
            new \stdClass(),
            self::wizard_generate_params(['include_pill' => true, 'include_latepenalty' => true])
        );

        $this->assertSame(150, $pillbonus);
        $this->assertSame(40, $latepenaltybonus);
    }

    /**
     * With Items/Missions in the same run, Pill and Latepenalty each claim one more slice of the
     * same shared distribution — so a full run (Items + Missions + Pill + Latepenalty) lands its
     * combined total on exactly the level ceiling, instead of overshooting it by their old fixed
     * defaults (150 + 40 = 190 XP that the budget never accounted for).
     */
    public function test_compute_shared_xp_shares_pill_and_latepenalty_share_the_budget_with_items(): void {
        $config = (object) ['xp_per_level' => 100, 'max_levels' => 20];
        $params = self::wizard_generate_params([
            'include_items' => true,
            'include_missions' => true,
            'include_pill' => true,
            'include_latepenalty' => true,
            'size' => 'short',
        ]);

        [$itemshares, $missionshares, $pillbonus, $latepenaltybonus] = wizard_generate::compute_shared_xp_shares(
            $this->instanceid,
            $config,
            $params
        );

        $total = array_sum($itemshares) + array_sum($missionshares) + $pillbonus + $latepenaltybonus;
        $this->assertSame(2000, $total);
    }

    /**
     * resolve_or_create_progress_item() creates the item on first call and reuses the same ID
     * on a second call — the story-arc chapter step relies on this to never duplicate it across
     * the arc's several chapter steps.
     */
    public function test_resolve_or_create_progress_item_is_idempotent(): void {
        global $DB;

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);

        $first = wizard_generate::resolve_or_create_progress_item($this->instanceid, 'fantasy', $runid);
        $second = wizard_generate::resolve_or_create_progress_item($this->instanceid, 'fantasy', $runid);

        $this->assertSame($first, $second);
        $this->assertEquals(1, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * This is what lets the merged RPG checkbox (classes, Chapter 1 and the rest of the AI story
     * arc) run without the teacher ever ticking "Item de progresso" on its own: on an instance
     * with none yet, the item created on demand must be indistinguishable from one
     * generate_progress_item() created directly — correct tone-based name and emoji, an infinite
     * drop (maxusage 0, so future chapters can spend it any number of times), and both rows
     * recorded in the run's manifest so an interrupted run can still be rolled back.
     */
    public function test_resolve_or_create_progress_item_creates_a_complete_item_when_missing(): void {
        global $DB;

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);
        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));

        $itemid = wizard_generate::resolve_or_create_progress_item($this->instanceid, 'scifi', $runid);

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame(get_string('wizard_progress_item_name_scifi', 'block_playerhud'), $item->name);
        $this->assertSame("\u{1F50B}", $item->image);
        $this->assertEquals(0, (int) $item->tradable);
        $this->assertSame('', $item->action_type);

        $drop = $DB->get_record('block_playerhud_drops', ['itemid' => $itemid], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $drop->maxusage, 'Infinite drop: future chapters can spend it any number of times.');

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        $this->assertCount(2, $manifest, 'item + drop, so an interrupted run can still roll it back.');
    }

    /**
     * resolve_previous_chapter_context() is empty for an instance with no chapters yet, and
     * combines the latest chapter's title/intro with its starting node's real text once one
     * exists — the story-arc chapter step uses this, read from the database rather than
     * trusting the browser, to keep each new AI chapter consistent with the one before it.
     */
    public function test_resolve_previous_chapter_context_reads_the_latest_chapter(): void {
        $this->assertSame('', wizard_generate::resolve_previous_chapter_context($this->instanceid));

        global $DB;
        $chapter = $this->create_chapter('The Sunken Library');
        $DB->set_field('block_playerhud_chapters', 'intro_text', 'A flooded archive of secrets.', ['id' => $chapter->id]);
        $this->create_node($chapter->id, 'You wade into the flooded archive, torch held high.', true);

        $context = wizard_generate::resolve_previous_chapter_context($this->instanceid);
        $this->assertStringContainsString('The Sunken Library', $context);
        $this->assertStringContainsString('A flooded archive of secrets.', $context);
        $this->assertStringContainsString('You wade into the flooded archive, torch held high.', $context);
    }

    /**
     * With more drops than eligible activities, distribute_drops() must cap each activity's
     * share via compute_activity_quotas() instead of letting every drop independently pick its
     * own best name match — without a cap, name-similarity alone could stack every drop onto
     * the single best-scoring activity even when several exist. Five drops over two activities
     * must split 3/2 (the exact compute_activity_quotas(5, 2) shares), never 5/0.
     */
    public function test_distribute_drops_caps_each_activity_to_its_quota(): void {
        global $DB;

        $pagea = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Alpha Wing',
            'content' => 'Original alpha body.',
        ]);
        $pageb = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Beta Wing',
            'content' => 'Original beta body.',
        ]);

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);
        $dropids = [];
        for ($i = 1; $i <= 5; $i++) {
            $item = $this->create_item($this->instanceid, 'Relic ' . $i);
            $dropids[] = (int) $DB->insert_record('block_playerhud_drops', (object) [
                'blockinstanceid' => $this->instanceid,
                'itemid' => $item->id,
                'name' => $item->name,
                'maxusage' => 1,
                'respawntime' => 0,
                'code' => \block_playerhud\utils::generate_drop_code($this->instanceid),
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $message = wizard_generate::distribute_drops($this->instanceid, $this->course->id, $dropids, $runid);
        $this->assertSame('', $message);

        $countshortcodes = fn(string $content): int => substr_count($content, '[PLAYERHUD_DROP');
        $contenta = (string) $DB->get_field('page', 'content', ['id' => $pagea->id]);
        $contentb = (string) $DB->get_field('page', 'content', ['id' => $pageb->id]);

        $this->assertSame(5, $countshortcodes($contenta) + $countshortcodes($contentb));
        $this->assertLessThanOrEqual(3, $countshortcodes($contenta), 'No activity may exceed its quota share.');
        $this->assertLessThanOrEqual(3, $countshortcodes($contentb), 'No activity may exceed its quota share.');
    }

    /**
     * With fewer drops than eligible activities, distribute_drops() must spread them across the
     * whole course — not pile the first N activities and leave every later one empty — and must
     * never target a label, whose inline media banner is no place for an auto-planted drop card.
     * Eight drops over a course of 14 pages + 2 labels: labels get nothing, and at least one
     * drop lands in the final third of the page list.
     */
    public function test_distribute_drops_spreads_across_all_activities_and_skips_labels(): void {
        global $DB;

        $pageids = [];
        for ($i = 0; $i < 16; $i++) {
            if ($i === 2 || $i === 13) {
                $this->getDataGenerator()->create_module('label', [
                    'course' => $this->course->id,
                    'intro' => 'Banner de mídia ' . $i,
                ]);
                continue;
            }
            $page = $this->getDataGenerator()->create_module('page', [
                'course' => $this->course->id,
                'name' => 'Módulo ' . $i,
                'content' => 'Corpo original ' . $i,
            ]);
            $pageids[] = (int) $page->id;
        }

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);
        $dropids = [];
        for ($i = 1; $i <= 8; $i++) {
            $item = $this->create_item($this->instanceid, 'Troféu ' . $i);
            $dropids[] = (int) $DB->insert_record('block_playerhud_drops', (object) [
                'blockinstanceid' => $this->instanceid,
                'itemid' => $item->id,
                'name' => $item->name,
                'maxusage' => 1,
                'respawntime' => 0,
                'code' => \block_playerhud\utils::generate_drop_code($this->instanceid),
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        $message = wizard_generate::distribute_drops($this->instanceid, $this->course->id, $dropids, $runid);
        $this->assertSame('', $message);

        $has = fn(string $text): bool => strpos($text, '[PLAYERHUD_DROP') !== false;

        // No label received a shortcode.
        foreach ($DB->get_records('label', ['course' => $this->course->id]) as $label) {
            $this->assertFalse($has((string) $label->intro), 'A label must never receive an auto-distributed drop.');
        }

        // Exactly the 8 drops landed, and they reached beyond the first 8 pages.
        $pagehits = [];
        foreach ($pageids as $index => $pageid) {
            if ($has((string) $DB->get_field('page', 'content', ['id' => $pageid]))) {
                $pagehits[] = $index;
            }
        }
        $this->assertCount(8, $pagehits);
        $this->assertGreaterThanOrEqual(
            9,
            max($pagehits),
            'Drops must spread across the whole course, not concentrate on the first activities.'
        );
    }

    /**
     * A drop id belonging to another block instance must never be looked up or distributed —
     * distribute_drops() receives $dropids straight from the client (accumulated across wizard
     * steps), so a foreign id mixed into the list must be silently dropped instead of leaking
     * its name or inflating compute_activity_quotas()'s count.
     */
    public function test_distribute_drops_ignores_a_drop_from_another_instance(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Only Wing',
            'content' => 'Original body.',
        ]);

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);
        $ownitem = $this->create_item($this->instanceid, 'Own Relic');
        $owndropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid' => $ownitem->id,
            'name' => $ownitem->name,
            'maxusage' => 1,
            'respawntime' => 0,
            'code' => \block_playerhud\utils::generate_drop_code($this->instanceid),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $foreigninstanceid = $this->create_block_instance();
        $foreignitem = $this->create_item($foreigninstanceid, 'Foreign Relic');
        $foreigndropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $foreigninstanceid,
            'itemid' => $foreignitem->id,
            'name' => $foreignitem->name,
            'maxusage' => 1,
            'respawntime' => 0,
            'code' => \block_playerhud\utils::generate_drop_code($foreigninstanceid),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $message = wizard_generate::distribute_drops(
            $this->instanceid,
            $this->course->id,
            [$owndropid, $foreigndropid],
            $runid
        );
        $this->assertSame('', $message);

        $content = (string) $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertSame(1, substr_count($content, '[PLAYERHUD_DROP'), 'Only the own-instance drop must be placed.');
        $this->assertStringNotContainsString('Foreign Relic', $content);
    }

    /**
     * An empty $dropids list (nothing left to distribute) must return early instead of calling
     * get_in_or_equal() with an empty array, which throws a coding_exception — a state the
     * client can legitimately reach, not a programmer mistake.
     */
    public function test_distribute_drops_returns_early_when_dropids_is_empty(): void {
        $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Only Wing',
            'content' => 'Original body.',
        ]);

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);

        $message = wizard_generate::distribute_drops($this->instanceid, $this->course->id, [], $runid);

        $this->assertSame('', $message);
    }

    /**
     * Builds a params array matching wizard_start::execute_parameters()'s shape, with every
     * include_* flag defaulting to false and every distribute_* flag defaulting to true (its
     * real default) — mirrors what wizard_start's own validate_parameters() produces, since
     * build_step_types()/compute_shared_xp_shares() are called with that same validated array,
     * not raw booleans.
     *
     * @param array $overrides Flags to override, e.g. ['include_missions' => true].
     * @return array The params array.
     */
    private static function wizard_generate_params(array $overrides): array {
        return array_merge([
            'instanceid' => 0,
            'courseid' => 0,
            'theme' => '',
            'tone' => '',
            'size' => 'short',
            'include_items' => false,
            'include_missions' => false,
            'include_playercoin' => false,
            'include_avatars' => false,
            'include_rpg' => false,
            'tone_key' => 'fantasy',
            'distribute_items' => true,
            'include_progress_item' => false,
            'include_next_chapter' => false,
            'include_comercio' => false,
            'include_pill' => false,
            'include_latepenalty' => false,
            'include_secret_drops' => false,
            'include_ranking' => false,
            'distribute_progress_item' => true,
            'distribute_playercoin' => true,
            'distribute_pill' => true,
            'distribute_secret' => true,
        ], $overrides);
    }
}
