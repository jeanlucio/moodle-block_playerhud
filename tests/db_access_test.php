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
 * Tests for db/access.php's capability declarations.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * block/playerhud:manage exposes other users' XP, inventory and class assignments through the
 * management panel, and its bulk-delete actions can destroy that same data for every student in
 * a course, so its risk profile must include RISK_PERSONAL and RISK_DATALOSS alongside
 * RISK_SPAM/RISK_XSS — this is a pure metadata declaration, checked directly against the raw
 * file rather than through any code path.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class db_access_test extends advanced_testcase {
    /**
     * block/playerhud:manage's riskbitmask flags it as exposing personal data and capable of
     * data loss.
     */
    public function test_manage_capability_flags_risk_personal_and_dataloss(): void {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/blocks/playerhud/db/access.php');

        $this->assertArrayHasKey('block/playerhud:manage', $capabilities);
        $riskbitmask = $capabilities['block/playerhud:manage']['riskbitmask'];
        $this->assertNotSame(0, $riskbitmask & RISK_PERSONAL, 'RISK_PERSONAL must be set.');
        $this->assertNotSame(0, $riskbitmask & RISK_DATALOSS, 'RISK_DATALOSS must be set.');
    }

    /**
     * The block is only usable inside a course (every page checks the course context), so it must
     * not be offered on the Dashboard, and no permission for adding it there may linger in the
     * role editor — it would grant nothing.
     */
    public function test_block_is_not_offered_on_the_dashboard(): void {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/blocks/playerhud/db/access.php');
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/playerhud/block_playerhud.php');

        $formats = (new \block_playerhud())->applicable_formats();

        $this->assertFalse($formats['my'] ?? false, 'The Dashboard must not accept this block.');
        $this->assertTrue($formats['course-view'] ?? false);
        $this->assertArrayNotHasKey('block/playerhud:myaddinstance', $capabilities);
    }
}
