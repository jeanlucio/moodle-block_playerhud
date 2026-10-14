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
 * Tests for the scene editing form.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;

/**
 * Tests for the labels edit_scene_form builds for the "next scene" select.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_scene_form
 */
final class edit_scene_form_test extends advanced_testcase {
    /**
     * Renders the form for a chapter holding the given scenes.
     *
     * @param string[] $contents HTML content of each existing scene.
     * @param array $currentdata Values the form is pre-filled with, such as choice_text_0.
     * @return string The rendered form HTML.
     */
    private function render_form_for(array $contents, array $currentdata = []): string {
        global $DB, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        $chapterid = (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid, 'title' => 'Cap', 'intro_text' => '', 'unlock_date' => 0,
            'required_level' => 0, 'sortorder' => 1,
        ]);
        foreach ($contents as $content) {
            $DB->insert_record('block_playerhud_story_nodes', (object) [
                'chapterid' => $chapterid, 'content' => $content, 'is_start' => 0,
            ]);
        }

        $PAGE->set_url('/blocks/playerhud/manage.php');
        $form = new edit_scene_form(null, [
            'chapterid' => $chapterid, 'instanceid' => $instanceid, 'current_data' => $currentdata,
        ]);
        ob_start();
        $form->display();
        return ob_get_clean();
    }

    /**
     * The scene preview in the "next scene" select is cut by characters, never in the middle of a
     * multibyte one — the label used to end in a broken character.
     */
    public function test_scene_labels_are_valid_utf8_when_cut(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // 39 ASCII letters, then a two-byte ê: the 40th byte is the first half of that character.
        $html = $this->render_form_for([str_repeat('a', 39) . 'êxito final da cena']);

        // A byte-wise cut leaves an invalid sequence that the template escaper turns into U+FFFD.
        $this->assertStringNotContainsString("\u{FFFD}", $html);
    }

    /**
     * Entities in the scene HTML show as the text they stand for, not literally.
     */
    public function test_scene_labels_decode_html_entities(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->render_form_for(['<p>Olá&nbsp;mundo &amp; amigos</p>']);

        $this->assertStringNotContainsString('&amp;nbsp;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString('Olá', $html);
    }

    /**
     * Short scenes get no ellipsis; long ones do.
     */
    public function test_scene_labels_only_get_an_ellipsis_when_cut(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->render_form_for(['Curta', str_repeat('palavra ', 20)]);

        $this->assertStringNotContainsString('Curta...', $html);
        $this->assertStringContainsString('palavra palavra palavra palavra ...', $html);
    }

    /**
     * A saved choice text is escaped once: the template escapes it, so the form must hand it over
     * raw. An ampersand used to reach the input as "&amp;amp;" and grew a level on every save.
     */
    public function test_choice_text_is_escaped_once(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->render_form_for(['Cena'], ['choice_text_0' => 'Tom & "Jerry" <b>']);

        $this->assertStringContainsString('Tom &amp; &quot;Jerry&quot; &lt;b&gt;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * The choice text field carries the column's own limit, so the browser stops the typing.
     */
    public function test_choice_text_field_has_a_maxlength(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->render_form_for(['Cena'], ['choice_text_0' => 'Go']);

        $this->assertMatchesRegularExpression('/name="choice_text_0"[^>]*maxlength="255"/s', $html);
    }

    /**
     * The karma fields stop at the range a player's karma can reach, so the browser warns before a
     * requirement nobody can meet is typed.
     */
    public function test_karma_fields_carry_the_karma_range(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->render_form_for(['Cena'], ['choice_text_0' => 'Go']);

        foreach (['choice_req_karma_0', 'choice_karma_0'] as $name) {
            $this->assertMatchesRegularExpression('/name="' . $name . '"[^>]*min="-999"[^>]*max="999"/s', $html);
        }
    }

    /**
     * Each press of "Add choice" adds one slot to those already on screen, even when the earlier
     * ones were never saved: it used to count from the saved choices and so added the same slot again.
     *
     * @dataProvider add_choice_provider
     * @param int|null $displayed Slots on screen when the button is pressed, null if it is not pressed.
     * @param int $expected Slots in the re-rendered form.
     */
    public function test_add_choice_grows_from_the_slots_on_screen(?int $displayed, int $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if ($displayed !== null) {
            $_POST['add_choice_btn'] = 'Add choice';
            $_POST['repeats'] = (string) $displayed;
        }

        $html = $this->render_form_for([]);

        $this->assertSame($expected, preg_match_all('/name="choice_text_\d+"/', $html));
    }

    /**
     * Cases for the add-choice button.
     *
     * @return array[] Slots on screen and slots expected after rendering.
     */
    public static function add_choice_provider(): array {
        return [
            'button not pressed' => [null, 3],
            'first press' => [3, 4],
            'second press' => [4, 5],
            'a tampered counter is capped' => [100000, \block_playerhud\controller\scenes::MAX_REPEATS],
        ];
    }
}
