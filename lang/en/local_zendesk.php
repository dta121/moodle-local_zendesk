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

/**
 * English language strings for the plugin.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
$string['ssoheading'] = 'Zendesk Help Center single sign-on';
$string['ssoheading_desc'] = 'Configure Moodle-issued JWT sign-in for Zendesk Help Center access.';
$string['ssoenabled'] = 'Enable Zendesk Help Center SSO';
$string['ssoenabled_desc'] = 'When enabled, Moodle can sign authenticated users into the Zendesk Help Center with JWT single sign-on.';
$string['jwtsharedsecret'] = 'Zendesk JWT shared secret';
$string['jwtsharedsecret_desc'] = 'Copy the shared secret from the Zendesk JWT SSO configuration. Keep this value secure.';
$string['ssodefaultpath'] = 'Default Zendesk Help Center path';
$string['ssodefaultpath_desc'] = 'Relative Help Center path to use when Moodle opens Zendesk directly. Example: /hc/en-us/requests';
$string['ssobuttonlabel'] = 'Help Center button label';
$string['ssobuttonlabel_desc'] = 'Student-facing label for the Moodle button that opens Zendesk Help Center through SSO.';

$string['zendesk:submitrequest'] = 'Submit Zendesk support requests';
$string['zendesk:viewownrequests'] = 'View own Zendesk support requests';
$string['zendesk:viewallrequests'] = 'View all Zendesk support requests';
$string['zendesk:usehelpcenter'] = 'Open Zendesk Help Center through SSO';

$string['subject'] = 'Subject';
$string['details'] = 'Support request details';
$string['submitrequestbutton'] = 'Submit request';
$string['newrequest'] = 'New request';
$string['openhelpcenter'] = 'Open Help Center';
$string['allrequests'] = 'All requests';
$string['norequests'] = 'No support requests have been submitted yet.';
$string['requestlistheading'] = 'Your support requests';
$string['requestdetailsheading'] = 'Request details';
$string['requestsubmitted'] = 'Your support request has been submitted.';
$string['submissionpendingconfirmation'] = 'Your request is being confirmed with Zendesk. Status updates will appear shortly.';
$string['requestsubmissionfailed'] = 'We could not submit your support request right now. Please try again.';
$string['backtorequests'] = 'Back to requests';
$string['conversationeyebrow'] = 'Zendesk support thread';
$string['messageheading'] = 'Your message';
$string['conversationheading'] = 'Conversation';
$string['norepliesyet'] = 'No follow-up replies have been added yet.';
$string['supportreplyauthor'] = 'Help desk';
$string['supportreplyinitial'] = 'HD';
$string['studentreplyauthor'] = 'You';
$string['studentreplyinitial'] = 'ME';
$string['replyloaderror'] = 'The ticket replies could not be loaded right now.';
$string['attachmentfile'] = 'Attachment';
$string['replymessage'] = 'Reply';
$string['replyplaceholder'] = 'Type your reply here...';
$string['replyrequired'] = 'Please enter a reply before sending it.';
$string['replyactiveheading'] = 'Send a reply';
$string['replyactivehelp'] = 'Your reply will be added to this ticket and sent to the help desk in the current conversation.';
$string['replyactivebutton'] = 'Send reply';
$string['replyreopenheading'] = 'Reply and reopen';
$string['replyreopenhelp'] = 'Your reply will be added to this solved ticket and the ticket will be reopened.';
$string['replyreopenbutton'] = 'Reply and reopen ticket';
$string['replyfollowupheading'] = 'Reply and continue support';
$string['replyfollowuphelp'] = 'Closed Zendesk tickets cannot be reopened. Sending this form will create a new follow-up ticket linked to the closed one.';
$string['replyfollowupbutton'] = 'Create follow-up ticket';
$string['replysent'] = 'Your reply was sent to the help desk.';
$string['ticketreopened'] = 'Your reply was sent and the ticket was reopened.';
$string['followupcreated'] = 'Your reply was sent and a follow-up ticket was created.';
$string['followupcreatedpending'] = 'Your reply was received and a follow-up ticket is being confirmed with Zendesk.';
$string['replysubmissionfailed'] = 'We could not send your reply right now. Please try again.';
$string['replynotallowed'] = 'This ticket cannot be replied to from Moodle in its current state.';
$string['replynotavailable'] = 'This ticket cannot be replied to from Moodle right now.';
$string['createdlabel'] = 'Created';
$string['updatedlabel'] = 'Updated';
$string['requestnumberlabel'] = 'Zendesk ticket';
$string['messagesubtitle'] = 'Your conversation with the help desk stays here in Moodle.';
$string['statuslabel'] = 'Status';
$string['notconfiguredmessage'] = 'Zendesk support has not been configured yet.';
$string['nodataavailable'] = 'No Zendesk request data is available yet.';
$string['helpcenterssounavailable'] = 'Zendesk Help Center sign-in is not available right now.';
$string['ssoredirectheading'] = 'Opening Zendesk Help Center';
$string['ssoredirectbody'] = 'You are being signed into Zendesk with your Moodle account.';
$string['ssocontinuebutton'] = 'Continue to Zendesk';
$string['ssologoutinfo'] = 'You have returned from Zendesk.';
$string['ssologouterror'] = 'Zendesk sign-in returned an error: {$a}';
$string['ssodisabled'] = 'Zendesk Help Center sign-in is currently disabled.';
$string['ssonotconfigured'] = 'Zendesk Help Center sign-in has not been configured yet.';
$string['ssofailed'] = 'Zendesk Help Center sign-in could not be started right now.';
$string['invalidreturnto'] = 'Zendesk tried to return to an invalid destination.';
$string['agentcontextservice'] = 'Zendesk agent context service';
$string['agentcontextmissinginput'] = 'An external ID or email address is required to look up Moodle context.';
$string['agentcontextinvalidemail'] = 'The supplied email address is not valid.';
$string['agentcontextnotfound'] = 'No Moodle user could be matched for the supplied Zendesk requester.';
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

$string['privacy:metadata'] = 'The Zendesk support integration stores local Zendesk ticket metadata in Moodle and exchanges support data with Zendesk.';
$string['privacy:metadata:local_zendesk_usermap'] = 'Stores the relationship between a Moodle user and the matching Zendesk end-user record.';
$string['privacy:metadata:local_zendesk_usermap:userid'] = 'The Moodle user ID that owns the Zendesk mapping.';
$string['privacy:metadata:local_zendesk_usermap:zendesk_user_id'] = 'The Zendesk user ID linked to the Moodle user.';
$string['privacy:metadata:local_zendesk_usermap:zendesk_external_id'] = 'The stable external ID used to match the Moodle user in Zendesk.';
$string['privacy:metadata:local_zendesk_usermap:zendesk_email'] = 'The email address sent to Zendesk for the mapped user.';
$string['privacy:metadata:local_zendesk_usermap:lastsyncedat'] = 'The last time the Moodle-to-Zendesk user mapping was synced.';
$string['privacy:metadata:local_zendesk_usermap:timecreated'] = 'The time the local Zendesk user mapping was created.';
$string['privacy:metadata:local_zendesk_usermap:timemodified'] = 'The time the local Zendesk user mapping was last updated.';
$string['privacy:metadata:local_zendesk_ticket'] = 'Stores Moodle-managed Zendesk ticket records for support requests created from Moodle.';
$string['privacy:metadata:local_zendesk_ticket:userid'] = 'The Moodle user who created the support request.';
$string['privacy:metadata:local_zendesk_ticket:usermapid'] = 'The local Zendesk user mapping used for the support request.';
$string['privacy:metadata:local_zendesk_ticket:courseid'] = 'The course associated with the support request, if any.';
$string['privacy:metadata:local_zendesk_ticket:contextid'] = 'The Moodle context associated with the support request, if any.';
$string['privacy:metadata:local_zendesk_ticket:zendesk_ticket_id'] = 'The Zendesk ticket ID returned by Zendesk.';
$string['privacy:metadata:local_zendesk_ticket:zendesk_ticket_external_id'] = 'The stable Zendesk external ticket ID generated by Moodle.';
$string['privacy:metadata:local_zendesk_ticket:subject'] = 'The support request subject.';
$string['privacy:metadata:local_zendesk_ticket:body'] = 'The support request details entered by the user.';
$string['privacy:metadata:local_zendesk_ticket:status'] = 'The current Zendesk ticket status stored in Moodle.';
$string['privacy:metadata:local_zendesk_ticket:custom_status_id'] = 'The Zendesk custom status ID, when available.';
$string['privacy:metadata:local_zendesk_ticket:syncstate'] = 'The current Moodle sync state for the Zendesk ticket.';
$string['privacy:metadata:local_zendesk_ticket:lastremoteupdatedat'] = 'The last known Zendesk update time for the ticket.';
$string['privacy:metadata:local_zendesk_ticket:lastsyncattemptat'] = 'The last time Moodle attempted to sync the ticket.';
$string['privacy:metadata:local_zendesk_ticket:lastsyncat'] = 'The last successful ticket sync time.';
$string['privacy:metadata:local_zendesk_ticket:submissionerror'] = 'The most recent submission or sync error stored for the request.';
$string['privacy:metadata:local_zendesk_ticket:timecreated'] = 'The time the local support request record was created.';
$string['privacy:metadata:local_zendesk_ticket:timemodified'] = 'The time the local support request record was last updated.';
$string['privacy:metadata:zendesk_support_api'] = 'In order to create and manage support requests, user and ticket data is sent from Moodle to Zendesk.';
$string['privacy:metadata:zendesk_support_api:fullname'] = 'The user full name sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:email'] = 'The user email address sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:externalid'] = 'The Moodle-generated external ID sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:subject'] = 'The support request subject sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:message'] = 'The support request message body sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:status'] = 'Ticket status information retrieved from Zendesk and processed by the plugin.';
$string['privacy:metadata:zendesk_support_api:ticketid'] = 'The Zendesk ticket identifier associated with the request.';
$string['privacy:metadata:zendesk_agent_context'] = 'When the optional Student Lookup Zendesk app is used, Moodle exposes a limited read-only student context payload to Zendesk.';
$string['privacy:metadata:zendesk_agent_context:userid'] = 'The Moodle user ID returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:fullname'] = 'The Moodle user full name returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:email'] = 'The Moodle user email returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:profileurl'] = 'The Moodle profile URL returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:auth'] = 'The Moodle authentication method returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:lastaccess'] = 'The user last access time returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:courses'] = 'The list of recent or current Moodle courses returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:ipaddress'] = 'The user last known IP address returned to the Zendesk agent app when permitted.';
$string['privacy:metadata:zendesk_agent_context:location'] = 'The approximate location derived from the last known IP address when permitted.';

$string['pluginnotconfigured'] = 'Zendesk integration is not configured. Ask a site administrator to add the Zendesk connection settings.';
$string['zendeskdisabled'] = 'Zendesk integration is currently disabled.';
$string['missingemail'] = 'Your Moodle account needs a valid email address before a Zendesk request can be created.';
$string['invalidapiresponse'] = 'Zendesk returned an unexpected response.';
$string['invalidrequestpayload'] = 'The support request payload was incomplete.';
$string['apifailure'] = 'Zendesk API failure: {$a}';
$string['unexpectedapistatus'] = 'Zendesk returned HTTP status {$a}.';
$string['attachmentdownloadfailed'] = 'Zendesk attachment download failed: {$a}';
$string['invalidattachmenturl'] = 'The requested Zendesk attachment URL is not valid for this ticket.';
$string['retryafterseconds'] = 'Retry after {$a} seconds.';
$string['duplicateticketexternalid'] = 'Multiple Zendesk tickets were found for the same external ID.';
$string['invalidticketid'] = 'The requested support ticket could not be found.';
$string['errorsubjecttoolong'] = 'The subject must be 255 characters or fewer.';
$string['errordetailsrequired'] = 'Please enter some details for your support request.';
