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

namespace block_playerhud\output\admin;

use advanced_testcase;
use moodle_url;

/**
 * Tests for the template data of the administrator's diagnostics page.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\admin\diagnostics
 */
final class diagnostics_test extends advanced_testcase {
    /**
     * Builds a report with the given orphans and loose rows and zero everywhere else.
     *
     * @param array $orphans Orphan instance objects.
     * @param array $loose Loose row counts keyed by table.
     * @return array Report in the shape of report::get().
     */
    private function make_report(array $orphans = [], array $loose = []): array {
        return [
            'time' => time(),
            'since' => time() - 90 * DAYSECS,
            'adoption' => [
                'courses' => 2, 'instances' => 3, 'inuse' => 2, 'withoutitems' => 1,
                'features' => ['enable_rpg' => 1, 'enable_quests' => 2, 'enable_ranking' => 3, 'enable_items' => 3],
            ],
            'engagement' => [
                'players' => 5, 'active' => 2, 'optedout' => 1, 'xp' => 300, 'itemsreceived' => 7,
                'questscompleted' => 1, 'trades' => 0, 'airequests' => ['Gemini' => 2, 'Groq' => 1], 'wizardruns' => 0,
            ],
            'orphans' => $orphans,
            'loose' => $loose + ['block_playerhud_choices' => 0],
        ];
    }

    /**
     * Builds an orphan instance summary.
     *
     * @param int $id Instance id.
     * @param bool|null $courseexists Whether its course still exists (null when unknown).
     * @return \stdClass Orphan summary.
     */
    private function make_orphan(int $id, ?bool $courseexists): \stdClass {
        return (object) [
            'id' => $id, 'players' => 4, 'xp' => 1000, 'items' => 30, 'firstactivity' => time() - DAYSECS,
            'lastactivity' => time(), 'deletedusers' => 0, 'courseid' => $courseexists === null ? 0 : 99,
            'courseexists' => $courseexists,
        ];
    }

    /**
     * Exports the page data with a real renderer.
     *
     * @param array $report Report.
     * @param array $backups Backup files.
     * @return array Template context.
     */
    private function export(array $report, array $backups = []): array {
        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/admin/diagnostics.php');
        $page = new diagnostics($report, $backups, new moodle_url('/blocks/playerhud/admin/diagnostics.php'));
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Cards cover every figure, the windowed ones say so, and AI requests list their providers.
     */
    public function test_cards(): void {
        $this->resetAfterTest(true);
        $data = $this->export($this->make_report());

        $this->assertCount(8, $data['adoption']);
        $this->assertCount(9, $data['engagement']);
        $this->assertCount(3, $data['integrity']);
        $ai = $data['engagement'][7];
        $this->assertSame('3', $ai['value']);
        $this->assertStringContainsString('Gemini 2 · Groq 1', $ai['subtitle']);
        $this->assertStringContainsString('90', $ai['subtitle']);
        $this->assertSame('', $data['engagement'][0]['subtitle'], 'All-time figures carry no window.');
    }

    /**
     * Orphans whose course still exists start unselected; deleted or unknown ones start selected.
     */
    public function test_orphan_rows_preselection(): void {
        $this->resetAfterTest(true);
        $report = $this->make_report([
            $this->make_orphan(100, true),
            $this->make_orphan(101, false),
            $this->make_orphan(102, null),
        ]);

        $rows = array_column($this->export($report)['orphans'], null, 'id');

        $this->assertFalse($rows[100]['checked']);
        $this->assertTrue($rows[100]['courseexists']);
        $this->assertTrue($rows[101]['checked']);
        $this->assertSame(get_string('diag_course_deleted', 'block_playerhud'), $rows[101]['course']);
        $this->assertTrue($rows[102]['checked']);
        $this->assertSame(get_string('diag_course_unknown', 'block_playerhud'), $rows[102]['course']);
    }

    /**
     * Without leftovers the page says so and offers no cleanup form.
     */
    public function test_render_without_leftovers(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $data = $this->export($this->make_report());
        $html = $PAGE->get_renderer('core')->render_from_template('block_playerhud/admin_diagnostics', $data);

        $this->assertFalse($data['hascleanup']);
        $this->assertStringContainsString(get_string('diag_orphans_none', 'block_playerhud'), $html);
        $this->assertStringNotContainsString('name="confirm"', $html);
    }

    /**
     * With leftovers the page renders the selectable orphans, the loose rows and the confirmation.
     */
    public function test_render_with_leftovers_and_backups(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $report = $this->make_report([$this->make_orphan(100, true)], ['block_playerhud_inventory' => 11]);
        $backups = [(object) ['name' => 'orphans-20261009-140000-ab12.json', 'size' => 2048, 'time' => time()]];
        $data = $this->export($report, $backups);
        $html = $PAGE->get_renderer('core')->render_from_template('block_playerhud/admin_diagnostics', $data);

        $this->assertSame(1, preg_match('/<input[^>]*name="instances\[\]" value="100"[^>]*>/s', $html, $match));
        $this->assertStringNotContainsString('checked', $match[0], 'Its course still exists, so it starts unselected.');
        $this->assertStringContainsString(get_string('diag_select_instance', 'block_playerhud', 100), $html);
        $this->assertStringContainsString('block_playerhud_inventory', $html);
        $this->assertStringContainsString('name="confirm"', $html);
        $this->assertStringContainsString('orphans-20261009-140000-ab12.json', $html);

        $download = new moodle_url(html_entity_decode($data['backups'][0]['downloadurl']));
        $this->assertSame('download', $download->get_param('action'));
        $this->assertSame(sesskey(), $download->get_param('sesskey'));
    }
}
