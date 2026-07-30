@block @block_playerhud @block_playerhud_student @javascript
Feature: PlayerHUD student gamification controls
  As a student enrolled in a gamified course
  I want to control my participation in the gamification system

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And I log out

  Scenario: New student sees the HUD active on first visit
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see the PlayerHUD XP bar
    And I should not see the PlayerHUD paused state

  Scenario: Student can disable gamification with confirmation
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on ".js-disable-hud" "css_element"
    And I click on "Yes" "button"
    Then I am on "Course 1" course homepage
    And I should see the PlayerHUD paused state
    And I should not see the PlayerHUD XP bar

  Scenario: Student can re-enable gamification from the paused state
    Given "student1" has disabled PlayerHUD on course "C1"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see the PlayerHUD paused state
    When I click on ".ph-sidebar-rejoin .btn-primary" "css_element"
    And I am on "Course 1" course homepage
    Then I should see the PlayerHUD XP bar
    And I should not see the PlayerHUD paused state

  Scenario: Student dismisses disable confirmation and HUD remains active
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on ".js-disable-hud" "css_element"
    And I am on "Course 1" course homepage
    Then I should see the PlayerHUD XP bar
    And I should not see the PlayerHUD paused state

  # Only a real page request proves this: tab_history::export_for_template()
  # forwards $output into get_audit_logs(), which requires the concrete
  # core_renderer type — a PHPUnit call to display() fails with a TypeError
  # before header() has run for real, but by the time view.php dispatches this
  # tab it already has.
  Scenario: Student opens the log tab without error
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "a[aria-label='Log']" "css_element"
    Then I should see "No records found in your journey yet."

  # Only a real AJAX round-trip proves this: use_item::execute() passed the
  # global $OUTPUT straight into a \renderer_base-typed parameter, and
  # lib/ajax/service.php never resolves $OUTPUT before dispatching execute() —
  # a PHPUnit call passes because PHPUnit's own setup already resolves $OUTPUT
  # earlier in the run, masking the exact failure a real "Equip" click hits.
  Scenario: Student equips an avatar item without error
    Given an equippable PlayerHUD avatar item "Test Avatar" is in "student1"'s inventory in course "C1"
    When I log in as "student1"
    And I open the PlayerHUD dashboard for course "C1"
    # First visit shows the one-time "Hi, I'm Huddy!" overlay, which blocks clicks
    # underneath it — reload once so it is dismissed before interacting with the tab.
    And I reload the page
    And I click on ".ph-item-equip-btn" "css_element"
    Then I should not see "generalexceptionmessage"
    And I should see "Unequip"
