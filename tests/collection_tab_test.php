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

namespace block_playerhud;

use advanced_testcase;
use block_playerhud\output\view\tab_collection;

/**
 * Tests for the collection tab renderer (filter_type, power hints, is_equipped).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_collection
 */
final class collection_tab_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var \stdClass Test user. */
    protected $user;

    /**
     * Create a fresh course, block instance and user for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course    = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->user      = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/view.php', ['id' => $this->course->id]);
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * An avatar_profile item has filter_type = 'avatar'.
     */
    public function test_filter_type_avatar_for_avatar_items(): void {
        $item = $this->create_item('Elf', 'avatar_profile');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found, 'Avatar item must appear in collection data.');
        $this->assertEquals('avatar', $found['filter_type']);
    }

    /**
     * A deadline_extension item has filter_type = 'deadline'.
     */
    public function test_filter_type_deadline_for_deadline_items(): void {
        $item = $this->create_item('Extra Time', 'deadline_extension');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found, 'Deadline item must appear in collection data.');
        $this->assertEquals('deadline', $found['filter_type']);
    }

    /**
     * Regression test for the N+1 performance finding: get_lp_activities() must be memoised,
     * not re-queried once per eligible deadline_extension item in the collection. Exercises
     * the render with two such items and confirms the query only runs once.
     */
    public function test_get_lp_activities_is_memoised_across_multiple_items(): void {
        global $DB;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        // Owned items only: the deadline_extension detail block (where get_lp_activities() is
        // called) is skipped entirely for unowned items.
        $item1 = $this->create_item('Extra Time A', 'deadline_extension');
        $item2 = $this->create_item('Extra Time B', 'deadline_extension');
        foreach ([$item1, $item2] as $item) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid' => $this->user->id, 'itemid' => $item->id, 'dropid' => 0,
                'source' => 'test', 'timecreated' => time(),
            ]);
        }

        $player = (object) ['userid' => $this->user->id, 'currentxp' => 0, 'last_inventory_view' => 0];
        $output = $this->createMock(\renderer_base::class);
        $tab    = new tab_collection(new \stdClass(), $player, $this->instanceid);

        // The full render processes both owned deadline_extension items, calling
        // get_lp_activities() twice internally — proves the loop itself only pays the query
        // cost once even with two eligible items.
        $ref = new \ReflectionMethod($tab, 'get_lp_activities');
        $ref->setAccessible(true);

        $beforefirst = $DB->perf_get_queries();
        $ref->invoke($tab);
        $firstcallqueries = $DB->perf_get_queries() - $beforefirst;

        $beforesecond = $DB->perf_get_queries();
        $ref->invoke($tab);
        $secondcallqueries = $DB->perf_get_queries() - $beforesecond;

        $this->assertGreaterThan(0, $firstcallqueries, 'The first call must actually query local_latepenalty_rules.');
        $this->assertSame(0, $secondcallqueries, 'A second call to get_lp_activities() must not hit the database.');

        // End-to-end: the real render (two owned deadline_extension items) still works and
        // surfaces the LP activity list, not just the isolated memoisation.
        $data = $tab->export_for_template($output);
        $found = $this->find_item($data, $item1->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('lp_activities', $found);
    }

    /**
     * A plain item (no action_type) has filter_type = 'none'.
     */
    public function test_filter_type_none_for_plain_items(): void {
        $item = $this->create_item('Magic Potion', '');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found, 'Plain item must appear in collection data.');
        $this->assertEquals('none', $found['filter_type']);
    }

    /**
     * An unowned, enabled, non-secret avatar item shows power_hint_avatar = true.
     */
    public function test_power_hint_avatar_shown_for_unowned_non_secret_item(): void {
        $item = $this->create_item('Vampire', 'avatar_profile');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertArrayHasKey('power_hint_avatar', $found);
        $this->assertTrue($found['power_hint_avatar']);
    }

    /**
     * A secret avatar item does not receive power_hint_avatar even when unowned.
     */
    public function test_power_hint_avatar_hidden_for_secret_item(): void {
        $item = $this->create_item('Secret Avatar', 'avatar_profile', secret: true);

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertArrayNotHasKey('power_hint_avatar', $found);
    }

    /**
     * An owned avatar item is flagged is_equipped = true when the user preference
     * matches its ID.
     */
    public function test_is_equipped_true_when_preference_matches_item(): void {
        global $DB;

        $item = $this->create_item('Fairy', 'avatar_profile');

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $this->user->id,
            'itemid'      => $item->id,
            'dropid'      => 0,
            'source'      => 'test',
            'timecreated' => time(),
        ]);

        set_user_preference('block_playerhud_avatar_' . $this->instanceid, $item->id, $this->user);

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertArrayHasKey('is_equipped', $found);
        $this->assertTrue($found['is_equipped']);
    }

    /**
     * An owned item with an action_type the card has no matching block for (e.g. 'playercoin',
     * a currency item with no avatar/deadline power of its own) must not set has_power — that
     * flag opens a bordered actions strip in the template that would otherwise render empty,
     * showing as a stray divider line with no content.
     */
    public function test_has_power_false_for_unrecognized_action_type(): void {
        $item = $this->create_item('PlayerCoin', 'playercoin');
        $this->grant_copy($item->id, 'map');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertArrayNotHasKey('has_power', $found);
    }

    /**
     * A deadline_extension item still sets has_power = false (not just an unset key) when the
     * companion plugin (local_latepenalty) is not installed — the recognised-action_type check
     * must gate has_power itself, not just which inner block renders.
     */
    public function test_has_power_false_for_deadline_extension_without_companion_plugin(): void {
        if (class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('local_latepenalty is installed in this environment.');
        }

        $item = $this->create_item('Scroll', 'deadline_extension');
        $this->grant_copy($item->id, 'map');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertArrayNotHasKey('has_power', $found);
    }

    /**
     * A large origin count (e.g. a currency item bought many times) gets a compact display
     * counterpart, mirroring count/count_display, without losing the exact value used for the
     * accessible title/aria-label text.
     */
    public function test_origin_display_is_compact_for_large_counts(): void {
        $item = $this->create_item('Gold Coin', '');
        $this->grant_stack($item->id, 4000, 'shop');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertSame(4000, $found['origin_shop']);
        $this->assertSame('4k', $found['origin_shop_display']);
    }

    /**
     * An inventory row whose source is outside PlayerHUD's own 4 (map/shop/quest/teacher) is
     * classified as origin_game, so external game plugins (e.g. mod_playerwords) are labelled
     * as such instead of falling into an unexplained "Others" bucket.
     */
    public function test_origin_game_for_unrecognized_source(): void {
        $item = $this->create_item('Gold Key', '');
        $this->grant_copy($item->id, 'playerwords');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertSame(1, $found['origin_game']);
        $this->assertArrayNotHasKey('origin_legacy', $found);
    }

    /**
     * Regression guard: a source PlayerHUD itself recognises (map) still classifies under its
     * own bucket, not under origin_game.
     */
    public function test_origin_map_still_classified_correctly(): void {
        $item = $this->create_item('Silver Key', '');
        $this->grant_copy($item->id, 'map');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertSame(1, $found['origin_map']);
        $this->assertSame(0, $found['origin_game']);
    }

    /**
     * An item obtained only through the new stacking engine (no legacy inventory rows at all)
     * still counts, dates, and badges correctly — count comes from block_playerhud_stack, the
     * "new" badge and date come from block_playerhud_stack_log, not from the (empty) legacy
     * per-copy loop.
     */
    public function test_new_engine_only_item_counts_and_shows_new_badge(): void {
        $item = $this->create_item('Diamond', '');
        $this->grant_stack($item->id, 5, 'shop');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertSame(5, $found['count']);
        $this->assertSame('5', $found['count_display']);
        $this->assertTrue($found['badge_new']);
        $this->assertSame(5, $found['origin_shop']);
    }

    /**
     * A user with balance split across both storage generations (legacy rows plus a new-engine
     * balance for the same item) sees the two sources summed into a single count.
     */
    public function test_combined_legacy_and_new_engine_balance_sums_correctly(): void {
        $item = $this->create_item('Ruby', '');
        $this->grant_copy($item->id, 'map');
        $this->grant_copy($item->id, 'map');
        $this->grant_stack($item->id, 3, 'quest');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertSame(5, $found['count']);
        $this->assertSame(2, $found['origin_map']);
        $this->assertSame(3, $found['origin_quest']);
    }

    /**
     * A quantity in the thousands is rendered compactly (count_display), while the exact value
     * is preserved separately in count for JS/data-attribute consumers.
     */
    public function test_high_quantity_uses_compact_display(): void {
        $item = $this->create_item('PlayerCoin', '');
        $this->grant_stack($item->id, 1500, 'shop');

        $data  = $this->export_for_user($this->user->id);
        $found = $this->find_item($data, $item->id);

        $this->assertNotNull($found);
        $this->assertSame(1500, $found['count']);
        $this->assertSame('1.5k', $found['count_display']);
    }

    /**
     * Build the export array for the given user with a minimal player object.
     *
     * @param int $userid User ID to build collection for.
     * @return array Template context from export_for_template.
     */
    private function export_for_user(int $userid): array {
        $player = (object) [
            'userid'               => $userid,
            'currentxp'            => 0,
            'last_inventory_view'  => 0,
        ];
        $output = $this->createMock(\renderer_base::class);
        $tab    = new tab_collection(new \stdClass(), $player, $this->instanceid);
        return $tab->export_for_template($output);
    }

    /**
     * Find a specific item by ID in the export result's items array.
     *
     * @param array $data Return value of export_for_template.
     * @param int $itemid Item ID to search for.
     * @return array|null The item data array, or null if not found.
     */
    private function find_item(array $data, int $itemid): ?array {
        foreach ($data['items'] as $itemdata) {
            if ((int) $itemdata['id'] === $itemid) {
                return $itemdata;
            }
        }
        return null;
    }

    /**
     * Insert a minimal block_instances row and return its ID.
     *
     * @return int The new instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        return $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Insert a minimal item and return it with id set.
     *
     * @param string $name Item name.
     * @param string $actiontype action_type value (empty string = plain item).
     * @param bool $secret Whether the item is secret.
     * @return \stdClass The inserted record.
     */
    private function create_item(string $name, string $actiontype, bool $secret = false): \stdClass {
        global $DB;
        $item = (object) [
            'blockinstanceid'  => $this->instanceid,
            'name'             => $name,
            'image'            => '',
            'description'      => '',
            'xp'               => 0,
            'enabled'          => 1,
            'tradable'         => 1,
            'secret'           => $secret ? 1 : 0,
            'required_class_id' => '0',
            'action_type'      => $actiontype,
            'action_value'     => '',
            'timecreated'      => time(),
            'timemodified'     => time(),
        ];
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Grant the current test user a copy of an item from the given source.
     *
     * @param int $itemid Item ID to grant.
     * @param string $source Inventory source value (e.g. 'map', 'playerwords').
     * @return void
     */
    private function grant_copy(int $itemid, string $source): void {
        global $DB;
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $this->user->id,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => $source,
            'timecreated' => time(),
        ]);
    }

    /**
     * Grant the current test user a quantity via the new stacking engine.
     *
     * @param int $itemid Item ID to grant.
     * @param int $qty Quantity to grant.
     * @param string $source Grant source value (e.g. 'shop', 'quest').
     * @return void
     */
    private function grant_stack(int $itemid, int $qty, string $source): void {
        \block_playerhud\local\external_items::grant(
            $this->instanceid,
            $itemid,
            $this->user->id,
            $qty,
            $source,
            true
        );
    }
}
