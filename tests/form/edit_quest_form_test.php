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
 * Tests for the quest form's target validation.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;
use block_playerhud\quest;

/**
 * A level quest must not ask for a level above the block's maximum.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_quest_form
 * @covers     \block_playerhud\utils
 */
final class edit_quest_form_test extends advanced_testcase {
    /**
     * Builds the form for a block whose maximum level is set.
     *
     * @param int|null $maxlevels Configured maximum level, null for a block never configured.
     * @return edit_quest_form
     */
    private function make_form(?int $maxlevels): edit_quest_form {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $config = $maxlevels === null ? new \stdClass() : (object) ['max_levels' => $maxlevels];
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize($config)),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);

        return new edit_quest_form(null, ['instanceid' => $instanceid, 'courseid' => (int) $course->id]);
    }

    /**
     * Submitted values that are valid apart from the target under test.
     *
     * @param int $type Quest type.
     * @param int $target Target value.
     * @return array Submitted data.
     */
    private function data(int $type, int $target): array {
        return ['name' => 'Quest', 'type' => $type, 'target_value' => $target, 'reward_xp' => 5];
    }

    /**
     * Provides targets against a maximum level of 10.
     *
     * @return array[] Quest type, target and whether the target is refused.
     */
    public static function targets_provider(): array {
        return [
            'level at the cap' => [quest::TYPE_LEVEL, 10, false],
            'level above the cap' => [quest::TYPE_LEVEL, 11, true],
            'other types are not capped by level' => [quest::TYPE_XP_TOTAL, 500, false],
        ];
    }

    /**
     * A level target above the block maximum is refused, any other target is accepted.
     *
     * @dataProvider targets_provider
     * @param int $type Quest type.
     * @param int $target Target value.
     * @param bool $refused Whether validation must reject the target.
     */
    public function test_level_target_is_checked_against_the_block_maximum(int $type, int $target, bool $refused): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $errors = $this->make_form(10)->validation($this->data($type, $target), []);

        $this->assertSame($refused, isset($errors['target_value']));
        if ($refused) {
            $this->assertSame(get_string('quest_validate_target_level', 'block_playerhud', 10), $errors['target_value']);
        }
    }

    /**
     * A block that was never configured has the default maximum of 20 levels.
     */
    public function test_an_unconfigured_block_uses_the_default_maximum(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->make_form(null);

        $this->assertArrayNotHasKey('target_value', $form->validation($this->data(quest::TYPE_LEVEL, 20), []));
        $this->assertArrayHasKey('target_value', $form->validation($this->data(quest::TYPE_LEVEL, 21), []));
    }
}
