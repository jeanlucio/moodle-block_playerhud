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
 * Tests for the wizard_run_step web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;
use core_external\external_api;

/**
 * Tests for the wizard_run_step web service — running one module at a time from a plan built by
 * wizard_start, no network involved (AI-backed steps like "items"/"next_chapter" are exercised
 * manually, same exclusion as wizard_generate_test.php).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_run_step
 */
final class wizard_run_step_test extends external_base_testcase {
    /**
     * Starts a run with no modules selected (build_step_types() only cares about the flags,
     * not what actually runs afterwards) and returns its run ID, for tests that call
     * wizard_run_step directly against a single step type.
     *
     * @return int The new run ID.
     */
    private function start_empty_run(): int {
        return \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);
    }

    /**
     * Running the "playercoin" step creates the item, reports it in counts.items, and records
     * it in the run's rollback manifest — same effect as calling generate_playercoin() directly,
     * just reached through the step-by-step entry point.
     */
    public function test_playercoin_step_creates_item_and_reports_counts(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'playercoin', '');

        $cleaned = external_api::clean_returnvalue(wizard_run_step::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);
        $this->assertSame(1, $cleaned['counts']['items']);
        $this->assertSame(0, $cleaned['counts']['quests']);

        $this->assertEquals(1, $DB->count_records('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'playercoin',
        ]));
        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        $this->assertCount(1, $manifest);
    }

    /**
     * The "playercoin" step's own "distribute" flag reaches generate_playercoin() the same way
     * it does through the single-call execute() — even with a news forum available, passing
     * distribute: false must still create the item without a drop.
     */
    public function test_playercoin_step_respects_distribute_false(): void {
        global $DB;

        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => '',
        ]);

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute(
            instanceid: $this->instanceid,
            courseid: $this->course->id,
            runid: $runid,
            steptype: 'playercoin',
            theme: '',
            distribute: false
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['counts']['items']);
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops'));
    }

    /**
     * The "rpg" step separates class and chapter counts — Chapter 1 is fixed content, never
     * AI-generated, so this step is deterministic and safe to exercise here.
     */
    public function test_rpg_step_reports_classes_and_chapter_counts(): void {
        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'rpg',
            '',
            '',
            'fantasy'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['counts']['classes']);
        $this->assertSame(1, $result['counts']['chapters']);
        $this->assertSame(0, $result['counts']['items']);
    }

    /**
     * An unknown step type fails gracefully (success: false, a message) rather than throwing —
     * the browser-driven loop relies on this to show its retry/undo choice instead of crashing.
     */
    public function test_unknown_step_type_returns_failure_without_throwing(): void {
        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'not_a_real_step', '');

        $this->assertFalse($result['success']);
        $this->assertNotSame('', $result['message']);
    }

    /**
     * A failed step does not finish the run — even when it was meant to be the last one, the
     * run stays 'running' so a retry can still write into the same manifest.
     */
    public function test_failed_last_step_does_not_finish_the_run(): void {
        global $DB;

        $runid = $this->start_empty_run();
        wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'not_a_real_step',
            '',
            '',
            'fantasy',
            'short',
            [],
            [],
            [],
            true
        );

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame('running', $run->status);
    }

    /**
     * is_last_step finishes the run, and report_economy only then attaches a non-empty economy
     * summary — a non-last or non-reporting call leaves economy_message empty.
     */
    public function test_last_step_finishes_run_and_reports_economy_only_when_requested(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $notlast = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'ranking',
            '',
            '',
            'fantasy',
            'short',
            [],
            [],
            [],
            false,
            true
        );
        $this->assertSame('', $notlast['economy_message']);
        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame('running', $run->status);

        $last = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'ranking',
            '',
            '',
            'fantasy',
            'short',
            [],
            [],
            [],
            true,
            true
        );
        $this->assertNotSame('', $last['economy_message']);
        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame('done', $run->status);
    }

    /**
     * The "auto_distribute" step forwards the drop IDs accumulated from earlier steps (passed
     * in by the browser) straight into distribute_drops() — with no eligible course activities
     * yet, that is a tolerated no-op that returns the "come back later" message.
     */
    public function test_auto_distribute_step_forwards_accumulated_drop_ids(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $item = $this->create_item($this->instanceid, 'Coin');
        $now = time();
        $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid' => $item->id,
            'name' => $item->name,
            'maxusage' => 1,
            'respawntime' => 0,
            'code' => \block_playerhud\utils::generate_drop_code($this->instanceid),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $result = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'auto_distribute',
            '',
            '',
            'fantasy',
            'short',
            [],
            [],
            [$dropid]
        );

        $this->assertTrue($result['success']);
        $this->assertNotSame('', $result['message']);
    }

    /**
     * § 5.9 Fatia 2: the "story_outline" step is AI-backed and exercised manually with a real
     * key (like "items"/"next_chapter" in wizard_generate_test.php); this only proves that with
     * no key configured it fails gracefully (success: false, no exception) and writes nothing —
     * the internal 1x retry (see generate_with_retry()) still ends in failure when both attempts
     * have no key to call, rather than looping forever or throwing past the try/catch.
     */
    public function test_story_outline_step_fails_gracefully_without_an_ai_key(): void {
        global $DB;

        set_config('apikey_gemini', '', 'block_playerhud');
        set_config('apikey_groq', '', 'block_playerhud');
        set_config('apikey_openai', '', 'block_playerhud');

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'story_outline', 'A haunted forest');

        $this->assertFalse($result['success']);
        $this->assertSame([], $result['arc_beats']);
        $this->assertEquals(0, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * Same as above for an individual "story_chapter_N" step — it must not create the progress
     * item nor any chapter/node/choice row when the AI call itself never succeeds.
     */
    public function test_story_chapter_step_fails_gracefully_without_an_ai_key(): void {
        global $DB;

        set_config('apikey_gemini', '', 'block_playerhud');
        set_config('apikey_groq', '', 'block_playerhud');
        set_config('apikey_openai', '', 'block_playerhud');

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'story_chapter_1',
            'A haunted forest',
            '',
            'fantasy',
            'short',
            [],
            [],
            [],
            false,
            false,
            ['Chapter 2: the descent begins.']
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_wizard_run_step_requires_manage_capability(): void {
        $runid = $this->start_empty_run();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'ranking', '');
    }

    /**
     * A runid that belongs to a different block instance must be rejected — the manifest must
     * never be written to on behalf of a run the caller does not own.
     */
    public function test_wizard_run_step_rejects_runid_from_other_instance(): void {
        $otherinstanceid = $this->create_block_instance();
        $foreignrunid = \block_playerhud\local\wizard::start_run($otherinstanceid, 2, []);

        $this->expectException(\dml_missing_record_exception::class);
        wizard_run_step::execute($this->instanceid, $this->course->id, $foreignrunid, 'ranking', '');
    }

    /**
     * A client retry of the "missions" step for a run that already succeeded must not create a
     * second batch of quests — same manifest-based guard as generate_items(), exercised here
     * through the real, AI-free "missions" step.
     */
    public function test_missions_step_retry_does_not_duplicate(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $first = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'missions', '');
        $this->assertTrue($first['success']);
        $this->assertGreaterThan(0, $first['counts']['quests']);

        $second = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'missions', '');

        $this->assertTrue($second['success']);
        $this->assertSame($first['counts']['quests'], $second['counts']['quests']);
        $this->assertEquals(
            $first['counts']['quests'],
            $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]),
            'Retrying the same step must not create a second batch of quests.'
        );
    }

    /**
     * generate_items() must reuse a run's already-recorded items/drops instead of calling the AI
     * again — proven here with no AI key configured at all: if the guard did not short-circuit
     * before the AI call, this would fail or return an empty batch instead of the fixture's own
     * item.
     */
    public function test_generate_items_reuses_manifest_when_step_already_ran(): void {
        set_config('apikey_gemini', '', 'block_playerhud');
        set_config('apikey_groq', '', 'block_playerhud');
        set_config('apikey_openai', '', 'block_playerhud');

        $runid = $this->start_empty_run();
        $item = $this->create_item($this->instanceid, 'Already generated item');
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_items', $item->id);

        $result = wizard_generate::generate_items($this->instanceid, new \stdClass(), '', '', 'short', [], $runid);

        $this->assertSame(['Already generated item'], $result['names']);
        $this->assertSame([], $result['drop_ids']);
    }

    /**
     * Requesting only the Missions module creates quests and a rollback manifest, without
     * touching the item/drop tables.
     */
    public function test_missions_step_creates_quests_and_manifest(): void {
        global $DB;

        $this->create_item($this->instanceid, 'Sword');
        $this->create_item($this->instanceid, 'Shield');

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'missions', '');

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['counts']['quests']);
        $this->assertSame(0, $result['counts']['items']);

        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount($result['counts']['quests'], $quests);

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        $this->assertCount(count($quests), $manifest);
        foreach ($manifest as $entry) {
            $this->assertSame('block_playerhud_quests', $entry->objecttable);
        }
    }

    /**
     * Requesting Missions also ensures the block's own enable_quests setting is on — a teacher
     * who had the Missions tab turned off still sees what the wizard just generated there.
     */
    public function test_missions_step_ensures_enable_quests_is_on(): void {
        \block_instance_by_id($this->instanceid)->instance_config_save((object) ['enable_quests' => 0]);

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'missions', '');

        $this->assertTrue($result['success']);
        $blockinstance = \block_instance_by_id($this->instanceid);
        $this->assertSame(1, (int) $blockinstance->config->enable_quests);
    }

    /**
     * Rolling back a Missions-only run removes the created quests.
     */
    public function test_missions_step_run_can_be_rolled_back(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'missions', '');
        $this->assertTrue($result['success']);

        $deleted = \block_playerhud\local\wizard::rollback($runid, $this->instanceid, $this->course->id);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * More candidate missions than the journey's mission count are trimmed down to the limit,
     * drawing from more than one candidate type rather than exhausting the first.
     */
    public function test_missions_step_are_capped_by_journey_size_and_stay_mixed(): void {
        global $DB;

        for ($i = 1; $i <= 4; $i++) {
            $this->create_item($this->instanceid, "Item $i");
        }

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'missions', '');

        $this->assertTrue($result['success']);
        // Short size caps at 3 missions, even though level (5/10/15) and unique-item (2/4)
        // milestones together offer 5 candidates.
        $this->assertSame(3, $result['counts']['quests']);

        $quests = array_values($DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
        $types = array_unique(array_map(static fn($q): int => (int) $q->type, $quests));
        $this->assertGreaterThan(1, count($types), 'Selection must not be entirely one candidate type.');
    }

    /**
     * Every selected mission's reward_xp is overridden to a deterministic share of the XP room
     * (instead of each type's own hardcoded formula: level*20, items*30...), and the shares
     * always sum to exactly the gap — the division's remainder lands as a +1 bonus on the first
     * selected missions rather than being quietly lost. The shares themselves are computed once
     * up front by wizard_start in production (compute_shared_xp_shares()); this test replicates
     * that single call before driving the step, the same way the browser would forward it.
     */
    public function test_missions_step_reward_xp_is_an_even_share_of_the_gap(): void {
        global $DB;

        for ($i = 1; $i <= 4; $i++) {
            $this->create_item($this->instanceid, "Item $i");
        }

        [, $missionxpshares] = wizard_generate::compute_shared_xp_shares($this->instanceid, new \stdClass(), [
            'include_items' => false,
            'include_missions' => true,
            'include_pill' => false,
            'include_latepenalty' => false,
            'size' => 'short',
        ]);

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute(
            instanceid: $this->instanceid,
            courseid: $this->course->id,
            runid: $runid,
            steptype: 'missions',
            theme: '',
            missionxpshares: $missionxpshares
        );

        $this->assertTrue($result['success']);
        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid], 'id ASC');
        $rewards = array_values(array_map(static fn($q): int => (int) $q->reward_xp, $quests));
        // Empty economy: ceiling 100 * 20 = 2000, 3 missions selected -> base 666, remainder 2,
        // so the first 2 missions get 667 and the last gets 666. Sum is exactly 2000.
        $this->assertSame([667, 667, 666], $rewards);
        $this->assertSame(2000, array_sum($rewards));
    }

    /**
     * A level-milestone mission's name is re-flavoured for the chosen tone, replacing the
     * generic "Reach Level N" wording.
     */
    public function test_missions_step_names_are_tone_flavoured(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            'missions',
            '',
            '',
            'scifi'
        );

        $this->assertTrue($result['success']);
        $names = array_map(
            static fn($q): string => $q->name,
            $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid])
        );
        $this->assertContains('Reach access level 5', $names);
    }

    /**
     * Activity-completion suggestions, filtered out of the wizard before, are now included: a
     * completion-enabled activity produces a tone-flavoured "complete this activity" mission.
     */
    public function test_missions_step_include_activity_completion(): void {
        global $CFG, $DB;

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Intro Reading',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $coursecontext = \context_course::instance($course->id);
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud',
            'parentcontextid' => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => base64_encode(serialize((object) [])),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $runid = \block_playerhud\local\wizard::start_run($instanceid, 2, []);
        $result = wizard_run_step::execute($instanceid, $course->id, $runid, 'missions', '');

        $this->assertTrue($result['success']);
        $names = array_map(
            static fn($q): string => $q->name,
            $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $instanceid])
        );
        $this->assertContains('Complete the trial: Intro Reading', $names);
    }

    /**
     * PlayerCoin and Avatars are mechanical (no AI/network) and create items with a manifest —
     * driven here as the browser would, one step at a time against the same run.
     */
    public function test_playercoin_and_avatars_steps_create_items_and_manifest(): void {
        global $DB;

        $runid = $this->start_empty_run();
        $playercoinresult = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'playercoin', '');
        $avatarsresult = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'avatars', '');

        $this->assertTrue($playercoinresult['success']);
        $this->assertTrue($avatarsresult['success']);
        $totalitems = $playercoinresult['counts']['items'] + $avatarsresult['counts']['items'];
        $this->assertGreaterThan(1, $totalitems);

        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount($totalitems, $items);
        $this->assertTrue($DB->record_exists('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'playercoin',
        ]));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        $this->assertCount(count($items), $manifest);
        foreach ($manifest as $entry) {
            $this->assertSame('block_playerhud_items', $entry->objecttable);
        }
    }

    /**
     * Running the "playercoin" step twice, in two different runs, must not duplicate the item
     * nor record it into the second run's manifest, since nothing new was actually created.
     */
    public function test_playercoin_step_is_idempotent_across_runs(): void {
        global $DB;

        $firstrunid = $this->start_empty_run();
        $first = wizard_run_step::execute($this->instanceid, $this->course->id, $firstrunid, 'playercoin', '');
        $this->assertSame(1, $first['counts']['items']);

        $secondrunid = $this->start_empty_run();
        $second = wizard_run_step::execute($this->instanceid, $this->course->id, $secondrunid, 'playercoin', '');
        $this->assertSame(0, $second['counts']['items']);

        $this->assertEquals(1, $DB->count_records('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'playercoin',
        ]));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $secondrunid]);
        $this->assertCount(0, $manifest);
    }

    /**
     * Rolling back a PlayerCoin + Avatars run removes the created items.
     */
    public function test_playercoin_and_avatars_steps_run_can_be_rolled_back(): void {
        global $DB;

        $runid = $this->start_empty_run();
        wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'playercoin', '');
        wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'avatars', '');

        $deleted = \block_playerhud\local\wizard::rollback($runid, $this->instanceid, $this->course->id);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * When the course has a news forum, the "playercoin" step auto-distributes its drop into it
     * and records both the drop and the shortcode for rollback — undoing the run must remove the
     * item, the drop and strip the shortcode back out of the forum intro.
     */
    public function test_playercoin_step_auto_distributes_drop_and_rolls_back_cleanly(): void {
        global $DB;

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => '',
        ]);

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'playercoin', '');
        $this->assertSame(1, $result['counts']['items']);

        $item = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type'     => 'playercoin',
        ], '*', MUST_EXIST);
        $drop = $DB->get_record('block_playerhud_drops', ['itemid' => $item->id], '*', MUST_EXIST);

        $introafter = $DB->get_field('forum', 'intro', ['id' => $forum->id]);
        $this->assertStringContainsString('[PLAYERHUD_DROP code=' . $drop->code . ']', $introafter);

        $manifesttables = $DB->get_records_menu(
            'block_playerhud_wizard_objects',
            ['runid' => $runid],
            '',
            'id, objecttable'
        );
        $this->assertContains('block_playerhud_drops', $manifesttables);
        $this->assertCount(1, $DB->get_records('block_playerhud_wizard_shortcodes', ['runid' => $runid]));

        \block_playerhud\local\wizard::rollback($runid, $this->instanceid, $this->course->id);

        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $drop->id]));
        $introrolledback = $DB->get_field('forum', 'intro', ['id' => $forum->id]);
        $this->assertStringNotContainsString('PLAYERHUD_DROP', (string) $introrolledback);
    }

    /**
     * The "comercio" step wires PlayerCoin<->Avatar Pack trades from whatever already exists in
     * the instance (not just this run's own creations), and records the trade plus its
     * requirement and reward rows into the manifest.
     */
    public function test_comercio_step_wires_playercoin_and_avatars_with_manifest(): void {
        global $DB;

        $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $avatar = $this->create_item($this->instanceid, 'Fox', ['action_type' => 'avatar_profile']);

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'comercio', '');

        $this->assertTrue($result['success']);
        // One avatar yields 2 suggestions: the individual trade for it, plus the "bundle all
        // avatars" trade — see game::build_trade_suggestions().
        $this->assertSame(2, $result['counts']['trades']);

        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(2, $trades);
        foreach ($trades as $trade) {
            $this->assertSame(1, (int) $trade->onetime);
            $req = $DB->get_record('block_playerhud_trade_reqs', ['tradeid' => $trade->id], '*', MUST_EXIST);
            $this->assertGreaterThan(0, (int) $req->qty);
            $reward = $DB->get_record(
                'block_playerhud_trade_rewards',
                ['tradeid' => $trade->id, 'itemid' => $avatar->id],
                '*',
                MUST_EXIST
            );
            $this->assertSame($avatar->id, (int) $reward->itemid);
        }

        $manifesttables = array_column(
            $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]),
            'objecttable'
        );
        $this->assertContains('block_playerhud_trades', $manifesttables);
        $this->assertContains('block_playerhud_trade_reqs', $manifesttables);
        $this->assertContains('block_playerhud_trade_rewards', $manifesttables);
    }

    /**
     * Running the "comercio" step twice, in two different runs, must not duplicate a trade that
     * already covers the same avatar.
     */
    public function test_comercio_step_is_idempotent_across_runs(): void {
        $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $this->create_item($this->instanceid, 'Fox', ['action_type' => 'avatar_profile']);

        $firstrunid = $this->start_empty_run();
        $first = wizard_run_step::execute($this->instanceid, $this->course->id, $firstrunid, 'comercio', '');
        $this->assertGreaterThan(0, $first['counts']['trades']);

        $secondrunid = $this->start_empty_run();
        $second = wizard_run_step::execute($this->instanceid, $this->course->id, $secondrunid, 'comercio', '');
        $this->assertSame(0, $second['counts']['trades']);
    }

    /**
     * Rolling back a "comercio" run removes the trade and its requirement/reward rows.
     */
    public function test_comercio_step_run_can_be_rolled_back(): void {
        global $DB;

        $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $this->create_item($this->instanceid, 'Fox', ['action_type' => 'avatar_profile']);

        $runid = $this->start_empty_run();
        $result = wizard_run_step::execute($this->instanceid, $this->course->id, $runid, 'comercio', '');
        $this->assertTrue($result['success']);

        \block_playerhud\local\wizard::rollback($runid, $this->instanceid, $this->course->id);

        $this->assertEquals(0, $DB->count_records('block_playerhud_trades', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_reqs', []));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_rewards', []));
    }
}
