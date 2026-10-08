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
 * Tests that the items tab escapes names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;
use moodle_url;

/**
 * A name with an ampersand or quotes must reach the page escaped once. format_string() already
 * returns HTML, so handing its result to a double-mustache variable shows "&amp;" on screen.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_items
 */
final class tab_items_escaping_test extends advanced_testcase {
    /** @var string Name used for every record; escapes to Caf&eacute; &amp; &quot;Co&quot;. */
    private const CANARY = 'Cafe & "Co"';

    /** @var string The canary escaped exactly once. */
    private const ESCAPED = 'Cafe &amp; &quot;Co&quot;';

    /** @var tab_items The tab under test. */
    private tab_items $tab;

    /**
     * Builds a course with one canary item, one drop of it and one canary activity.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB, $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => self::CANARY]);
        $context = \context_course::instance($course->id);
        $blockid = (int) $this->getDataGenerator()->create_block('playerhud', [
            'parentcontextid' => $context->id,
        ])->id;

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $blockid, 'name' => self::CANARY, 'xp' => 10, 'enabled' => 1,
            'secret' => 0, 'tradable' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $blockid, 'itemid' => $itemid, 'name' => self::CANARY, 'maxusage' => 1,
            'value' => 0, 'respawntime' => 0, 'code' => 'ABC123',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $PAGE->set_url('/blocks/playerhud/manage.php', ['id' => $course->id]);
        $PAGE->set_context($context);
        $this->tab = new tab_items($blockid, (int) $course->id);
    }

    /**
     * Provides both values of the site setting that changes what format_string() returns.
     *
     * @return array
     */
    public static function striptags_provider(): array {
        return ['strip tags on' => [1], 'strip tags off' => [0]];
    }

    /**
     * Calls a protected render method of the tab.
     *
     * @param string $method Method name.
     * @return string The rendered HTML.
     */
    private function render(string $method): string {
        $reflection = new \ReflectionMethod($this->tab, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->tab, new moodle_url('/blocks/playerhud/manage.php'));
    }

    /**
     * Asserts that the canary appears escaped once and never twice.
     *
     * @param string $html The rendered HTML.
     */
    private function assert_escaped_once(string $html): void {
        // The delete confirmation travels in an attribute that Notification.confirm() later reads back
        // and shows as HTML, so the attribute legitimately holds one more level of escaping.
        preg_match_all('/data-confirm-msg="([^"]*)"/', $html, $matches);
        foreach ($matches[1] as $message) {
            $this->assertStringContainsString(self::ESCAPED, html_entity_decode($message));
        }
        $html = preg_replace('/data-confirm-msg="[^"]*"/', '', $html);

        $this->assertStringContainsString(self::ESCAPED, $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
    }

    /**
     * The items list shows the item name, the delete confirmation and the drops label once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_items_list_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assert_escaped_once($this->render('render_list_view'));
    }

    /**
     * The all-drops list shows the item and drop names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_all_drops_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assert_escaped_once($this->render('render_all_drops_view'));
    }

    /**
     * The distribute view shows item, drop and activity names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_distribute_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $this->assert_escaped_once($this->render('render_distribute_view'));
    }
}
