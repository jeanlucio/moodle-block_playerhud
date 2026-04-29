@block @block_playerhud @block_playerhud_access
Feature: PlayerHUD block access control
  As a site administrator
  I need the PlayerHUD block to respect Moodle capability-based access

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | teacher1  | Teacher   | One      | teacher1@example.com  |
      | student1  | Student   | One      | student1@example.com  |
      | outsider1 | Outsider  | One      | outsider1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Editing teacher can add the PlayerHUD block to a course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "PlayerHUD" block
    Then I should see "PlayerHUD" in the "PlayerHUD" "block"

  Scenario: Enrolled student sees the block with the HUD active
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "PlayerHUD" in the "PlayerHUD" "block"
    And I should see the PlayerHUD XP bar

  Scenario: Non-enrolled user cannot see the block
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And I log out
    When I log in as "outsider1"
    And I am on "Course 1" course homepage
    Then I should not see "Game Master Panel"
