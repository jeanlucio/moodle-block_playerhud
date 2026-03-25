@block @block_playerhud @javascript
Feature: PlayerHUD Hero Path and Accessibility
  In order to ensure the game is playable and accessible
  As a user
  I need to navigate the Master Panel and Student Backpack

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    # O truque de ouro: Colocamos o bloco na região "content" (centro da tela).
    # Assim ele nunca ficará oculto em gavetas recolhíveis!
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | playerhud | Course       | C1        | course-view-* | content       |

  Scenario: Teacher creates an item via Master Panel
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    # Agora que o bloco está no meio da tela, o clique funciona de primeira
    When I click on "Game Master Panel" "link"
    And I click on "New Item" "link"
    And I set the field "Item Name" to "Magic Potion"
    And I set the field "Emoji or Image URL" to "🧪"
    And I press "Save changes"
    Then I should see "Changes saved successfully"
    And I should see "Magic Potion"

  Scenario: Student checks Backpack and Shop Accessibility
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    # O robô clica no ícone da mochila
    When I click on "Open Backpack" "link"
    Then I should see "Collection"

    # Validação de Ouro: Audita o contraste e as tags ARIA na Mochila
    And the page should meet accessibility standards

    When I click on "Shop" "link"
    Then I should see "No trades available"

    # Validação de Ouro: Audita o contraste e as tags ARIA na Loja
    And the page should meet accessibility standards
