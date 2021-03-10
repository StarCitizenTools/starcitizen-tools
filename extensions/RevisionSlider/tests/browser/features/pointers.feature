@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration
Feature: RevisionSlider pointers
  Background:
    Given a page with 5 revision(s) exists

#  Deactivated until bar clicking will be reintroduced see T165831
#
#  Scenario: RevisionSlider pointers move when revision bars are clicked
#    Given I am on the diff page
#    When I have loaded the RevisionSlider and dismissed the help dialog
#    And I click on revision 3 to move the older pointer
#    And I wait until the diff has loaded
#    And I click on revision 4 to move the newer pointer
#    And I wait until the diff has loaded
#    Then the older pointer should be on revision 3
#    And the newer pointer should be on revision 4
#    And revision 3 should be loaded on the left of the diff
#    And revision 4 should be loaded on the right of the diff

  Scenario: RevisionSlider pointers can be dragged
    Given I am on the diff page
    When I have loaded the RevisionSlider and dismissed the help dialog
    And I drag the older pointer to revision 3
    And I wait until the diff has loaded
    And I drag the newer pointer to revision 4
    And I wait until the diff has loaded
    Then the older pointer should be on revision 3
    And the newer pointer should be on revision 4
    And revision 3 should be loaded on the left of the diff
    And revision 4 should be loaded on the right of the diff
