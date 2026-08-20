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
 * Tests for the anonymous usage reporter.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use advanced_testcase;

/**
 * Tests for the anonymous usage reporter.
 *
 * send() itself is deliberately not exercised here — it performs real network I/O, which does
 * not belong in a unit test. The logic worth testing lives in is_due() (the rate-limit gate)
 * and build_payload() (the shape of what would be sent), both pure of network access.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\usage_reporter
 */
final class usage_reporter_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * With the setting disabled, no report is ever due, regardless of timing.
     */
    public function test_is_due_is_false_when_setting_disabled(): void {
        set_config('usagereport', '0', 'block_playerhud');
        $this->assertFalse(usage_reporter::is_due());
    }

    /**
     * A site that has never sent a report (usagereportlast never set) is due immediately once
     * the setting is enabled — this is what guarantees the first report reaches the developer
     * within one cron cycle of the site enabling/upgrading, not after waiting out the interval.
     */
    public function test_is_due_is_true_on_first_run_when_never_sent(): void {
        set_config('usagereport', '1', 'block_playerhud');
        $this->assertFalse((bool) get_config('block_playerhud', usage_reporter::CONFIG_LAST_SENT));

        $this->assertTrue(usage_reporter::is_due());
    }

    /**
     * Right after a send, the report is not due again until the interval elapses.
     */
    public function test_is_due_is_false_immediately_after_a_send(): void {
        set_config('usagereport', '1', 'block_playerhud');
        set_config(usage_reporter::CONFIG_LAST_SENT, time(), 'block_playerhud');

        $this->assertFalse(usage_reporter::is_due());
    }

    /**
     * Once the interval has fully elapsed, the report becomes due again.
     */
    public function test_is_due_is_true_after_the_interval_elapses(): void {
        set_config('usagereport', '1', 'block_playerhud');
        set_config(usage_reporter::CONFIG_LAST_SENT, time() - usage_reporter::REPORT_INTERVAL - 1, 'block_playerhud');

        $this->assertTrue(usage_reporter::is_due());
    }

    /**
     * The payload carries every field the receiving service expects, and reflects whatever
     * diagnostics counters have accumulated since the last send.
     */
    public function test_build_payload_contains_expected_keys_and_error_counters(): void {
        diagnostics::increment('ai_all_providers_failed');

        $payload = usage_reporter::build_payload();

        $this->assertSame('block_playerhud', $payload['plugin']);
        $this->assertArrayHasKey('siteidentifier', $payload);
        $this->assertArrayHasKey('url', $payload);
        $this->assertArrayHasKey('moodle_version', $payload);
        $this->assertArrayHasKey('moodle_release', $payload);
        $this->assertArrayHasKey('php_version', $payload);
        $this->assertArrayHasKey('country', $payload);
        $this->assertArrayHasKey('lang', $payload);
        $this->assertArrayHasKey('active_users', $payload);
        $this->assertArrayHasKey('course_count', $payload);
        $this->assertArrayHasKey('site_size_bucket', $payload);
        $this->assertArrayHasKey('moodle_flavour', $payload);
        $this->assertArrayHasKey('moodle_flavour_version', $payload);
        $this->assertArrayHasKey('companion_plugins', $payload);
        $this->assertSame(['ai_all_providers_failed' => 1], $payload['error_counters']);
    }

    /**
     * The payload's error_counters is empty for a site with nothing to report.
     */
    public function test_build_payload_has_empty_error_counters_when_nothing_failed(): void {
        $payload = usage_reporter::build_payload();
        $this->assertSame([], $payload['error_counters']);
    }

    /**
     * moodle_flavour/moodle_flavour_version detect Totara/IOMAD/Workplace forks — on the
     * vanilla Moodle this test suite runs against, both must come back null, never a false
     * positive that would misreport a real site's platform.
     */
    public function test_build_payload_flavour_is_null_on_vanilla_moodle(): void {
        $payload = usage_reporter::build_payload();
        $this->assertNull($payload['moodle_flavour']);
        $this->assertNull($payload['moodle_flavour_version']);
    }

    /**
     * companion_plugins mirrors exactly the list already shown in this plugin's own
     * "Companion Plugins" admin settings section (settings.php), so the two can never drift
     * apart. Cross-checks against the live core_plugin_manager rather than hardcoding
     * presence/versions, so the test holds regardless of which companion plugins happen to be
     * installed in whichever environment runs it.
     */
    public function test_build_payload_reports_installed_companion_plugins(): void {
        $payload = usage_reporter::build_payload();
        $this->assertArrayHasKey('companion_plugins', $payload);

        $pluginman = \core_plugin_manager::instance();
        foreach (['availability_playerhud', 'filter_playerhud'] as $component) {
            $info = $pluginman->get_plugin_info($component);
            if ($info === null) {
                $this->assertArrayNotHasKey($component, $payload['companion_plugins']);
                continue;
            }
            $this->assertArrayHasKey($component, $payload['companion_plugins']);
            $this->assertSame((string) $info->release, $payload['companion_plugins'][$component]['r']);
            $this->assertSame((string) $info->versiondisk, $payload['companion_plugins'][$component]['v']);
        }
    }

    /**
     * course_count reflects deployment breadth (how many courses have the block added), not
     * engagement — a course with the block placed but nobody having earned XP yet still counts.
     * Only course-context placements count; a placement outside a course (e.g. system context)
     * must not be mistaken for a course deployment.
     */
    public function test_build_payload_course_count_only_counts_course_context_placements(): void {
        global $DB;

        $this->assertSame(0, usage_reporter::build_payload()['course_count']);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->timecreated = time();
        $bi->timemodified = time();
        $DB->insert_record('block_instances', $bi);

        $this->assertSame(1, usage_reporter::build_payload()['course_count']);

        // A placement outside a course context (system, in this case) must not inflate the
        // count — it is not a course deployment.
        $systembi = clone $bi;
        $systembi->parentcontextid = \context_system::instance()->id;
        $systembi->pagetypepattern = 'site-index';
        $DB->insert_record('block_instances', $systembi);

        $this->assertSame(1, usage_reporter::build_payload()['course_count']);
    }

    /**
     * size_bucket() maps a total user count to the four agreed-upon ranges, including both
     * boundaries of each range.
     *
     * @dataProvider size_bucket_provider
     * @param int $totalusers
     * @param string $expected
     */
    public function test_size_bucket_maps_totals_to_ranges(int $totalusers, string $expected): void {
        $this->assertSame($expected, $this->call_size_bucket($totalusers));
    }

    /**
     * Data provider for test_size_bucket_maps_totals_to_ranges().
     *
     * @return array
     */
    public static function size_bucket_provider(): array {
        return [
            'zero users' => [0, '<50'],
            'just under first boundary' => [49, '<50'],
            'first boundary' => [50, '50-500'],
            'just under second boundary' => [499, '50-500'],
            'second boundary' => [500, '500-5000'],
            'just under third boundary' => [4999, '500-5000'],
            'third boundary' => [5000, '>5000'],
            'well above third boundary' => [50000, '>5000'],
        ];
    }

    /**
     * Invoke the protected static size_bucket() via reflection, matching the pattern already
     * used elsewhere in this plugin's test suite (e.g. tests/ai/generator_test.php) for testing
     * protected helpers without widening their visibility just for tests.
     *
     * @param int $totalusers
     * @return string
     */
    private function call_size_bucket(int $totalusers): string {
        $method = new \ReflectionMethod(usage_reporter::class, 'size_bucket');
        $method->setAccessible(true);

        return $method->invoke(null, $totalusers);
    }
}
