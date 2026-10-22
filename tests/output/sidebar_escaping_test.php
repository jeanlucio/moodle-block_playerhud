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
 * Tests that the block sidebar escapes names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use block_playerhud\tests\escaping_testcase;

/**
 * The block shown in the course sidebar lists the recent items, the class name and the "+N" popover
 * data: the template escapes the first two once, and the popover JSON carries plain text that the
 * script assigns to a `title`, which does not decode entities.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud
 */
final class sidebar_escaping_test extends escaping_testcase {
    /**
     * Sets the site setting and makes the formatter read it again; the formatter is built once per
     * container and keeps the value it read.
     *
     * @param int $striptags Value of formatstringstriptags.
     */
    private function apply_striptags(int $striptags): void {
        set_config('formatstringstriptags', $striptags);
        \core\di::reset_container();
    }

    /**
     * Renders the block for a student who holds six items (the sidebar shows five) and a class.
     *
     * @return \stdClass The block content.
     */
    private function render_block_for_student(): \stdClass {
        global $DB, $COURSE, $PAGE;

        $now = time();
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize((object) [
            'enable_rpg' => 1,
        ])), ['id' => $this->blockid]);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->blockid, 'userid' => $student->id, 'currentxp' => 10,
            'enable_gamification' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        for ($i = 0; $i < 6; $i++) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid' => $student->id, 'itemid' => $this->create_item(), 'dropid' => 0, 'source' => 'map',
                'timecreated' => $now + $i, 'xpawarded' => 0,
            ]);
        }
        $classid = $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'base_hp' => 10,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_rpg_progress', (object) [
            'blockinstanceid' => $this->blockid, 'userid' => $student->id, 'classid' => $classid, 'karma' => 0,
            'current_nodes' => '{}', 'completed_chapters' => '[]', 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $this->setUser($student);
        $COURSE = $this->course;
        $record = $DB->get_record('block_instances', ['id' => $this->blockid], '*', MUST_EXIST);
        $PAGE->set_course($this->course);
        $block = block_instance('playerhud', $record, $PAGE);

        return $block->get_content();
    }

    /**
     * The recent items and the class name reach the page escaped once, and the popover JSON is plain text.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_sidebar_escapes_names_once(int $striptags): void {
        $this->apply_striptags($striptags);

        $content = $this->render_block_for_student();

        $this->assert_escaped_once($content->text, 'none');
        $this->assertStringContainsString('data-name="' . self::ESCAPED . '"', $content->text);
        $this->assertStringContainsString('title="' . self::ESCAPED . '"', $content->text);

        $this->assertSame(1, preg_match('/data-overflow="([^"]*)"/', $content->text, $matches));
        $overflow = json_decode(html_entity_decode($matches[1]), true);
        $this->assertSame(self::CANARY, $overflow[0]['n']);
    }

    /**
     * Data provider: the two values of formatstringstriptags.
     *
     * @return array
     */
    public static function striptags_provider(): array {
        return ['strip tags on' => [1], 'strip tags off' => [0]];
    }
}
