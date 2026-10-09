@block @block_playerhud @block_playerhud_diagnostics
Feature: PlayerHUD usage and diagnostics page
  As a site administrator
  I want to see how the PlayerHUD block is used and clean the data left by removed blocks
  So that I can follow its adoption and keep the database tidy

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | playerhud | Course       | C1        | course-view-*   | side-pre      |
    And "student1" has 30 PlayerHUD XP in course "C1"
    And a removed PlayerHUD block instance left 3 players behind

  Scenario: The administrator finds the page under Reports and sees the figures
    When I log in as "admin"
    And I navigate to "Reports > PlayerHUD usage and diagnostics" in site administration
    Then I should see "Courses with the block"
    And I should see "Removed instances with data left"
    And I should see "Players of removed instances"
    And I should see "I confirm that I want to delete this data"

  Scenario: Nothing is deleted without ticking the confirmation
    When I log in as "admin"
    And I navigate to "Reports > PlayerHUD usage and diagnostics" in site administration
    And I press "Delete leftover data"
    Then I should see "Nothing was deleted: tick the confirmation box first."
    And I should see "I confirm that I want to delete this data"

  Scenario: The administrator deletes the leftover data and keeps a copy
    When I log in as "admin"
    And I navigate to "Reports > PlayerHUD usage and diagnostics" in site administration
    And I set the field "I confirm that I want to delete this data" to "1"
    And I press "Delete leftover data"
    Then I should see "Leftover data deleted: 1 instances and 3 rows."
    And I should see "No leftover data found."
    And I should see "orphans-" in the "File" "table"
