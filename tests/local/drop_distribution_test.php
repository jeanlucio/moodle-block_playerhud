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
 * @covers     \block_playerhud\utils
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
     * The course's own news forum is excluded, since it is reserved for PlayerCoin and Secret
     * Drops' own discreet placement — a regular (non-news) forum in the same course is still
     * included.
     */
    public function test_get_eligible_modules_excludes_news_forum(): void {
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type' => 'news',
        ]);
        $regularforum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'name' => 'Fórum de dúvidas',
        ]);
        $regularcm = get_coursemodule_from_instance('forum', $regularforum->id, $this->course->id);

        $modules = drop_distribution::get_eligible_modules($this->course->id);
        $this->assertCount(1, $modules);
        $this->assertSame($regularcm->id, $modules[0]['cmid']);
    }

    /**
     * A course with no activities returns an empty eligible-modules list.
     */
    public function test_get_eligible_modules_returns_empty_for_activity_less_course(): void {
        $modules = drop_distribution::get_eligible_modules($this->course->id);

        $this->assertSame([], $modules);
    }

    /**
     * A label is eligible by default but excluded when $includelabels is false — the mode the
     * wizard's automatic distribution uses so drop cards never land inside an inline media banner.
     */
    public function test_get_eligible_modules_excludes_labels_on_request(): void {
        $this->getDataGenerator()->create_module('label', [
            'course' => $this->course->id,
            'intro' => 'Banner com imagens',
        ]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Introdução',
        ]);
        $pagecm = get_coursemodule_from_instance('page', $page->id, $this->course->id);

        $withlabels = drop_distribution::get_eligible_modules($this->course->id);
        $this->assertCount(2, $withlabels);
        $this->assertContains('label', array_column($withlabels, 'modname'));

        $nolabels = drop_distribution::get_eligible_modules($this->course->id, false);
        $this->assertCount(1, $nolabels);
        $this->assertSame((int) $pagecm->id, (int) $nolabels[0]['cmid']);
        $this->assertNotContains('label', array_column($nolabels, 'modname'));
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

    /**
     * compute_activity_quotas always sums to exactly the target, spreading the remainder as a
     * +1 bonus on the first activities (course order) rather than the last.
     */
    public function test_compute_activity_quotas_always_sums_to_target(): void {
        $this->assertSame([2, 1, 1, 1, 1, 1, 1, 1, 1, 1], drop_distribution::compute_activity_quotas(11, 10));
        $this->assertSame([3, 2, 2, 2, 2], drop_distribution::compute_activity_quotas(11, 5));
        $this->assertSame([4, 4, 3], drop_distribution::compute_activity_quotas(11, 3));
        $this->assertSame(array_fill(0, 11, 1), drop_distribution::compute_activity_quotas(11, 11));
        $this->assertSame([11], drop_distribution::compute_activity_quotas(11, 1));

        foreach ([[11, 10], [11, 5], [11, 3], [11, 11], [11, 1]] as [$target, $count]) {
            $this->assertSame($target, array_sum(drop_distribution::compute_activity_quotas($target, $count)));
        }
    }

    /**
     * More eligible activities than the target: the $target single-unit quotas are spread
     * evenly across the whole activity list (one entry per activity, some 0), never piled onto
     * the first $target activities and leaving the rest empty.
     */
    public function test_compute_activity_quotas_spreads_evenly_when_more_activities(): void {
        $quotas = drop_distribution::compute_activity_quotas(11, 15);
        $this->assertCount(15, $quotas);
        $this->assertSame(11, array_sum($quotas));
        $this->assertContains(0, $quotas);
        $this->assertContains(1, array_slice($quotas, -4), 'the last activities must not be starved');
        $this->assertLessThanOrEqual(1, max($quotas), 'no activity gets more than one when target < count');

        // 12 drops over 20 activities: evenly spaced, last drop well past the 12th activity.
        $quotas = drop_distribution::compute_activity_quotas(12, 20);
        $this->assertCount(20, $quotas);
        $this->assertSame(12, array_sum($quotas));
        $this->assertSame(1, $quotas[0]);
        $lastwithdrop = max(array_keys(array_filter($quotas)));
        $this->assertGreaterThan(12, $lastwithdrop);
    }

    /**
     * Zero activities or a non-positive target yield no quotas at all.
     */
    public function test_compute_activity_quotas_handles_edge_cases(): void {
        $this->assertSame([], drop_distribution::compute_activity_quotas(11, 0));
        $this->assertSame([], drop_distribution::compute_activity_quotas(0, 5));
        $this->assertSame([], drop_distribution::compute_activity_quotas(-1, 5));
    }
}
