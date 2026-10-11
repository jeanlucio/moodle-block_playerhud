@block @block_playerhud @block_playerhud_items_drops_js @javascript
Feature: PlayerHUD item library and drops screens driven by JavaScript
  As an editing teacher managing items and drops
  I want the previews, confirmations, generators and countdowns to work in a real browser
  So that the item library, the drops screen and the drop timer stay proven end to end

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
  # Item library (manage_items).
  # -----------------------------------------------------------------

  Scenario: Teacher previews an item in its details modal
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on ".ph-preview-trigger" "css_element"
    Then the PlayerHUD item details modal is visible
    And I should see "Golden Key" in the PlayerHUD modal

  Scenario: Teacher deletes the selected items in bulk after confirming
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    And a PlayerHUD item "Silver Key" with drop code "SILV01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "#ph-select-all" "css_element"
    And I should see "Delete 2 items" in the "#ph-btn-bulk-delete" "css_element"
    And I click on "#ph-btn-bulk-delete" "css_element"
    And I should see "Are you sure you want to delete the selected items?"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then I should see "Deleted 2 items successfully."

  Scenario: Teacher deletes one item after confirming
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Delete Golden Key']" "css_element"
    And I should see "Are you sure you want to delete the item \"Golden Key\"?"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then I should see "Item deleted."
    And I should not see "Golden Key" in the "region-main" "region"

  Scenario: The item generator shows the drop options and asks for a theme inside its modal
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "#btn-open-ai-modal" "css_element"
    And I wait until "#ph-ai-modal.show" "css_element" exists
    And I click on "Generate Drop location?" "checkbox"
    And the "#ai-drop-options" element is visible
    And I click on "#ph-btn-conjure" "css_element"
    Then I should see "Please enter a theme for the items!" in the "#ph-ai-modal [data-region='ph-modal-error']" "css_element"

  Scenario: The item generator reports the failure inside its modal when no AI is available
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "#btn-open-ai-modal" "css_element"
    And I wait until "#ph-ai-modal.show" "css_element" exists
    And I set the field "Theme (e.g. Mythology, Chemistry)" to "Pirates"
    And I click on "#ph-btn-conjure" "css_element"
    And I wait until "#ph-btn-conjure[aria-busy]" "css_element" does not exist
    Then "#ph-ai-modal [data-region='ph-modal-error']:not([hidden])" "css_element" should exist

  Scenario: Teacher creates the PlayerCoin without a drop location
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I close block drawer if open
    And I click on "button.dropdown-toggle-split[aria-label='Create PlayerCoin']" "css_element"
    And I click on "#btn-create-playercoin" "css_element"
    And I should see "Create PlayerCoin" in the ".modal.show" "css_element"
    And I click on "No, create the item only" "button" in the ".modal.show" "css_element"
    Then I should see "PlayerCoin" in the "region-main" "region"

  Scenario: Teacher creates the avatar pack after confirming
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I close block drawer if open
    And I click on "button.dropdown-toggle-split[aria-label='Create PlayerCoin']" "css_element"
    And I click on "#btn-create-avatar-pack" "css_element"
    And I should see "Create a pack of 17 pre-defined avatar characters."
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then ".ph-preview-trigger" "css_element" should exist

  # -----------------------------------------------------------------
  # Drops screen (manage_drops): shortcode generator and deletions.
  # -----------------------------------------------------------------

  Scenario: The shortcode generator builds the code for each display mode
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Manage Drops for: Golden Key']" "css_element"
    And I click on ".js-open-gen-modal" "css_element"
    And I wait until "#ph-codegen-modal.show" "css_element" exists
    And the field "finalCode" matches value "[PLAYERHUD_DROP code=GOLD01]"
    And I click on "#modeText" "css_element"
    And I set the field "Link Text" to "Grab the key"
    And the field "finalCode" matches value "[PLAYERHUD_DROP code=GOLD01 mode=text text=\"Grab the key\"]"
    And I click on "#modeImage" "css_element"
    Then the field "finalCode" matches value "[PLAYERHUD_DROP code=GOLD01 mode=image]"

  Scenario: Teacher deletes the selected drops in bulk after confirming
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Manage Drops for: Golden Key']" "css_element"
    And I click on "#ph-select-all" "css_element"
    And I should see "Delete 1 items" in the "#ph-btn-bulk-delete" "css_element"
    And I click on "#ph-btn-bulk-delete" "css_element"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then "[data-dropcode='GOLD01']" "css_element" should not exist

  Scenario: Teacher deletes one drop after confirming
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "a[aria-label='Manage Drops for: Golden Key']" "css_element"
    And I click on ".js-delete-btn" "css_element"
    And I click on "Yes" "button" in the ".modal.show" "css_element"
    Then I should see "Item deleted."
    And "[data-dropcode='GOLD01']" "css_element" should not exist

  # -----------------------------------------------------------------
  # Drop countdown (timers): after a collection, the card counts down.
  # -----------------------------------------------------------------

  Scenario: A drop on cooldown shows a running countdown to the student
    Given a PlayerHUD item "Golden Key" with drop code "GOLD01" and a cooldown of 30 minutes exists in course "C1"
    And a label with shortcode "[PLAYERHUD_DROP code=GOLD01]" exists in the course
    And "student1" has collected drop "GOLD01" in course "C1"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "min" in the ".ph-timer" "css_element"
