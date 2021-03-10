@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration
Feature: RevisionSlider tooltips
  Background:
    Given a page with 4 revision(s) exists

  Scenario: RevisionSlider revision tooltip appears on hover
    Given I am on the diff page
    When I have loaded the RevisionSlider and dismissed the help dialog
    And I hover over revision 1
    And I hover over revision 2
    Then a tooltip should be present for revision 2
    And no tooltip should be present for revision 1

  Scenario: RevisionSlider revision tooltip appears and remains on hover
    Given I am on the diff page
    When I have loaded the RevisionSlider and dismissed the help dialog
    And I hover over revision 1
    And I hover over the revision 1 tooltip
    And I hover over revision 2
    And I hover over the revision 2 tooltip
    Then a tooltip should be present for revision 2
    And no tooltip should be present for revision 1