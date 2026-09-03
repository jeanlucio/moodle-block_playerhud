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
 * Tests for the reports tab renderer (KPIs, charts, quest stats, audit drill-down).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep.
 * export_for_template()'s $output parameter stays untyped on purpose — see the
 * bootstrap_renderer case log in moodle-lessons.md — which is precisely why
 * display() (unlike display() on files where $output IS typed renderer_base) can
 * be exercised directly here: with no native type hint to violate, passing the
 * still-bootstrap_renderer global $OUTPUT through does not throw a TypeError.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;

/**
 * Tests for the reports tab renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_reports
 * @covers     \block_playerhud\local\external_items
 */
final class tab_reports_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Create a fresh course, block instance and logged-in teacher for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->setUser($this->getDataGenerator()->create_user());

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/manage.php', ['id' => $this->course->id]);
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
     * A mocked core_renderer, satisfying both the outer renderer_base type check and
     * get_ai_logs()/get_audit_logs()'s own core_renderer|bootstrap_renderer union for
     * paging_bar().
     *
     * @return \core_renderer
     */
    private function mock_output(): \core_renderer {
        $output = $this->createMock(\core_renderer::class);
        $output->method('paging_bar')->willReturn('');
        return $output;
    }

    /**
     * Inserts an AI log row directly and returns its id.
     *
     * @param int $timecreated Creation timestamp, to control ordering.
     * @return int The new log row id.
     */
    private function create_ai_log(int $timecreated): int {
        global $DB;
        return $DB->insert_record('block_playerhud_ai_logs', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => 2,
            'action_type'     => 'item',
            'object_name'     => 'Widget',
            'ai_provider'     => 'Gemini',
            'timecreated'     => $timecreated,
        ]);
    }

    /**
     * An instance with no players/items/quests still exports a well-formed summary
     * with the audit drill-down inactive, instead of crashing.
     */
    public function test_export_for_template_empty_instance(): void {
        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertFalse($data['is_audit']);
        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('charts', $data);
        $this->assertArrayHasKey('quest_stats', $data);
        $this->assertArrayHasKey('user_selector', $data);
    }

    /**
     * The audit user selector omits email from every option's label once the site removes
     * it from showuseridentity, mirroring what the participants list already does.
     */
    public function test_export_for_template_user_selector_omits_email_when_hidden_by_site_policy(): void {
        $this->setAdminUser();
        set_config('showuseridentity', 'idnumber');

        $student = $this->getDataGenerator()->create_user(['email' => 'ana@example.com']);
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        global $DB;
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $student->id,
            'currentxp'       => 10,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $labels = array_column($data['user_selector']['options'], 'label');
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('ana@example.com', $label);
        }
    }

    /**
     * The audit user selector lists students in the order they are actually read — collated by
     * the visible label — not by the SQL lastname/firstname order, which looks shuffled once the
     * label renders as "Firstname Lastname". The placeholder stays first.
     */
    public function test_export_for_template_user_selector_is_alphabetical_by_label(): void {
        global $DB;

        $names = [
            ['firstname' => 'Eve', 'lastname' => 'Adaga'],
            ['firstname' => 'Bob', 'lastname' => 'Arco'],
            ['firstname' => 'Alice', 'lastname' => 'Espada'],
            ['firstname' => 'Dave', 'lastname' => 'Escudo'],
        ];
        foreach ($names as $name) {
            $student = $this->getDataGenerator()->create_user($name);
            $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid' => $this->instanceid,
                'userid'          => $student->id,
                'currentxp'       => 10,
                'timecreated'     => time(),
                'timemodified'    => time(),
            ]);
        }

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $options = $data['user_selector']['options'];
        $this->assertSame(0, $options[0]['value'], 'The placeholder option must stay first.');

        $studentlabels = array_column(array_slice($options, 1), 'label');
        $sorted = $studentlabels;
        \core_collator::asort($sorted);
        $this->assertSame(array_values($sorted), $studentlabels);
        $this->assertStringStartsWith('Alice Espada', reset($studentlabels));
    }

    /**
     * display() renders real HTML end to end, going through the global $OUTPUT.
     */
    public function test_display_renders_html(): void {
        $tab = new tab_reports($this->instanceid, $this->course->id);
        $html = $tab->display();

        $this->assertIsString($html);
        $this->assertNotSame('', $html);
    }

    /**
     * More than 30 AI log rows only export the first page (30) by default, newest first.
     */
    public function test_export_for_template_ai_logs_paginate_at_30(): void {
        $now = time();
        for ($i = 0; $i < 35; $i++) {
            $this->create_ai_log($now + $i);
        }

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertTrue($data['has_ai_logs']);
        $this->assertCount(30, $data['ai_logs']);
        $this->assertSame(0, $data['ai_showall']);
    }

    /**
     * ai_showall=1 returns every AI log row instead of the default 30-row page.
     */
    public function test_export_for_template_ai_logs_showall_returns_everything(): void {
        $now = time();
        for ($i = 0; $i < 35; $i++) {
            $this->create_ai_log($now + $i);
        }

        $_GET['ai_showall'] = 1;

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertSame(1, $data['ai_showall']);
        $this->assertCount(35, $data['ai_logs']);
    }

    /**
     * Regression test for the security-audit finding: when inventory.source has no matching
     * report_src_<source> lang string, the raw value was used unescaped as details_html, which
     * the template renders via triple-mustache ({{{details_html}}}) — a live-HTML sink on the
     * teacher's own Reports tab. The fallback must now be escaped.
     */
    public function test_export_for_template_audit_drilldown_escapes_unknown_source_fallback(): void {
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
            'userid'      => 2,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => $payload,
            'timecreated' => time(),
            'xpawarded'   => 0,
        ]);

        $_GET['r_userid'] = 2;

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $this->assertTrue($data['is_audit']);
        $this->assertNotEmpty($data['audit_logs']);
        $this->assertStringNotContainsString('<img', $data['audit_logs'][0]['details_html']);
        $this->assertStringContainsString('&lt;img', $data['audit_logs'][0]['details_html']);
    }

    /**
     * The "most collected item" KPI sums how many times an item was ever granted across both
     * storage generations — legacy inventory rows and the new engine's ledger.
     */
    public function test_kpi_most_collected_item_sums_both_storage_generations(): void {
        global $DB;

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Diamante', 'xp' => 0,
            'enabled' => 1, 'tradable' => 1, 'secret' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        // 3 legacy rows.
        for ($i = 0; $i < 3; $i++) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid' => 2, 'itemid' => $itemid, 'dropid' => 0, 'source' => 'map',
                'timecreated' => time(), 'xpawarded' => 0,
            ]);
        }
        // 40 more via the new engine, in one grant.
        \block_playerhud\local\external_items::grant($this->instanceid, $itemid, 2, 40, 'teacher', true);

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $mostcollected = null;
        foreach ($data['kpis'] as $card) {
            if ($card['title'] === get_string('report_most_collected', 'block_playerhud')) {
                $mostcollected = $card;
            }
        }

        $this->assertNotNull($mostcollected);
        $this->assertSame('Diamante', $mostcollected['value']);
        $this->assertSame(
            get_string('report_collected_times', 'block_playerhud', 43),
            $mostcollected['subtitle']
        );
    }

    /**
     * The students table's total_items sums current active holdings across both storage
     * generations for an enrolled student.
     */
    public function test_students_table_total_items_sums_both_storage_generations(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');

        $legacyitem = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Iron Ore', 'xp' => 0,
            'enabled' => 1, 'tradable' => 1, 'secret' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $student->id, 'itemid' => $legacyitem, 'dropid' => 0, 'source' => 'map',
            'timecreated' => time(), 'xpawarded' => 0,
        ]);
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $student->id, 'itemid' => $legacyitem, 'dropid' => 0, 'source' => 'map',
            'timecreated' => time(), 'xpawarded' => 0,
        ]);

        $newitem = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Diamante', 'xp' => 0,
            'enabled' => 1, 'tradable' => 1, 'secret' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        \block_playerhud\local\external_items::grant($this->instanceid, $newitem, $student->id, 40, 'teacher', true);
        \block_playerhud\game::get_player($this->instanceid, $student->id);

        $tab = new tab_reports($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->mock_output());

        $row = null;
        foreach ($data['students'] as $studentrow) {
            if ((int) $studentrow['id'] === (int) $student->id) {
                $row = $studentrow;
            }
        }

        $this->assertNotNull($row, 'Enrolled student must appear in the students table.');
        $this->assertSame(42, (int) $row['total_items']);
    }
}
