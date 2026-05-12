@block @block_playerhud @block_playerhud_modals @javascript
Feature: PlayerHUD modal behaviour
  As a student interacting with drops and the widget stash
  I need modals to open correctly, close correctly, and never redirect the page

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
    And a PlayerHUD item "Test Gem" with drop code "GEM01" exists in course "C1"
    And I log out

  # -----------------------------------------------------------------
  # Modal de coleta (item-details-trigger) — abertura e fechamento
  # -----------------------------------------------------------------

  Scenario: Student opens item details modal after collecting a drop
    Given "student1" has collected drop "GEM01" in course "C1"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on the first ".ph-item-trigger" element
    Then the PlayerHUD item details modal is visible
    And I should see "Test Gem" in the PlayerHUD modal

  Scenario: Student closes the item details modal with the close button
    Given "student1" has collected drop "GEM01" in course "C1"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on the first ".ph-item-trigger" element
    And the PlayerHUD item details modal is visible
    And I click on ".ph-modal-close-f, [data-bs-dismiss='modal']" "css_element"
    Then the PlayerHUD item details modal is not visible

  Scenario: Clicking an item trigger multiple times opens the modal only once
    Given "student1" has collected drop "GEM01" in course "C1"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on the first ".ph-item-trigger" element
    And the PlayerHUD item details modal is visible
    And I close the PlayerHUD modal
    And I click on the first ".ph-item-trigger" element
    Then the PlayerHUD item details modal is visible
    And there is only one PlayerHUD modal in the DOM

  # -----------------------------------------------------------------
  # Coleta AJAX — não redireciona a página
  # -----------------------------------------------------------------

  Scenario: Collecting a drop via AJAX does not redirect the page
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And a label with shortcode "[PLAYERHUD_DROP code=GEM01]" exists in the course
    And I am on "Course 1" course homepage
    And I remember the current page URL
    And I click on ".ph-action-collect" "css_element"
    And I wait for the PlayerHUD AJAX collect to complete
    Then the page URL has not changed
    And the ".ph-action-collect" element is not visible

  # -----------------------------------------------------------------
  # Strings — não devem mostrar placeholders [[...]]
  # -----------------------------------------------------------------

  Scenario: Modal does not display raw string placeholders
    Given "student1" has collected drop "GEM01" in course "C1"
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on the first ".ph-item-trigger" element
    Then the PlayerHUD item details modal is visible
    And I should not see "[[" in the PlayerHUD modal
    And I should not see "]]" in the PlayerHUD modal
