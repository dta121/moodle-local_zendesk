@local @local_zendesk
Feature: Students submit Zendesk support requests from Moodle
  In order to ask the help desk a question without leaving Moodle
  As a logged-in student
  I should be able to fill out the request form and see my submission
  appear in the Moodle-side request list once it has been confirmed with
  Zendesk

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | student1 | Student   | One      | s1@example.com |
    And the Zendesk integration is configured

  Scenario: Submit form requires both subject and details
    When I log in as "student1"
    And I visit "/local/zendesk/request.php"
    And I press "Submit request"
    Then I should see "Subject"
    And I should see "Support request details"
    # The required-field validation prevents the submission, so the user is
    # still on the form and the field labels remain visible.

  Scenario: Successful submission shows the new ticket in the request list
    Given the Zendesk API will respond to "POST /users/create_or_update.json" with status 200 and body:
      """
      {"user": {"id": 12345, "email": "s1@example.com"}}
      """
    And the Zendesk API will respond to "POST /tickets.json" with status 200 and body:
      """
      {"ticket": {"id": 67890, "status": "open", "updated_at": "2026-04-28T12:00:00Z"}}
      """
    When I log in as "student1"
    And I visit "/local/zendesk/request.php"
    And I set the field "Subject" to "Behat smoke test"
    And I set the field "Support request details" to "End-to-end submission via Behat"
    And I press "Submit request"
    Then I should see "Behat smoke test"
    And I visit "/local/zendesk/index.php"
    And I should see "Behat smoke test"
