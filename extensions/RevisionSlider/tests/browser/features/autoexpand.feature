@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration
Feature: RevisionSlider auto expand
  Background:
    Given a page with 2 revision(s) exists

  Scenario: Revision slider does not automatically expand by default
    Given I am on the diff page
    Then There should be a RevisionSlider expand button
    And RevisionSlider wrapper should be hidden

  Scenario: Revision slider expands automatically when auto expand is on
    Given I am on the diff page
    When I click on the expand button
    And RevisionSlider wrapper should be visible
    And The RevisionSlider has loaded
    And I have dismissed the help dialog
    And I click on the auto expand button
    And I wait for the setting to be saved
    And I refresh the page
    Then The auto expand button should be visible
    And The auto expand button should be on
    And RevisionSlider wrapper should be visible
    And The RevisionSlider has loaded

  Scenario: Revision slider does not expand automatically when auto expand is off
    Given I am on the diff page
    When I click on the expand button
    And RevisionSlider wrapper should be visible
    And The RevisionSlider has loaded
    And I have dismissed the help dialog
    And I click on the auto expand button
    And I wait for the setting to be saved
    And I click on the auto expand button
    And I wait for the setting to be saved
    And I refresh the page
    And I click on the expand button
    And RevisionSlider wrapper should be visible
    And The RevisionSlider has loaded
    Then The auto expand button should be visible
    And The auto expand button should be off
