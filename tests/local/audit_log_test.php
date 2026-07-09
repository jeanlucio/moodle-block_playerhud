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
 * Tests for the shared audit log query.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use advanced_testcase;
use moodle_url;
use stdClass;

/**
 * Tests for the audit log helper shared by the teacher Reports tab and the student History tab.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\local\audit_log
 */
final class audit_log_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var \stdClass Test user. */
    protected $user;

    /**
     * Create a fresh block instance and user for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        global $DB;
        $this->instanceid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'subpagepattern' => null, 'defaultregion' => 'side-pre',
            'defaultweight' => 0, 'configdata' => base64_encode(serialize(new stdClass())),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->user = $this->getDataGenerator()->create_user();
    }

    /**
     * Insert an item.
     *
     * @param int $xp Current XP value of the item.
     * @return int The new item ID.
     */
    protected function create_item(int $xp): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Relic', 'xp' => $xp, 'image' => '',
            'description' => '', 'enabled' => 1, 'secret' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Insert an inventory row with an explicit xpawarded value.
     *
     * @param int $itemid Item ID.
     * @param string $source Inventory source.
     * @param int $xpawarded XP actually paid out for this copy.
     * @return void
     */
    protected function grant_copy(int $itemid, string $source, int $xpawarded): void {
        global $DB;
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $this->user->id, 'itemid' => $itemid, 'dropid' => 0,
            'source' => $source, 'timecreated' => time(), 'xpawarded' => $xpawarded,
        ]);
    }

    /**
     * Run get_logs() for the test user with sensible defaults.
     *
     * @return array The return value of audit_log::get_logs().
     */
    protected function get_logs(): array {
        $output = $this->createMock(\core\output\core_renderer::class);
        $baseurl = new moodle_url('/blocks/playerhud/manage.php', ['id' => 1, 'instanceid' => $this->instanceid]);

        return audit_log::get_logs(
            $this->instanceid,
            $this->user->id,
            0,
            'timecreated',
            'DESC',
            '',
            '',
            0,
            $baseurl,
            $output
        );
    }

    /**
     * An item collection's xp_gained comes from the recorded xpawarded value, not the item's
     * current xp — so editing the item afterwards never changes what the log shows.
     *
     * @covers ::get_logs
     */
    public function test_item_xp_gained_uses_recorded_value_not_current_item_xp(): void {
        global $DB;

        $itemid = $this->create_item(100);
        $this->grant_copy($itemid, 'map', 100);

        // Item is edited after the grant: the log must still show what was actually paid.
        $DB->set_field('block_playerhud_items', 'xp', 30, ['id' => $itemid]);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(100, $logs[0]->xp_gained);
    }

    /**
     * Regression guard: an item never edited after the grant reports the same value either way.
     *
     * @covers ::get_logs
     */
    public function test_item_xp_gained_matches_when_item_never_edited(): void {
        $itemid = $this->create_item(75);
        $this->grant_copy($itemid, 'map', 75);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(75, $logs[0]->xp_gained);
    }

    /**
     * A quest-granted item (source = quest) reports 0 xp_gained, since the item's own XP is
     * never paid through that path — matching xpawarded as recorded at grant time.
     *
     * @covers ::get_logs
     */
    public function test_quest_granted_item_reports_zero_xp_gained(): void {
        $itemid = $this->create_item(500);
        $this->grant_copy($itemid, 'quest', 0);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(0, $logs[0]->xp_gained);
        $this->assertEquals('item', $logs[0]->event_type);
    }

    /**
     * A revoked row reports the negative of the originally recorded xpawarded, not the item's
     * current xp — mirroring the soft-revoke pattern where source is overwritten to 'revoked'
     * but xpawarded is left untouched.
     *
     * @covers ::get_logs
     */
    public function test_revoked_item_reports_negative_recorded_value(): void {
        global $DB;

        $itemid = $this->create_item(100);
        $this->grant_copy($itemid, 'map', 100);
        $DB->set_field('block_playerhud_inventory', 'source', 'revoked', ['userid' => $this->user->id]);
        // Item edited after the revoke: must not affect the historical figure either.
        $DB->set_field('block_playerhud_items', 'xp', 10, ['id' => $itemid]);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(-100, $logs[0]->xp_gained);
        $this->assertEquals('item_revoked', $logs[0]->event_type);
    }
}
