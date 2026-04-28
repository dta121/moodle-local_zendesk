@local @local_zendesk
Feature: Authenticated users can launch the Zendesk Help Center via JWT SSO
  In order to reach the Zendesk Help Center seamlessly from Moodle
  As a logged-in user with the usehelpcenter capability
  I should see the Help Center button when SSO is configured, and the SSO
  redirect page should produce a form that posts to the configured Zendesk
  JWT endpoint with a signed token

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | student1 | Student   | One      | s1@example.com |

  Scenario: Help Center button is hidden when SSO is disabled
    Given the Zendesk integration is configured
    When I log in as "student1"
    And I visit "/local/zendesk/index.php"
    Then I should not see "Open Help Center"

  Scenario: Help Center button is visible when SSO is enabled and configured
    Given the Zendesk integration is configured
    And Zendesk Help Center SSO is enabled and configured
    When I log in as "student1"
    And I visit "/local/zendesk/index.php"
    Then I should see "Open Help Center"

  Scenario: SSO redirect page produces a form posting to the configured Zendesk JWT endpoint
    Given the Zendesk integration is configured
    And Zendesk Help Center SSO is enabled and configured
    When I log in as "student1"
    And I visit "/local/zendesk/sso.php?target=requests"
    Then "#local-zendesk-sso-redirect-form" "css_element" should exist
    And the "action" attribute of "#local-zendesk-sso-redirect-form" "css_element" should contain "test.zendesk.com/access/jwt"
    And "input[name=\"jwt\"]" "css_element" should exist
