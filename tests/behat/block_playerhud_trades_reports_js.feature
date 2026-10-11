@block @block_playerhud @block_playerhud_trades_reports_js @javascript
Feature: PlayerHUD trades and reports screens driven by JavaScript
  As an editing teacher managing trades and following students
  I want the trade confirmations, suggestions, item previews and the audit log tools to work in a real browser
  So that the trades tab, the trade form and the reports tab stay proven end to end

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

  # -----------------------------------------------------------------
  # Trades tab (manage_trades) and trade form (edit_trade).
  # -----------------------------------------------------------------

  Scenario: Teacher deletes a trade after confirming
    Given a PlayerHUD trade "Key Swap" that costs "Old Key" and gives "New Key" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Trades (NPC)" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "a[aria-label='Delete Key Swap']" "css_element"
    And I should see "Key Swap" in the ".modal.show" "css_element"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then I should not see "Key Swap" in the "region-main" "region"

  Scenario: The trade suggestions can all be cleared and selected again
    Given a PlayerHUD PlayerCoin and two avatar items exist in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Trades (NPC)" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "Suggest Trades" "link"
    And all PlayerHUD trade suggestions are checked
    And I click on "#ph-trade-sug-toggle-all" "css_element"
    And all PlayerHUD trade suggestions are unchecked
    And I click on "#ph-trade-sug-toggle-all" "css_element"
    Then all PlayerHUD trade suggestions are checked

  Scenario: The trade form previews the item picked as a requirement
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Trades (NPC)" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "New Trade Offer" "link"
    And "#preview_req_0.border-success" "css_element" should not exist
    And I set the field "req_itemid_0" to "Golden Key"
    Then "#preview_req_0.border-success" "css_element" should exist

  # -----------------------------------------------------------------
  # Reports tab (manage_reports): student selector, live search, revoke.
  # -----------------------------------------------------------------

  Scenario: Teacher opens a student's log from the selector and filters it with the live search
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    And a PlayerHUD item "Silver Key" with drop code "SILV01" exists in course "C1"
    And "student1" has collected drop "GOLD01" in course "C1"
    And "student1" has collected drop "SILV01" in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Reports" "link" in the "#ph-manage-tabs" "css_element"
    And I set the field "Audit / History" to "Student One (student1@example.com)"
    And "Silver Key" "table_row" should be visible
    And I set the field "Type any term to search..." to "Golden"
    Then "Golden Key" "table_row" should be visible
    And "Silver Key" "table_row" should not be visible

  Scenario: Teacher removes an item from a student after confirming
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    And "student1" has collected drop "GOLD01" in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Reports" "link" in the "#ph-manage-tabs" "css_element"
    And I set the field "Audit / History" to "Student One (student1@example.com)"
    And I click on ".js-delete-report-btn" "css_element"
    And I should see "Are you sure you want to remove this item from the student?"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then I should see "Item removed and XP recalculated successfully."
