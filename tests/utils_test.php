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
 * @covers     \block_playerhud\utils
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
     */
    public function test_get_avatar_html_emoji_generates_div_with_span(): void {
        $item = $this->create_item('🧛');
        $context = \context_block::instance($this->instanceid);

        $html = utils::get_avatar_html($item, $context, $this->createMock(\renderer_base::class));

        $this->assertStringContainsString('ph-avatar-emoji', $html);
        $this->assertStringContainsString('rounded-circle', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('🧛', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * An item whose image field is an HTTP URL produces an <img> tag with
     * aria-hidden and the URL as src.
     */
    public function test_get_avatar_html_http_url_generates_img_tag(): void {
        $url = 'https://example.com/avatar.png';
        $item = $this->create_item($url);
        $context = \context_block::instance($this->instanceid);

        $html = utils::get_avatar_html($item, $context, $this->createMock(\renderer_base::class));

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('ph-avatar-img', $html);
        $this->assertStringContainsString('rounded-circle', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString($url, $html);
        $this->assertStringNotContainsString('ph-avatar-emoji', $html);
    }

    /**
     * An item with a null image (no file uploaded, no emoji/URL set) must not
     * crash get_items_display_data() with a TypeError/deprecation on strpos().
     */
    public function test_get_items_display_data_with_null_image_does_not_throw(): void {
        $item = $this->create_item_with_null_image();
        $context = \context_block::instance($this->instanceid);

        $result = utils::get_items_display_data([$item], $context);

        $this->assertFalse($result[$item->id]['is_image']);
        $this->assertNull($result[$item->id]['url']);
        $this->assertSame('', $result[$item->id]['content']);
    }

    /**
     * The same null-image item must also survive get_avatar_html(), which
     * calls strip_tags() on the returned content.
     */
    public function test_get_avatar_html_with_null_image_does_not_throw(): void {
        $item = $this->create_item_with_null_image();
        $context = \context_block::instance($this->instanceid);

        $html = utils::get_avatar_html($item, $context, $this->createMock(\renderer_base::class));

        $this->assertStringContainsString('ph-avatar-emoji', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * generate_drop_code() returns a 6-character uppercase alphanumeric code — the format the
     * collect shortcode ([PLAYERHUD_DROP code=...]) expects.
     */
    public function test_generate_drop_code_returns_a_six_character_uppercase_code(): void {
        $code = utils::generate_drop_code($this->instanceid);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code);
    }

    /**
     * Regression coverage for the security-audit finding: cli/seed_pt_br.php was generating
     * drop codes with md5(uniqid()) — a weaker, more predictable source than this function's
     * own random_string() — and has since been switched to call this function directly instead
     * of duplicating its own generator. Forcing an actual collision isn't practical to assert
     * directly (random_string(6) draws from a ~2.17 billion-value space, so a fixed code is
     * never realistically hit), so this instead proves the property that matters for the seed
     * script's real usage pattern: generating and persisting several codes in a row for the
     * same instance — exactly what happens when seed_upsert_drop() runs once per location —
     * never produces a duplicate.
     */
    public function test_generate_drop_code_never_repeats_within_the_same_instance(): void {
        global $DB;

        $itemid = $this->create_item('🗝️')->id;
        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $code = utils::generate_drop_code($this->instanceid);
            $DB->insert_record('block_playerhud_drops', (object) [
                'blockinstanceid' => $this->instanceid,
                'itemid'          => $itemid,
                'name'            => 'Drop ' . $i,
                'maxusage'        => 1,
                'respawntime'     => 0,
                'code'            => $code,
                'timecreated'     => time(),
                'timemodified'    => time(),
            ]);
            $codes[] = $code;
        }

        $this->assertCount(20, array_unique($codes));
    }

    /**
     * Regression test for a production incident: an editingteacher copied the visible text
     * out of an already-open item modal and pasted it into another item's description field.
     * The browser selection carried along the modal's own DOM skeleton (nested
     * <div class="modal">...<div class="modal-content"> plus stray yui_* ids), which then
     * rendered as a modal nested inside the real modal when the item was opened. The div
     * skeleton and its ids must not survive sanitization, but the actual description text
     * must be preserved.
     */
    public function test_sanitize_rich_description_strips_pasted_modal_skeleton(): void {
        $poisoned = '<div id="ph-item-modal-view" class="modal fade ph-modal-zindex ph-item-modal show">'
            . '<div id="yui_3_18_1_1_1786538342166_238" class="modal-dialog modal-dialog-centered">'
            . '<div id="yui_3_18_1_1_1786538342166_237" class="modal-content border-0 shadow-lg">'
            . '<div id="yui_3_18_1_1_1786538342166_236" class="modal-body pt-0">'
            . '<div id="phModalDescView" class="text-muted text-break mb-3">'
            . '<p id="yui_3_18_1_1_1786538342166_242">Aparelho quântico que une as mentes da tripulação.</p>'
            . '</div></div></div></div></div>';

        $clean = utils::sanitize_rich_description($poisoned);

        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringNotContainsString('modal', $clean);
        $this->assertStringNotContainsString('yui_3_18_1_1', $clean);
        $this->assertStringContainsString('Aparelho quântico que une as mentes da tripulação.', $clean);
    }

    /**
     * Ordinary formatting an editingteacher applies through the WYSIWYG toolbar (bold,
     * italic, list, paragraph) must survive sanitization unchanged.
     */
    public function test_sanitize_rich_description_preserves_basic_formatting(): void {
        $html = '<p>A <strong>rare</strong> item that grants <em>bonus XP</em>.</p><ul><li>Effect one</li></ul>';

        $clean = utils::sanitize_rich_description($html);

        $this->assertStringContainsString('<strong>rare</strong>', $clean);
        $this->assertStringContainsString('<em>bonus XP</em>', $clean);
        $this->assertStringContainsString('<li>Effect one</li>', $clean);
    }

    /**
     * A <script> tag pasted or typed into the editor's HTML source view must not survive —
     * neither as an executable tag nor implicitly re-created by the cleanup step.
     */
    public function test_sanitize_rich_description_strips_script_tag(): void {
        $html = '<p>Item text</p><script>alert(document.cookie)</script>';

        $clean = utils::sanitize_rich_description($html);

        $this->assertStringNotContainsString('<script', $clean);
    }

    /**
     * A javascript: URI on an otherwise-allowed <a> tag must be neutralized by the
     * clean_param(PARAM_CLEANHTML) pass, not merely left in place because <a> is allowed.
     */
    public function test_sanitize_rich_description_neutralizes_javascript_uri(): void {
        $html = '<a href="javascript:alert(1)">click</a>';

        $clean = utils::sanitize_rich_description($html);

        $this->assertStringNotContainsString('javascript:', $clean);
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

    /**
     * Insert a minimal item with a null image field, mirroring a real
     * NOTNULL="false" column value, and return it with id set.
     *
     * @return \stdClass The inserted item record.
     */
    private function create_item_with_null_image(): \stdClass {
        global $DB;
        $item = (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Test No Image',
            'image'           => null,
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
