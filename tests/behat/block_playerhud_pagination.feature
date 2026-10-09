@block @block_playerhud @block_playerhud_pagination
Feature: PlayerHUD ranking and reports pagination
  As a student or teacher on a course with many players
  I want the ranking and the reports to show one page at a time
  So that the pages stay light and I can still find myself or any student

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
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | playerhud | Course       | C1        | course-view-*   | side-pre      |
    And 60 ranked PlayerHUD players exist in course "C1"
    And "student1" has 1 PlayerHUD XP in course "C1"

  Scenario: A student ranked below the first page sees their own row pinned under it
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "View Leaderboard" "link" in the "PlayerHUD" "block"
    Then I should see "Ranked050 Player"
    And I should not see "Ranked051 Player"
    And I should see "Your position"
    And I should see "Student One"

  Scenario: The second page lists the next players and no longer pins the student who is on it
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "View Leaderboard" "link" in the "PlayerHUD" "block"
    And I click on "2" "link" in the ".pagination" "css_element"
    Then I should see "Ranked051 Player"
    And I should see "Student One"
    And I should not see "Ranked050 Player"
    And I should not see "Your position"

  Scenario: A teacher hiding a student from the ranking stays on the same page
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "View Leaderboard" "link" in the "PlayerHUD" "block"
    And I click on "2" "link" in the ".pagination" "css_element"
    And I click on "Hide from ranking" "link" in the "Ranked055 Player" "table_row"
    Then I should see "Ranked051 Player"
    And I should not see "Ranked050 Player"

  Scenario: The reports students table shows the second page of students
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Reports" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "2" "link" in the ".pagination" "css_element"
    Then "Ranked051 Player" "table_row" should exist
    And "Ranked050 Player" "table_row" should not exist

  Scenario: A teacher finds a student in the reports by searching for the name
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Reports" "link" in the "#ph-manage-tabs" "css_element"
    And I set the field "Search student by name" to "player ranked055"
    And I press "Search"
    Then "Ranked055 Player" "table_row" should exist
    And "Ranked054 Player" "table_row" should not exist
    And "Ranked001 Player" "table_row" should not exist
