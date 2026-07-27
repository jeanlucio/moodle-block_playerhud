@block @block_playerhud @block_playerhud_manage_crud
Feature: PlayerHUD management screens beyond the item library
  As an editing teacher
  I want to reach every management tab and create content through the real forms
  So that the screens the unit tests only exercise in isolation are proven to work end to end

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

  # -----------------------------------------------------------------
  # Tabs that no scenario reached before — a fatal in any of these
  # renderers only ever surfaces on a real page request.
  # -----------------------------------------------------------------

  Scenario: Teacher navigates to the trades tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Trades (NPC)" "link" in the "#ph-manage-tabs" "css_element"
    Then I should see "Trades (NPC)" in the "#ph-manage-tabs .active" "css_element"

  Scenario: Teacher navigates to the characters tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Characters" "link" in the "#ph-manage-tabs" "css_element"
    Then I should see "Characters" in the "#ph-manage-tabs .active" "css_element"
    And I should see "New Character"

  Scenario: Teacher navigates to the story tab
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Story" "link" in the "#ph-manage-tabs" "css_element"
    Then I should see "Story" in the "#ph-manage-tabs .active" "css_element"

  # -----------------------------------------------------------------
  # Drops — the full navigation chain and a real form round trip.
  # The unit tests drive the controller directly; only this proves the
  # item library actually links through to the drops screen.
  # -----------------------------------------------------------------

  # The drop code is never rendered as visible text — it only reaches the page as the
  # shortcode-generator button's data-dropcode attribute and the copy-to-clipboard hidden
  # input — so the row is identified by that attribute rather than by "I should see".

  Scenario: Teacher reaches the drops screen for an item and sees its existing drop
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Manage Drops for: Golden Key']" "css_element"
    Then I should see "Golden Key"
    And "[data-dropcode='GOLD01']" "css_element" should exist

  Scenario: Teacher creates a drop through the real form and sees it in the listing
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Manage Drops for: Golden Key']" "css_element"
    And I follow "New Drop Location"
    And I set the field "Location / Name" to "Library Corner"
    And I press "Save Location"
    Then I should see "Library Corner"
    And "[data-dropcode='GOLD01']" "css_element" should exist

  # -----------------------------------------------------------------
  # Characters — a real form round trip, including the file manager
  # fields the unit tests deliberately leave alone.
  # -----------------------------------------------------------------

  Scenario: Teacher creates a character through the real form
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Characters" "link" in the "#ph-manage-tabs" "css_element"
    And I follow "New Character"
    And I set the field "Name" to "Forest Ranger"
    And I press "Save changes"
    Then I should see "Forest Ranger"

  # -----------------------------------------------------------------
  # Bulk selection is pure JavaScript: the master checkbox drives every
  # row checkbox, which no PHP-level test can observe.
  # -----------------------------------------------------------------

  @javascript
  Scenario: The master checkbox selects and clears every drop row
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Manage Drops for: Golden Key']" "css_element"
    And I click on "#ph-select-all" "css_element"
    Then all PlayerHUD bulk checkboxes are checked
    When I click on "#ph-select-all" "css_element"
    Then all PlayerHUD bulk checkboxes are unchecked
