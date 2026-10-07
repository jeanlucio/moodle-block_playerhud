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
 * Tests for the block configuration form validation.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;
use ReflectionClass;

/**
 * Tests for block_playerhud_edit_form::validation().
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud_edit_form
 */
final class edit_form_test extends advanced_testcase {
    /**
     * Builds the form without its constructor, which needs a full block page context that
     * validation() does not use.
     *
     * @return \block_playerhud_edit_form
     */
    private function make_form(): \block_playerhud_edit_form {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/edit_form.php');
        require_once($CFG->dirroot . '/blocks/playerhud/edit_form.php');

        return (new ReflectionClass(\block_playerhud_edit_form::class))->newInstanceWithoutConstructor();
    }

    /**
     * "XP per level" below 1 is rejected, because 0 stops level progression and breaks the export.
     *
     * @dataProvider invalid_xp_per_level_provider
     * @param mixed $value The submitted value.
     */
    public function test_xp_per_level_below_one_is_rejected($value): void {
        $this->resetAfterTest();

        $errors = $this->make_form()->validation(['config_xp_per_level' => $value], []);

        $this->assertArrayHasKey('config_xp_per_level', $errors);
        $this->assertSame(get_string('error_xp_per_level_min', 'block_playerhud'), $errors['config_xp_per_level']);
    }

    /**
     * Invalid "XP per level" values.
     *
     * @return array
     */
    public static function invalid_xp_per_level_provider(): array {
        return [
            'zero' => [0],
            'negative' => [-50],
            'zero as string' => ['0'],
            'empty after PARAM_INT cleaning' => [''],
        ];
    }

    /**
     * A positive "XP per level" passes validation.
     */
    public function test_positive_xp_per_level_is_accepted(): void {
        $this->resetAfterTest();

        $errors = $this->make_form()->validation(['config_xp_per_level' => 100], []);

        $this->assertArrayNotHasKey('config_xp_per_level', $errors);
    }
}
