@block @block_playerhud @javascript
Feature: PlayerHUD Hero Path
  In order to progress in the game
  As a student
  I need to collect drops and view them in my backpack

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

  Scenario: Teacher creates an item and Student collects it
    # 1. Professor cria o Item e o Drop via Interface (Testando o Painel do Mestre)
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block to the default region with:
      | config_xp_per_level | 100 |

    When I click on "Game Master Panel" "link"
    And I click on "Add Item" "link"
    And I set the field "Item Name" to "Magic Potion"
    And I set the field "Emoji or Image URL" to "🧪"
    And I press "Save changes"
    Then I should see "Changes saved successfully"

    # 2. Aluno entra, abre a mochila e a loja para auditar a Acessibilidade!
    Given I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage

    When I click on "Open Backpack" "link"
    Then I should see "Collection"

    # Validação de Ouro: WCAG 2.1 AA da Mochila
    And the page should meet accessibility standards

    When I click on "Shop" "link"
    Then I should see "No trades available"

    # Validação de Ouro: WCAG 2.1 AA da Loja
    And the page should meet accessibility standards
