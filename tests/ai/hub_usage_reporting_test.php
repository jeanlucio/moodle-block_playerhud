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
 * Tests that every provider attempt made with a hub-borrowed key reaches the AI Hub's
 * usage log, not just the one that finally answered.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\ai;

use block_playerhud\tests\ai\hub_reporting_double;
use block_playerhud\tests\external\external_base_testcase;

/**
 * local_aihub is a soft dependency: block_playerhud installs and works without it, and
 * the CI matrix for this plugin does not install it alongside. Every test here skips
 * when the hub class is absent, since the guarded integration has nothing to exercise.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\ai\generator
 */
final class hub_usage_reporting_test extends external_base_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\local_aihub\ai::class)) {
            $this->markTestSkipped('local_aihub is not installed; the hub-reporting path is inert.');
        }
    }

    /**
     * A hub-borrowed key that fails on every provider in its tier must leave a failed
     * row per provider in the hub's usage log, not just the last one tried — before
     * this fix, overwriting $result on each attempt lost every failure but the final
     * one, and a request that failed outright reported nothing at all.
     */
    public function test_reports_every_failed_attempt_with_a_hub_key(): void {
        global $DB;

        $double = new hub_reporting_double(
            $this->instanceid,
            ['fakegemini', 'fakegroq', '', '', '', 'hub_site'],
            [
                ['success' => false, 'message' => 'gemini down', 'provider' => 'Gemini'],
                ['success' => false, 'message' => 'groq down', 'provider' => 'Groq'],
            ]
        );

        try {
            $double->call_with_fallback_public(['system' => '', 'user' => 'hi'], 'test_desc');
            $this->fail('Expected a moodle_exception when every provider in the tier fails.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('groq down', $e->getMessage());
        }

        $rows = array_values($DB->get_records('local_aihub_log', ['component' => 'block_playerhud'], 'id ASC'));
        $this->assertCount(2, $rows, 'Both failed attempts must reach the hub log, not just the last one.');

        $this->assertSame('Gemini', $rows[0]->provider);
        $this->assertSame(0, (int) $rows[0]->success);
        $this->assertSame('gemini down', $rows[0]->errormessage);
        $this->assertSame('site', $rows[0]->keysource);

        $this->assertSame('Groq', $rows[1]->provider);
        $this->assertSame(0, (int) $rows[1]->success);
        $this->assertSame('groq down', $rows[1]->errormessage);
    }

    /**
     * A plugin-owned key (own_personal / own_site) must never leak into the hub's usage
     * log — the hub's report exists to explain hub-borrowed key usage, not every AI
     * call the plugin ever makes.
     */
    public function test_never_reports_a_plugin_owned_key(): void {
        global $DB;

        $double = new hub_reporting_double(
            $this->instanceid,
            ['fakegemini', '', '', '', '', 'own_personal'],
            [
                ['success' => true, 'data' => '{}', 'provider' => 'Gemini'],
            ]
        );

        $double->call_with_fallback_public(['system' => '', 'user' => 'hi'], 'test_desc');

        $this->assertEquals(0, $DB->count_records('local_aihub_log', ['component' => 'block_playerhud']));
    }
}
