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
 * Step definitions for block_playerhud Behat tests.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing

/**
 * Custom Behat step definitions for the PlayerHUD block.
 */
class behat_block_playerhud extends behat_base {
    /**
     * Asserts that the PlayerHUD XP progress bar is visible inside the block.
     *
     * The element .ph-progress-container is server-side rendered by
     * sidebar_view.mustache when the student has gamification enabled.
     *
     * @Then I should see the PlayerHUD XP bar
     */
    public function i_should_see_the_playerhud_xp_bar(): void {
        $this->execute('behat_general::should_exist_in_the', [
            '.ph-progress-container',
            'css_element',
            'PlayerHUD',
            'block',
        ]);
    }

    /**
     * Asserts that the PlayerHUD XP progress bar is NOT visible inside the block.
     *
     * @Then I should not see the PlayerHUD XP bar
     */
    public function i_should_not_see_the_playerhud_xp_bar(): void {
        $this->execute('behat_general::should_not_exist_in_the', [
            '.ph-progress-container',
            'css_element',
            'PlayerHUD',
            'block',
        ]);
    }

    /**
     * Asserts that the paused/rejoin state is visible inside the block.
     *
     * The element .ph-sidebar-rejoin is rendered by sidebar_rejoin.mustache
     * when the student has gamification disabled.
     *
     * @Then I should see the PlayerHUD paused state
     */
    public function i_should_see_the_playerhud_paused_state(): void {
        $this->execute('behat_general::should_exist_in_the', [
            '.ph-sidebar-rejoin',
            'css_element',
            'PlayerHUD',
            'block',
        ]);
    }

    /**
     * Asserts that the paused/rejoin state is NOT visible inside the block.
     *
     * @Then I should not see the PlayerHUD paused state
     */
    public function i_should_not_see_the_playerhud_paused_state(): void {
        $this->execute('behat_general::should_not_exist_in_the', [
            '.ph-sidebar-rejoin',
            'css_element',
            'PlayerHUD',
            'block',
        ]);
    }

    /**
     * Asserts that the management tab navigation is visible on the page.
     *
     * The element #ph-manage-tabs is rendered by manage_layout.mustache on
     * manage.php, which is a separate page from the course homepage block.
     *
     * @Then I should see the PlayerHUD management tabs
     */
    public function i_should_see_the_playerhud_management_tabs(): void {
        $this->execute('behat_general::should_exist', [
            '#ph-manage-tabs',
            'css_element',
        ]);
    }

    /**
     * Programmatically disables gamification for a user on a given course.
     *
     * Sets enable_gamification = 0 directly in the database so that
     * scenarios testing the paused/rejoin state do not need to run
     * the full disable flow (which requires @javascript) as a prerequisite.
     *
     * @param string $username  Moodle username.
     * @param string $shortname Course shortname.
     * @Given :username has disabled PlayerHUD on course :shortname
     */
    public function user_has_disabled_playerhud(string $username, string $shortname): void {
        global $DB;

        $user   = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);

        $coursecontext = context_course::instance($course->id);
        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id],
            '*',
            MUST_EXIST
        );

        // Ensure the player record exists (get_player creates it with enable_gamification = 1).
        $player = $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $instance->id,
            'userid'          => $user->id,
        ]);

        if (!$player) {
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid'     => $instance->id,
                'userid'              => $user->id,
                'currentxp'           => 0,
                'enable_gamification' => 0,
                'ranking_visibility'  => 1,
                'timecreated'         => time(),
                'timemodified'        => time(),
            ]);
        } else {
            $player->enable_gamification = 0;
            $player->timemodified        = time();
            $DB->update_record('block_playerhud_user', $player);
        }
    }
}
