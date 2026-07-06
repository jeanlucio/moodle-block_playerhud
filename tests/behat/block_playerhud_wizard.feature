@block @block_playerhud @block_playerhud_wizard @javascript
Feature: PlayerHUD gamification wizard
  As an editing teacher
  I want to generate gamification content for my course from the wizard

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
    And I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I open the PlayerHUD wizard

  Scenario: Teacher opens the wizard and sees the generation form
    Then I should see "Generate gamification" in the "#ph-wizard-modal" "css_element"
    And I should see "PlayerCoin" in the "#ph-wizard-modal" "css_element"

  Scenario: Teacher opens the wizard's help view
    When I click on "#ph-wizard-help-btn" "css_element"
    Then the "#ph-wizard-help-view" element is visible
    And I should see "How the wizard works" in the "#ph-wizard-help-view" "css_element"
    And I should see "The three sections" in the "#ph-wizard-help-view" "css_element"

  Scenario: Teacher opens the wizard's external recommendations view
    When I click on "#ph-wizard-external-btn" "css_element"
    Then the "#ph-wizard-external-view" element is visible
    And I should see "Groups (PlayerGroup)" in the "#ph-wizard-external-view" "css_element"
    And I should see "Deadline Extension (latepenalty)" in the "#ph-wizard-external-view" "css_element"

  Scenario: Teacher generates PlayerCoin from the wizard
    # Items is ticked by default and needs a real AI key — untick it so only the
    # deterministic PlayerCoin mechanic runs.
    When I click on "#ph-wizard-module-items" "css_element"
    And I click on "#ph-wizard-module-playercoin" "css_element"
    And I click on "#ph-wizard-generate-btn" "css_element"
    Then I should see the PlayerHUD wizard success report
    When I click on "#ph-wizard-progress-ok-btn" "css_element"
    And I wait for the PlayerHUD wizard to close and reload the page
    And I follow "Item Library"
    Then I should see "PlayerCoin"

  Scenario: PlayerCoin card locks after being generated
    When I click on "#ph-wizard-module-items" "css_element"
    And I click on "#ph-wizard-module-playercoin" "css_element"
    And I click on "#ph-wizard-generate-btn" "css_element"
    Then I should see the PlayerHUD wizard success report
    When I click on "#ph-wizard-progress-ok-btn" "css_element"
    And I wait for the PlayerHUD wizard to close and reload the page
    And I open the PlayerHUD wizard
    Then the "#ph-wizard-module-playercoin" element is disabled
    And I should see "Already generated in this course" in the "#ph-wizard-modal" "css_element"

  Scenario: Teacher undoes a wizard run from the history view
    When I click on "#ph-wizard-module-items" "css_element"
    And I click on "#ph-wizard-module-playercoin" "css_element"
    And I click on "#ph-wizard-generate-btn" "css_element"
    Then I should see the PlayerHUD wizard success report
    When I click on "#ph-wizard-progress-ok-btn" "css_element"
    And I wait for the PlayerHUD wizard to close and reload the page
    And I open the PlayerHUD wizard
    And I click on "#ph-wizard-history-btn" "css_element"
    Then I should see "items" in the "#ph-wizard-history-list" "css_element"
    When I click on "Undo gamification" "button" in the "#ph-wizard-history-list" "css_element"
    Then I should see "No runs to undo yet." in the "#ph-wizard-history-view" "css_element"
    When I click on "#ph-wizard-cancel-btn" "css_element"
    And I wait for the PlayerHUD wizard to close and reload the page
    And I follow "Item Library"
    Then I should not see "PlayerCoin"
