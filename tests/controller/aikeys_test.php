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
 * @coversDefaultClass \block_playerhud\controller\aikeys
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
     *
     * @covers ::save
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
     * A missing field is stored as an empty preference.
     *
     * @covers ::save
     */
    public function test_save_stores_empty_for_missing_field(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->make_instance();

        aikeys::save(['gemini_key' => 'only-gemini'], $instance, (int) $user->id);

        $this->assertSame('only-gemini', get_user_preferences('block_playerhud_gemini_key', '', $user->id));
        $this->assertSame('', get_user_preferences('block_playerhud_groq_key', 'fallback', $user->id));
    }

    /**
     * Legacy keys in block config are stripped while other config survives.
     *
     * @covers ::save
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
     *
     * @covers ::save
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
}
