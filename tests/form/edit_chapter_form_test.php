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
 * Tests for the chapter form's required level validation.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;

/**
 * A chapter must not ask for a level above the block's maximum, which no student could reach.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_chapter_form
 */
final class edit_chapter_form_test extends advanced_testcase {
    /**
     * Builds the form for a block whose maximum level is set.
     *
     * @param int|null $maxlevels Configured maximum level, null for a block never configured.
     * @return edit_chapter_form
     */
    private function make_form(?int $maxlevels): edit_chapter_form {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $config = $maxlevels === null ? new \stdClass() : (object) ['max_levels' => $maxlevels];
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize($config)),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);

        return new edit_chapter_form(null, ['chapterid' => 0, 'instanceid' => $instanceid, 'nextposition' => 1]);
    }

    /**
     * Provides required levels against a maximum level of 10.
     *
     * @return array[] Required level and whether it is refused.
     */
    public static function levels_provider(): array {
        return [
            'no requirement' => [0, false],
            'at the maximum' => [10, false],
            'above the maximum' => [11, true],
            'far above the maximum' => [30, true],
            'negative' => [-1, true],
        ];
    }

    /**
     * A required level outside 0..maximum is refused.
     *
     * @dataProvider levels_provider
     * @param int $level Submitted required level.
     * @param bool $refused Whether validation must reject it.
     */
    public function test_required_level_is_checked_against_the_block_maximum(int $level, bool $refused): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $errors = $this->make_form(10)->validation(['title' => 'Chapter', 'required_level' => $level], []);

        $this->assertSame($refused, isset($errors['required_level']));
        if ($refused) {
            $this->assertSame(get_string('chapter_validate_required_level', 'block_playerhud', 10), $errors['required_level']);
        }
    }

    /**
     * A block that was never configured has the default maximum of 20 levels.
     */
    public function test_an_unconfigured_block_uses_the_default_maximum(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->make_form(null);

        $this->assertArrayNotHasKey('required_level', $form->validation(['required_level' => 20], []));
        $this->assertArrayHasKey('required_level', $form->validation(['required_level' => 21], []));
    }
}
