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
 * Tests that the items tab escapes names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use block_playerhud\tests\escaping_testcase;
use moodle_url;

/**
 * Names shown by the items tab (list, all drops, distribute) are escaped once.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_items
 */
final class tab_items_escaping_test extends escaping_testcase {
    /** @var tab_items The tab under test. */
    private tab_items $tab;

    /**
     * Adds one drop of a canary item and one canary activity.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => self::CANARY]);
        $itemid = $this->create_item();
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->blockid, 'itemid' => $itemid, 'name' => self::CANARY, 'maxusage' => 1,
            'value' => 0, 'respawntime' => 0, 'code' => 'ABC123',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->tab = new tab_items($this->blockid, (int) $this->course->id);
    }

    /**
     * Calls a protected render method of the tab.
     *
     * @param string $method Method name.
     * @return string The rendered HTML.
     */
    private function render(string $method): string {
        $reflection = new \ReflectionMethod($this->tab, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->tab, new moodle_url('/blocks/playerhud/manage.php'));
    }

    /**
     * The items list shows the item name, the delete confirmation and the drops label once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_items_list_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assert_escaped_once($this->render('render_list_view'));
    }

    /**
     * The all-drops list shows the item and drop names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_all_drops_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assert_escaped_once($this->render('render_all_drops_view'));
    }

    /**
     * The distribute view shows item, drop and activity names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_distribute_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assert_escaped_once($this->render('render_distribute_view'));
    }
}
