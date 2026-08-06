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
 * Tests for the history tab (audit log listing with sort/filter headers).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep. Tests
 * export_for_template() directly rather than display(): display() reads the global
 * $OUTPUT, which in a bare PHPUnit run is still the bootstrap_renderer stand-in and
 * would fail the strict renderer_base type check — the same constraint documented
 * for the manage/ tabs, but here purely a test-harness concern (production view.php
 * always calls header() first). $output stays untyped in export_for_template()
 * itself because it is forwarded to get_audit_logs(), which requires the concrete
 * \core\output\core_renderer type — same case as header.php/tab_ranking.php.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use advanced_testcase;

/**
 * Tests for the history tab renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_history
 */
final class tab_history_test extends advanced_testcase {
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
        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/view.php', ['id' => $this->course->id]);
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * Creates a minimal block_instances row and returns its id.
     *
     * @return int The new block instance ID.
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
     * A player with no logged events still exports a well-formed empty state, with
     * all 5 sortable column headers present.
     */
    public function test_export_for_template_with_no_logs(): void {
        $_GET['id'] = $this->course->id;

        $config = new \stdClass();
        $player = (object) ['userid' => $this->user->id];
        $tab = new tab_history($config, $player, $this->instanceid);

        $data = $tab->export_for_template($this->createMock(\core\output\core_renderer::class));

        $this->assertFalse($data['has_logs']);
        $this->assertSame([], $data['logs']);
        $this->assertArrayHasKey('date', $data['headers']);
        $this->assertArrayHasKey('type', $data['headers']);
        $this->assertArrayHasKey('element', $data['headers']);
        $this->assertArrayHasKey('xp', $data['headers']);
        $this->assertArrayHasKey('details', $data['headers']);
    }

    /**
     * Regression test for the security-audit finding: when inventory.source has no matching
     * report_src_<source> lang string, the raw value was used unescaped as details_html, which
     * the template renders via triple-mustache ({{{details_html}}}) — a live-HTML sink. The
     * fallback must now be escaped, so a stray '<'/'>' in an unrecognised source value (e.g. a
     * future third-party integrator's tag) can never be interpreted as markup.
     */
    public function test_export_for_template_escapes_unknown_source_fallback(): void {
        global $DB;

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Test Item',
            'xp'              => 0,
            'enabled'         => 1,
            'tradable'        => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        // The inventory.source column is CHAR(20) — keep the payload within that limit.
        $payload = '<img src=x>';
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $this->user->id,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => $payload,
            'timecreated' => time(),
            'xpawarded'   => 0,
        ]);

        $_GET['id'] = $this->course->id;

        $config = new \stdClass();
        $player = (object) ['userid' => $this->user->id];
        $tab = new tab_history($config, $player, $this->instanceid);

        $data = $tab->export_for_template($this->createMock(\core\output\core_renderer::class));

        $this->assertTrue($data['has_logs']);
        $this->assertStringNotContainsString('<img', $data['logs'][0]['details_html']);
        $this->assertStringContainsString('&lt;img', $data['logs'][0]['details_html']);
    }
}
