@block @block_playerhud
Feature: Core gamification flows and accessibility
  In order to gamify my course
  As a teacher
  I need to create items and manage the game
  As a student
  I need to be able to interact with the HUD and manage my participation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And I log out

  @javascript
  Scenario: Teacher creates an item via Master Panel
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    # Essencial para Moodle 4.x/5.1: Abrir a gaveta onde o bloco está escondido
    And I open the block drawer
    # O professor pula o Opt-in devido ao auto-enable e já vê o painel principal
    When I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    Then I should see "Game Master Panel"
    And I should see "Item Library"
    # Você pode continuar os steps de criação do item aqui:
    # And I click on "New Item" "link"
    # ...

  @javascript
  Scenario: Student checks Backpack and Shop Accessibility
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I open the block drawer
    # O aluno não vê o botão "Yes" no primeiro acesso, pois já nasce ativado
    Then I should see "Level 1" in the "PlayerHUD" "block"
    And I should see "0 / 0 XP" in the "PlayerHUD" "block"
    When I click on "Shop" "link" in the "PlayerHUD" "block"
    Then I should see "Shop"
    And I should see "No trades available at the moment."

  @javascript
  Scenario: Student pauses and resumes gamification (Opt-out and Opt-in)
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I open the block drawer
    # Aluno faz o Opt-out voluntário
    When I click on "Disable and Exit" "link" in the "PlayerHUD" "block"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    # Confirma o estado de pausa (a tela de rejoin é renderizada)
    Then I should see "Gamification paused." in the "PlayerHUD" "block"
    # Aluno decide voltar, agora sim testamos o link que estava falhando
    When I click on "Yes, I want to join!" "link" in the "PlayerHUD" "block"
    Then I should see "Level 1" in the "PlayerHUD" "block"
