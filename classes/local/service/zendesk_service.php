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
 * Service layer for Zendesk API integration.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\local\service;

use local_zendesk\local\constants;
use local_zendesk\local\repository\ticket_repository;

/**
 * High-level Zendesk integration service.
 *
 * @package   local_zendesk
 */
final class zendesk_service {
    /**
     * Content types the attachment proxy is allowed to serve inline. Anything
     * else (text/html, image/svg+xml, application/javascript, XML, ...) gets
     * remapped to application/octet-stream so the response cannot execute on
     * the Moodle origin.
     *
     * @var string[]
     */
    private const SAFE_INLINE_CONTENT_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    /**
     * Hard cap for proxied attachment bodies. The progress callback aborts a
     * cURL transfer that exceeds this in-flight; CURLOPT_MAXFILESIZE handles
     * the case where the upstream advertises Content-Length up front.
     */
    private const MAX_ATTACHMENT_BYTES = 104857600;

    /** @var ticket_repository */
    private $repository;

    /**
     * Constructor.
     *
     * @param ticket_repository|null $repository Optional repository override.
     */
    public function __construct(?ticket_repository $repository = null) {
        $this->repository = $repository ?? new ticket_repository();
    }

    /**
     * Check whether the integration is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return !empty(get_config(constants::COMPONENT, 'enabled'));
    }

    /**
     * Check whether the integration has enough config to call Zendesk.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $config = $this->get_config();
        return !empty($config->subdomain) && !empty($config->serviceemail) && !empty($config->apitoken);
    }

    /**
     * Get the dashboard item limit.
     *
     * @return int
     */
    public function get_dashboard_limit(): int {
        $config = $this->get_config();
        $limit = (int) ($config->dashboardlimit ?? 5);
        return max(1, $limit);
    }

    /**
     * Get the sync batch size.
     *
     * @return int
     */
    public function get_sync_batch_size(): int {
        $config = $this->get_config();
        $limit = (int) ($config->syncbatchsize ?? 100);
        return max(1, min(100, $limit));
    }

    /**
     * Ensure the current Moodle user exists in Zendesk.
     *
     * @param \stdClass $user Moodle user record.
     * @return \stdClass
     */
    public function ensure_remote_user(\stdClass $user): \stdClass {
        $this->assert_ready();

        if (empty($user->email)) {
            throw new \moodle_exception('missingemail', constants::COMPONENT);
        }

        $externalid = $this->build_user_external_id((int) $user->id);
        $config = $this->get_config();
        $payload = [
            'user' => [
                'name' => fullname($user),
                'email' => $user->email,
                'external_id' => $externalid,
                'role' => 'end-user',
                'skip_verify_email' => !empty($config->skipverifyemail),
            ],
        ];

        $response = $this->request('POST', '/users/create_or_update.json', $payload);
        if (empty($response['body']['user']['id'])) {
            throw new \moodle_exception('invalidapiresponse', constants::COMPONENT);
        }

        return $this->repository->upsert_user_map(
            (int) $user->id,
            (int) $response['body']['user']['id'],
            $externalid,
            $user->email
        );
    }

    /**
     * Suspend a remote Zendesk end user.
     *
     * Used by the user_deleted observer to prevent a future Moodle user with
     * the same email from inheriting the deleted user's Zendesk record via
     * users/create_or_update's email-based upsert (security review F13).
     *
     * @param int $zendeskuserid Zendesk-side user id.
     * @return void
     */
    public function suspend_remote_user(int $zendeskuserid): void {
        $this->assert_ready();

        if ($zendeskuserid <= 0) {
            throw new \invalid_parameter_exception('Zendesk user id must be positive.');
        }

        $this->request('PUT', '/users/' . $zendeskuserid . '.json', [
            'user' => ['suspended' => true],
        ]);
    }

    /**
     * Submit a new ticket to Zendesk.
     *
     * @param int $userid Moodle user id.
     * @param array $payload Form payload.
     * @return \stdClass
     */
    public function submit_request(int $userid, array $payload): \stdClass {
        global $DB;

        $this->assert_ready();

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if (isguestuser($user)) {
            throw new \moodle_exception('noguest', 'moodle');
        }

        $subject = trim((string) ($payload['subject'] ?? ''));
        $details = trim((string) ($payload['details'] ?? ''));
        if ($subject === '' || $details === '') {
            throw new \moodle_exception('invalidrequestpayload', constants::COMPONENT);
        }

        $usermap = $this->ensure_remote_user($user);
        $uuid = $this->generate_uuid();
        $ticketexternalid = $this->build_ticket_external_id($uuid);
        $courseid = !empty($payload['courseid']) ? (int) $payload['courseid'] : null;
        $contextid = !empty($payload['contextid']) ? (int) $payload['contextid'] : null;

        $localticket = $this->repository->create_local_ticket(
            $userid,
            (int) $usermap->id,
            $courseid,
            $contextid,
            $uuid,
            $ticketexternalid,
            $subject,
            $details
        );

        try {
            $response = $this->request('POST', '/tickets.json', [
                'ticket' => $this->build_ticket_payload($user, $payload, $ticketexternalid),
            ]);
            $remoteticket = $this->extract_remote_ticket($response['body']);

            return $this->repository->mark_ticket_created((int) $localticket->id, $remoteticket);
        } catch (\RuntimeException $e) {
            return $this->repository->mark_ticket_confirming((int) $localticket->id, $e->getMessage());
        } catch (\Throwable $e) {
            $this->repository->mark_ticket_error((int) $localticket->id, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync a batch of tickets from Zendesk.
     *
     * @param int $batchsize Number of tickets to process.
     * @return int
     */
    public function sync_batch(int $batchsize = 100): int {
        $this->assert_ready();

        $processed = 0;
        $batchsize = max(1, min(100, $batchsize));

        $confirming = $this->repository->get_confirming_tickets(min(25, $batchsize));
        foreach ($confirming as $ticket) {
            $this->repository->mark_sync_attempt((int) $ticket->id);
            $remoteticket = $this->find_ticket_by_external_id($ticket->zendesk_ticket_external_id);

            if ($remoteticket) {
                $this->repository->attach_remote_ticket((int) $ticket->id, $remoteticket);
                $processed++;
                continue;
            }

            if (!empty($ticket->timecreated) && $ticket->timecreated < (time() - 900)) {
                $this->repository->mark_ticket_error(
                    (int) $ticket->id,
                    get_string('couldnotconfirmticket', constants::COMPONENT)
                );
            }
        }

        $remaining = max(0, $batchsize - $processed);
        if ($remaining === 0) {
            return $processed;
        }

        $active = $this->repository->get_active_tickets_for_sync($remaining);
        if (empty($active)) {
            return $processed;
        }

        $ids = [];
        foreach ($active as $ticket) {
            if (empty($ticket->zendesk_ticket_id)) {
                continue;
            }

            $this->repository->mark_sync_attempt((int) $ticket->id);
            $ids[] = (int) $ticket->zendesk_ticket_id;
        }

        if (empty($ids)) {
            return $processed;
        }

        $remotetickets = $this->get_many_tickets($ids);
        $processed += $this->repository->apply_remote_snapshots($remotetickets);

        return $processed;
    }

    /**
     * Get user requests formatted for display.
     *
     * @param int $userid Moodle user id.
     * @param int $limit Max ticket count.
     * @param bool $activeonly Whether to return only active tickets.
     * @return array
     */
    public function get_user_requests(int $userid, int $limit = 5, bool $activeonly = false): array {
        $records = $this->repository->get_user_tickets($userid, $limit, $activeonly);
        $tickets = [];

        foreach ($records as $record) {
            $tickets[] = $this->format_ticket_for_output($record);
        }

        return $tickets;
    }

    /**
     * Get a specific request formatted for output.
     *
     * @param int $ticketid Local ticket id.
     * @param int $userid Current user id.
     * @param bool $canviewall Whether current user can see any ticket.
     * @return array
     */
    public function get_request_for_user(int $ticketid, int $userid, bool $canviewall = false): array {
        $record = $this->repository->get_ticket($ticketid);
        $this->assert_ticket_access($record, $userid, $canviewall);

        $ticket = $this->format_ticket_for_output($record, true, $canviewall);
        $ticket['messageheading'] = get_string('messageheading', constants::COMPONENT);
        $ticket['repliesheading'] = get_string('conversationheading', constants::COMPONENT);
        $ticket['norepliesyet'] = get_string('norepliesyet', constants::COMPONENT);
        $ticket['publicreplies'] = [];
        $ticket['haspublicreplies'] = false;
        $ticket['hasreplyloaderror'] = false;
        $ticket['hasreplyform'] = false;
        $ticket['replyheading'] = '';
        $ticket['replyhelptext'] = '';
        $ticket['replyactionlabel'] = '';

        if (empty($record->zendesk_ticket_id) || !$this->is_enabled() || !$this->is_configured()) {
            return $ticket;
        }

        $replyaction = $this->get_reply_action_for_ticket($record);
        if ($replyaction !== 'none') {
            $ticket['hasreplyform'] = true;
            if ($replyaction === 'followup') {
                $ticket['replyheading'] = get_string('replyfollowupheading', constants::COMPONENT);
                $ticket['replyhelptext'] = get_string('replyfollowuphelp', constants::COMPONENT);
                $ticket['replyactionlabel'] = get_string('replyfollowupbutton', constants::COMPONENT);
            } else if ($replyaction === 'reopen') {
                $ticket['replyheading'] = get_string('replyreopenheading', constants::COMPONENT);
                $ticket['replyhelptext'] = get_string('replyreopenhelp', constants::COMPONENT);
                $ticket['replyactionlabel'] = get_string('replyreopenbutton', constants::COMPONENT);
            } else {
                $ticket['replyheading'] = get_string('replyactiveheading', constants::COMPONENT);
                $ticket['replyhelptext'] = get_string('replyactivehelp', constants::COMPONENT);
                $ticket['replyactionlabel'] = get_string('replyactivebutton', constants::COMPONENT);
            }
        }

        try {
            $usermap = $this->repository->get_user_map_by_id((int) $record->usermapid);
            $requesterzendeskuserid = !empty($usermap->zendesk_user_id) ? (int) $usermap->zendesk_user_id : 0;
            $ticket['publicreplies'] = $this->get_public_replies(
                (int) $record->id,
                (int) $record->zendesk_ticket_id,
                $requesterzendeskuserid
            );
            $ticket['haspublicreplies'] = !empty($ticket['publicreplies']);
        } catch (\Throwable $e) {
            $ticket['hasreplyloaderror'] = true;
            $ticket['replyloaderror'] = get_string('replyloaderror', constants::COMPONENT);
        }

        return $ticket;
    }

    /**
     * Reply to a Zendesk ticket.
     *
     * @param int $ticketid Local ticket id.
     * @param int $userid Moodle user id.
     * @param string $message Reply text.
     * @param bool $canviewall Whether current user can see any ticket.
     * @return \stdClass
     */
    public function reply_to_request(int $ticketid, int $userid, string $message, bool $canviewall = false): \stdClass {
        global $DB;

        $this->assert_ready();
        if (!has_capability('local/zendesk:submitrequest', \context_system::instance())) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/zendesk:submitrequest',
                'nopermissions',
                ''
            );
        }

        $message = trim($message);
        if ($message === '') {
            throw new \moodle_exception('replyrequired', constants::COMPONENT);
        }

        $record = $this->repository->get_ticket($ticketid);
        $this->assert_ticket_access($record, $userid, $canviewall);

        if (empty($record->zendesk_ticket_id)) {
            throw new \moodle_exception('replynotavailable', constants::COMPONENT);
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        $usermap = $this->ensure_remote_user($user);
        $remoteticket = $this->get_ticket_by_id((int) $record->zendesk_ticket_id);
        $record = $this->repository->attach_remote_ticket((int) $record->id, $remoteticket);

        $replyaction = $this->get_reply_action_for_ticket($record);
        if ($replyaction === 'followup') {
            return $this->create_followup_reply($record, $user, $usermap, $message);
        }

        if (!in_array($replyaction, ['reply', 'reopen'], true)) {
            throw new \moodle_exception('replynotallowed', constants::COMPONENT);
        }

        $response = $this->request('PUT', '/tickets/' . (int) $record->zendesk_ticket_id . '.json', [
            'ticket' => [
                'status' => 'open',
                'comment' => $this->build_public_comment_payload($message, (int) $usermap->zendesk_user_id),
            ],
        ]);
        $updatedticket = $this->extract_remote_ticket($response['body']);
        $updatedlocalticket = $this->repository->attach_remote_ticket((int) $record->id, $updatedticket);

        return (object) [
            'localticketid' => (int) $updatedlocalticket->id,
            'action' => $replyaction,
            'pendingconfirmation' => false,
        ];
    }

    /**
     * Fetch a Zendesk attachment response for an authorised Moodle user.
     *
     * @param int $ticketid Local ticket id.
     * @param int $userid Moodle user id.
     * @param string $encodedurl Encoded remote asset URL.
     * @param bool $canviewall Whether current user can see any ticket.
     * @return array
     */
    public function get_attachment_response_for_user(
        int $ticketid,
        int $userid,
        string $encodedurl,
        bool $canviewall = false
    ): array {
        $this->assert_ready();

        $record = $this->repository->get_ticket($ticketid);
        $this->assert_ticket_access($record, $userid, $canviewall);

        $remoteurl = $this->decode_attachment_url($encodedurl);
        if ($remoteurl === '' || !$this->is_proxyable_zendesk_url($remoteurl)) {
            throw new \moodle_exception('invalidattachmenturl', constants::COMPONENT);
        }

        // Bind the requested URL to a manifest row scoped to this ticket. The
        // manifest is populated when the ticket detail view is rendered, so a
        // user can only fetch an attachment URL that belongs to a comment on a
        // ticket they themselves can see. This closes the gap where any URL on
        // the configured Zendesk subdomain would otherwise be proxied with the
        // service-account token's privileges.
        $manifest = $this->repository->get_attachment_manifest((int) $record->id, $remoteurl);
        if (!$manifest) {
            throw new \moodle_exception('invalidattachmenturl', constants::COMPONENT);
        }

        $upstream = $this->download_remote_asset((string) $manifest->remoteurl);

        return [
            'filepath' => $upstream['filepath'],
            'contenttype' => self::safe_response_content_type((string) $upstream['contenttype']),
            'contentlength' => $upstream['contentlength'],
            'contentdisposition' => self::build_content_disposition((string) $manifest->filename),
        ];
    }

    /**
     * Build a Zendesk-compatible payload for ticket create.
     *
     * @param \stdClass $user Moodle user.
     * @param array $payload Request payload.
     * @param string $externalid Zendesk ticket external id.
     * @return array
     */
    private function build_ticket_payload(\stdClass $user, array $payload, string $externalid): array {
        $config = $this->get_config();
        $tags = constants::DEFAULT_TAGS;
        $courseid = !empty($payload['courseid']) ? (int) $payload['courseid'] : 0;

        if ($courseid > 0) {
            $tags[] = 'moodle_course_' . $courseid;
        }

        $ticket = [
            'subject' => trim((string) ($payload['subject'] ?? '')),
            'comment' => [
                'body' => trim((string) ($payload['details'] ?? '')),
            ],
            'requester' => [
                'name' => fullname($user),
                'email' => $user->email,
            ],
            'external_id' => $externalid,
            'tags' => $tags,
        ];

        if (!empty($config->ticketformid)) {
            $ticket['ticket_form_id'] = (int) $config->ticketformid;
        }
        if (!empty($config->brandid)) {
            $ticket['brand_id'] = (int) $config->brandid;
        }
        if (!empty($config->groupid)) {
            $ticket['group_id'] = (int) $config->groupid;
        }

        return $ticket;
    }

    /**
     * Fetch a batch of tickets from Zendesk.
     *
     * @param array $ids Zendesk ticket ids.
     * @return array
     */
    private function get_many_tickets(array $ids): array {
        $response = $this->request('GET', '/tickets/show_many.json', null, [
            'ids' => implode(',', array_unique(array_map('intval', $ids))),
            'include' => 'custom_statuses',
        ]);

        $tickets = [];
        foreach ($response['body']['tickets'] ?? [] as $ticket) {
            $tickets[] = $this->normalise_remote_ticket($ticket);
        }

        return $tickets;
    }

    /**
     * Fetch a single ticket from Zendesk.
     *
     * @param int $zendeskticketid Zendesk ticket id.
     * @return \stdClass
     */
    private function get_ticket_by_id(int $zendeskticketid): \stdClass {
        $response = $this->request('GET', '/tickets/' . $zendeskticketid . '.json');
        if (empty($response['body']['ticket']) || !is_array($response['body']['ticket'])) {
            throw new \moodle_exception('invalidapiresponse', constants::COMPONENT);
        }

        return $this->normalise_remote_ticket($response['body']['ticket']);
    }

    /**
     * Fetch public Zendesk replies for a ticket.
     *
     * @param int $localticketid Local ticket id.
     * @param int $zendeskticketid Zendesk ticket id.
     * @param int $requesterzendeskuserid Zendesk user id for the Moodle requester.
     * @return array
     */
    private function get_public_replies(
        int $localticketid,
        int $zendeskticketid,
        int $requesterzendeskuserid = 0
    ): array {
        $replies = [];
        $hiderequesteropeningcomment = true;
        foreach ($this->get_ticket_comments($zendeskticketid) as $comment) {
            if (empty($comment['public'])) {
                continue;
            }

            $authorid = !empty($comment['author_id']) ? (int) $comment['author_id'] : 0;
            $isrequester = $requesterzendeskuserid > 0 && $authorid === $requesterzendeskuserid;
            $bodyhtml = $this->format_comment_body_html($localticketid, $comment);
            $attachments = $this->format_comment_attachments($localticketid, $comment);
            if ($bodyhtml === '' && empty($attachments)) {
                continue;
            }

            if ($isrequester && $hiderequesteropeningcomment) {
                $hiderequesteropeningcomment = false;
                continue;
            }

            $createdat = !empty($comment['created_at']) ? strtotime((string) $comment['created_at']) : 0;
            $authorlabel = $isrequester
                ? get_string('studentreplyauthor', constants::COMPONENT)
                : get_string('supportreplyauthor', constants::COMPONENT);
            $replies[] = [
                'authorlabel' => $authorlabel,
                'authorinitial' => $isrequester
                    ? get_string('studentreplyinitial', constants::COMPONENT)
                    : get_string('supportreplyinitial', constants::COMPONENT),
                'messageclass' => $isrequester
                    ? 'local-zendesk-chat__message--student'
                    : 'local-zendesk-chat__message--support',
                'bubbleclass' => $isrequester
                    ? 'local-zendesk-chat__bubble--student'
                    : 'local-zendesk-chat__bubble--support',
                'avatarclass' => $isrequester
                    ? 'local-zendesk-chat__avatar--student'
                    : 'local-zendesk-chat__avatar--support',
                'createdhuman' => $createdat ? userdate($createdat) : '',
                'bodyhtml' => $bodyhtml,
                'hasbodyhtml' => $bodyhtml !== '',
                'attachments' => $attachments,
                'hasattachments' => !empty($attachments),
            ];
        }

        return $replies;
    }

    /**
     * Fetch Zendesk ticket comments with inline image metadata.
     *
     * @param int $zendeskticketid Zendesk ticket id.
     * @return array
     */
    private function get_ticket_comments(int $zendeskticketid): array {
        $response = $this->request('GET', '/tickets/' . $zendeskticketid . '/comments.json', null, [
            'sort_order' => 'asc',
            'per_page' => 100,
            'include_inline_images' => 'true',
        ]);

        $comments = $response['body']['comments'] ?? [];
        return is_array($comments) ? $comments : [];
    }

    /**
     * Format a Zendesk comment body for safe Moodle output.
     *
     * @param int $localticketid Local ticket id.
     * @param array $comment Raw Zendesk comment payload.
     * @return string
     */
    private function format_comment_body_html(int $localticketid, array $comment): string {
        $htmlbody = trim((string) ($comment['html_body'] ?? ''));
        if ($htmlbody !== '') {
            $htmlbody = $this->rewrite_comment_asset_urls($localticketid, $htmlbody);
            $htmlbody = $this->remove_inline_images_from_comment_html($htmlbody);
            return format_text($htmlbody, FORMAT_HTML, [
                'trusted' => false,
                'filter' => true,
                'para' => false,
                'newlines' => false,
            ]);
        }

        $plainbody = trim((string) ($comment['plain_body'] ?? $comment['body'] ?? ''));
        if ($plainbody === '') {
            return '';
        }

        return format_text($plainbody, FORMAT_PLAIN);
    }

    /**
     * Format Zendesk comment attachments for the chat template.
     *
     * @param int $localticketid Local ticket id.
     * @param array $comment Raw Zendesk comment payload.
     * @return array
     */
    private function format_comment_attachments(int $localticketid, array $comment): array {
        $attachments = [];

        foreach ($comment['attachments'] ?? [] as $attachment) {
            if (!is_array($attachment) || !empty($attachment['deleted'])) {
                continue;
            }

            $contenturl = trim((string) ($attachment['content_url'] ?? $attachment['mapped_content_url'] ?? ''));
            if ($contenturl === '') {
                continue;
            }

            $previewurl = $contenturl;
            if (!empty($attachment['thumbnails']) && is_array($attachment['thumbnails'])) {
                $thumbnail = reset($attachment['thumbnails']);
                if (is_array($thumbnail) && !empty($thumbnail['content_url'])) {
                    $previewurl = trim((string) $thumbnail['content_url']);
                } else if (is_array($thumbnail) && !empty($thumbnail['mapped_content_url'])) {
                    $previewurl = trim((string) $thumbnail['mapped_content_url']);
                }
            }

            $filesize = !empty($attachment['size']) ? (int) $attachment['size'] : 0;
            $contenttype = strtolower((string) ($attachment['content_type'] ?? ''));
            $filename = trim((string) ($attachment['file_name'] ?? get_string('attachmentfile', constants::COMPONENT)));
            $manifestsize = $filesize > 0 ? $filesize : null;
            $attachments[] = [
                'filename' => $filename,
                'downloadurl' => $this->proxy_or_passthrough_asset_url(
                    $localticketid,
                    $contenturl,
                    $filename,
                    $contenttype !== '' ? $contenttype : null,
                    $manifestsize
                ),
                'previewurl' => $this->proxy_or_passthrough_asset_url(
                    $localticketid,
                    $previewurl,
                    $filename,
                    $contenttype !== '' ? $contenttype : null
                ),
                'isimage' => self::is_safe_inline_image_content_type($contenttype),
                'filesizehuman' => $filesize > 0 ? display_size($filesize) : '',
                'hasfilesize' => $filesize > 0,
            ];
        }

        return $attachments;
    }

    /**
     * Remove inline images from Zendesk comment HTML so the preview rail can render them as thumbnails.
     *
     * @param string $html Raw Zendesk comment HTML.
     * @return string
     */
    private function remove_inline_images_from_comment_html(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousstate = libxml_use_internal_errors(true);
        $wrapperid = 'local-zendesk-comment-root';
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="' . $wrapperid . '">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousstate);

        if (!$loaded) {
            return preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;
        }

        $images = [];
        foreach ($document->getElementsByTagName('img') as $node) {
            $images[] = $node;
        }

        foreach ($images as $image) {
            if ($image->parentNode !== null) {
                $image->parentNode->removeChild($image);
            }
        }

        $xpath = new \DOMXPath($document);
        $nbsp = html_entity_decode('&nbsp;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        while (true) {
            $query = '//*[@id="' . $wrapperid . '"]//*[self::p or self::div or self::span'
                . ' or self::figure or self::a][not(*) and '
                . 'normalize-space(translate(., "' . $nbsp . '", " ")) = ""]';
            $nodes = $xpath->query($query);
            if ($nodes === false || $nodes->length === 0) {
                break;
            }

            $removed = false;
            foreach ($nodes as $node) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                    $removed = true;
                }
            }

            if (!$removed) {
                break;
            }
        }

        $root = $document->getElementById($wrapperid);
        if ($root === null) {
            return '';
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim((string) preg_replace('/(?:&nbsp;|\x{00A0}|\s)+/u', ' ', $output));
    }

    /**
     * Find a ticket by its external id.
     *
     * @param string $externalid Zendesk ticket external id.
     * @return \stdClass|null
     */
    private function find_ticket_by_external_id(string $externalid): ?\stdClass {
        $response = $this->request('GET', '/tickets.json', null, ['external_id' => $externalid]);
        $tickets = $response['body']['tickets'] ?? [];

        if (empty($tickets)) {
            return null;
        }

        if (count($tickets) > 1) {
            throw new \moodle_exception('duplicateticketexternalid', constants::COMPONENT);
        }

        return $this->normalise_remote_ticket(reset($tickets));
    }

    /**
     * Extract a ticket object from a create response.
     *
     * @param array $body API response body.
     * @return \stdClass
     */
    private function extract_remote_ticket(array $body): \stdClass {
        if (empty($body['ticket']) || !is_array($body['ticket'])) {
            throw new \moodle_exception('invalidapiresponse', constants::COMPONENT);
        }

        return $this->normalise_remote_ticket($body['ticket']);
    }

    /**
     * Convert a raw Zendesk ticket response to a local shape.
     *
     * @param array $ticket Raw Zendesk ticket record.
     * @return \stdClass
     */
    private function normalise_remote_ticket(array $ticket): \stdClass {
        return (object) [
            'zendesk_ticket_id' => (int) ($ticket['id'] ?? 0),
            'status' => (string) ($ticket['status'] ?? 'submitted'),
            'custom_status_id' => !empty($ticket['custom_status_id']) ? (int) $ticket['custom_status_id'] : null,
            'updatedat' => !empty($ticket['updated_at']) ? strtotime((string) $ticket['updated_at']) : time(),
        ];
    }

    /**
     * Format a local ticket record for templates.
     *
     * @param \stdClass $ticket Ticket record.
     * @param bool $includedetail Whether to include full body html.
     * @param bool $canviewerrordetail Whether to surface raw upstream error
     *                                 text (capability-gated). End users get a
     *                                 generic message; admins/agents with
     *                                 viewallrequests can see the upstream
     *                                 detail for triage.
     * @return array
     */
    private function format_ticket_for_output(
        \stdClass $ticket,
        bool $includedetail = false,
        bool $canviewerrordetail = false
    ): array {
        $statuslabel = $this->get_status_label($ticket);
        $bodyhtml = format_text($ticket->body, FORMAT_PLAIN);
        $rawerror = trim((string) ($ticket->submissionerror ?? ''));
        $haserror = $rawerror !== '';
        $errortext = '';
        if ($haserror) {
            $errortext = $canviewerrordetail
                ? $rawerror
                : get_string('submissionerrorgeneric', constants::COMPONENT);
        }

        return [
            'id' => (int) $ticket->id,
            'subject' => $ticket->subject,
            'statuslabel' => $statuslabel,
            'statusclass' => $this->get_status_badge_class($ticket),
            'syncstate' => $ticket->syncstate,
            'statusraw' => strtolower((string) ($ticket->status ?? '')),
            'bodyexcerpt' => shorten_text($ticket->body, 140),
            'bodyhtml' => $includedetail ? $bodyhtml : '',
            'hasbodyhtml' => $includedetail,
            'timecreatedhuman' => userdate($ticket->timecreated),
            'timemodifiedhuman' => userdate($ticket->timemodified),
            'coursefullname' => $ticket->coursefullname ?? '',
            'hascourse' => !empty($ticket->coursefullname),
            'viewurl' => (new \moodle_url('/local/zendesk/view.php', ['id' => $ticket->id]))->out(false),
            'zendeskticketid' => !empty($ticket->zendesk_ticket_id) ? (int) $ticket->zendesk_ticket_id : null,
            'haszendeskticketid' => !empty($ticket->zendesk_ticket_id),
            'submissionerror' => $errortext,
            'haserror' => $haserror,
        ];
    }

    /**
     * Get the display label for the ticket status.
     *
     * @param \stdClass $ticket Ticket record.
     * @return string
     */
    private function get_status_label(\stdClass $ticket): string {
        if ($ticket->syncstate === constants::STATE_CONFIRMINGCREATE) {
            return get_string('statusconfirming', constants::COMPONENT);
        }
        if ($ticket->syncstate === constants::STATE_PENDINGCREATE) {
            return get_string('statussubmitted', constants::COMPONENT);
        }
        if ($ticket->syncstate === constants::STATE_ERROR) {
            return get_string('statuserror', constants::COMPONENT);
        }

        $status = trim((string) ($ticket->status ?? ''));
        if ($status === '') {
            return get_string('statussubmitted', constants::COMPONENT);
        }

        $stringkey = 'status_' . clean_param(strtolower($status), PARAM_ALPHANUMEXT);
        if (get_string_manager()->string_exists($stringkey, constants::COMPONENT)) {
            return get_string($stringkey, constants::COMPONENT);
        }

        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Determine what reply action a ticket supports.
     *
     * @param \stdClass $ticket Ticket record.
     * @return string One of followup, reopen, reply, or none.
     */
    private function get_reply_action_for_ticket(\stdClass $ticket): string {
        if (empty($ticket->zendesk_ticket_id)) {
            return 'none';
        }

        $status = strtolower((string) ($ticket->status ?? ''));
        if ($status === 'closed') {
            return 'followup';
        }

        if ($status === 'solved') {
            return 'reopen';
        }

        if (in_array($status, ['new', 'open', 'pending', 'hold'], true)) {
            return 'reply';
        }

        return 'none';
    }

    /**
     * Rewrite Zendesk-hosted asset URLs so Moodle can proxy them securely.
     *
     * @param int $localticketid Local ticket id.
     * @param string $html Raw Zendesk comment HTML.
     * @return string
     */
    private function rewrite_comment_asset_urls(int $localticketid, string $html): string {
        $rewritten = preg_replace_callback(
            '/\b(href|src)=(["\'])([^"\']+)\2/i',
            function (array $matches) use ($localticketid): string {
                $rawurl = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5);
                $url = $this->proxy_or_passthrough_asset_url($localticketid, $rawurl);
                return $matches[1] . '=' . $matches[2] . s($url) . $matches[2];
            },
            $html
        );

        return $rewritten ?? $html;
    }

    /**
     * Convert a remote Zendesk asset URL into a Moodle proxy URL when needed.
     *
     * Persists a manifest row (ticket id + URL hash + URL) so that
     * get_attachment_response_for_user can later confirm the URL legitimately
     * belongs to a comment on this ticket.
     *
     * @param int $localticketid Local ticket id.
     * @param string $url Remote asset URL.
     * @param string|null $filename Optional structured filename (when known).
     * @param string|null $contenttype Optional structured content type.
     * @param int|null $filesize Optional structured byte size.
     * @return string
     */
    private function proxy_or_passthrough_asset_url(
        int $localticketid,
        string $url,
        ?string $filename = null,
        ?string $contenttype = null,
        ?int $filesize = null
    ): string {
        if ($this->is_proxyable_zendesk_url($url)) {
            $this->repository->upsert_attachment_manifest(
                $localticketid,
                $url,
                $filename,
                $contenttype,
                $filesize
            );

            return (new \moodle_url('/local/zendesk/attachment.php', [
                'id' => $localticketid,
                'url' => $this->encode_attachment_url($url),
            ]))->out(false);
        }

        return $url;
    }

    /**
     * Determine whether an upstream Content-Type can render inline as a
     * thumbnail / image preview. SVG is excluded because it can carry script.
     *
     * @param string $contenttype Upstream Content-Type.
     * @return bool
     */
    public static function is_safe_inline_image_content_type(string $contenttype): bool {
        $cleaned = strtolower(self::sanitise_header_value($contenttype));
        $semi = strpos($cleaned, ';');
        if ($semi !== false) {
            $cleaned = trim(substr($cleaned, 0, $semi));
        }

        return in_array(
            $cleaned,
            ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
            true
        );
    }

    /**
     * Strip CR/LF and other ASCII control characters from a header value so it
     * cannot inject additional response headers.
     *
     * @param string $value Raw value (typically from an upstream response).
     * @return string
     */
    public static function sanitise_header_value(string $value): string {
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return trim((string) $cleaned);
    }

    /**
     * Map an upstream Content-Type to a safe value for the proxied response.
     *
     * Any type that is not on the inline-safe allow-list (image/png,
     * image/jpeg, image/gif, image/webp, application/pdf) becomes
     * application/octet-stream, which the browser will treat as a download
     * rather than render. SVG, HTML, JS, XML, plain text are all caught.
     *
     * @param string $upstream Upstream Content-Type header value.
     * @return string
     */
    public static function safe_response_content_type(string $upstream): string {
        $cleaned = self::sanitise_header_value($upstream);
        $cleaned = strtolower($cleaned);
        $semi = strpos($cleaned, ';');
        if ($semi !== false) {
            $cleaned = trim(substr($cleaned, 0, $semi));
        }

        return in_array($cleaned, self::SAFE_INLINE_CONTENT_TYPES, true)
            ? $cleaned
            : 'application/octet-stream';
    }

    /**
     * Build a Content-Disposition: attachment header from a server-known
     * filename. Always forces "attachment" so the browser downloads rather
     * than rendering. Sanitises the filename, falls back to a generic name
     * when none is known, and emits both a quoted ASCII form and an RFC 5987
     * encoded filename* so non-ASCII names are preserved.
     *
     * @param string $filename Filename recorded on the manifest row.
     * @return string
     */
    public static function build_content_disposition(string $filename): string {
        $basename = basename(self::sanitise_header_value($filename));
        $basename = preg_replace('#[\\\\/]#', '', $basename);
        $basename = trim((string) $basename);
        if ($basename === '') {
            $basename = 'attachment';
        }

        // Collapse runs of non-printable / non-ASCII bytes to a single underscore
        // so a multibyte filename yields one underscore per character rather
        // than one per byte. Quotes and backslashes break the quoted form.
        $asciiform = preg_replace('/[^\x20-\x7E]+/', '_', $basename);
        $asciiform = str_replace(['\\', '"'], '_', (string) $asciiform);
        $rfc5987 = rawurlencode($basename);

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $asciiform,
            $rfc5987
        );
    }

    /**
     * Determine whether a remote URL should be proxied through Moodle.
     *
     * @param string $url Remote asset URL.
     * @return bool
     */
    private function is_proxyable_zendesk_url(string $url): bool {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($host === '' || $scheme !== 'https') {
            return false;
        }

        return $host === strtolower($this->get_zendesk_host());
    }

    /**
     * Encode a remote asset URL for safe transport through Moodle.
     *
     * @param string $url Remote asset URL.
     * @return string
     */
    private function encode_attachment_url(string $url): string {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    /**
     * Decode an encoded remote asset URL.
     *
     * @param string $encodedurl Encoded remote asset URL.
     * @return string
     */
    private function decode_attachment_url(string $encodedurl): string {
        $encodedurl = trim($encodedurl);
        if ($encodedurl === '') {
            return '';
        }

        $encodedurl = strtr($encodedurl, '-_', '+/');
        $padding = strlen($encodedurl) % 4;
        if ($padding > 0) {
            $encodedurl .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($encodedurl, true);
        return $decoded === false ? '' : $decoded;
    }

    /**
     * Get the configured Zendesk hostname.
     *
     * @return string
     */
    private function get_zendesk_host(): string {
        $config = $this->get_config();
        return $this->normalise_subdomain($config->subdomain) . '.zendesk.com';
    }

    /**
     * Map a ticket to a badge class.
     *
     * @param \stdClass $ticket Ticket record.
     * @return string
     */
    private function get_status_badge_class(\stdClass $ticket): string {
        if ($ticket->syncstate === constants::STATE_ERROR) {
            return 'badge badge-danger';
        }
        if (in_array($ticket->syncstate, [constants::STATE_PENDINGCREATE, constants::STATE_CONFIRMINGCREATE], true)) {
            return 'badge badge-warning';
        }

        switch (strtolower((string) ($ticket->status ?? ''))) {
            case 'solved':
            case 'closed':
                return 'badge badge-success';
            case 'pending':
            case 'hold':
                return 'badge badge-warning';
            case 'open':
            case 'new':
                return 'badge badge-info';
            default:
                return 'badge badge-secondary';
        }
    }

    /**
     * Verify the current user can act on the requested ticket.
     *
     * @param \stdClass $ticket Ticket record.
     * @param int $userid Moodle user id.
     * @param bool $canviewall Whether current user can see any ticket.
     * @return void
     */
    private function assert_ticket_access(\stdClass $ticket, int $userid, bool $canviewall): void {
        if (!$canviewall && (int) $ticket->userid !== $userid) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/zendesk:viewallrequests',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Validate configuration before making remote API calls.
     *
     * @return void
     */
    private function assert_ready(): void {
        if (!$this->is_enabled()) {
            throw new \moodle_exception('zendeskdisabled', constants::COMPONENT);
        }
        if (!$this->is_configured()) {
            throw new \moodle_exception('pluginnotconfigured', constants::COMPONENT);
        }
    }

    /**
     * Get plugin config with defaults applied.
     *
     * @return \stdClass
     */
    private function get_config(): \stdClass {
        return (object) [
            'enabled' => (int) get_config(constants::COMPONENT, 'enabled'),
            'subdomain' => trim((string) get_config(constants::COMPONENT, 'subdomain')),
            'serviceemail' => trim((string) get_config(constants::COMPONENT, 'serviceemail')),
            'apitoken' => trim((string) get_config(constants::COMPONENT, 'apitoken')),
            'skipverifyemail' => (int) get_config(constants::COMPONENT, 'skipverifyemail'),
            'ticketformid' => trim((string) get_config(constants::COMPONENT, 'ticketformid')),
            'brandid' => trim((string) get_config(constants::COMPONENT, 'brandid')),
            'groupid' => trim((string) get_config(constants::COMPONENT, 'groupid')),
            'dashboardlimit' => (int) get_config(constants::COMPONENT, 'dashboardlimit'),
            'syncbatchsize' => (int) get_config(constants::COMPONENT, 'syncbatchsize'),
            'instanceuuid' => trim((string) get_config(constants::COMPONENT, 'instanceuuid')),
        ];
    }

    /**
     * Stream a Zendesk-hosted binary asset to a request-scoped temp file.
     *
     * The caller (attachment.php) reads from the returned filepath via
     * readfile() so the body never lives in PHP memory in full. The download
     * is bounded by MAX_ATTACHMENT_BYTES via two layers of defence:
     *
     * - CURLOPT_MAXFILESIZE rejects up front when the upstream advertises a
     *   Content-Length larger than the cap.
     * - CURLOPT_PROGRESSFUNCTION aborts in-flight when the running byte count
     *   exceeds the cap (handles Content-Length-less / dishonest responses).
     *
     * After the transfer the effective URL is re-checked against the
     * configured Zendesk subdomain, so a redirect that hops off the
     * Help-Center host cannot smuggle data through. CURLOPT_UNRESTRICTED_AUTH
     * is explicitly disabled so the Basic-auth service-account token is never
     * forwarded to a redirected origin.
     *
     * @param string $url Remote asset URL.
     * @return array
     */
    private function download_remote_asset(string $url): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $maxsize = self::MAX_ATTACHMENT_BYTES;
        $tmpdir = make_request_directory();
        $tmppath = $tmpdir . '/zendesk-attachment-' . bin2hex(random_bytes(8));

        $curl = $this->build_authenticated_curl(['Accept: */*']);
        $curl->setopt([
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_MAXFILESIZE' => $maxsize,
            'CURLOPT_UNRESTRICTED_AUTH' => 0,
            'CURLOPT_NOPROGRESS' => 0,
            'CURLOPT_PROGRESSFUNCTION' => static function ($ch, $dltotal, $dlnow) use ($maxsize) {
                return $dlnow > $maxsize ? 1 : 0;
            },
        ]);

        $curl->download_one($url, [], [
            'filepath' => $tmppath,
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        if ($this->has_curl_error($curl)) {
            throw new \RuntimeException(
                $this->get_curl_error_text($curl, 'Zendesk attachment request failed.')
            );
        }

        $info = $curl->get_info();
        $effectiveurl = (string) ($info['url'] ?? '');
        if ($effectiveurl !== '' && !$this->is_proxyable_zendesk_url($effectiveurl)) {
            throw new \moodle_exception('invalidattachmenturl', constants::COMPONENT);
        }

        $status = $this->get_curl_http_status($curl);
        if ($status >= 400) {
            throw new \moodle_exception(
                'attachmentdownloadfailed',
                constants::COMPONENT,
                '',
                null,
                get_string('unexpectedapistatus', constants::COMPONENT, $status)
            );
        }

        if (!is_file($tmppath)) {
            throw new \RuntimeException('Zendesk attachment download produced no payload.');
        }

        $headers = $this->normalise_response_headers($curl->getResponse());
        $bytesondisk = (int) filesize($tmppath);
        if ($bytesondisk > $maxsize) {
            throw new \moodle_exception('attachmenttoolarge', constants::COMPONENT);
        }

        $contentlength = !empty($headers['content-length'])
            ? (int) $headers['content-length']
            : $bytesondisk;

        return [
            'filepath' => $tmppath,
            'contenttype' => $headers['content-type'] ?? 'application/octet-stream',
            'contentlength' => $contentlength,
            'contentdisposition' => $headers['content-disposition'] ?? '',
        ];
    }

    /**
     * Perform an authenticated Zendesk API request.
     *
     * @param string $method HTTP verb.
     * @param string $path API path.
     * @param array|null $payload Optional JSON payload.
     * @param array $query Optional query parameters.
     * @return array
     */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): array {
        $config = $this->get_config();
        $subdomain = $this->normalise_subdomain($config->subdomain);
        $path = '/' . ltrim($path, '/');
        $url = 'https://' . $subdomain . '.zendesk.com/api/v2' . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $requestheaders = ['Accept: application/json'];
        $json = null;

        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \coding_exception('Unable to encode Zendesk request payload.');
            }
            $requestheaders[] = 'Content-Type: application/json';
        }

        $curl = $this->build_authenticated_curl($requestheaders);
        $options = [
            'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ];

        switch (strtoupper($method)) {
            case 'GET':
                $rawbody = $curl->get($url, [], $options);
                break;

            case 'POST':
                $rawbody = $curl->post($url, $json ?? '', $options);
                break;

            case 'PUT':
                $rawbody = $curl->put($url, $json ?? '', $options);
                break;

            default:
                throw new \coding_exception('Unsupported Zendesk request method: ' . $method);
        }

        if ($this->has_curl_error($curl)) {
            throw new \RuntimeException(
                $this->get_curl_error_text($curl, 'Zendesk request failed.')
            );
        }

        $status = $this->get_curl_http_status($curl);
        $headers = $this->normalise_response_headers($curl->getResponse());

        $decoded = [];
        if ($rawbody !== '') {
            $decoded = json_decode($rawbody, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception('invalidapiresponse', constants::COMPONENT);
            }
        }

        if ($status >= 400) {
            $message = $this->extract_api_error_message($decoded, $status);
            if ($status === 429 && !empty($headers['retry-after'])) {
                $message .= ' ' . get_string('retryafterseconds', constants::COMPONENT, (int) $headers['retry-after']);
            }
            throw new \moodle_exception('apifailure', constants::COMPONENT, '', null, $message);
        }

        return [
            'status' => $status,
            'body' => $decoded,
            'headers' => $headers,
        ];
    }

    /**
     * Build an authenticated Moodle curl client for Zendesk requests.
     *
     * @param array $headers Optional request headers.
     * @return \curl
     */
    private function build_authenticated_curl(array $headers = []): \curl {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $config = $this->get_config();
        $curl = new \curl();
        if ($headers !== []) {
            $curl->setHeader($headers);
        }

        $curl->setopt([
            'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
            'CURLOPT_USERPWD' => $config->serviceemail . '/token:' . $config->apitoken,
        ]);

        return $curl;
    }

    /**
     * Get the HTTP status from a Moodle curl response.
     *
     * @param \curl $curl Moodle curl client.
     * @return int
     */
    private function get_curl_http_status(\curl $curl): int {
        $info = $curl->get_info();
        return (int) ($info['http_code'] ?? 0);
    }

    /**
     * Determine whether a Moodle curl request failed before receiving a valid response.
     *
     * @param \curl $curl Moodle curl client.
     * @return bool
     */
    private function has_curl_error(\curl $curl): bool {
        return !empty($curl->errno)
            || ($this->get_curl_http_status($curl) === 0 && !empty($curl->error));
    }

    /**
     * Extract a readable curl error string.
     *
     * @param \curl $curl Moodle curl client.
     * @param string $fallback Fallback message.
     * @return string
     */
    private function get_curl_error_text(\curl $curl, string $fallback): string {
        $error = trim((string) ($curl->error ?? ''));
        return $error !== '' ? $error : $fallback;
    }

    /**
     * Normalise response headers from Moodle curl.
     *
     * @param array $headers Response headers.
     * @return array
     */
    private function normalise_response_headers(array $headers): array {
        $normalised = [];

        foreach ($headers as $name => $value) {
            $key = strtolower(trim((string) $name));
            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $value = end($value);
            }

            $normalised[$key] = trim((string) $value);
        }

        return $normalised;
    }

    /**
     * Extract the best error string from a Zendesk response body.
     *
     * @param array $decoded Response body.
     * @param int $status HTTP status.
     * @return string
     */
    private function extract_api_error_message(array $decoded, int $status): string {
        foreach (['description', 'error', 'message'] as $field) {
            if (!empty($decoded[$field]) && is_string($decoded[$field])) {
                return $decoded[$field];
            }
        }

        return get_string('unexpectedapistatus', constants::COMPONENT, $status);
    }

    /**
     * Normalise a Zendesk subdomain configuration value.
     *
     * @param string $subdomain Configured subdomain or domain.
     * @return string
     */
    private function normalise_subdomain(string $subdomain): string {
        $subdomain = trim($subdomain);
        $subdomain = preg_replace('#^https?://#i', '', $subdomain);
        $subdomain = preg_replace('#/.*$#', '', $subdomain);
        $subdomain = preg_replace('#\.zendesk\.com$#i', '', $subdomain);

        return trim($subdomain);
    }

    /**
     * Build the stable Zendesk user external id.
     *
     * @param int $userid Moodle user id.
     * @return string
     */
    private function build_user_external_id(int $userid): string {
        return 'mdl:' . $this->get_instance_uuid() . ':user:' . $userid;
    }

    /**
     * Build the stable Zendesk ticket external id.
     *
     * @param string $uuid Generated local ticket UUID.
     * @return string
     */
    private function build_ticket_external_id(string $uuid): string {
        return 'mdl:' . $this->get_instance_uuid() . ':ticket:' . $uuid;
    }

    /**
     * Build a public comment payload.
     *
     * @param string $message Comment text.
     * @param int $authorid Zendesk author id.
     * @return array
     */
    private function build_public_comment_payload(string $message, int $authorid): array {
        $comment = [
            'body' => trim($message),
            'public' => true,
        ];

        if ($authorid > 0) {
            $comment['author_id'] = $authorid;
        }

        return $comment;
    }

    /**
     * Create a follow-up ticket for a closed Zendesk ticket.
     *
     * @param \stdClass $record Local ticket record.
     * @param \stdClass $user Moodle user record.
     * @param \stdClass $usermap Zendesk user map record.
     * @param string $message Reply text.
     * @return \stdClass
     */
    private function create_followup_reply(\stdClass $record, \stdClass $user, \stdClass $usermap, string $message): \stdClass {
        $uuid = $this->generate_uuid();
        $ticketexternalid = $this->build_ticket_external_id($uuid);
        $localticket = $this->repository->create_local_ticket(
            (int) $record->userid,
            (int) $usermap->id,
            !empty($record->courseid) ? (int) $record->courseid : null,
            !empty($record->contextid) ? (int) $record->contextid : null,
            $uuid,
            $ticketexternalid,
            (string) $record->subject,
            $message
        );
        $ticketpayload = $this->build_ticket_payload($user, [
            'subject' => (string) $record->subject,
            'details' => $message,
            'courseid' => !empty($record->courseid) ? (int) $record->courseid : 0,
        ], $ticketexternalid);
        $ticketpayload['via_followup_source_id'] = (int) $record->zendesk_ticket_id;
        $ticketpayload['comment'] = $this->build_public_comment_payload($message, (int) $usermap->zendesk_user_id);

        try {
            $response = $this->request('POST', '/tickets.json', [
                'ticket' => $ticketpayload,
            ]);
            $remoteticket = $this->extract_remote_ticket($response['body']);
            $localticket = $this->repository->mark_ticket_created((int) $localticket->id, $remoteticket);

            return (object) [
                'localticketid' => (int) $localticket->id,
                'action' => 'followup',
                'pendingconfirmation' => false,
            ];
        } catch (\RuntimeException $e) {
            $localticket = $this->repository->mark_ticket_confirming((int) $localticket->id, $e->getMessage());

            return (object) [
                'localticketid' => (int) $localticket->id,
                'action' => 'followup',
                'pendingconfirmation' => true,
            ];
        } catch (\Throwable $e) {
            $this->repository->mark_ticket_error((int) $localticket->id, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Read the plugin instance UUID seeded at install/upgrade time
     * (security review F12). Treated as immutable at runtime so concurrent
     * first-hit requests cannot race and produce two different UUIDs.
     *
     * @return string
     */
    private function get_instance_uuid(): string {
        $config = $this->get_config();
        if (!empty($config->instanceuuid)) {
            return $config->instanceuuid;
        }

        throw new \coding_exception(
            'local_zendesk instance UUID is not initialised; run admin/cli/upgrade.php to seed it.'
        );
    }

    /**
     * Generate a RFC4122-compatible UUIDv4.
     *
     * @return string
     */
    private function generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
