@local @local_zendesk
Feature: Site administrator can manage and validate the Zendesk integration settings
  In order to safely connect Moodle to Zendesk
  As a site administrator
  I should be able to view the plugin settings page and have malformed
  subdomain values rejected before they can weaken the SSRF host allow-list

  Background:
    Given I log in as "admin"

  Scenario: Administrator can view the Zendesk integration settings page
    When I navigate to "Plugins > Local plugins > Zendesk support integration" in site administration
    Then I should see "Enable Zendesk integration"
    And I should see "Zendesk subdomain"
    And I should see "Zendesk API token"
    And I should see "Zendesk Help Center single sign-on"

  Scenario: Subdomain field accepts a clean DNS label
    Given I navigate to "Plugins > Local plugins > Zendesk support integration" in site administration
    When I set the field "Zendesk subdomain" to "example-org"
    And I press "Save changes"
    Then I should see "Changes saved"

  Scenario: Subdomain field rejects a malformed value
    Given I navigate to "Plugins > Local plugins > Zendesk support integration" in site administration
    When I set the field "Zendesk subdomain" to "evil.com#"
    And I press "Save changes"
    Then I should see "DNS label"
