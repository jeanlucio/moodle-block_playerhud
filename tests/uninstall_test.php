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
 * Tests for the block_playerhud pre-uninstallation hook.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

/**
 * Tests for xmldb_block_playerhud_uninstall(). Every table in db/install.xml is
 * dropped automatically by core, so the only thing worth exercising here is the one
 * piece of cleanup core does not do for us: user_preferences rows (personal AI keys,
 * chosen avatar, etc.).
 *
 * Unlike a mod_* plugin, core calls this hook by the full component name
 * (uninstall_plugin() in lib/adminlib.php only strips the "mod_" prefix for the
 * "mod" plugin type), so the function is already named correctly by construction —
 * this test guards against a future accidental rename dropping the "block_" prefix.
 *
 * @covers ::xmldb_block_playerhud_uninstall
 */
final class uninstall_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/blocks/playerhud/db/uninstall.php');
    }

    /**
     * Tests that the uninstall hook deletes only block_playerhud-prefixed
     * preferences, leaving unrelated preferences (including those of other plugins)
     * untouched.
     *
     * @return void
     */
    public function test_uninstall_deletes_only_own_prefixed_preferences(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        set_user_preference('block_playerhud_gemini_key', 'secret', $user);
        set_user_preference('filter_playerhud_pref', 1, $user);
        set_user_preference('unrelated_pref', 'keep', $user);

        $result = xmldb_block_playerhud_uninstall();

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('user_preferences', [
            'userid' => $user->id,
            'name' => 'block_playerhud_gemini_key',
        ]));
        $this->assertTrue($DB->record_exists('user_preferences', [
            'userid' => $user->id,
            'name' => 'filter_playerhud_pref',
        ]));
        $this->assertTrue($DB->record_exists('user_preferences', [
            'userid' => $user->id,
            'name' => 'unrelated_pref',
        ]));
    }

    /**
     * Tests that running the hook with no matching preferences at all is a harmless
     * no-op.
     *
     * @return void
     */
    public function test_uninstall_with_no_matching_preferences_is_a_noop(): void {
        $this->assertTrue(xmldb_block_playerhud_uninstall());
    }
}
