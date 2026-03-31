<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

$string['pluginname'] = 'Zendesk support integration';
$string['enabled'] = 'Enable Zendesk integration';
$string['enabled_desc'] = 'When enabled, Moodle can create and track Zendesk tickets for logged-in users.';
$string['subdomain'] = 'Zendesk subdomain';
$string['subdomain_desc'] = 'Enter the Zendesk subdomain only, for example "myorganisation" for myorganisation.zendesk.com.';
$string['serviceemail'] = 'Zendesk service account email';
$string['serviceemail_desc'] = 'This Zendesk agent email is used for server-side API calls.';
$string['apitoken'] = 'Zendesk API token';
$string['apitoken_desc'] = 'Store the Zendesk API token here. It is never exposed to the browser.';
$string['skipverifyemail'] = 'Skip Zendesk email verification';
$string['skipverifyemail_desc'] = 'Recommended for the Moodle-only MVP because students are not expected to log in directly to Zendesk.';
$string['ticketformid'] = 'Default ticket form ID';
$string['ticketformid_desc'] = 'Optional Zendesk ticket form ID to apply to new tickets.';
$string['brandid'] = 'Default brand ID';
$string['brandid_desc'] = 'Optional Zendesk brand ID for new tickets.';
$string['groupid'] = 'Default group ID';
$string['groupid_desc'] = 'Optional Zendesk group ID for new tickets.';
$string['dashboardlimit'] = 'Dashboard request limit';
$string['dashboardlimit_desc'] = 'How many recent requests to show in the dashboard block.';
$string['syncbatchsize'] = 'Sync batch size';
$string['syncbatchsize_desc'] = 'Maximum number of tickets to refresh during each scheduled task run.';

$string['zendesk:submitrequest'] = 'Submit Zendesk support requests';
$string['zendesk:viewownrequests'] = 'View own Zendesk support requests';
$string['zendesk:viewallrequests'] = 'View all Zendesk support requests';

$string['subject'] = 'Subject';
$string['details'] = 'Support request details';
$string['submitrequestbutton'] = 'Submit request';
$string['newrequest'] = 'New request';
$string['allrequests'] = 'All requests';
$string['norequests'] = 'No support requests have been submitted yet.';
$string['requestlistheading'] = 'Your support requests';
$string['requestdetailsheading'] = 'Request details';
$string['requestsubmitted'] = 'Your support request has been submitted.';
$string['submissionpendingconfirmation'] = 'Your request is being confirmed with Zendesk. Status updates will appear shortly.';
$string['requestsubmissionfailed'] = 'We could not submit your support request right now. Please try again.';
$string['backtorequests'] = 'Back to requests';
$string['messageheading'] = 'Your message';
$string['repliesheading'] = 'Help desk replies';
$string['norepliesyet'] = 'No public replies have been added yet.';
$string['supportreplyauthor'] = 'Help desk';
$string['replyloaderror'] = 'The ticket replies could not be loaded right now.';
$string['createdlabel'] = 'Created';
$string['updatedlabel'] = 'Updated';
$string['requestnumberlabel'] = 'Zendesk ticket';
$string['statuslabel'] = 'Status';
$string['notconfiguredmessage'] = 'Zendesk support has not been configured yet.';
$string['nodataavailable'] = 'No Zendesk request data is available yet.';
$string['couldnotconfirmticket'] = 'Zendesk ticket creation could not be confirmed automatically.';

$string['statussubmitted'] = 'Submitted';
$string['statusconfirming'] = 'Confirming';
$string['statuserror'] = 'Needs attention';
$string['status_new'] = 'New';
$string['status_open'] = 'Open';
$string['status_pending'] = 'Pending';
$string['status_hold'] = 'On hold';
$string['status_solved'] = 'Solved';
$string['status_closed'] = 'Closed';

$string['tasksynctickets'] = 'Sync Zendesk ticket statuses';

$string['pluginnotconfigured'] = 'Zendesk integration is not configured. Ask a site administrator to add the Zendesk connection settings.';
$string['zendeskdisabled'] = 'Zendesk integration is currently disabled.';
$string['missingemail'] = 'Your Moodle account needs a valid email address before a Zendesk request can be created.';
$string['invalidapiresponse'] = 'Zendesk returned an unexpected response.';
$string['invalidrequestpayload'] = 'The support request payload was incomplete.';
$string['apifailure'] = 'Zendesk API failure: {$a}';
$string['unexpectedapistatus'] = 'Zendesk returned HTTP status {$a}.';
$string['retryafterseconds'] = 'Retry after {$a} seconds.';
$string['duplicateticketexternalid'] = 'Multiple Zendesk tickets were found for the same external ID.';
$string['invalidticketid'] = 'The requested support ticket could not be found.';
$string['errorsubjecttoolong'] = 'The subject must be 255 characters or fewer.';
$string['errordetailsrequired'] = 'Please enter some details for your support request.';
