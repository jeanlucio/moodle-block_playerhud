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

    // Step definitions for modal behaviour tests (block_playerhud_modals.feature).

    /**
     * Creates a PlayerHUD item and associated drop with the given code in the given course.
     *
     * @param string $itemname Display name for the item.
     * @param string $dropcode Short alphanumeric code used in the [PLAYERHUD_DROP] shortcode.
     * @param string $shortname Course shortname.
     * @Given a PlayerHUD item :itemname with drop code :dropcode exists in course :shortname
     */
    public function playerhud_item_with_drop_exists(string $itemname, string $dropcode, string $shortname): void {
        global $DB;

        $course  = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $context->id],
            '*',
            MUST_EXIST
        );

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instance->id,
            'name'            => $itemname,
            'description'     => '',
            'image'           => '🏆',
            'xp'              => 10,
            'secret'          => 0,
            'enabled'         => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instance->id,
            'itemid'          => $itemid,
            'code'            => strtoupper($dropcode),
            'maxusage'        => 1,
            'respawntime'     => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Programmatically records a drop collection for a user.
     *
     * @param string $username  Moodle username.
     * @param string $dropcode  Drop code.
     * @param string $shortname Course shortname.
     * @Given :username has collected drop :dropcode in course :shortname
     */
    public function user_has_collected_drop(string $username, string $dropcode, string $shortname): void {
        global $DB;

        $user    = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $course  = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $context->id],
            '*',
            MUST_EXIST
        );

        $drop = $DB->get_record(
            'block_playerhud_drops',
            ['blockinstanceid' => $instance->id, 'code' => strtoupper($dropcode)],
            '*',
            MUST_EXIST
        );

        // Ensure player record exists with gamification enabled.
        $player = $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $instance->id,
            'userid'          => $user->id,
        ]);
        if (!$player) {
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid'     => $instance->id,
                'userid'              => $user->id,
                'currentxp'           => 10,
                'enable_gamification' => 1,
                'ranking_visibility'  => 1,
                'timecreated'         => time(),
                'timemodified'        => time(),
            ]);
        }

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $user->id,
            'itemid'      => $drop->itemid,
            'dropid'      => $drop->id,
            'source'      => 'map',
            'timecreated' => time(),
        ]);
    }

    /**
     * Creates a Moodle label (mod_label) in the current course containing the given shortcode text.
     *
     * Used to embed a [PLAYERHUD_DROP] shortcode in course content without
     * going through the UI, so the filter renders the collect button.
     *
     * @param string $shortcode Raw shortcode string, e.g. [PLAYERHUD_DROP code=GEM01].
     * @Given a label with shortcode :shortcode exists in the course
     */
    public function label_with_shortcode_exists_in_course(string $shortcode): void {
        global $DB;

        $url     = $this->getSession()->getCurrentUrl();
        $matches = [];
        // Moodle course view URL format: course/view.php?id=NNN.
        preg_match('/[?&]id=(\d+)/', $url, $matches);
        if (empty($matches[1])) {
            throw new \Exception('Cannot determine course id from current URL: ' . $url);
        }
        $courseid = (int) $matches[1];
        $course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Ensure filter_playerhud is active so the shortcode renders as a collect button.
        filter_set_global_state('playerhud', TEXTFILTER_ON);

        \testing_util::get_data_generator()->create_module('label', [
            'course'      => $course->id,
            'section'     => 0,
            'intro'       => $shortcode,
            'introformat' => FORMAT_HTML,
        ]);
    }

    /**
     * Asserts that the given CSS element is not visible on the page.
     *
     * Matches the pattern used in block_playerhud_modals.feature:
     *   Then the ".ph-action-collect" element is not visible
     *
     * @param string $selector CSS selector.
     * @Then the :selector element is not visible
     */
    public function element_is_not_visible(string $selector): void {
        try {
            $node = $this->find('css', $selector);
            if ($node && $node->isVisible()) {
                throw new \Exception("Element '{$selector}' is visible but expected to be hidden.");
            }
        } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
            // Element absent from DOM — considered not visible, return normally.
            return;
        }
    }

    /**
     * Clicks the first element matching a CSS selector on the page.
     *
     * @param string $selector CSS selector.
     * @When I click on the first :selector element
     */
    public function i_click_on_first_css_element(string $selector): void {
        $node = $this->find('css', $selector);
        $node->click();
    }

    /**
     * Asserts that the PlayerHUD item details modal is visible in the DOM.
     *
     * Checks for either the filter modal (#phItemModalFilter) or the
     * sidebar/view modal (#phItemModalView / #ph-item-modal-view).
     *
     * @Then the PlayerHUD item details modal is visible
     */
    public function playerhud_item_details_modal_is_visible(): void {
        $this->spin(function () {
            $candidates = ['#phItemModalFilter', '#phItemModalView', '#ph-item-modal-view'];
            foreach ($candidates as $sel) {
                try {
                    $node = $this->find('css', $sel);
                    if ($node && $node->isVisible()) {
                        return true;
                    }
                } catch (\Exception $e) {
                    // Not found, try next selector.
                    continue;
                }
            }
            return false;
        });
    }

    /**
     * Asserts that the PlayerHUD item details modal is NOT visible.
     *
     * @Then the PlayerHUD item details modal is not visible
     */
    public function playerhud_item_details_modal_is_not_visible(): void {
        $candidates = ['#phItemModalFilter', '#phItemModalView', '#ph-item-modal-view'];
        foreach ($candidates as $sel) {
            try {
                $node = $this->find('css', $sel);
                if ($node && $node->isVisible()) {
                    throw new \Exception("PlayerHUD modal {$sel} is still visible but should not be.");
                }
            } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
                // Element not found means it is not visible — expected state.
                continue;
            }
        }
    }

    /**
     * Asserts that the given text appears inside the visible PlayerHUD modal.
     *
     * @param string $text Text to search for.
     * @Then I should see :text in the PlayerHUD modal
     */
    public function i_should_see_text_in_playerhud_modal(string $text): void {
        $candidates = ['#phItemModalFilter', '#phItemModalView', '#ph-item-modal-view'];
        foreach ($candidates as $sel) {
            try {
                $node = $this->find('css', $sel);
                if ($node && $node->isVisible() && str_contains($node->getText(), $text)) {
                    return;
                }
            } catch (\Exception $e) {
                // Element not found, try next selector.
                continue;
            }
        }
        throw new \Exception("Text '{$text}' not found in any visible PlayerHUD modal.");
    }

    /**
     * Asserts that the given text does NOT appear inside the visible PlayerHUD modal.
     *
     * @param string $text Text that must be absent.
     * @Then I should not see :text in the PlayerHUD modal
     */
    public function i_should_not_see_text_in_playerhud_modal(string $text): void {
        $candidates = ['#phItemModalFilter', '#phItemModalView', '#ph-item-modal-view'];
        foreach ($candidates as $sel) {
            try {
                $node = $this->find('css', $sel);
                if ($node && $node->isVisible() && str_contains($node->getText(), $text)) {
                    throw new \Exception("Text '{$text}' was found in PlayerHUD modal {$sel} but should not be.");
                }
            } catch (\Behat\Mink\Exception\ElementNotFoundException $e) {
                // Element not found means text is absent — expected state.
                continue;
            }
        }
    }

    /**
     * Closes the currently visible PlayerHUD modal by clicking its dismiss button.
     *
     * @When I close the PlayerHUD modal
     */
    public function i_close_the_playerhud_modal(): void {
        $candidates = [
            '#phItemModalFilter [data-bs-dismiss="modal"]',
            '#phItemModalView [data-bs-dismiss="modal"]',
            '#ph-item-modal-view [data-bs-dismiss="modal"]',
        ];
        foreach ($candidates as $sel) {
            try {
                $node = $this->find('css', $sel);
                if ($node && $node->isVisible()) {
                    $node->click();
                    $this->getSession()->wait(500);
                    return;
                }
            } catch (\Exception $e) {
                // Element not found, try next selector.
                continue;
            }
        }
        throw new \Exception("No visible PlayerHUD modal dismiss button found.");
    }

    /**
     * Asserts that only one PlayerHUD item details modal element exists in the DOM.
     *
     * Catches the "modal opened multiple times" regression: each extra call to
     * appendTo('body') or insertAdjacentHTML duplicates the element.
     *
     * @Then there is only one PlayerHUD modal in the DOM
     */
    public function there_is_only_one_playerhud_modal_in_dom(): void {
        $js = "return document.querySelectorAll(
            '#phItemModalFilter, #phItemModalView, #ph-item-modal-view'
        ).length;";
        $count = $this->getSession()->evaluateScript($js);
        if ((int) $count > 1) {
            throw new \Exception("Expected 1 PlayerHUD modal in DOM but found {$count}. Modal is being duplicated.");
        }
    }

    /**
     * Stores the current page URL for later comparison.
     *
     * @When I remember the current page URL
     */
    public function i_remember_current_page_url(): void {
        $this->pageurl = $this->getSession()->getCurrentUrl();
    }

    /** @var string|null Remembered URL for redirect detection. */
    protected ?string $pageurl = null;

    /**
     * Asserts that the current URL has not changed since it was remembered.
     *
     * @Then the page URL has not changed
     */
    public function the_page_url_has_not_changed(): void {
        if ($this->pageurl === null) {
            throw new \Exception("No URL was remembered. Use 'I remember the current page URL' first.");
        }
        $current = $this->getSession()->getCurrentUrl();
        if ($current !== $this->pageurl) {
            throw new \Exception("Page was redirected. Expected: {$this->pageurl} — Got: {$current}");
        }
    }

    /**
     * Waits for a PlayerHUD AJAX collect response (success indicator in DOM).
     *
     * Waits until the collect button disappears or changes state (disabled / replaced).
     *
     * @When I wait for the PlayerHUD AJAX collect to complete
     */
    public function i_wait_for_playerhud_ajax_collect(): void {
        // Phase 1: wait for the button to enter loading state (click handler ran).
        $this->spin(function () {
            $js = "return document.querySelector('.ph-action-collect') === null
                || document.querySelector('.ph-action-collect.disabled') !== null
                || document.querySelector('.ph-action-collect[aria-disabled]') !== null;";
            return (bool) $this->getSession()->evaluateScript($js);
        }, false, 5);

        // Phase 2: wait until loading state ends, meaning AJAX completed and UI updated.
        $this->spin(function () {
            $js = "return document.querySelector('.ph-action-collect') === null
                || (document.querySelector('.ph-action-collect.disabled') === null
                    && document.querySelector('.ph-action-collect[aria-disabled]') === null);";
            return (bool) $this->getSession()->evaluateScript($js);
        }, false, 15);
    }

    /**
     * Navigates to the student PlayerHUD dashboard (view.php) for a course.
     *
     * The mascot introduction popup is fired on this page, so this step is
     * needed to reach it.
     *
     * @param string $shortname Course shortname.
     * @When I open the PlayerHUD dashboard for course :shortname
     */
    public function i_open_the_playerhud_dashboard(string $shortname): void {
        global $DB, $CFG;

        $course   = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $context  = context_course::instance($course->id);
        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $context->id],
            '*',
            MUST_EXIST
        );

        $url = $CFG->wwwroot . '/blocks/playerhud/view.php?id=' . $course->id
            . '&instanceid=' . $instance->id;
        $this->getSession()->visit($url);
    }

    /**
     * Creates an always-completable, unclaimed quest and enables quests on the block,
     * so a reward is waiting to be claimed (which triggers the first-quest popup).
     *
     * @param string $shortname Course shortname.
     * @Given a claimable PlayerHUD quest exists in course :shortname
     */
    public function claimable_playerhud_quest_exists(string $shortname): void {
        global $DB;

        $course   = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $context  = context_course::instance($course->id);
        $instance = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $context->id],
            '*',
            MUST_EXIST
        );

        // The block is added fresh in the background, so a minimal config enabling
        // quests is enough for the sidebar to compute the claimable state.
        $config = new \stdClass();
        $config->enable_quests = 1;
        $DB->set_field(
            'block_instances',
            'configdata',
            base64_encode(serialize($config)),
            ['id' => $instance->id]
        );

        // TYPE_LEVEL (1) with requirement "1" is met by every player (everyone is level 1+).
        $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid'   => $instance->id,
            'name'              => 'Welcome Quest',
            'description'       => '',
            'type'              => 1,
            'requirement'       => '1',
            'req_itemid'        => 0,
            'reward_xp'         => 10,
            'reward_itemid'     => 0,
            'required_class_id' => '0',
            'image_todo'        => '📋',
            'image_done'        => '🏅',
            'enabled'           => 1,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    // Step definitions for the gamification wizard (block_playerhud_wizard.feature).

    /**
     * Opens the gamification wizard modal from the management panel and waits for it to
     * finish its fade-in transition.
     *
     * @When I open the PlayerHUD wizard
     */
    public function i_open_the_playerhud_wizard(): void {
        $this->execute('behat_general::i_click_on', ['#ph-wizard-open-btn', 'css_element']);

        $this->spin(function () {
            $node = $this->find('css', '#ph-wizard-modal');
            return $node && $node->isVisible();
        });
    }

    /**
     * Waits for the wizard's live progress bar to finish and its success report to appear.
     *
     * A generous timeout accounts for a run driving several deterministic steps in sequence
     * (each its own AJAX round-trip), still well short of the AI-backed steps this feature
     * deliberately never exercises.
     *
     * @Then I should see the PlayerHUD wizard success report
     */
    public function i_should_see_the_playerhud_wizard_success_report(): void {
        $this->spin(function () {
            $node = $this->find('css', '#ph-wizard-progress-report');
            return $node && $node->isVisible();
        }, false, 30);
    }
}
