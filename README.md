# Zendesk support integration for Moodle 4.5

`local_zendesk` is a Moodle local plugin that lets authenticated Moodle users submit Zendesk support tickets without leaving Moodle, track their current ticket status, and read public help desk replies from inside Moodle.

## Features

- Submit Zendesk-backed support requests from Moodle
- Map Moodle users to Zendesk end users with a stable external ID
- Show ticket status and public help desk replies in Moodle
- Allow students to reply to active tickets, reopen solved tickets, and continue closed tickets with follow-up tickets
- Sync ticket status back into Moodle with a scheduled task
- Offer optional Zendesk Help Center SSO using JWT
- Expose a read-only Moodle web service for the separate Zendesk `Student Lookup` sidebar app

## Moodle compatibility

- Moodle 4.5

## Requirements

- A Zendesk account with API access
- A Zendesk agent or service account email
- A Zendesk API token
- Outbound HTTPS access from the Moodle server to `*.zendesk.com`

Optional:

- Zendesk Help Center JWT SSO for end-user authentication
- The separate `Student Lookup` Zendesk Support app if you want Moodle context in the Zendesk agent sidebar

## Files and plugin paths

- Install this plugin in `local/zendesk`
- Install the companion dashboard block, if used, in `blocks/zendesk_dashboard`

## Site administrator setup

### Step 1: Prepare Zendesk

Before you install the plugin in Moodle, gather the Zendesk values you will need:

1. Sign in to Zendesk as an administrator.
2. Create or choose a dedicated Zendesk service account that Moodle will use for API calls.
3. Create a Zendesk API token for that service account.
4. Note your Zendesk subdomain. Example: if your Zendesk URL is `https://example.zendesk.com`, the subdomain is `example`.
5. If you want default routing for new tickets, note any optional Zendesk IDs you plan to use:
   - ticket form ID
   - brand ID
   - group ID
6. If you plan to use Help Center SSO later, create the Zendesk JWT SSO configuration and keep the shared secret ready, but do not turn on `Redirect to SSO only` until you finish testing.

### Step 2: Install the plugin in Moodle

1. Copy the plugin folder into `local/zendesk`.
2. Log in to Moodle as a site administrator.
3. Go to `Site administration > Notifications`.
4. Complete the plugin installation and database upgrade.
5. Confirm the plugin appears under `Site administration > Plugins > Local plugins > Zendesk support integration`.

### Step 3: Configure the Zendesk connection

1. Open `Site administration > Plugins > Local plugins > Zendesk support integration`.
2. Turn on `Enable Zendesk integration`.
3. Enter the Zendesk subdomain.
4. Enter the Zendesk service account email.
5. Enter the Zendesk API token.
6. Decide whether to keep `Skip Zendesk email verification` enabled.
   - For the Moodle-first workflow, leaving this enabled is the recommended default.
7. If needed, enter the optional Zendesk ticket defaults:
   - default ticket form ID
   - default brand ID
   - default group ID
8. Set the dashboard item limit.
9. Set the sync batch size.
10. Save changes.

### Step 4: Make sure cron is running

This plugin depends on Moodle cron to sync Zendesk ticket statuses back into Moodle.

1. Confirm normal Moodle cron is already configured for the site.
2. After installing the plugin, check `Site administration > Server > Scheduled tasks`.
3. Confirm the task `Sync Zendesk ticket statuses` exists and is enabled.
4. Run cron once manually in a test environment if you want to verify the first sync immediately.

### Step 5: Review permissions

The plugin uses Moodle capabilities to control access.

Default student-facing capabilities:

- Enable the plugin
- `local/zendesk:submitrequest`
- `local/zendesk:viewownrequests`
- `local/zendesk:usehelpcenter`

Admin or support-manager capability:

- `local/zendesk:viewallrequests`

Review your site roles if you need to change who can submit requests or view all tickets.

### Step 6: Test the base workflow

Use a non-admin student account for the first smoke test.

1. Log in as a student in Moodle.
2. Open the Zendesk request submission page.
3. Submit a test support request.
4. Confirm the request appears in Moodle.
5. Confirm the ticket appears in Zendesk.
6. Reply to the ticket in Zendesk as a help desk agent.
7. Run Moodle cron or wait for the scheduled sync.
8. Confirm the updated ticket status and public reply appear in Moodle.

### Step 7: Optional dashboard block setup

If you want students to see recent ticket status from their Dashboard:

1. Install the companion block plugin `block_zendesk_dashboard`.
2. Add the block to the Dashboard (`/my/`) page.
3. Verify that the block shows:
   - `New request`
   - the recent ticket list
   - the Help Center button when SSO is enabled

### Step 8: Optional Zendesk Help Center SSO setup

Only do this after the core ticket workflow is working.

1. In Zendesk, create or review the JWT SSO configuration for end users.
2. In Moodle, open `Site administration > Plugins > Local plugins > Zendesk support integration`.
3. Turn on `Enable Zendesk Help Center SSO`.
4. Paste the Zendesk JWT shared secret into `Zendesk JWT shared secret`.
5. Set the default Help Center path, such as `/hc/en-us/requests`.
6. Optionally change the student-facing button label.
7. In Zendesk, set:
   - Remote Login URL: `https://yourmoodlesite/local/zendesk/sso.php`
   - Remote Logout URL: `https://yourmoodlesite/local/zendesk/logout.php`
8. Test SSO with a student account before enforcing Zendesk end-user SSO-only mode.

### Step 9: Optional agent context service for the Zendesk sidebar app

If you want Zendesk agents to see Moodle context in the separate `Student Lookup` Zendesk app:

1. Enable Moodle web services.
2. Enable the REST protocol in Moodle.
3. Create a dedicated Moodle service user for the Zendesk app.
4. Give that user the required capabilities:
   - `local/zendesk:viewallrequests`
   - `webservice/rest:use`
   - `moodle/user:viewlastip` if you want IP address and location fields
5. Go to `Site administration > Server > Web services > External services`.
6. Confirm the service `local_zendesk_agent_context_service` exists.
7. Add the service user to the service's authorised users.
8. Create a token for that user and that service.
9. Use that token in the Zendesk `Student Lookup` app settings.

## Configuration reference

Required settings:

- Zendesk subdomain
- Zendesk service account email
- Zendesk API token

Optional settings:

- Skip Zendesk email verification
- Default Zendesk ticket form ID
- Default Zendesk brand ID
- Default Zendesk group ID
- Dashboard request limit
- Scheduled sync batch size
- Help Center JWT SSO settings

## Scheduled task

This plugin registers a scheduled task to refresh Zendesk ticket statuses and reconcile any pending ticket creates. Make sure Moodle cron is running normally.

## Quick troubleshooting for site administrators

- If students can submit a request in Moodle but nothing appears in Zendesk, re-check the Zendesk subdomain, service account email, and API token.
- If a ticket appears in Zendesk but status changes do not come back into Moodle, confirm cron is running and the scheduled task is enabled.
- If students do not see the dashboard block, verify that the companion block is installed and added to the Dashboard page.
- If the Help Center button appears but SSO does not complete, re-check the JWT shared secret and the Zendesk remote login/logout configuration.
- If the Zendesk sidebar app cannot load Moodle context, verify the Moodle web-service token, the authorised user, and the external service permissions.

## Identity model and lifecycle

The plugin links a Moodle user to a Zendesk end user with a stable external ID of the form `mdl:<instance_uuid>:user:<moodleuserid>`, where `<instance_uuid>` is generated once at install time (`db/install.php`) and never regenerated. The mapping itself lives in `local_zendesk_usermap`.

Lifecycle behaviour:

- **New Moodle user**: no Zendesk record is created until the user submits a request. On first submission the plugin calls `users/create_or_update`, which keys on the external ID first and the email second.
- **Email change in Moodle**: the next interaction sends the new email to Zendesk under the same external ID. Zendesk updates the existing user record in place.
- **Moodle user deleted**: the plugin's `\core\event\user_deleted` observer queues a `suspend_zendesk_user` adhoc task. When cron runs the task it sets `suspended: true` on the matching Zendesk user, then removes the local mapping row. This prevents a future Moodle user with the same email from accidentally inheriting the deleted user's Zendesk record via the email-based upsert.
- **Privacy delete (GDPR)**: the privacy provider removes the local mapping and ticket-attachment manifest. Zendesk-side records are not deleted by this path; if you need them removed or anonymised, do so via Zendesk's user-deletion endpoint or the Zendesk admin UI.

If you need to manually anonymise or delete a Zendesk record for an end user (for example after a GDPR request), do so through Zendesk's standard tooling rather than the Moodle admin UI; the plugin does not currently expose a manual Zendesk-side delete.

## Operational runbook

### Rotating the Zendesk API token

The Zendesk API token is stored in plain text in `mdl_config_plugins` (the standard Moodle pattern for `admin_setting_configpasswordunmask`). Rotate the token on a schedule (90–180 days is a reasonable default) and after any suspected compromise.

1. In Zendesk, go to `Admin Center > Apps and integrations > Zendesk API > API token`.
2. Generate a new token for the Moodle service account.
3. In Moodle, open `Site administration > Plugins > Local plugins > Zendesk support integration`.
4. Paste the new token into `Zendesk API token` and save.
5. Verify the integration: submit a test ticket as a non-admin student, or run `Site administration > Server > Scheduled tasks > Sync Zendesk ticket statuses` once.
6. In Zendesk, revoke the old token.

### Rotating the JWT shared secret

The JWT shared secret is also stored in plain text in `mdl_config_plugins`. Rotate alongside the API token.

1. In Zendesk, go to the JWT SSO configuration for end users.
2. Generate a new shared secret.
3. In Moodle, paste the new secret into `Zendesk JWT shared secret` and save.
4. Test SSO with a non-admin student account before retiring the old secret.
5. In Zendesk, retire the old secret.

### Backup encryption expectation

Because plugin secrets live in plain text in `mdl_config_plugins`, anyone with read access to the Moodle database or its backups can recover them. The hosting provider should:

- Encrypt database backups at rest.
- Restrict backup-read permissions to the operators who genuinely need them.
- Audit the list of admins with `tool_dbaccess`-style direct-DB access.

### Disabling / decommissioning the integration

To stop the integration cleanly:

1. Untick `Enable Zendesk integration` in plugin settings. This is now a real kill switch (the agent-context external function and the scheduled task both check it).
2. If you used the Zendesk sidebar app, revoke the Moodle web-service token at `Site administration > Server > Web services > Manage tokens` so the Zendesk side cannot keep calling Moodle.
3. If you want to remove the data, run `Site administration > Plugins > Local plugins > Zendesk support integration > Uninstall` after confirming the privacy provider's cascade is acceptable for your tenancy.

## Privacy

This plugin stores Moodle-to-Zendesk user mappings and Moodle-managed Zendesk ticket metadata in Moodle. It also sends user and ticket data to Zendesk so support requests can be created and tracked. A Privacy API provider is included.

## External services and credentials

This plugin integrates with a third-party service and requires Zendesk credentials. Site administrators must provide their own Zendesk configuration and credentials before the plugin can be used. If you submit this plugin to the Moodle Plugins directory, make sure the plugin description clearly says that Zendesk access is required and provide safe review credentials to the approval team separately.

## Optional companion components

- `block_zendesk_dashboard`: a dashboard block that surfaces recent ticket status inside Moodle
- `Student Lookup`: a separate Zendesk Support app that calls the Moodle web service exposed by this plugin

## Support and issue tracking

- Source: [https://github.com/dta121/moodle-local_zendesk](https://github.com/dta121/moodle-local_zendesk)
- Issues: [https://github.com/dta121/moodle-local_zendesk/issues](https://github.com/dta121/moodle-local_zendesk/issues)
