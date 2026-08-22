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
 * @covers     \block_playerhud\local\audit_log
 * @covers     \block_playerhud\local\external_items
 * @covers     \block_playerhud\controller\items
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

    /**
     * A quest claim's xp_gained comes from the recorded xpawarded value, not the quest's
     * current reward_xp — so editing the quest afterwards never changes what the log shows.
     */
    public function test_quest_xp_gained_uses_recorded_value_not_current_reward_xp(): void {
        global $DB;

        $questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Bonus', 'description' => '',
            'type' => 1, 'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 200,
            'reward_itemid' => 0, 'required_class_id' => '0', 'image_todo' => '', 'image_done' => '',
            'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $this->user->id, 'timecreated' => time(),
            'xpawarded' => 200,
        ]);

        // Quest is edited after the claim: the log must still show what was actually paid.
        $DB->set_field('block_playerhud_quests', 'reward_xp', 50, ['id' => $questid]);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(200, $logs[0]->xp_gained);
        $this->assertEquals('quest', $logs[0]->event_type);
    }

    /**
     * A grant recorded through the new engine (block_playerhud_stack_log) appears in the feed
     * as an 'item' event with its recorded xp_gained — the storage generation every grant now
     * uses, alongside the legacy inventory branch above.
     */
    public function test_new_engine_grant_appears_as_item_event(): void {
        $itemid = $this->create_item(30);
        external_items::grant($this->instanceid, $itemid, $this->user->id, 2, 'teacher', false);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals('item', $logs[0]->event_type);
        $this->assertEquals(60, $logs[0]->xp_gained);
    }

    /**
     * A consume recorded through the new engine appears as its own 'item_consumed' event,
     * distinct from the grant that preceded it — reports xp_gained = 0, since consume() never
     * touches XP.
     */
    public function test_new_engine_consume_appears_as_item_consumed_event(): void {
        $itemid = $this->create_item(0);
        external_items::grant($this->instanceid, $itemid, $this->user->id, 1, 'teacher', true);
        external_items::consume($this->instanceid, $itemid, $this->user->id, 1);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(2, $logs);
        $consumed = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item_consumed'));
        $this->assertCount(1, $consumed);
        $this->assertEquals(0, $consumed[0]->xp_gained);
    }

    /**
     * A grant and a consume can share the same caller-identifying $source tag (e.g. a game
     * plugin passes 'playerwords' to both grant() and consume()), but must resolve to distinct
     * report_src_* lang string keys: 'details' carries the raw source for a grant, and a
     * 'consumed_' prefixed variant for a consume, so the history/report views never render a
     * spend with the same wording as an earn.
     */
    public function test_consume_details_is_disambiguated_from_grant_with_same_source(): void {
        $itemid = $this->create_item(0);
        external_items::grant($this->instanceid, $itemid, $this->user->id, 2, 'playerwords', true);
        external_items::consume($this->instanceid, $itemid, $this->user->id, 1, 'playerwords');

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(2, $logs);

        $granted = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item'));
        $consumed = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item_consumed'));

        $this->assertCount(1, $granted);
        $this->assertCount(1, $consumed);
        $this->assertEquals('playerwords', $granted[0]->details);
        $this->assertEquals('consumed_playerwords', $consumed[0]->details);
    }

    /**
     * A consume() call without an explicit $source keeps the original generic 'consumed' details
     * value — the default must stay untouched for every pre-existing caller (trade, story items,
     * the student's own "Use" button) that never had a caller identity to disambiguate.
     */
    public function test_consume_without_source_keeps_generic_details(): void {
        $itemid = $this->create_item(0);
        external_items::grant($this->instanceid, $itemid, $this->user->id, 1, 'teacher', true);
        external_items::consume($this->instanceid, $itemid, $this->user->id, 1);

        $logs = array_values($this->get_logs()['logs']);
        $consumed = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item_consumed'));
        $this->assertCount(1, $consumed);
        $this->assertEquals('consumed', $consumed[0]->details);
    }

    /**
     * Revoking a new-engine grant appears as its own 'item_revoked' event reporting the
     * negative of the original grant's recorded xpawarded — the regression this test guards:
     * revoke_stack_log_entry() must carry that amount onto its own compensating log row, not
     * default it to zero, or this figure would silently read 0 instead of what was actually
     * deducted.
     */
    public function test_new_engine_revoke_appears_as_item_revoked_event_with_negative_value(): void {
        $itemid = $this->create_item(50);
        $logid = external_items::grant($this->instanceid, $itemid, $this->user->id, 1, 'teacher', false);

        \block_playerhud\controller\items::revoke_stack_log_entry($logid, $this->instanceid);

        $logs = array_values($this->get_logs()['logs']);
        $revoked = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item_revoked'));
        $this->assertCount(1, $revoked);
        $this->assertEquals(-50, $revoked[0]->xp_gained);
    }

    /**
     * A legacy inventory row always represents exactly one unit, so a plain grant reports a
     * qty of +1 regardless of the item's xp value.
     */
    public function test_legacy_grant_reports_qty_of_one(): void {
        $itemid = $this->create_item(10);
        $this->grant_copy($itemid, 'map', 10);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(1, $logs[0]->qty);
    }

    /**
     * A legacy inventory row marked as consumed reports a qty of -1, the unit it represents
     * being spent.
     */
    public function test_legacy_consumed_reports_qty_of_negative_one(): void {
        global $DB;

        $itemid = $this->create_item(0);
        $this->grant_copy($itemid, 'map', 0);
        $DB->set_field('block_playerhud_inventory', 'source', 'consumed', ['userid' => $this->user->id]);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals('item_consumed', $logs[0]->event_type);
        $this->assertEquals(-1, $logs[0]->qty);
    }

    /**
     * A new-engine grant reports the exact qty that was granted, not just +1 — the whole point
     * of the stack ledger over the legacy one-row-per-unit inventory.
     */
    public function test_new_engine_grant_reports_qty_matching_amount(): void {
        $itemid = $this->create_item(0);
        external_items::grant($this->instanceid, $itemid, $this->user->id, 5, 'teacher', true);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(1, $logs);
        $this->assertEquals(5, $logs[0]->qty);
    }

    /**
     * A new-engine consume reports the negative of the exact qty consumed.
     */
    public function test_new_engine_consume_reports_negative_qty_matching_amount(): void {
        $itemid = $this->create_item(0);
        external_items::grant($this->instanceid, $itemid, $this->user->id, 5, 'teacher', true);
        external_items::consume($this->instanceid, $itemid, $this->user->id, 3);

        $logs = array_values($this->get_logs()['logs']);
        $consumed = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item_consumed'));
        $this->assertCount(1, $consumed);
        $this->assertEquals(-3, $consumed[0]->qty);
    }

    /**
     * Revoking a new-engine grant reports the negative of the exact qty that grant put in,
     * mirroring the legacy revoke's -1 but for an arbitrary amount.
     */
    public function test_new_engine_revoke_reports_negative_qty_matching_grant(): void {
        $itemid = $this->create_item(0);
        $logid = external_items::grant($this->instanceid, $itemid, $this->user->id, 4, 'teacher', true);

        \block_playerhud\controller\items::revoke_stack_log_entry($logid, $this->instanceid);

        $logs = array_values($this->get_logs()['logs']);
        $revoked = array_values(array_filter($logs, static fn($log) => $log->event_type === 'item_revoked'));
        $this->assertCount(1, $revoked);
        $this->assertEquals(-4, $revoked[0]->qty);
    }

    /**
     * Trade and quest rows are event markers, not item movements — they always report a qty of
     * 0, leaving the qty badge with nothing to show.
     */
    public function test_trade_and_quest_events_report_zero_qty(): void {
        global $DB;

        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Barter', 'groupid' => 0,
            'centralized' => 1, 'onetime' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_log', (object) [
            'tradeid' => $tradeid, 'userid' => $this->user->id, 'timecreated' => time(),
        ]);

        $questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Bonus', 'description' => '',
            'type' => 1, 'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 0,
            'reward_itemid' => 0, 'required_class_id' => '0', 'image_todo' => '', 'image_done' => '',
            'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $this->user->id, 'timecreated' => time(), 'xpawarded' => 0,
        ]);

        $logs = array_values($this->get_logs()['logs']);
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertEquals(0, $log->qty);
        }
    }

    /**
     * The qty badge helper renders nothing for zero, a green "+N" for a gain, and a red "-N"
     * (the sign already carried by the negative int) for a loss.
     */
    public function test_format_qty_badge_renders_expected_html(): void {
        $this->assertSame('', audit_log::format_qty_badge(0));
        $this->assertStringContainsString('bg-success', audit_log::format_qty_badge(2));
        $this->assertStringContainsString('+2', audit_log::format_qty_badge(2));
        $this->assertStringContainsString('bg-danger', audit_log::format_qty_badge(-3));
        $this->assertStringContainsString('-3', audit_log::format_qty_badge(-3));
    }
}
