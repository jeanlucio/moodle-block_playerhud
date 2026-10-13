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
 * Tests for the character form.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;

/**
 * Tests for the length limit of the character name.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_class_form
 */
final class edit_class_form_test extends advanced_testcase {
    /**
     * Submits the form with a given name and tells whether it was accepted.
     *
     * @param string $name The character name.
     * @return bool True when the form validated.
     */
    private function accepts(string $name): bool {
        edit_class_form::mock_submit(['name' => $name, 'description' => '', 'classid' => 0]);
        $form = new edit_class_form(null, ['classid' => 0]);

        return $form->get_data() !== null;
    }

    /**
     * The name column holds 100 characters: 100 are accepted and 101 are refused by the form,
     * instead of reaching the database and failing the insert.
     */
    public function test_name_longer_than_the_column_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue($this->accepts(str_repeat('ê', 100)));
        $this->assertFalse($this->accepts(str_repeat('ê', 101)));
    }
}
