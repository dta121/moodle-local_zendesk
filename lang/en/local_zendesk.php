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


$string['agentcontextinvalidemail'] = 'The supplied email address is not valid.';
$string['agentcontextmissinginput'] = 'A Zendesk requester ID is required to look up Moodle context.';
$string['agentcontextnotfound'] = 'No Moodle user could be matched for the supplied Zendesk requester.';
$string['agentcontextservice'] = 'Zendesk agent context service';
$string['allrequests'] = 'All requests';
$string['apifailure'] = 'Zendesk API failure: {$a}';
$string['apitoken'] = 'Zendesk API token';
$string['apitoken_desc'] = 'Store the Zendesk API token here. It is never exposed to the browser.';
$string['attachmentdownloadfailed'] = 'Zendesk attachment download failed: {$a}';
$string['attachmentfile'] = 'Attachment';
$string['attachmenttoolarge'] = 'The Zendesk attachment is too large to be downloaded through Moodle.';
$string['backtorequests'] = 'Back to requests';
$string['brandid'] = 'Default brand ID';
$string['brandid_desc'] = 'Optional Zendesk brand ID for new tickets.';
$string['conversationeyebrow'] = 'Zendesk support thread';
$string['conversationheading'] = 'Conversation';
$string['couldnotconfirmticket'] = 'Zendesk ticket creation could not be confirmed automatically.';
$string['createdlabel'] = 'Created';
$string['dashboardlimit'] = 'Dashboard request limit';
$string['dashboardlimit_desc'] = 'How many recent requests to show in the dashboard block.';
$string['details'] = 'Support request details';
$string['duplicateticketexternalid'] = 'Multiple Zendesk tickets were found for the same external ID.';
$string['enabled'] = 'Enable Zendesk integration';
$string['enabled_desc'] = 'When enabled, Moodle can create and track Zendesk tickets for logged-in users.';
$string['errordetailsrequired'] = 'Please enter some details for your support request.';
$string['errorsubjecttoolong'] = 'The subject must be 255 characters or fewer.';
$string['followupcreated'] = 'Your reply was sent and a follow-up ticket was created.';
$string['followupcreatedpending'] = 'Your reply was received and a follow-up ticket is being confirmed with Zendesk.';
$string['groupid'] = 'Default group ID';
$string['groupid_desc'] = 'Optional Zendesk group ID for new tickets.';
$string['helpcenterssounavailable'] = 'Zendesk Help Center sign-in is not available right now.';
$string['invalidapiresponse'] = 'Zendesk returned an unexpected response.';
$string['invalidattachmenturl'] = 'The requested Zendesk attachment URL is not valid for this ticket.';
$string['invalidrequestpayload'] = 'The support request payload was incomplete.';
$string['invalidreturnto'] = 'Zendesk tried to return to an invalid destination.';
$string['invalidticketid'] = 'The requested support ticket could not be found.';
$string['jwtsharedsecret'] = 'Zendesk JWT shared secret';
$string['jwtsharedsecret_desc'] = 'Copy the shared secret from the Zendesk JWT SSO configuration. Keep this value secure.';
$string['messageheading'] = 'Your message';
$string['messagesubtitle'] = 'Your conversation with the help desk stays here in Moodle.';
$string['missingemail'] = 'Your Moodle account needs a valid email address before a Zendesk request can be created.';
$string['newrequest'] = 'New request';
$string['nodataavailable'] = 'No Zendesk request data is available yet.';
$string['norepliesyet'] = 'No follow-up replies have been added yet.';
$string['norequests'] = 'No support requests have been submitted yet.';
$string['notconfiguredmessage'] = 'Zendesk support has not been configured yet.';
$string['openhelpcenter'] = 'Open Help Center';
$string['pluginname'] = 'Zendesk support integration';
$string['pluginnotconfigured'] = 'Zendesk integration is not configured. Ask a site administrator to add the Zendesk connection settings.';
$string['privacy:metadata'] = 'The Zendesk support integration stores local Zendesk ticket metadata in Moodle and exchanges support data with Zendesk.';
$string['privacy:metadata:local_zendesk_ticket'] = 'Stores Moodle-managed Zendesk ticket records for support requests created from Moodle.';
$string['privacy:metadata:local_zendesk_ticket:body'] = 'The support request details entered by the user.';
$string['privacy:metadata:local_zendesk_ticket:contextid'] = 'The Moodle context associated with the support request, if any.';
$string['privacy:metadata:local_zendesk_ticket:courseid'] = 'The course associated with the support request, if any.';
$string['privacy:metadata:local_zendesk_ticket:custom_status_id'] = 'The Zendesk custom status ID, when available.';
$string['privacy:metadata:local_zendesk_ticket:lastremoteupdatedat'] = 'The last known Zendesk update time for the ticket.';
$string['privacy:metadata:local_zendesk_ticket:lastsyncat'] = 'The last successful ticket sync time.';
$string['privacy:metadata:local_zendesk_ticket:lastsyncattemptat'] = 'The last time Moodle attempted to sync the ticket.';
$string['privacy:metadata:local_zendesk_ticket:status'] = 'The current Zendesk ticket status stored in Moodle.';
$string['privacy:metadata:local_zendesk_ticket:subject'] = 'The support request subject.';
$string['privacy:metadata:local_zendesk_ticket:submissionerror'] = 'The most recent submission or sync error stored for the request.';
$string['privacy:metadata:local_zendesk_ticket:syncstate'] = 'The current Moodle sync state for the Zendesk ticket.';
$string['privacy:metadata:local_zendesk_ticket:timecreated'] = 'The time the local support request record was created.';
$string['privacy:metadata:local_zendesk_ticket:timemodified'] = 'The time the local support request record was last updated.';
$string['privacy:metadata:local_zendesk_ticket:userid'] = 'The Moodle user who created the support request.';
$string['privacy:metadata:local_zendesk_ticket:usermapid'] = 'The local Zendesk user mapping used for the support request.';
$string['privacy:metadata:local_zendesk_ticket:zendesk_ticket_external_id'] = 'The stable Zendesk external ticket ID generated by Moodle.';
$string['privacy:metadata:local_zendesk_ticket:zendesk_ticket_id'] = 'The Zendesk ticket ID returned by Zendesk.';
$string['privacy:metadata:local_zendesk_ticket_attachment'] = 'Stores the per-ticket allow-list of Zendesk-hosted attachment URLs Moodle is permitted to proxy on behalf of the ticket owner.';
$string['privacy:metadata:local_zendesk_ticket_attachment:contenttype'] = 'The MIME type Zendesk reported for the attachment.';
$string['privacy:metadata:local_zendesk_ticket_attachment:filename'] = 'The original filename Zendesk reported for the attachment.';
$string['privacy:metadata:local_zendesk_ticket_attachment:filesize'] = 'The byte size Zendesk reported for the attachment.';
$string['privacy:metadata:local_zendesk_ticket_attachment:localticketid'] = 'The local Zendesk ticket the attachment belongs to.';
$string['privacy:metadata:local_zendesk_ticket_attachment:remoteurl'] = 'The Zendesk-hosted attachment URL that Moodle is permitted to proxy.';
$string['privacy:metadata:local_zendesk_ticket_attachment:timecreated'] = 'The time the manifest entry was first created.';
$string['privacy:metadata:local_zendesk_ticket_attachment:urlhash'] = 'A hash of the attachment URL used to look the manifest entry up at download time.';
$string['privacy:metadata:local_zendesk_usermap'] = 'Stores the relationship between a Moodle user and the matching Zendesk end-user record.';
$string['privacy:metadata:local_zendesk_usermap:lastsyncedat'] = 'The last time the Moodle-to-Zendesk user mapping was synced.';
$string['privacy:metadata:local_zendesk_usermap:timecreated'] = 'The time the local Zendesk user mapping was created.';
$string['privacy:metadata:local_zendesk_usermap:timemodified'] = 'The time the local Zendesk user mapping was last updated.';
$string['privacy:metadata:local_zendesk_usermap:userid'] = 'The Moodle user ID that owns the Zendesk mapping.';
$string['privacy:metadata:local_zendesk_usermap:zendesk_email'] = 'The email address sent to Zendesk for the mapped user.';
$string['privacy:metadata:local_zendesk_usermap:zendesk_external_id'] = 'The stable external ID used to match the Moodle user in Zendesk.';
$string['privacy:metadata:local_zendesk_usermap:zendesk_user_id'] = 'The Zendesk user ID linked to the Moodle user.';
$string['privacy:metadata:zendesk_agent_context'] = 'When the optional Student Lookup Zendesk app is used, Moodle exposes a limited read-only student context payload to Zendesk.';
$string['privacy:metadata:zendesk_agent_context:auth'] = 'The Moodle authentication method returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:courses'] = 'The list of recent or current Moodle courses returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:email'] = 'The Moodle user email returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:fullname'] = 'The Moodle user full name returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_agent_context:ipaddress'] = 'The user last known IP address returned to the Zendesk agent app when permitted.';
$string['privacy:metadata:zendesk_agent_context:location'] = 'The approximate location derived from the last known IP address when permitted.';
$string['privacy:metadata:zendesk_agent_context:profileurl'] = 'The Moodle profile URL returned to the Zendesk agent app.';
$string['privacy:metadata:zendesk_support_api'] = 'In order to create and manage support requests, user and ticket data is sent from Moodle to Zendesk.';
$string['privacy:metadata:zendesk_support_api:email'] = 'The user email address sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:externalid'] = 'The Moodle-generated external ID sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:fullname'] = 'The user full name sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:message'] = 'The support request message body sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:status'] = 'Ticket status information retrieved from Zendesk and processed by the plugin.';
$string['privacy:metadata:zendesk_support_api:subject'] = 'The support request subject sent to Zendesk.';
$string['privacy:metadata:zendesk_support_api:ticketid'] = 'The Zendesk ticket identifier associated with the request.';
$string['replyactivebutton'] = 'Send reply';
$string['replyactiveheading'] = 'Send a reply';
$string['replyactivehelp'] = 'Your reply will be added to this ticket and sent to the help desk in the current conversation.';
$string['replyfollowupbutton'] = 'Create follow-up ticket';
$string['replyfollowupheading'] = 'Reply and continue support';
$string['replyfollowuphelp'] = 'Closed Zendesk tickets cannot be reopened. Sending this form will create a new follow-up ticket linked to the closed one.';
$string['replyloaderror'] = 'The ticket replies could not be loaded right now.';
$string['replymessage'] = 'Reply';
$string['replynotallowed'] = 'This ticket cannot be replied to from Moodle in its current state.';
$string['replynotavailable'] = 'This ticket cannot be replied to from Moodle right now.';
$string['replyplaceholder'] = 'Type your reply here...';
$string['replyreopenbutton'] = 'Reply and reopen ticket';
$string['replyreopenheading'] = 'Reply and reopen';
$string['replyreopenhelp'] = 'Your reply will be added to this solved ticket and the ticket will be reopened.';
$string['replyrequired'] = 'Please enter a reply before sending it.';
$string['replysent'] = 'Your reply was sent to the help desk.';
$string['replysubmissionfailed'] = 'We could not send your reply right now. Please try again.';
$string['requestdetailsheading'] = 'Request details';
$string['requestlistheading'] = 'Your support requests';
$string['requestnumberlabel'] = 'Zendesk ticket';
$string['requestsubmissionfailed'] = 'We could not submit your support request right now. Please try again.';
$string['requestsubmitted'] = 'Your support request has been submitted.';
$string['retryafterseconds'] = 'Retry after {$a} seconds.';
$string['serviceemail'] = 'Zendesk service account email';
$string['serviceemail_desc'] = 'This Zendesk agent email is used for server-side API calls.';
$string['skipverifyemail'] = 'Skip Zendesk email verification';
$string['skipverifyemail_desc'] = 'Recommended for the Moodle-only MVP because students are not expected to log in directly to Zendesk.';
$string['ssobuttonlabel'] = 'Help Center button label';
$string['ssobuttonlabel_desc'] = 'Student-facing label for the Moodle button that opens Zendesk Help Center through SSO.';
$string['ssocontinuebutton'] = 'Continue to Zendesk';
$string['ssodefaultpath'] = 'Default Zendesk Help Center path';
$string['ssodefaultpath_desc'] = 'Relative Help Center path to use when Moodle opens Zendesk directly. Example: /hc/en-us/requests';
$string['ssodisabled'] = 'Zendesk Help Center sign-in is currently disabled.';
$string['ssoenabled'] = 'Enable Zendesk Help Center SSO';
$string['ssoenabled_desc'] = 'When enabled, Moodle can sign authenticated users into the Zendesk Help Center with JWT single sign-on.';
$string['ssofailed'] = 'Zendesk Help Center sign-in could not be started right now.';
$string['ssoheading'] = 'Zendesk Help Center single sign-on';
$string['ssoheading_desc'] = 'Configure Moodle-issued JWT sign-in for Zendesk Help Center access.';
$string['ssologouterror'] = 'Zendesk sign-in returned an error: {$a}';
$string['ssologoutinfo'] = 'You have returned from Zendesk.';
$string['ssonotconfigured'] = 'Zendesk Help Center sign-in has not been configured yet.';
$string['ssoredirectbody'] = 'You are being signed into Zendesk with your Moodle account.';
$string['ssoredirectheading'] = 'Opening Zendesk Help Center';
$string['status_closed'] = 'Closed';
$string['status_hold'] = 'On hold';
$string['status_new'] = 'New';
$string['status_open'] = 'Open';
$string['status_pending'] = 'Pending';
$string['status_solved'] = 'Solved';
$string['statusconfirming'] = 'Confirming';
$string['statuserror'] = 'Needs attention';
$string['statuslabel'] = 'Status';
$string['statussubmitted'] = 'Submitted';
$string['studentreplyauthor'] = 'You';
$string['studentreplyinitial'] = 'ME';
$string['subdomain'] = 'Zendesk subdomain';
$string['subdomain_desc'] = 'Enter the Zendesk subdomain only, for example "myorganisation" for myorganisation.zendesk.com.';
$string['subdomaininvalid'] = 'The Zendesk subdomain must be a clean DNS label (lowercase letters, digits, and hyphens only). You can paste a full https://myorg.zendesk.com URL and it will be normalised.';
$string['subject'] = 'Subject';
$string['submissionerrorgeneric'] = 'We had trouble syncing this request with the help desk. A site administrator can review the details.';
$string['submissionpendingconfirmation'] = 'Your request is being confirmed with Zendesk. Status updates will appear shortly.';
$string['submitrequestbutton'] = 'Submit request';
$string['supportreplyauthor'] = 'Help desk';
$string['supportreplyinitial'] = 'HD';
$string['syncbatchsize'] = 'Sync batch size';
$string['syncbatchsize_desc'] = 'Maximum number of tickets to refresh during each scheduled task run.';
$string['tasksuspendzendeskuser'] = 'Suspend Zendesk end user after Moodle user deletion';
$string['tasksynctickets'] = 'Sync Zendesk ticket statuses';
$string['ticketformid'] = 'Default ticket form ID';
$string['ticketformid_desc'] = 'Optional Zendesk ticket form ID to apply to new tickets.';
$string['ticketreopened'] = 'Your reply was sent and the ticket was reopened.';
$string['unexpectedapistatus'] = 'Zendesk returned HTTP status {$a}.';
$string['updatedlabel'] = 'Updated';
$string['zendesk:submitrequest'] = 'Submit Zendesk support requests';
$string['zendesk:usehelpcenter'] = 'Open Zendesk Help Center through SSO';
$string['zendesk:viewallrequests'] = 'View all Zendesk support requests';
$string['zendesk:viewownrequests'] = 'View own Zendesk support requests';
$string['zendeskdisabled'] = 'Zendesk integration is currently disabled.';
