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
 * Tests that the remaining screens and the names saved by suggestions escape names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use block_playerhud\controller\drops;
use block_playerhud\external\insert_drop_shortcode;
use block_playerhud\form\suggest_quests_form;
use block_playerhud\form\suggest_trades_form;
use block_playerhud\game;
use block_playerhud\output\manage\tab_classes;
use block_playerhud\output\view\tab_collection;
use block_playerhud\quest;
use block_playerhud\tests\escaping_testcase;

/**
 * The student's collection and profile, the classes tab, the drops page and the leaderboard groups
 * show names escaped once; the names a suggestion saves into the database stay raw text, so that a
 * later edit form and every screen above show them correctly.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_collection
 * @covers     \block_playerhud\output\profile_content
 * @covers     \block_playerhud\output\manage\tab_classes
 * @covers     \block_playerhud\controller\drops
 * @covers     \block_playerhud\game
 * @covers     \block_playerhud\quest
 * @covers     \block_playerhud\external\insert_drop_shortcode
 * @covers     \block_playerhud\form\suggest_quests_form
 * @covers     \block_playerhud\form\suggest_trades_form
 */
final class player_screens_escaping_test extends escaping_testcase {
    /**
     * Gives a student the canary item and a gamification row.
     *
     * @return \stdClass The student.
     */
    private function create_student_with_item(): \stdClass {
        global $DB;

        $now = time();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->blockid, 'userid' => $student->id, 'currentxp' => 10,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $student->id, 'itemid' => $this->create_item(), 'dropid' => 0, 'source' => 'map',
            'timecreated' => $now, 'xpawarded' => 0,
        ]);

        return $student;
    }

    /**
     * Whether any string anywhere in a nested structure equals the given text.
     *
     * @param mixed $data The structure.
     * @param string $text The text to look for.
     * @return bool
     */
    private function contains_string(mixed $data, string $text): bool {
        $found = false;
        $data = json_decode(json_encode($data), true);
        array_walk_recursive($data, static function ($value) use ($text, &$found): void {
            $found = $found || $value === $text;
        });

        return $found;
    }

    /**
     * The student's collection shows the item name, in the card and in the data the modal reads.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_collection_escapes_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);
        $student = $this->create_student_with_item();
        $this->setUser($student);

        $tab = new tab_collection(new \stdClass(), (object) ['userid' => $student->id, 'currentxp' => 10], $this->blockid);

        $output = $PAGE->get_renderer('core');
        $this->assert_escaped_once(
            $output->render_from_template('block_playerhud/view_collection', $tab->export_for_template($output))
        );
    }

    /**
     * The profile section lists the collected item once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_profile_escapes_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);
        $student = $this->create_student_with_item();

        $output = $PAGE->get_renderer('core');
        $content = new profile_content($this->blockid, (int) $student->id);

        $this->assert_escaped_once(
            $output->render_from_template('block_playerhud/profile_content', $content->export_for_template($output))
        );
    }

    /**
     * The classes tab shows the class name once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_classes_tab_escapes_names_once(int $striptags): void {
        global $DB;

        set_config('formatstringstriptags', $striptags);
        $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'base_hp' => 10,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $tab = new tab_classes($this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display(), 'none');
    }

    /**
     * The drops page of an item shows the item name in its heading and the drop name in its labels once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_drops_page_escapes_item_name_once(int $striptags): void {
        global $DB;

        set_config('formatstringstriptags', $striptags);
        $_GET['instanceid'] = $this->blockid;
        $_GET['id'] = $this->course->id;
        $_GET['itemid'] = $this->create_item();
        $now = time();
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->blockid, 'itemid' => $_GET['itemid'], 'name' => self::CANARY, 'maxusage' => 1,
            'value' => 0, 'respawntime' => 0, 'code' => 'DRP123', 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $this->assert_escaped_once((new drops())->view_manage_page(), 'none');
    }

    /**
     * The leaderboard hands group names to the template as plain text.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_leaderboard_group_names_are_plain(int $striptags): void {
        set_config('formatstringstriptags', $striptags);
        $student = $this->create_student_with_item();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => self::CANARY]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $board = game::get_leaderboard($this->blockid, (int) $student->id, false);

        $this->assertTrue($this->contains_string($board, self::CANARY));
        $this->assertFalse($this->contains_string($board, self::ESCAPED));
    }

    /**
     * A quest suggested from an activity is saved with the activity's name as typed, not as HTML.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_quest_suggestion_name_is_plain(int $striptags): void {
        set_config('formatstringstriptags', $striptags);
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'name' => self::CANARY, 'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $names = array_column(quest::get_heuristic_suggestions($this->blockid, (int) $course->id, new \stdClass()), 'name');

        $this->assertNotEmpty(preg_grep('/' . preg_quote(self::CANARY, '/') . '/', $names));
        $this->assertEmpty(preg_grep('/&amp;|&quot;/', $names));
    }

    /**
     * A trade suggested for an avatar is saved with the avatar's name as typed, not as HTML.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_trade_suggestion_name_is_plain(int $striptags): void {
        set_config('formatstringstriptags', $striptags);
        $this->create_item(['name' => 'PlayerCoin', 'action_type' => 'playercoin']);
        $this->create_item(['action_type' => 'avatar_profile', 'image' => '🙂']);

        $suggestions = game::build_trade_suggestions($this->blockid);

        $this->assertSame(self::CANARY, $suggestions[0]['name']);
        $this->assertSame(self::CANARY, $suggestions[0]['reward_label']);
    }

    /**
     * The suggestion forms print the name once-escaped inside their checkbox labels.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_suggestion_forms_escape_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);
        $url = new \moodle_url('/blocks/playerhud/manage.php');
        $questsuggestion = [
            'uid' => 'act_1', 'name' => self::CANARY, 'image_done' => '🏅', 'reward_xp' => 5,
        ];
        $tradesuggestion = [
            'uid' => 'ind_1', 'cost_qty' => 5, 'cost_emoji' => '🪙', 'reward_emoji' => '🙂',
            'reward_label' => self::CANARY,
        ];

        $questform = new suggest_quests_form($url, ['suggestions' => [$questsuggestion]]);
        $tradeform = new suggest_trades_form($url, ['suggestions' => [$tradesuggestion]]);

        $this->assert_escaped_once($questform->render());
        $this->assert_escaped_once($tradeform->render());
    }

    /**
     * Inserting a drop into an activity renames the drop after it, keeping the activity's name as typed.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_drop_renamed_after_activity_keeps_plain_name(int $striptags): void {
        global $DB;

        set_config('formatstringstriptags', $striptags);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id, 'name' => self::CANARY, 'content' => 'Body',
        ]);
        $now = time();
        $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->blockid, 'itemid' => $this->create_item(), 'name' => 'Old', 'maxusage' => 1,
            'value' => 0, 'respawntime' => 0, 'code' => 'XYZ789', 'timecreated' => $now, 'timemodified' => $now,
        ]);

        insert_drop_shortcode::execute($this->blockid, (int) $this->course->id, $dropid, (int) $page->cmid, 'content', 'top');

        $this->assertSame(self::CANARY, $DB->get_field('block_playerhud_drops', 'name', ['id' => $dropid]));
    }

    /**
     * Provides both values of the site setting that changes what format_string() returns.
     *
     * @return array
     */
    public static function striptags_provider(): array {
        return ['strip tags on' => [1], 'strip tags off' => [0]];
    }
}
