@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration
Feature: RevisionSlider expand
  Background:
    Given a page with 2 revision(s) exists

  Scenario: RevisionSlider is collapsed initially
    Given I am on the diff page
    Then  There should be a RevisionSlider expand button
    And RevisionSlider wrapper should be hidden

  Scenario: RevisionSlider loads after expanding
    Given I am on the diff page
    When I click on the expand button
    Then RevisionSlider wrapper should be visible
    And The RevisionSlider has loaded

  Scenario: RevisionSlider hides after collapsing
    Given I am on the diff page
    When I click on the expand button
    And I wait until the RevisionSlider has loaded
    And I have dismissed the help dialog
    And I click on the expand button
    Then RevisionSlider wrapper should be hidden
