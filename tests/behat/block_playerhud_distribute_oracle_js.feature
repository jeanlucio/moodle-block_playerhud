@block @block_playerhud @block_playerhud_distribute_oracle_js @javascript
Feature: PlayerHUD drop distribution and character oracle driven by JavaScript
  As an editing teacher
  I want to place drops into activities and ask the character oracle in a real browser
  So that the distribution screen and the oracle's own checks stay proven end to end

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
    And the following "activities" exist:
      | activity | name        | intro         | course | idnumber |
      | page     | Lesson page | Page summary. | C1     | page1    |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"

  # -----------------------------------------------------------------
  # Drop distribution (distribute_drops): select a drop, insert its
  # shortcode into an activity through the web service, then undo it.
  # -----------------------------------------------------------------

  Scenario: Selecting a drop enables the insert button with the count
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Distribute Drops" "link"
    And the "#ph-btn-bulk-insert" element is disabled
    And I click on ".ph-dist-check" "css_element"
    Then I should see "Insert selected (1)" in the "#ph-btn-bulk-insert" "css_element"
    And "#ph-btn-bulk-insert[disabled]" "css_element" should not exist

  Scenario: Teacher inserts a drop into an activity and the insertion is kept
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Distribute Drops" "link"
    And I click on ".ph-dist-check" "css_element"
    And I click on "#ph-btn-bulk-insert" "css_element"
    And I should see "Inserted!" in the ".ph-dist-status" "css_element"
    And I reload the page
    Then I should see "Inserted!" in the ".ph-dist-status" "css_element"
    And ".ph-distribute-row.ph-row-inserted" "css_element" should exist

  Scenario: Teacher undoes an insertion after confirming
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Distribute Drops" "link"
    And I click on ".ph-dist-check" "css_element"
    And I click on "#ph-btn-bulk-insert" "css_element"
    And I should see "Inserted!" in the ".ph-dist-status" "css_element"
    And I click on ".ph-dist-check" "css_element"
    And I click on "#ph-btn-bulk-remove" "css_element"
    And I should see "Remove the shortcode from the selected activities?"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then I should see "Pending" in the ".ph-dist-status" "css_element"
    And ".ph-distribute-row.ph-row-inserted" "css_element" should not exist

  # -----------------------------------------------------------------
  # Character oracle (ai_oracle): the checks that run without an AI key.
  # A successful generation needs a real provider and is not covered here.
  # -----------------------------------------------------------------

  Scenario: The character oracle asks for a theme before calling the AI
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Characters" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "Character Oracle" "button"
    And I wait until "#ph-ai-oracle-modal.show" "css_element" exists
    And I click on "[data-action='ai-oracle-submit']" "css_element"
    Then I should see "Please enter a theme for the story!"

  Scenario: The character oracle reports the failure and frees the button when no AI is available
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Characters" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "Character Oracle" "button"
    And I wait until "#ph-ai-oracle-modal.show" "css_element" exists
    And I set the field "Theme / Description" to "Pirates"
    And I click on "[data-action='ai-oracle-submit']" "css_element"
    And I wait until "[data-action='ai-oracle-submit'][aria-busy]" "css_element" does not exist
    Then I should see "Error"
    And "[data-action='ai-oracle-submit'][disabled]" "css_element" should not exist
    And "#ph-oracle-result" "css_element" should not exist
