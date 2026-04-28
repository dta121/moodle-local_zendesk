@local @local_zendesk
Feature: The attachment proxy enforces the per-ticket manifest allow-list
  In order to prevent any URL on the configured Zendesk subdomain from
  being proxied with the service-account token's privileges (security
  review F7)
  As an authenticated Moodle user
  Visiting the attachment proxy with a URL that has not been registered
  in the manifest for the requested ticket must be rejected, even when
  the URL does live on the configured Zendesk host

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | student1 | Student   | One      | s1@example.com |
    And the Zendesk integration is configured
    And the following Zendesk tickets exist:
      | username | subject       | body         | syncstate | status |
      | student1 | Manifest test | Body content | active    | open   |

  Scenario: Off-manifest URL on the configured Zendesk subdomain is rejected
    # No manifest row exists for this URL on the seeded ticket. The host is
    # the configured Zendesk subdomain so the host allow-list passes; the
    # manifest lookup must still reject the request.
    When I log in as "student1"
    Then I expect a Moodle exception containing "not valid for this ticket" when I visit the Zendesk attachment proxy with URL "https://test.zendesk.com/attachments/token/untrusted/?name=foo.png" for the "Manifest test" ticket

  Scenario: A non-Zendesk URL is rejected by the host allow-list
    # The host allow-list rejects URLs not on the configured Zendesk subdomain
    # before the manifest check ever runs.
    When I log in as "student1"
    Then I expect a Moodle exception containing "not valid for this ticket" when I visit the Zendesk attachment proxy with URL "https://evil.example.com/hijack/?name=foo.png" for the "Manifest test" ticket
