@block @block_playerhud @block_playerhud_manage_js @javascript
Feature: PlayerHUD management screens driven by JavaScript
  As an editing teacher
  I want the delete confirmations, bulk selection and key toggles to work in a real browser
  So that the behaviour no PHP-level test can observe stays proven end to end

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
  # Characters tab — the shared delete modal is filled in by manage_classes.
  # -----------------------------------------------------------------

  Scenario: Teacher deletes a character after confirming in the modal
    Given a PlayerHUD character "Forest Ranger" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Characters" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "[data-action='delete-class']" "css_element"
    And I wait until "#ph-confirm-delete-class.show" "css_element" exists
    And I should see "Delete this character? Students who have selected it will lose their character selection." in the "#ph-confirm-delete-class" "css_element"
    And I click on "Delete" "link" in the "#ph-confirm-delete-class" "css_element"
    Then I should see "Character deleted."
    And I should not see "Forest Ranger"

  Scenario: Teacher cancels a character deletion and the character stays
    Given a PlayerHUD character "Forest Ranger" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Characters" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "[data-action='delete-class']" "css_element"
    And I wait until "#ph-confirm-delete-class.show" "css_element" exists
    And I click on "Cancel" "button" in the "#ph-confirm-delete-class" "css_element"
    And I wait until "#ph-confirm-delete-class.show" "css_element" does not exist
    Then I should see "Forest Ranger"

  # -----------------------------------------------------------------
  # Settings tab — the API key fields start masked and the eye button
  # (tab_config) flips them.
  # -----------------------------------------------------------------

  Scenario: Teacher reveals an API key
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Settings" "link" in the "#ph-manage-tabs" "css_element"
    And "#gemini_key[type='password']" "css_element" should exist
    And I click on ".ph-toggle-key[data-target='gemini_key']" "css_element"
    Then "#gemini_key[type='text']" "css_element" should exist
    And ".ph-toggle-key[data-target='gemini_key'][aria-pressed='true']" "css_element" should exist

  Scenario: Teacher hides an API key again
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Settings" "link" in the "#ph-manage-tabs" "css_element"
    And I click on ".ph-toggle-key[data-target='gemini_key']" "css_element"
    And I click on ".ph-toggle-key[data-target='gemini_key']" "css_element"
    Then "#gemini_key[type='password']" "css_element" should exist
    And ".ph-toggle-key[data-target='gemini_key'][aria-pressed='false']" "css_element" should exist

  # -----------------------------------------------------------------
  # Quests tab — confirmation dialogs and bulk selection (manage_quests).
  # -----------------------------------------------------------------

  Scenario: Teacher deletes a quest after confirming
    Given a PlayerHUD quest "Reach level two" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Quests" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "a[aria-label='Delete Reach level two']" "css_element"
    And I should see "Are you sure you want to delete 'Reach level two'?"
    And I click on "Yes" "button"
    Then I should see "Quest deleted."
    And I should see "No quests created yet."

  Scenario: Teacher declines the quest deletion and the quest stays
    Given a PlayerHUD quest "Reach level two" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Quests" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "a[aria-label='Delete Reach level two']" "css_element"
    And I should see "Are you sure you want to delete 'Reach level two'?"
    And I click on "Cancel" "button"
    Then I should see "Reach level two"
    And I should not see "Quest deleted."

  Scenario: The bulk delete button shows the number of selected quests
    Given a PlayerHUD quest "Reach level two" exists in course "C1"
    And a PlayerHUD quest "Reach level three" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Quests" "link" in the "#ph-manage-tabs" "css_element"
    And the "#ph-btn-bulk-delete" element is disabled
    And I click on "#ph-select-all" "css_element"
    Then I should see "Delete 2 items" in the "#ph-btn-bulk-delete" "css_element"
    And "#ph-btn-bulk-delete[disabled]" "css_element" should not exist

  Scenario: The bulk delete button goes back to disabled when the selection is cleared
    Given a PlayerHUD quest "Reach level two" exists in course "C1"
    And a PlayerHUD quest "Reach level three" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Quests" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "#ph-select-all" "css_element"
    And I click on "#ph-select-all" "css_element"
    Then I should see "Delete selected" in the "#ph-btn-bulk-delete" "css_element"
    And the "#ph-btn-bulk-delete" element is disabled

  Scenario: Teacher deletes the selected quests in bulk after confirming
    Given a PlayerHUD quest "Reach level two" exists in course "C1"
    And a PlayerHUD quest "Reach level three" exists in course "C1"
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Quests" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "#ph-select-all" "css_element"
    And I click on "#ph-btn-bulk-delete" "css_element"
    And I should see "Are you sure you want to delete the selected items?"
    And I click on "Yes" "button"
    Then I should see "No quests created yet."
