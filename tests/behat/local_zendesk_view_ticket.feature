@local @local_zendesk
Feature: Students view their own Zendesk requests
  In order to track support requests they have submitted
  As a logged-in Moodle student
  I should see my own tickets in the request list, be able to open them,
  and be told the same generic "could not be found" error whether a ticket
  id does not exist or simply does not belong to me

  Background:
    Given the Zendesk integration is configured
    And the following "users" exist:
      | username  | firstname | lastname | email          |
      | student1  | Student   | One      | s1@example.com |
      | student2  | Student   | Two      | s2@example.com |
    And the following Zendesk tickets exist:
      | username | subject              | body                  | syncstate | status |
      | student1 | Student one question | Help me please        | active    | open   |
      | student2 | Student two question | Different help        | active    | open   |

  Scenario: Student sees their own ticket in the request list
    When I log in as "student1"
    And I visit "/local/zendesk/index.php"
    Then I should see "Student one question"
    And I should not see "Student two question"

  Scenario: Student can open their own ticket from the request list
    When I log in as "student1"
    And I visit "/local/zendesk/index.php"
    And I follow "Student one question"
    Then I should see "Help me please"

  Scenario: A non-existent ticket id raises the generic invalid-ticket exception
    When I log in as "student1"
    Then I expect a Moodle exception containing "could not be found" when I visit "/local/zendesk/view.php?id=999999"

  Scenario: Another user's ticket id raises the same invalid-ticket exception
    When I log in as "student1"
    Then I expect a Moodle exception containing "could not be found" when I visit the Zendesk ticket page for "Student two question"
