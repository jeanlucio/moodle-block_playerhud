@block @block_playerhud @block_playerhud_story_js @javascript
Feature: PlayerHUD story screens driven by JavaScript
  As a teacher writing a story and a student reading it
  I want the story modals to load scenes, follow choices and confirm deletions in a real browser
  So that the story player and the story manager stay proven end to end

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
    And I log out
    And a PlayerHUD story chapter "The Old Gate" with scene "The old gate creaks in the wind." and choice "Open the gate" exists in course "C1"

  # -----------------------------------------------------------------
  # Student — the story player (story_player) loads the scene through a
  # web service, follows the choice and offers the summary.
  # -----------------------------------------------------------------

  Scenario: Student reads a chapter to the end
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "a[aria-label='Story']" "css_element"
    And I click on "[data-action='open-chapter']" "css_element"
    And I wait until "#ph-story-modal.show" "css_element" exists
    And I should see "The old gate creaks in the wind." in the "#ph-story-content" "css_element"
    And I click on "Open the gate" "button" in the "#ph-story-choices" "css_element"
    Then I should see "Chapter completed!" in the "#ph-story-choices" "css_element"

  Scenario: Student opens the story summary after finishing a chapter
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "a[aria-label='Story']" "css_element"
    And I click on "[data-action='open-chapter']" "css_element"
    And I wait until "#ph-story-modal.show" "css_element" exists
    And I click on "Open the gate" "button" in the "#ph-story-choices" "css_element"
    And I click on "Story Summary" "button" in the "#ph-story-choices" "css_element"
    Then I should see "End of chapter." in the "#ph-story-content" "css_element"
    And I should see "The old gate creaks in the wind." in the "#ph-story-content .ph-recap-container" "css_element"

  # -----------------------------------------------------------------
  # Teacher — the story manager (manage_story): preview and the delete
  # modals for a chapter and a scene.
  # -----------------------------------------------------------------

  Scenario: Teacher previews a chapter to the end without saving progress
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Story" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "button[data-bs-target='#ph-story-test-modal']" "css_element"
    And I wait until "#ph-story-test-modal.show" "css_element" exists
    And I should see "The old gate creaks in the wind." in the "#ph-test-content" "css_element"
    And I click on "Open the gate" "button" in the "#ph-test-choices" "css_element"
    Then I should see "End of preview." in the "#ph-test-content" "css_element"

  Scenario: Teacher deletes a chapter after confirming in the modal
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Story" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "[data-action='delete-chapter']" "css_element"
    And I wait until "#ph-confirm-delete-chapter.show" "css_element" exists
    And I should see "Are you sure you want to delete the chapter \"The Old Gate\"? All its scenes and choices will be permanently removed." in the "#ph-confirm-delete-chapter" "css_element"
    And I click on "Delete" "link" in the "#ph-confirm-delete-chapter" "css_element"
    Then I should see "Chapter deleted."
    And I should not see "The Old Gate"

  Scenario: Teacher deletes a scene after confirming in the modal
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Game Master Panel" "link" in the "PlayerHUD" "block"
    And I click on "Story" "link" in the "#ph-manage-tabs" "css_element"
    And I click on "a[href*='manage_scenes.php']" "css_element"
    And I click on "[data-action='delete-scene']" "css_element"
    And I wait until "#ph-confirm-delete-scene.show" "css_element" exists
    And I should see "Are you sure you want to delete this scene? All its choices will be permanently removed." in the "#ph-confirm-delete-scene" "css_element"
    And I click on "Delete" "link" in the "#ph-confirm-delete-scene" "css_element"
    Then I should see "Scene deleted successfully."
    And I should not see "The old gate creaks in the wind."
