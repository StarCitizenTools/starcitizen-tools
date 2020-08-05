@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration
Feature: RevisionSlider help
  Background:
    Given a page with 2 revision(s) exists
    And I am on the diff page

  Scenario: RevisionSlider tutorial is present on first load
    When I click on the expand button
    And I wait until the RevisionSlider has loaded
    Then The help dialog should be visible

  Scenario: RevisionSlider tutorial is not present after it was dismissed once
    When I click on the expand button
    And I wait until the RevisionSlider has loaded
    And I have dismissed the help dialog
    And I refresh the page
    And I click on the expand button
    And I wait until the RevisionSlider has loaded
    Then The help dialog should not be present

  Scenario: RevisionSlider tutorial sequence works
    When I click on the expand button
    And I wait until the RevisionSlider has loaded
    And I have moved to the next step
    And I have moved to the next step
    And I have moved to the next step
    And I have closed the help dialog at the end
    And I refresh the page
    And I click on the expand button
    And I wait until the RevisionSlider has loaded
    Then The help dialog should not be present