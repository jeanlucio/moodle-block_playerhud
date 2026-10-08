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
 * Tests for the items tab's late penalty lookups.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;

/**
 * The items tab asks which activities of its course have a late penalty rule. The rules table is
 * site-wide, so the lookup must be restricted to the course's own activities instead of reading
 * every rule of the site and discarding the ones from other courses afterwards.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_items
 */
final class tab_items_test extends advanced_testcase {
    /** @var \stdClass The course holding the tab. */
    private \stdClass $course;

    /** @var \stdClass A page in the course with an enabled rule. */
    private \stdClass $page;

    /**
     * Builds a course with one rule-bearing page, plus rules for another course and for a module
     * that no longer exists.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        if (!class_exists(\local_latepenalty\recalculator::class)) {
            $this->markTestSkipped('local_latepenalty is not installed.');
        }

        $this->course = $this->getDataGenerator()->create_course();
        $this->page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => 'Essay']);
        $other = $this->getDataGenerator()->create_course();
        $otherpage = $this->getDataGenerator()->create_module('page', ['course' => $other->id]);

        foreach ([$this->page->cmid, $otherpage->cmid, 999999] as $cmid) {
            // The late penalty plugin may already have created a rule for a new module.
            $existing = $DB->get_record('local_latepenalty_rules', ['cmid' => $cmid]);
            if ($existing) {
                $DB->set_field('local_latepenalty_rules', 'enabled', 1, ['id' => $existing->id]);
                continue;
            }
            $DB->insert_record('local_latepenalty_rules', (object) [
                'cmid' => $cmid, 'enabled' => 1, 'daily_penalty' => 5, 'max_penalty' => 50,
                'recalc_on_deadline' => 1, 'recalc_on_rate' => 1, 'last_deadline' => 0, 'keepbest' => 0,
                'timeenabled' => 0,
            ]);
        }
    }

    /**
     * Builds the tab for the test course.
     *
     * @return tab_items
     */
    private function make_tab(): tab_items {
        $context = \context_course::instance($this->course->id);
        $blockid = (int) $this->getDataGenerator()->create_block('playerhud', [
            'parentcontextid' => $context->id,
        ])->id;

        return new tab_items($blockid, (int) $this->course->id);
    }

    /**
     * Calls a protected method of the tab.
     *
     * @param tab_items $tab The tab.
     * @param string $method Method name.
     * @param array $args Arguments.
     * @return mixed The method result.
     */
    private function call(tab_items $tab, string $method, array $args = []) {
        $reflection = new \ReflectionMethod($tab, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($tab, $args);
    }

    /**
     * Only the course's own activities with a rule are offered.
     */
    public function test_lp_activities_are_limited_to_the_course(): void {
        $activities = $this->call($this->make_tab(), 'get_lp_activities');

        $this->assertSame([(int) $this->page->cmid => 'Essay'], $activities);
    }

    /**
     * The rules are looked up by the course's activity ids, not read for the whole site.
     */
    public function test_lp_rules_query_is_restricted_to_the_course_activities(): void {
        global $DB;

        $tab = $this->make_tab();
        ob_start();
        $DB->set_debug(true);
        $this->call($tab, 'get_lp_activities');
        $DB->set_debug(false);
        $sql = ob_get_clean();

        // A single activity makes the DML layer write "cmid = $1" instead of "cmid IN (...)".
        $this->assertMatchesRegularExpression('/local_latepenalty_rules\s+WHERE[^\n]*\bcmid\s*(=|IN\s*\()/i', $sql);
    }

    /**
     * "Does the course have any rule" is answered for the course only, and from its activity ids.
     */
    public function test_course_has_lp_activities_looks_only_at_the_course(): void {
        global $DB;

        $tab = $this->make_tab();
        $this->assertTrue($this->call($tab, 'course_has_lp_activities', [get_fast_modinfo($this->course->id)]));

        $DB->set_field('local_latepenalty_rules', 'enabled', 0, ['cmid' => $this->page->cmid]);
        $this->assertFalse($this->call($tab, 'course_has_lp_activities', [get_fast_modinfo($this->course->id)]));
    }
}
