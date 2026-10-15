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
 * Tests for activity quests whose activity the student can no longer open.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;
use block_playerhud\output\view\tab_quests;

/**
 * An activity quest after the activity closes through an access restriction.
 *
 * The restriction is exercised both ways a teacher can display it — greyed out on the course page or
 * hidden entirely — since both leave the activity unavailable to the student.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\quest
 * @covers     \block_playerhud\output\view\tab_quests
 */
final class quest_closed_activity_test extends advanced_testcase {
    /** @var string Quest name searched for in the rendered quest tab. */
    private const QUEST_NAME = 'Closed activity quest';

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var int Block instance ID. */
    private int $instanceid;

    /** @var \stdClass Enrolled student. */
    private \stdClass $student;

    /** @var int Course module ID of the activity the quest points at. */
    private int $cmid;

    /** @var \stdClass The activity quest. */
    private \stdClass $quest;

    protected function setUp(): void {
        parent::setUp();
        global $CFG, $DB, $PAGE;

        $this->resetAfterTest(true);
        $CFG->enablecompletion = 1;
        $CFG->enableavailability = 1;

        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $context = \context_course::instance($this->course->id);
        $this->instanceid = (int) $this->getDataGenerator()->create_block('playerhud', [
            'parentcontextid' => $context->id,
        ])->id;
        // A block saved from the UI always has configdata; the generator leaves it null.
        $DB->set_field('block_instances', 'configdata', base64_encode(serialize(new \stdClass())), ['id' => $this->instanceid]);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $this->cmid = (int) $page->cmid;
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $now = time();
        $this->quest = (object) [
            'blockinstanceid' => $this->instanceid, 'name' => self::QUEST_NAME, 'description' => '',
            'type' => quest::TYPE_ACTIVITY, 'requirement' => (string) $this->cmid, 'req_itemid' => 0,
            'reward_xp' => 100, 'reward_itemid' => 0, 'reward_itemqty' => 0, 'required_class_id' => '0',
            'image_todo' => '📋', 'image_done' => '🏅', 'enabled' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ];
        $this->quest->id = (int) $DB->insert_record('block_playerhud_quests', $this->quest);
        game::get_player($this->instanceid, (int) $this->student->id);

        $PAGE->set_url('/blocks/playerhud/view.php', ['id' => $this->course->id]);
        $PAGE->set_context($context);
    }

    /**
     * Both ways a teacher can display an unmet access restriction on the course page.
     *
     * @return array
     */
    public static function restriction_display_provider(): array {
        return [
            'shown greyed out' => [true],
            'hidden entirely' => [false],
        ];
    }

    /**
     * Sets a single date restriction on the activity.
     *
     * @param string $direction '<' for "until", '>=' for "from".
     * @param int $time Restriction date.
     * @param bool $show Whether the course page shows the unmet restriction greyed out.
     */
    private function restrict(string $direction, int $time, bool $show): void {
        global $DB;
        $availability = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => $direction, 't' => $time]],
            'showc' => [$show],
        ]);
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $this->cmid]);
        rebuild_course_cache($this->course->id, true);
    }

    /**
     * The student completes the activity.
     */
    private function complete_activity(): void {
        // Completed as the student, as in quest_test: on Moodle 5.2+ only that branch of
        // completion_info refreshes the cached modinfo instead of just deleting it.
        $this->setUser($this->student);
        $cm = get_fast_modinfo($this->course)->get_cm($this->cmid);
        (new \completion_info($this->course))->update_state($cm, COMPLETION_COMPLETE, $this->student->id);
        \cache::make('core', 'completion')->purge();
        \course_modinfo::clear_instance_cache();
    }

    /**
     * Status of the quest for the student.
     *
     * @return \stdClass
     */
    private function quest_status(): \stdClass {
        return quest::check_status($this->quest, (int) $this->student->id, (int) $this->course->id, 0, 1);
    }

    /**
     * The student's quest tab.
     *
     * @return string Rendered HTML.
     */
    private function render_tab(): string {
        global $DB;
        $this->setUser($this->student);
        $player = $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $this->instanceid,
            'userid' => $this->student->id,
        ]);
        return (new tab_quests(new \stdClass(), $player, $this->instanceid, (int) $this->course->id))->display();
    }

    /**
     * A reward earned before the activity closed can still be claimed afterwards.
     *
     * @dataProvider restriction_display_provider
     * @param bool $show Whether the restriction is shown greyed out.
     */
    public function test_completed_quest_stays_claimable_after_activity_closes(bool $show): void {
        $this->restrict('<', time() + DAYSECS, $show);
        $this->complete_activity();
        $this->restrict('<', time() - DAYSECS, $show);

        $status = $this->quest_status();
        $this->assertFalse($status->hidden);
        $this->assertTrue($status->completed);
        // No link to an activity the student can no longer open.
        $this->assertNull($status->action_url);

        $this->assertTrue(quest::has_claimable_quests(
            $this->instanceid,
            (int) $this->student->id,
            (int) $this->course->id,
            0,
            1
        ));
        $this->assertStringContainsString(self::QUEST_NAME, $this->render_tab());

        quest::claim_reward($this->quest->id, (int) $this->student->id, $this->instanceid, (int) $this->course->id);
        $this->assertEquals(100, game::get_player($this->instanceid, (int) $this->student->id)->currentxp);
    }

    /**
     * A claimed quest stays in the student's history after the activity closes, with its XP kept.
     *
     * @dataProvider restriction_display_provider
     * @param bool $show Whether the restriction is shown greyed out.
     */
    public function test_claimed_quest_stays_in_history_after_activity_closes(bool $show): void {
        global $DB;

        $this->restrict('<', time() + DAYSECS, $show);
        $this->complete_activity();
        quest::claim_reward($this->quest->id, (int) $this->student->id, $this->instanceid, (int) $this->course->id);

        $this->restrict('<', time() - DAYSECS, $show);
        // Even with the completion gone (e.g. reset by the teacher), the claim is history.
        $DB->delete_records('course_modules_completion', ['coursemoduleid' => $this->cmid, 'userid' => $this->student->id]);
        \cache::make('core', 'completion')->purge();
        \course_modinfo::clear_instance_cache();

        $this->assertStringContainsString(self::QUEST_NAME, $this->render_tab());
        $this->assertEquals(100, game::get_player($this->instanceid, (int) $this->student->id)->currentxp);
    }

    /**
     * A quest the student never completed disappears once the activity closes, and cannot be claimed.
     *
     * @dataProvider restriction_display_provider
     * @param bool $show Whether the restriction is shown greyed out.
     */
    public function test_not_completed_quest_stays_hidden_after_activity_closes(bool $show): void {
        $this->restrict('<', time() - DAYSECS, $show);

        $this->assertTrue($this->quest_status()->hidden);
        $this->assertStringNotContainsString(self::QUEST_NAME, $this->render_tab());

        $this->expectException(\moodle_exception::class);
        quest::claim_reward($this->quest->id, (int) $this->student->id, $this->instanceid, (int) $this->course->id);
    }

    /**
     * A quest for an activity that has not opened yet stays out of sight until it opens.
     *
     * @dataProvider restriction_display_provider
     * @param bool $show Whether the restriction is shown greyed out.
     */
    public function test_quest_hidden_until_activity_opens(bool $show): void {
        $this->restrict('>=', time() + DAYSECS, $show);

        $this->assertTrue($this->quest_status()->hidden);
        $this->assertFalse(quest::has_claimable_quests(
            $this->instanceid,
            (int) $this->student->id,
            (int) $this->course->id,
            0,
            1
        ));
        $this->assertStringNotContainsString(self::QUEST_NAME, $this->render_tab());
    }
}
