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

namespace block_playerhud\local\diagnostics;

use advanced_testcase;

/**
 * Tests for the cached diagnostics report.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\diagnostics\report
 */
final class report_test extends advanced_testcase {
    /** @var int Live block instance. */
    protected int $instanceid;

    /**
     * Creates one live instance.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->instanceid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Adds a player to the live instance.
     *
     * @return void
     */
    private function add_player(): void {
        global $DB;
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $this->getDataGenerator()->create_user()->id,
            'currentxp' => 10,
            'enable_gamification' => 1,
            'ranking_visibility' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The report has every section the page reads.
     */
    public function test_compute_shape(): void {
        $report = report::compute();

        $keys = ['time', 'since', 'adoption', 'engagement', 'orphans', 'orphancount', 'orphanplayers', 'loose'];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $report);
        }
        $this->assertSame(usage::WINDOWDAYS * DAYSECS, $report['time'] - $report['since']);
    }

    /**
     * A fresh report is reused until refreshed or invalidated.
     */
    public function test_cache_is_reused_until_refresh_or_invalidate(): void {
        $this->add_player();
        $this->assertSame(1, report::get()['engagement']['players']);

        $this->add_player();
        $this->assertSame(1, report::get()['engagement']['players'], 'Cached copy reused.');
        $this->assertSame(2, report::get(true)['engagement']['players'], 'Refresh recounts.');

        $this->add_player();
        report::invalidate();
        $this->assertSame(3, report::get()['engagement']['players'], 'Invalidate forces a recount.');
    }

    /**
     * A cached report older than MAXAGE is recounted.
     */
    public function test_stale_report_is_recounted(): void {
        $stale = report::compute();
        $stale['time'] = time() - report::MAXAGE - 1;
        $stale['engagement']['players'] = 42;
        \cache::make('block_playerhud', 'diagnostics')->set('report', $stale);

        $report = report::get();

        $this->assertSame(0, $report['engagement']['players']);
        $this->assertGreaterThanOrEqual(time() - 5, $report['time']);
    }
}
