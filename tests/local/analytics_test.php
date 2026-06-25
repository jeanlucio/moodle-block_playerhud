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

namespace block_playerhud\local;

use advanced_testcase;

/**
 * Tests for the reporting and balance analytics helper.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\local\analytics
 */
final class analytics_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected $instanceid;

    /**
     * Set up a block instance for the economy tests.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $this->instanceid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'subpagepattern' => null, 'defaultregion' => 'side-pre',
            'defaultweight' => 0, 'configdata' => base64_encode(serialize(new \stdClass())),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Create an enabled item with a fixed XP value.
     *
     * @param string $name Item name.
     * @param int $xp XP each copy is worth.
     * @return int The created item ID.
     */
    protected function create_item(string $name, int $xp): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => $name, 'xp' => $xp, 'image' => '',
            'description' => '', 'enabled' => 1, 'secret' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Attach a drop to an item.
     *
     * @param int $itemid The item ID.
     * @param int $maxusage Maximum collections (0 = infinite).
     */
    protected function create_drop(int $itemid, int $maxusage): void {
        global $DB;
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid, 'itemid' => $itemid, 'name' => 'Loc',
            'maxusage' => $maxusage, 'respawntime' => 0, 'code' => 'C' . rand(1000, 9999),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * An instance with no items or quests reports an empty economy.
     *
     * @covers ::economy_health
     */
    public function test_economy_health_empty(): void {
        $health = analytics::economy_health($this->instanceid, 100, 10);
        $this->assertEquals(0, $health->total_items_xp);
        $this->assertEquals(1000, $health->xp_ceiling);
        $this->assertEquals('empty', $health->status);
        $this->assertSame([], $health->breakdown);
    }

    /**
     * Total earnable XP below the ceiling is flagged as too hard.
     *
     * @covers ::economy_health
     */
    public function test_economy_health_hard(): void {
        // 1 item x 5 uses x 50 XP = 250, ceiling 1000 -> 25% -> hard.
        $item = $this->create_item('Coin', 50);
        $this->create_drop($item, 5);

        $health = analytics::economy_health($this->instanceid, 100, 10);
        $this->assertEquals(250, $health->total_items_xp);
        $this->assertEquals('hard', $health->status);
        $this->assertEqualsWithDelta(25.0, $health->ratio, 0.01);
        $this->assertCount(1, $health->breakdown);
        $this->assertEquals(250, $health->breakdown[0]['xp_total']);
    }

    /**
     * Matching the ceiling exactly is a perfect balance; quest rewards count too.
     *
     * @covers ::economy_health
     */
    public function test_economy_health_perfect_includes_quests(): void {
        // Item: 1 use x 600 = 600. Quest reward: 400. Total 1000 = ceiling -> perfect.
        $item = $this->create_item('Relic', 600);
        $this->create_drop($item, 1);
        global $DB;
        $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Bonus', 'description' => '', 'type' => 1,
            'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 400, 'reward_itemid' => 0,
            'required_class_id' => '0', 'image_todo' => '', 'image_done' => '', 'enabled' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $health = analytics::economy_health($this->instanceid, 100, 10);
        $this->assertEquals(1000, $health->total_items_xp);
        $this->assertEquals('perfect', $health->status);
        $this->assertEqualsWithDelta(100.0, $health->ratio, 0.01);
    }

    /**
     * Earnable XP above the ceiling is flagged as too easy.
     *
     * @covers ::economy_health
     */
    public function test_economy_health_easy(): void {
        // 1 item x 3 uses x 1000 = 3000, ceiling 1000 -> 300% -> easy.
        $item = $this->create_item('Jackpot', 1000);
        $this->create_drop($item, 3);

        $health = analytics::economy_health($this->instanceid, 100, 10);
        $this->assertEquals(3000, $health->total_items_xp);
        $this->assertEquals('easy', $health->status);
    }

    /**
     * An item without a drop contributes one copy; an infinite drop is marked.
     *
     * @covers ::economy_health
     */
    public function test_economy_health_no_drop_and_infinite(): void {
        $loose = $this->create_item('Loose', 70);
        $infinite = $this->create_item('Spring', 40);
        $this->create_drop($infinite, 0);

        $health = analytics::economy_health($this->instanceid, 100, 10);

        // Loose item counts once (70); the infinite drop adds nothing to the total.
        $this->assertEquals(70, $health->total_items_xp);

        $byname = [];
        foreach ($health->breakdown as $row) {
            $byname[$row['name']] = $row;
        }
        $this->assertEquals(1, $byname['Loose']['total_uses']);
        $this->assertTrue($byname['Spring']['infinite']);
        $this->assertEquals('∞', $byname['Spring']['total_uses']);
    }

    /**
     * A zero ceiling never divides by zero and yields a zero ratio.
     *
     * @covers ::economy_health
     */
    public function test_economy_health_zero_ceiling(): void {
        $item = $this->create_item('Coin', 50);
        $this->create_drop($item, 5);

        $health = analytics::economy_health($this->instanceid, 0, 10);
        $this->assertEquals(0, $health->xp_ceiling);
        $this->assertEquals(0, $health->ratio);
        // Ratio is forced to 0 to avoid division by zero, so a non-empty economy reads as "hard".
        $this->assertEquals('hard', $health->status);
    }

    /**
     * level_distribution buckets players by level and caps overflow into one bucket.
     *
     * @covers ::level_distribution
     */
    public function test_level_distribution_buckets_and_overflow(): void {
        // With 100 XP per level and a cap of 5, the players land on levels 1, 1, 2, 5 and 10.
        // The level-5 player fills the cap bucket; the level-10 player overflows into "5+".
        $dist = analytics::level_distribution([0, 50, 150, 450, 999], 100, 5);

        $bylabel = [];
        foreach ($dist as $row) {
            $bylabel[$row['label']] = $row['total'];
        }
        $this->assertEquals(2, $bylabel['1']);
        $this->assertEquals(1, $bylabel['2']);
        $this->assertEquals(1, $bylabel['5']);
        $this->assertEquals(1, $bylabel['5+']);

        // The tallest bar (level 1, two players) is 100%.
        $this->assertEqualsWithDelta(100.0, $dist[0]['percent'], 0.01);
        $this->assertEquals('1', $dist[0]['label']);
        // The cap bucket "5" sorts immediately before the overflow bucket "5+".
        $labels = array_column($dist, 'label');
        $this->assertEquals(array_search('5', $labels, true) + 1, array_search('5+', $labels, true));
        $this->assertEquals('5+', $labels[count($labels) - 1]);
    }

    /**
     * An empty player set produces no distribution rows.
     *
     * @covers ::level_distribution
     */
    public function test_level_distribution_empty(): void {
        $this->assertSame([], analytics::level_distribution([], 100, 10));
    }

    /**
     * A non-positive XP-per-level never divides by zero; everyone lands on level 1.
     *
     * @covers ::level_distribution
     */
    public function test_level_distribution_guards_zero_xpperlevel(): void {
        $dist = analytics::level_distribution([0, 500, 9000], 0, 5);
        $this->assertCount(1, $dist);
        $this->assertEquals('1', $dist[0]['label']);
        $this->assertEquals(3, $dist[0]['total']);
    }
}
