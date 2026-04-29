@block @block_playerhud @block_playerhud_teacher
Feature: PlayerHUD teacher management panel
  As an editing teacher
  I want to access the game master panel and navigate between management tabs

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block

  Scenario: Teacher sees the Game Master Panel button in the block
    Then I should see "Game Master Panel" in the "PlayerHUD" "block"

  Scenario: Teacher accesses the management panel and sees item library tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    Then I should see the PlayerHUD management tabs
    And I should see "Item Library" in the "#ph-manage-tabs" "css_element"
    And I should see "Quests" in the "#ph-manage-tabs" "css_element"
    And I should see "Reports" in the "#ph-manage-tabs" "css_element"

  Scenario: Teacher navigates to the item library tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I follow "Item Library"
    Then I should see "Item Library" in the ".ph-manage-content" "css_element"

  Scenario: Teacher navigates to the quests tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I follow "Quests"
    Then I should see "Quests" in the ".ph-manage-content" "css_element"

  Scenario: Teacher navigates to the reports tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I follow "Reports"
    Then I should see "Reports" in the ".ph-manage-content" "css_element"

  Scenario: Teacher can return to the course from the management panel
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    Then I should see the PlayerHUD management tabs
    When I follow "Back to Course"
    Then I am on "Course 1" course homepage
