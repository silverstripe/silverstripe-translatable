@database-defaults
Feature: Create a translation
  As a content translator
  I want to translate an existing page into a new language in the CMS
  So that I manage its content separately

  Background:
    Given I am logged in with "ADMIN" permissions
    And I go to "/admin/pages"
    Then I should see "About Us" in CMS Tree

  @javascript
  Scenario: I can create a new page for a new language
    When I follow "About Us"
    And I click the "Translations" CMS tab
    And I fill in "New language" dropdown with "German"
    And I press the "Create" button
    Then I should see an edit page form
    And I should see "German" in the "#Form_LangForm #Locale" element
    And I should see "About Us" in CMS Tree
    When I fill in "Über uns" for "Title"
    And I press the "Save & Publish" button
    Then I should see an edit page form
    And I should see "Über uns" in CMS Tree
    And I should not see "About Us" in CMS Tree