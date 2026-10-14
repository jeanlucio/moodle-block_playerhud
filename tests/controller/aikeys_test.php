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
 * Tests for the AI keys controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use stdClass;

/**
 * Tests for the AI keys persistence logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\controller\aikeys
 */
final class aikeys_test extends advanced_testcase {
    /**
     * Creates a block instance with the given configdata payload.
     *
     * @param array $config Associative config to serialise (empty = no payload).
     * @return stdClass The block_instances record.
     */
    protected function make_instance(array $config = []): stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $configdata = empty($config) ? '' : base64_encode(serialize((object) $config));

        $id = $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => $configdata,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);

        return $DB->get_record('block_instances', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Submitted keys are trimmed and stored as that user's preferences.
     */
    public function test_save_stores_trimmed_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance();

        aikeys::save([
            'gemini_key'   => '  gem-123  ',
            'groq_key'     => 'groq-456',
            'openai_key'   => 'oa-789',
            'openai_url'   => ' https://example.com/v1 ',
            'openai_model' => 'gpt-x',
        ], $instance, (int) $user->id);

        $this->assertSame('gem-123', get_user_preferences('block_playerhud_gemini_key', '', $user->id));
        $this->assertSame('groq-456', get_user_preferences('block_playerhud_groq_key', '', $user->id));
        $this->assertSame('oa-789', get_user_preferences('block_playerhud_openai_key', '', $user->id));
        $this->assertSame('https://example.com/v1', get_user_preferences('block_playerhud_openai_url', '', $user->id));
        $this->assertSame('gpt-x', get_user_preferences('block_playerhud_openai_model', '', $user->id));
    }

    /**
     * The keys are write-only: a field left empty keeps the saved key, a typed one replaces it, and
     * only the remove flag deletes it (even when text is typed in the same field). The endpoint and
     * the model are not secrets, so an empty value still clears them.
     */
    public function test_save_keeps_replaces_and_removes_secret_keys(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance();
        $uid = (int) $user->id;
        aikeys::save([
            'gemini_key' => 'gem-old', 'groq_key' => 'groq-old', 'openai_key' => 'oa-old',
            'openai_url' => 'https://example.com/v1', 'openai_model' => 'gpt-x',
        ], $instance, $uid);

        aikeys::save([
            'gemini_key' => '', 'groq_key' => 'groq-new', 'openai_key' => 'ignored', 'remove_openai' => 1,
            'openai_url' => '', 'openai_model' => '',
        ], $instance, $uid);

        $this->assertSame('gem-old', get_user_preferences('block_playerhud_gemini_key', '', $uid));
        $this->assertSame('groq-new', get_user_preferences('block_playerhud_groq_key', '', $uid));
        $this->assertSame('', get_user_preferences('block_playerhud_openai_key', 'fallback', $uid));
        $this->assertSame('', get_user_preferences('block_playerhud_openai_url', 'fallback', $uid));
        $this->assertSame('', get_user_preferences('block_playerhud_openai_model', 'fallback', $uid));
    }

    /**
     * A field missing from the submission leaves the saved key alone.
     */
    public function test_save_leaves_a_missing_key_field_alone(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance();

        aikeys::save(['gemini_key' => 'only-gemini'], $instance, (int) $user->id);

        $this->assertSame('only-gemini', get_user_preferences('block_playerhud_gemini_key', '', $user->id));
        $this->assertSame('fallback', get_user_preferences('block_playerhud_groq_key', 'fallback', $user->id));
    }

    /**
     * Legacy keys in block config are stripped while other config survives.
     */
    public function test_save_strips_legacy_keys_from_config(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance([
            'apikey_gemini' => 'leaked-gem',
            'apikey_groq'   => 'leaked-groq',
            'max_levels'    => 10,
        ]);

        aikeys::save(['gemini_key' => 'new'], $instance, (int) $user->id);

        $stored = $DB->get_record('block_instances', ['id' => $instance->id], '*', MUST_EXIST);
        $config = (array) unserialize_object(base64_decode($stored->configdata));
        $this->assertArrayNotHasKey('apikey_gemini', $config);
        $this->assertArrayNotHasKey('apikey_groq', $config);
        $this->assertSame(10, (int) $config['max_levels']);
    }

    /**
     * A config without legacy keys is left byte-for-byte untouched.
     */
    public function test_save_leaves_clean_config_untouched(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance(['max_levels' => 20]);
        $original = $instance->configdata;

        aikeys::save(['gemini_key' => 'new'], $instance, (int) $user->id);

        $stored = $DB->get_field('block_instances', 'configdata', ['id' => $instance->id]);
        $this->assertSame($original, $stored);
    }

    /**
     * Stripping legacy keys writes only the configuration, not the whole stale record it was handed.
     */
    public function test_save_does_not_overwrite_other_columns_with_a_stale_record(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance(['apikey_groq' => 'leaked-groq', 'max_levels' => 10]);
        // The block is moved after the record was read, as a teacher dragging it in another tab would do.
        $DB->set_field('block_instances', 'defaultweight', 7, ['id' => $instance->id]);

        aikeys::save(['groq_key' => 'new'], $instance, (int) $user->id);

        $stored = $DB->get_record('block_instances', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame(7, (int) $stored->defaultweight);
        $config = (array) unserialize_object(base64_decode($stored->configdata));
        $this->assertArrayNotHasKey('apikey_groq', $config);
        $this->assertSame(10, (int) $config['max_levels']);
    }

    /**
     * Provides typed endpoints and the reason each one is refused ('' when it is fine).
     *
     * @return array[] Typed value and the lang key that explains the refusal.
     */
    public static function endpoint_provider(): array {
        return [
            'nothing typed means no endpoint' => ['', ''],
            'only spaces' => ['   ', ''],
            'a public address over https' => ['https://8.8.8.8/v1', ''],
            'an IPv6 address in brackets cannot be read as a URL' => [
                'https://[2606:4700:4700::1111]/v1', 'ai_keys_endpoint_unreadable'],
            'an IPv4-compatible loopback in brackets' => ['https://[::7f00:1]/v1', 'ai_keys_endpoint_unreadable'],
            'a scheme that is not a URL' => ['javascript:alert(1)', 'ai_keys_endpoint_unreadable'],
            'plain http' => ['http://8.8.8.8/v1', 'ai_keys_endpoint_refused'],
            'a private network' => ['https://10.0.0.5/v1', 'ai_keys_endpoint_refused'],
            'localhost' => ['https://localhost/v1', 'ai_keys_endpoint_refused'],
            'a host that does not resolve' => ['https://nonexistent-host-xyz.invalid/v1', 'ai_keys_endpoint_refused'],
        ];
    }

    /**
     * A typed endpoint that cannot be read or that a request would refuse is turned down with the
     * reason, instead of being cleaned to nothing, which used to erase the saved one while the page
     * said the keys were saved.
     *
     * @dataProvider endpoint_provider
     * @param string $typed What was typed.
     * @param string $expected The lang key of the refusal, or '' when accepted.
     */
    public function test_endpoint_problem(string $typed, string $expected): void {
        $this->resetAfterTest();
        $instance = $this->make_instance();

        $this->assertSame($expected, aikeys::endpoint_problem($typed, (int) $instance->id));
    }
}
