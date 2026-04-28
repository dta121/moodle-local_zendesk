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

  Scenario: Visiting attachment.php with an off-manifest Zendesk URL is rejected
    # No manifest row exists for this URL on the seeded ticket. The host is
    # the configured Zendesk subdomain so the host allow-list passes; the
    # manifest lookup must still reject the request.
    When I log in as "student1"
    Then I expect a Moodle exception containing "not valid for this ticket" when I visit "/local/zendesk/attachment.php?id=1&url=aHR0cHM6Ly90ZXN0LnplbmRlc2suY29tL2F0dGFjaG1lbnRzL3Rva2VuL3VudHJ1c3RlZC8/bmFtZT1mb28ucG5n"

  Scenario: Visiting attachment.php with a non-Zendesk URL is rejected
    # Even before the manifest check, the host allow-list rejects URLs that
    # are not on the configured Zendesk subdomain.
    When I log in as "student1"
    Then I expect a Moodle exception containing "not valid for this ticket" when I visit "/local/zendesk/attachment.php?id=1&url=aHR0cHM6Ly9ldmlsLmV4YW1wbGUuY29tL2hpamFjay8/bmFtZT1mb28ucG5n"
