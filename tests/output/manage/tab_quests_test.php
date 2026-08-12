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
 * Tests for the quests tab management save path.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;
use stdClass;

/**
 * Regression coverage for the description-sanitization fix in tab_quests::process(): the raw
 * HTML submitted through the quest editing form must be sanitized before it reaches the database,
 * the same way item descriptions are (see utils_test.php for the pure sanitize_rich_description()
 * coverage). These tests exercise the real save path — a simulated form POST — rather than the
 * helper function in isolation, to prove the two are actually wired together.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_quests
 */
final class tab_quests_test extends advanced_testcase {
    /** @var stdClass The shared course. */
    private stdClass $course;

    /** @var int Block instance ID. */
    private int $instanceid;

    /**
     * Create a fresh course, block instance and a clean request state for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $_POST = [];
        $_GET  = [];

        $this->course     = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->make_instance();
        $this->setAdminUser();

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/manage.php', ['id' => $this->course->id]);
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * Leave no request state behind for the next test.
     */
    protected function tearDown(): void {
        $_POST = [];
        $_GET  = [];
        parent::tearDown();
    }

    /**
     * Creates a PlayerHUD block instance on the shared course.
     *
     * @return int The new block instance ID.
     */
    private function make_instance(): int {
        global $DB;

        $coursecontext = \context_course::instance($this->course->id);

        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Builds a minimal valid POST payload for creating a level-type quest (no type-specific
     * required fields), with the given description HTML.
     *
     * @param string $descriptionhtml Raw HTML for the description editor field.
     * @return array The $_POST payload.
     */
    private function quest_post(string $descriptionhtml): array {
        $formidentifier = str_replace('\\', '_', \block_playerhud\form\edit_quest_form::class);

        return [
            'name'                      => 'Sanitization Quest',
            'description'               => ['text' => $descriptionhtml, 'format' => FORMAT_HTML],
            'type'                      => (string) \block_playerhud\quest::TYPE_LEVEL,
            'target_value'              => 1,
            'req_itemid'                => 0,
            'req_tradeid'               => 0,
            'activity_cmid'             => 0,
            'chapter_id'                => 0,
            'reward_xp'                 => 0,
            'reward_itemid'             => 0,
            'image_todo'                => '🎯',
            'image_done'                => '🏅',
            'enabled'                   => 1,
            'required_class_id'         => '0',
            'questid'                   => 0,
            'instanceid'                => $this->instanceid,
            'courseid'                  => $this->course->id,
            'sesskey'                   => sesskey(),
            '_qf__' . $formidentifier   => 1,
        ];
    }

    /**
     * Runs process() and asserts it ends in the expected post-save redirect.
     */
    private function submit_and_expect_redirect(): void {
        $tab = new tab_quests($this->instanceid, $this->course->id);
        try {
            $tab->process();
            $this->fail('Expected the save to end in a redirect.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
    }

    /**
     * Regression test for the production incident that also affected items: pasting the visible
     * text of an already-open modal into the quest description field drags along its DOM
     * skeleton. The stored description must come out with only the real text, no nested
     * <div class="modal">...</div> structure and no leftover yui_* ids.
     */
    public function test_process_sanitizes_pasted_modal_skeleton_in_description(): void {
        global $DB;

        $poisoned = '<div id="ph-item-modal-view" class="modal fade ph-modal-zindex ph-item-modal show">'
            . '<div id="yui_3_18_1_1_1786538342166_237" class="modal-content border-0 shadow-lg">'
            . '<div id="phModalDescView" class="text-muted text-break mb-3">'
            . '<p id="yui_3_18_1_1_1786538342166_242">Reune tres pergaminhos perdidos na masmorra.</p>'
            . '</div></div></div>';

        $_GET  = ['action' => 'add'];
        $_POST = $this->quest_post($poisoned);

        $this->submit_and_expect_redirect();

        $quest = $DB->get_record('block_playerhud_quests', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertStringNotContainsString('<div', $quest->description);
        $this->assertStringNotContainsString('modal', $quest->description);
        $this->assertStringNotContainsString('yui_3_18_1_1', $quest->description);
        $this->assertStringContainsString('Reune tres pergaminhos perdidos na masmorra.', $quest->description);
    }

    /**
     * Ordinary WYSIWYG formatting must survive the save path unchanged — the sanitizer must not
     * regress legitimate quest descriptions.
     */
    public function test_process_preserves_basic_formatting_in_description(): void {
        global $DB;

        $html = '<p>Defeat the <strong>dungeon boss</strong> to earn <em>bonus XP</em>.</p>';

        $_GET  = ['action' => 'add'];
        $_POST = $this->quest_post($html);

        $this->submit_and_expect_redirect();

        $quest = $DB->get_record('block_playerhud_quests', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertStringContainsString('<strong>dungeon boss</strong>', $quest->description);
        $this->assertStringContainsString('<em>bonus XP</em>', $quest->description);
    }
}
