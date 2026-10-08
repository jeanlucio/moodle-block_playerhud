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
 * Tests that the trade screens escape names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use block_playerhud\output\manage\tab_trades;
use block_playerhud\output\view\tab_shop;
use block_playerhud\tests\escaping_testcase;

/**
 * Trade, requirement and reward names shown in the teacher's trades tab and the student's shop.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_trades
 * @covers     \block_playerhud\output\view\tab_shop
 */
final class trades_escaping_test extends escaping_testcase {
    /**
     * Creates a trade that costs one canary item and pays another, both named with the canary.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $tradeid = (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'groupid' => 0,
            'centralized' => 1, 'onetime' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach (['block_playerhud_trade_reqs', 'block_playerhud_trade_rewards'] as $table) {
            $DB->insert_record($table, (object) [
                'tradeid' => $tradeid, 'itemid' => $this->create_item(), 'qty' => 2,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
    }

    /**
     * The teacher's trades tab shows names, popovers and the delete confirmation once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_manage_trades_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $tab = new tab_trades($this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display());
    }

    /**
     * The student's shop shows names and popover titles once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_shop_escapes_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);

        $student = $this->getDataGenerator()->create_user();
        $tab = new tab_shop(new \stdClass(), (object) ['userid' => $student->id], $this->blockid, (int) $this->course->id);

        $output = $PAGE->get_renderer('core');
        $this->assert_escaped_once(
            $output->render_from_template('block_playerhud/tab_shop', $tab->export_for_template($output))
        );
    }
}
