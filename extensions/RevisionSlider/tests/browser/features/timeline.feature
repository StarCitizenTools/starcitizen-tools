@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration
Feature: RevisionSlider timeline
  Scenario: RevisionSlider timeline arrows to be disabled with 3 revisions
    Given a page with 3 revision(s) exists
    And I am on the diff page
    When I have loaded the RevisionSlider and dismissed the help dialog
    Then The backward arrow should be disabled
    And The forward arrow should be disabled

  Scenario: RevisionSlider timeline arrows to be enabled with adequate revisions
    Given a page with 30 revision(s) exists
    And The window size is 800 by 600
    And I am on the diff page
    When I have loaded the RevisionSlider and dismissed the help dialog
    And I click on the backward arrow
    And I click on the forward arrow
    Then The backward arrow should be enabled
    And The forward arrow should be disabled
