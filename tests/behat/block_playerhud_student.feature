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
    And I follow "Game Master Panel" in the "PlayerHUD" "block"
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
    Then I should see the PlayerHUD XP bar
    And I should not see the PlayerHUD paused state

  Scenario: Student dismisses disable confirmation and HUD remains active
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Game Master Panel" in the "PlayerHUD" "block"
    And I click on ".js-disable-hud" "css_element"
    And I click on "Cancel" "button"
    Then I should see the PlayerHUD XP bar
