@local @local_zendesk
Feature: Students reply to existing Zendesk requests from inside Moodle
  In order to continue a conversation with the help desk
  As a logged-in student who owns an open Zendesk ticket
  I should see a reply form on the ticket detail page and be able to send
  a reply that the plugin syncs back through the Zendesk API

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | student1 | Student   | One      | s1@example.com |
    And the Zendesk integration is configured
    And the following Zendesk tickets exist:
      | username | subject              | body           | syncstate | status |
      | student1 | Open conversation    | Initial body   | active    | open   |

  Scenario: Reply form is rendered for an open ticket
    When I log in as "student1"
    And I visit the Zendesk ticket page for "Open conversation"
    Then I should see "Send a reply"
    And "textarea[name=\"replymessage\"]" "css_element" should exist
    And "Send reply" "button" should exist

  Scenario: Submitting a reply on an open ticket succeeds
    Given the Zendesk API will respond to "POST /users/create_or_update.json" with status 200 and body:
      """
      {"user": {"id": 12345, "email": "s1@example.com"}}
      """
    And the Zendesk API will respond to "GET /tickets/*.json" with status 200 and body:
      """
      {"ticket": {"id": 5001, "status": "open", "updated_at": "2026-04-28T12:00:00Z"}}
      """
    And the Zendesk API will respond to "PUT /tickets/*.json" with status 200 and body:
      """
      {"ticket": {"id": 5001, "status": "open", "updated_at": "2026-04-28T12:30:00Z"}}
      """
    And the Zendesk API will respond to "GET /tickets/*/comments.json" with status 200 and body:
      """
      {"comments": []}
      """
    When I log in as "student1"
    And I visit the Zendesk ticket page for "Open conversation"
    And I set the field "Reply" to "Behat-driven follow-up reply"
    And I press "Send reply"
    Then I should see "Your reply was sent to the help desk."
