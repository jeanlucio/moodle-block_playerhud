@block @block_playerhud @block_playerhud_celebrations @javascript
Feature: PlayerHUD mascot celebration popups
  As a student in a gamified course
  I want Huddy to greet me and to nudge me, each only once

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

  Scenario: Huddy introduces himself only once on the dashboard
    When I log in as "student1"
    And I open the PlayerHUD dashboard for course "C1"
    Then I should see "Hi, I'm Huddy!"
    When I reload the page
    Then I should not see "Hi, I'm Huddy!"

  Scenario: The first-quest nudge appears only once when a reward is claimable
    Given a claimable PlayerHUD quest exists in course "C1"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "First quest complete!"
    When I am on "Course 1" course homepage
    Then I should not see "First quest complete!"
