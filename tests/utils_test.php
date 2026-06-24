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

namespace block_playerhud;

use advanced_testcase;
use block_playerhud\utils;

/**
 * Tests for the utils helper class.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\utils
 */
final class utils_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Create a fresh block instance for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->instanceid = $this->create_block_instance();
    }

    /**
     * An emoji item (no HTTP URL, no file) produces a <div class="ph-avatar-emoji">
     * wrapping an aria-hidden span with the emoji content.
     *
     * @covers ::get_avatar_html
     */
    public function test_get_avatar_html_emoji_generates_div_with_span(): void {
        $item = $this->create_item('🧛');
        $context = \context_block::instance($this->instanceid);

        $html = utils::get_avatar_html($item, $context, null);

        $this->assertStringContainsString('ph-avatar-emoji', $html);
        $this->assertStringContainsString('rounded-circle', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('🧛', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * An item whose image field is an HTTP URL produces an <img> tag with
     * aria-hidden and the URL as src.
     *
     * @covers ::get_avatar_html
     */
    public function test_get_avatar_html_http_url_generates_img_tag(): void {
        $url = 'https://example.com/avatar.png';
        $item = $this->create_item($url);
        $context = \context_block::instance($this->instanceid);

        $html = utils::get_avatar_html($item, $context, null);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('ph-avatar-img', $html);
        $this->assertStringContainsString('rounded-circle', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString($url, $html);
        $this->assertStringNotContainsString('ph-avatar-emoji', $html);
    }

    /**
     * Insert a minimal block_instances row and return its ID.
     *
     * @return int The new instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
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
     * Insert a minimal item with the given image value and return it with id set.
     *
     * @param string $image Emoji character or HTTP URL.
     * @return \stdClass The inserted item record.
     */
    private function create_item(string $image): \stdClass {
        global $DB;
        $item = (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Test Avatar',
            'image'           => $image,
            'description'     => '',
            'xp'              => 0,
            'enabled'         => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }
}
