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
}
