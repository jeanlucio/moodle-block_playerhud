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

namespace block_playerhud\output\view;

use advanced_testcase;

/**
 * Tests for the ranking tab (disabled state, student privacy gate, teacher view).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep. Tests
 * export_for_template() directly rather than display(): display() reads the global
 * $OUTPUT, which in a bare PHPUnit run is still the bootstrap_renderer stand-in and
 * would fail the strict renderer_base type check — the same constraint documented
 * for the manage/ tabs, but here purely a test-harness concern (production view.php
 * always calls header() first).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_ranking
 */
final class tab_ranking_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var \stdClass Test user. */
    protected $user;

    /**
     * Create a fresh course, block instance and user for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
        $this->setUser($this->user);

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/view.php', ['id' => $this->course->id]);
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * Creates a minimal block_instances row and returns its id.
     *
     * @return int The new block instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        return $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * A mocked core_renderer, satisfying both the outer renderer_base type hint and
     * enrich_userpictures()'s own core_renderer requirement.
     *
     * @return \core_renderer
     */
    private function mock_output(): \core_renderer {
        $output = $this->createMock(\core_renderer::class);
        $output->method('user_picture')->willReturn('<img alt="picture">');
        return $output;
    }

    /**
     * Ranking disabled in block config short-circuits before touching any player data.
     */
    public function test_export_for_template_disabled(): void {
        $config = (object) ['enable_ranking' => 0];
        $player = (object) ['userid' => $this->user->id, 'ranking_visibility' => 1];

        $tab = new tab_ranking($config, $player, $this->instanceid, $this->course->id, false);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertTrue($data['is_disabled']);
        $this->assertNotEmpty($data['str_disabled']);
    }

    /**
     * A student with ranking visibility on sees the leaderboard content.
     */
    public function test_export_for_template_visible_student_sees_content(): void {
        $config = (object) ['enable_ranking' => 1];
        $player = (object) ['userid' => $this->user->id, 'ranking_visibility' => 1];

        $tab = new tab_ranking($config, $player, $this->instanceid, $this->course->id, false);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertFalse($data['is_disabled']);
        $this->assertTrue($data['privacy_visible']);
        $this->assertTrue($data['show_content']);
    }

    /**
     * A student who opted out of the ranking sees their own privacy toggle but not the
     * leaderboard content, since they are neither visible nor a teacher.
     */
    public function test_export_for_template_hidden_student_sees_no_content(): void {
        $config = (object) ['enable_ranking' => 1];
        $player = (object) ['userid' => $this->user->id, 'ranking_visibility' => 0];

        $tab = new tab_ranking($config, $player, $this->instanceid, $this->course->id, false);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertFalse($data['privacy_visible']);
        $this->assertFalse($data['show_content']);
    }

    /**
     * A teacher always sees content, with the teacher-only filter controls active,
     * regardless of their own (irrelevant) ranking_visibility value.
     */
    public function test_export_for_template_teacher_sees_content_and_filters(): void {
        $config = (object) ['enable_ranking' => 1];
        $player = (object) ['userid' => $this->user->id, 'ranking_visibility' => 0];

        $tab = new tab_ranking($config, $player, $this->instanceid, $this->course->id, true);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertTrue($data['show_content']);
        $this->assertTrue($data['is_teacher']);
        $this->assertTrue($data['teacher_filter_active']);
    }

    /**
     * Builds ranked entries as get_leaderboard() would hand them over.
     *
     * @param int $count Number of entries.
     * @return array Entries with userid 1..$count and rank equal to the position.
     */
    private function make_entries(int $count): array {
        $entries = [];
        for ($i = 1; $i <= $count; $i++) {
            $entries[] = (object) ['userid' => $i, 'rank' => $i];
        }
        return $entries;
    }

    /**
     * The first page holds exactly one page worth of rows and the total counts every entry.
     */
    public function test_paginate_first_page(): void {
        $result = tab_ranking::paginate($this->make_entries(120), 0, 50, 0);

        $this->assertCount(50, $result['rows']);
        $this->assertSame(1, $result['rows'][0]->userid);
        $this->assertSame(50, $result['rows'][49]->userid);
        $this->assertSame(0, $result['page']);
        $this->assertSame(120, $result['total']);
    }

    /**
     * The last page holds the remainder and a page past the end is clamped to it.
     */
    public function test_paginate_last_page_and_clamping(): void {
        $entries = $this->make_entries(120);

        $last = tab_ranking::paginate($entries, 2, 50, 0);
        $this->assertCount(20, $last['rows']);
        $this->assertSame(101, $last['rows'][0]->userid);

        $beyond = tab_ranking::paginate($entries, 99, 50, 0);
        $this->assertSame(2, $beyond['page']);
        $this->assertCount(20, $beyond['rows']);

        $negative = tab_ranking::paginate($entries, -3, 50, 0);
        $this->assertSame(0, $negative['page']);
    }

    /**
     * An empty list yields an empty page 0, not an error.
     */
    public function test_paginate_empty_list(): void {
        $result = tab_ranking::paginate([], 5, 50, 7);

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['page']);
        $this->assertSame(0, $result['total']);
    }

    /**
     * A viewer outside the page gets their own row appended, flagged as pinned and still
     * carrying the real rank from the whole list.
     */
    public function test_paginate_pins_viewer_outside_the_page(): void {
        $result = tab_ranking::paginate($this->make_entries(120), 0, 50, 87);

        $this->assertCount(51, $result['rows']);
        $pinned = end($result['rows']);
        $this->assertSame(87, $pinned->userid);
        $this->assertSame(87, $pinned->rank);
        $this->assertTrue($pinned->is_pinned);
        $this->assertSame(120, $result['total']);
    }

    /**
     * A viewer already on the page is not repeated and nothing is flagged as pinned.
     */
    public function test_paginate_does_not_duplicate_viewer_on_the_page(): void {
        $result = tab_ranking::paginate($this->make_entries(120), 0, 50, 10);

        $this->assertCount(50, $result['rows']);
        $ids = array_map(static fn($row) => $row->userid, $result['rows']);
        $this->assertSame(1, count(array_keys($ids, 10)));
        foreach ($result['rows'] as $row) {
            $this->assertObjectNotHasProperty('is_pinned', $row);
        }
    }

    /**
     * A viewer who is not in the ranking at all (for example a hidden teacher) pins nothing.
     */
    public function test_paginate_viewer_absent_from_list_pins_nothing(): void {
        $result = tab_ranking::paginate($this->make_entries(120), 1, 50, 9999);

        $this->assertCount(50, $result['rows']);
    }

    /**
     * Creates enrolled students with a player row each; XP descends with the index so the
     * rank of student N is N.
     *
     * @param int $count Number of students.
     * @return array Student user objects, best rank first.
     */
    private function create_ranked_students(int $count): array {
        global $DB;

        $students = [];
        $now = time();
        for ($i = 1; $i <= $count; $i++) {
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid' => $this->instanceid,
                'userid' => $student->id,
                'currentxp' => 10000 - $i,
                'ranking_visibility' => 1,
                'enable_gamification' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $students[] = $student;
        }
        return $students;
    }

    /**
     * End to end: a student ranked 87th of 120 sees ranks 1-50 plus their own pinned row
     * labelled 87, and the paging bar is built for the whole list.
     */
    public function test_export_for_template_student_outside_page_sees_pinned_row(): void {
        $students = $this->create_ranked_students(120);
        $viewer = $students[86];
        $this->setUser($viewer);

        $output = $this->createMock(\core_renderer::class);
        $output->method('user_picture')->willReturn('<img alt="picture">');
        $output->expects($this->once())
            ->method('paging_bar')
            ->with(120, 0, tab_ranking::PERPAGE)
            ->willReturn('<nav>paging</nav>');

        $player = (object) ['userid' => $viewer->id, 'ranking_visibility' => 1];
        $tab = new tab_ranking((object) ['enable_ranking' => 1], $player, $this->instanceid, $this->course->id, false);
        $data = $tab->export_for_template($output);

        $this->assertCount(51, $data['individual']);
        $this->assertSame('<nav>paging</nav>', $data['paging_bar']);
        $pinned = end($data['individual']);
        $this->assertEquals($viewer->id, $pinned->userid);
        $this->assertEquals(87, $pinned->rank);
        $this->assertTrue($pinned->is_pinned);
        $this->assertTrue($pinned->is_me);
    }

    /**
     * The page request parameter selects the slice, and a teacher gets plain paging with no
     * pinned row.
     */
    public function test_export_for_template_teacher_second_page(): void {
        $this->create_ranked_students(120);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);
        $_GET['page'] = '1';

        $player = (object) ['userid' => $teacher->id, 'ranking_visibility' => 1];
        $tab = new tab_ranking((object) ['enable_ranking' => 1], $player, $this->instanceid, $this->course->id, true);
        $data = $tab->export_for_template($this->mock_output());
        unset($_GET['page']);

        $this->assertCount(50, $data['individual']);
        $this->assertEquals(51, $data['individual'][0]->rank);
        foreach ($data['individual'] as $row) {
            $this->assertObjectNotHasProperty('is_pinned', $row);
        }
    }

    /**
     * The real template renders the pinned row behind its separator and the paging bar.
     */
    public function test_template_renders_pinned_row_and_paging_bar(): void {
        global $PAGE;

        $students = $this->create_ranked_students(120);
        $viewer = $students[86];
        $this->setUser($viewer);

        $output = $this->createMock(\core_renderer::class);
        $output->method('user_picture')->willReturn('<img alt="picture">');
        $output->method('paging_bar')->willReturn('<nav id="ph-test-paging">paging</nav>');

        $player = (object) ['userid' => $viewer->id, 'ranking_visibility' => 1];
        $tab = new tab_ranking((object) ['enable_ranking' => 1], $player, $this->instanceid, $this->course->id, false);
        $html = $PAGE->get_renderer('core')->render_from_template(
            'block_playerhud/view_ranking',
            $tab->export_for_template($output)
        );

        $this->assertStringContainsString('ph-rank-gap', $html);
        $this->assertStringContainsString(get_string('ranking_your_position', 'block_playerhud'), $html);
        $this->assertStringContainsString('id="ph-test-paging"', $html);
        $this->assertSame(1, substr_count($html, 'ph-rank-gap'));
    }
}
