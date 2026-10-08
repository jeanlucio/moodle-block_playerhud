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
 * Shared base for the tests that check names are escaped exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\tests;

use advanced_testcase;

/**
 * A name with an ampersand or quotes must reach the page escaped once. format_string() already
 * returns HTML, so handing its result to a double-mustache variable shows "&amp;" on screen.
 * Subclasses render a screen full of records named with the canary and assert on the HTML.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class escaping_testcase extends advanced_testcase {
    /** @var string Name used for every record. */
    protected const CANARY = 'Cafe & "Co"';

    /** @var string The canary escaped exactly once. */
    protected const ESCAPED = 'Cafe &amp; &quot;Co&quot;';

    /** @var \stdClass The course holding the block. */
    protected \stdClass $course;

    /** @var int The block instance id. */
    protected int $blockid;

    /**
     * Builds a course with a PlayerHUD block and points the page at it.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB, $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($this->course->id);
        $this->blockid = (int) $this->getDataGenerator()->create_block('playerhud', [
            'parentcontextid' => $context->id,
        ])->id;

        // A block saved from the UI always has configdata; the generator leaves it null.
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize(new \stdClass())), ['id' => $this->blockid]);

        $PAGE->set_url('/blocks/playerhud/manage.php', ['id' => $this->course->id]);
        $PAGE->set_context($context);
    }

    /**
     * Creates an item named with the canary.
     *
     * @param array $overrides Column values replacing the defaults.
     * @return int The item id.
     */
    protected function create_item(array $overrides = []): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) ($overrides + [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'xp' => 10, 'enabled' => 1,
            'secret' => 0, 'tradable' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]));
    }

    /**
     * Asserts that the canary appears escaped once and never twice.
     *
     * @param string $html The rendered HTML.
     * @param string $confirm How script reads the delete confirmation attribute: 'html' (Notification.confirm,
     *     one more level of escaping than the page), 'text' (textContent, the raw name) or 'none' (the
     *     message carries no name).
     */
    protected function assert_escaped_once(string $html, string $confirm = 'html'): void {
        preg_match_all('/data-confirm-msg="([^"]*)"/', $html, $matches);
        foreach ($matches[1] as $message) {
            if ($confirm !== 'none') {
                $this->assertStringContainsString(
                    $confirm === 'text' ? self::CANARY : self::ESCAPED,
                    html_entity_decode($message)
                );
            }
        }
        $html = preg_replace('/data-confirm-msg="[^"]*"/', '', $html);

        $this->assertStringContainsString(self::ESCAPED, $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
    }
}
