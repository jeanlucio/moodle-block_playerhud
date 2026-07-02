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
 * Tests for the drop_distribution shared class.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use advanced_testcase;

/**
 * Tests for the drop_distribution shared class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\drop_distribution
 */
final class drop_distribution_test extends advanced_testcase {
    /** @var \stdClass Course used by every test. */
    protected $course;

    /**
     * Create a fresh course for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A course module whose table has an intro field is included as eligible.
     */
    public function test_get_eligible_modules_includes_forum(): void {
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => 'Avisos do curso',
        ]);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $this->course->id);

        $modules = drop_distribution::get_eligible_modules($this->course->id);

        $this->assertCount(1, $modules);
        $this->assertSame($cm->id, $modules[0]['cmid']);
        $this->assertSame('forum', $modules[0]['modname']);
        $this->assertSame('Avisos do curso', $modules[0]['name']);
    }

    /**
     * A course module pending deletion is excluded, even if its table has an intro field.
     */
    public function test_get_eligible_modules_excludes_deletion_in_progress(): void {
        global $DB;

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $this->course->id);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm->id]);
        rebuild_course_cache($this->course->id, true);

        $modules = drop_distribution::get_eligible_modules($this->course->id);

        $this->assertSame([], $modules);
    }

    /**
     * A course with no activities returns an empty eligible-modules list.
     */
    public function test_get_eligible_modules_returns_empty_for_activity_less_course(): void {
        $modules = drop_distribution::get_eligible_modules($this->course->id);

        $this->assertSame([], $modules);
    }

    /**
     * The module whose name best matches the haystack text is suggested.
     */
    public function test_suggest_module_returns_best_name_match(): void {
        $modules = [
            ['cmid' => 1, 'name' => 'Fórum de Avisos'],
            ['cmid' => 2, 'name' => 'Cristal Mágico da Sabedoria'],
        ];

        $best = drop_distribution::suggest_module('Cristal Magico', $modules);

        $this->assertSame(2, $best['cmid']);
    }

    /**
     * With no eligible modules, no suggestion can be made.
     */
    public function test_suggest_module_returns_null_when_no_modules(): void {
        $this->assertNull(drop_distribution::suggest_module('anything', []));
    }

    /**
     * A drop code already present in a module's intro is found by find_inserted_cmids.
     */
    public function test_find_inserted_cmids_finds_existing_shortcode(): void {
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'intro' => 'Bem-vindos! [PLAYERHUD_DROP code=ABC123]',
        ]);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $this->course->id);
        $modules = drop_distribution::get_eligible_modules($this->course->id);

        $result = drop_distribution::find_inserted_cmids([42 => 'ABC123'], $modules);

        // Note: get_coursemodule_from_instance() returns id as a raw DB string, while
        // get_eligible_modules() sources cmid from cached modinfo, normalised to int.
        $this->assertSame([(int) $cm->id], $result[42]['cmids']);
        $this->assertSame((int) $cm->id, $result[42]['first_cmid']);
        $this->assertSame('intro', $result[42]['first_field']);
    }

    /**
     * A drop code not present anywhere yields an empty cmids list for that drop.
     */
    public function test_find_inserted_cmids_returns_empty_when_code_not_found(): void {
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'intro' => 'No shortcode here.',
        ]);
        $modules = drop_distribution::get_eligible_modules($this->course->id);

        $result = drop_distribution::find_inserted_cmids([42 => 'NOPE99'], $modules);

        $this->assertSame([], $result[42]['cmids']);
        $this->assertNull($result[42]['first_cmid']);
    }

    /**
     * Empty inputs short-circuit to an empty result without querying anything.
     */
    public function test_find_inserted_cmids_handles_empty_inputs(): void {
        $this->assertSame([], drop_distribution::find_inserted_cmids([], []));
        $this->assertSame([], drop_distribution::find_inserted_cmids([1 => 'X'], []));
    }
}
