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

## Installation

1. Copy this plugin into `local/zendesk`.
2. Visit `Site administration > Notifications` to complete installation.
3. Configure the plugin at `Site administration > Plugins > Local plugins > Zendesk support integration`.

## Configuration

Minimum configuration:

- Enable the plugin
- Zendesk subdomain
- Zendesk service account email
- Zendesk API token

Optional configuration:

- Skip Zendesk email verification
- Default Zendesk ticket form ID
- Default Zendesk brand ID
- Default Zendesk group ID
- Dashboard request limit
- Scheduled sync batch size
- Help Center JWT SSO settings

## Scheduled task

This plugin registers a scheduled task to refresh Zendesk ticket statuses and reconcile any pending ticket creates. Make sure Moodle cron is running normally.

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

