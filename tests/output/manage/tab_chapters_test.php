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
 * Tests for the chapters management tab.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;

/**
 * Tests for the chapter-card visibility warnings.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\output\manage\tab_chapters
 */
final class tab_chapters_test extends advanced_testcase {
    /**
     * A chapter without a start scene is flagged as not visible.
     *
     * @covers ::chapter_warnings
     */
    public function test_chapter_warnings_flags_missing_start_scene(): void {
        $warnings = tab_chapters::chapter_warnings(0, false, 20);

        $this->assertTrue($warnings['no_start_warning']);
    }

    /**
     * A chapter with a start scene raises no start warning.
     *
     * @covers ::chapter_warnings
     */
    public function test_chapter_warnings_no_start_when_scene_present(): void {
        $warnings = tab_chapters::chapter_warnings(0, true, 20);

        $this->assertFalse($warnings['no_start_warning']);
    }

    /**
     * A required level above the block maximum is flagged with a text message.
     *
     * @covers ::chapter_warnings
     */
    public function test_chapter_warnings_flags_level_above_maximum(): void {
        $warnings = tab_chapters::chapter_warnings(50, true, 20);

        $this->assertTrue($warnings['level_warning']);
        $expected = get_string('chapter_level_warning', 'block_playerhud', (object) [
            'required' => 50,
            'max'      => 20,
        ]);
        $this->assertSame($expected, $warnings['level_warning_text']);
    }

    /**
     * A required level within the block maximum raises no level warning.
     *
     * @covers ::chapter_warnings
     */
    public function test_chapter_warnings_no_level_warning_within_maximum(): void {
        $warnings = tab_chapters::chapter_warnings(10, true, 20);

        $this->assertFalse($warnings['level_warning']);
        $this->assertSame('', $warnings['level_warning_text']);
    }
}
