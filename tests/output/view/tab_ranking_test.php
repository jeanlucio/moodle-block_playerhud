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
}
