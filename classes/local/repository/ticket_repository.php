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
 * Data access for Moodle-managed Zendesk records.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\local\repository;

use local_zendesk\local\constants;

/**
 * Data access for Moodle-managed Zendesk records.
 *
 * @package   local_zendesk
 */
final class ticket_repository {
    /** @var \moodle_database */
    private $db;

    /**
     * Constructor.
     *
     * @param \moodle_database|null $db Optional database handle.
     */
    public function __construct(?\moodle_database $db = null) {
        global $DB;

        $this->db = $db ?? $DB;
    }

    /**
     * Upsert the local Zendesk user mapping.
     *
     * @param int $userid Moodle user id.
     * @param int $zendeskuserid Zendesk user id.
     * @param string $externalid Zendesk external id.
     * @param string $email User email.
     * @return \stdClass
     */
    public function upsert_user_map(int $userid, int $zendeskuserid, string $externalid, string $email): \stdClass {
        $record = $this->db->get_record('local_zendesk_usermap', ['userid' => $userid]);
        $now = time();

        if ($record) {
            $record->zendesk_user_id = $zendeskuserid;
            $record->zendesk_external_id = $externalid;
            $record->zendesk_email = $email;
            $record->lastsyncedat = $now;
            $record->timemodified = $now;
            $this->db->update_record('local_zendesk_usermap', $record);
            return $this->db->get_record('local_zendesk_usermap', ['id' => $record->id], '*', MUST_EXIST);
        }

        $record = (object) [
            'userid' => $userid,
            'zendesk_user_id' => $zendeskuserid,
            'zendesk_external_id' => $externalid,
            'zendesk_email' => $email,
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $this->db->insert_record('local_zendesk_usermap', $record);

        return $record;
    }

    /**
     * Create the local ticket record before the remote API call.
     *
     * @param int $userid Moodle user id.
     * @param int $usermapid Local user map id.
     * @param int|null $courseid Optional course id.
     * @param int|null $contextid Optional context id.
     * @param string $uuid Local UUID.
     * @param string $externalid Zendesk external id for the ticket.
     * @param string $subject Request subject.
     * @param string $body Request body.
     * @return \stdClass
     */
    public function create_local_ticket(
        int $userid,
        int $usermapid,
        ?int $courseid,
        ?int $contextid,
        string $uuid,
        string $externalid,
        string $subject,
        string $body
    ): \stdClass {
        $now = time();
        $record = (object) [
            'uuid' => $uuid,
            'userid' => $userid,
            'usermapid' => $usermapid,
            'courseid' => $courseid ?: null,
            'contextid' => $contextid ?: null,
            'zendesk_ticket_id' => null,
            'zendesk_ticket_external_id' => $externalid,
            'subject' => $subject,
            'body' => $body,
            'status' => 'submitted',
            'custom_status_id' => null,
            'syncstate' => constants::STATE_PENDINGCREATE,
            'lastremoteupdatedat' => null,
            'lastsyncattemptat' => null,
            'lastsyncat' => null,
            'submissionerror' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $this->db->insert_record('local_zendesk_ticket', $record);

        return $this->get_ticket($record->id);
    }

    /**
     * Update a ticket after remote create succeeds.
     *
     * @param int $localticketid Local ticket id.
     * @param \stdClass $remoteticket Normalised remote ticket payload.
     * @return \stdClass
     */
    public function mark_ticket_created(int $localticketid, \stdClass $remoteticket): \stdClass {
        return $this->attach_remote_ticket($localticketid, $remoteticket);
    }

    /**
     * Mark a ticket as awaiting remote confirmation.
     *
     * @param int $localticketid Local ticket id.
     * @param string $message Error message to persist.
     * @return \stdClass
     */
    public function mark_ticket_confirming(int $localticketid, string $message = ''): \stdClass {
        $ticket = $this->db->get_record('local_zendesk_ticket', ['id' => $localticketid], '*', MUST_EXIST);
        $ticket->syncstate = constants::STATE_CONFIRMINGCREATE;
        $ticket->submissionerror = $message;
        $ticket->lastsyncattemptat = time();
        $ticket->timemodified = time();
        $this->db->update_record('local_zendesk_ticket', $ticket);

        return $this->get_ticket($localticketid);
    }

    /**
     * Mark a ticket as errored.
     *
     * @param int $localticketid Local ticket id.
     * @param string $message Error message to persist.
     * @return \stdClass
     */
    public function mark_ticket_error(int $localticketid, string $message): \stdClass {
        $ticket = $this->db->get_record('local_zendesk_ticket', ['id' => $localticketid], '*', MUST_EXIST);
        $ticket->syncstate = constants::STATE_ERROR;
        $ticket->submissionerror = $message;
        $ticket->lastsyncattemptat = time();
        $ticket->timemodified = time();
        $this->db->update_record('local_zendesk_ticket', $ticket);

        return $this->get_ticket($localticketid);
    }

    /**
     * Attach a normalised remote ticket to a local ticket row.
     *
     * @param int $localticketid Local ticket id.
     * @param \stdClass $remoteticket Normalised remote ticket payload.
     * @return \stdClass
     */
    public function attach_remote_ticket(int $localticketid, \stdClass $remoteticket): \stdClass {
        $ticket = $this->db->get_record('local_zendesk_ticket', ['id' => $localticketid], '*', MUST_EXIST);
        $ticket->zendesk_ticket_id = $remoteticket->zendesk_ticket_id;
        $ticket->status = $remoteticket->status;
        $ticket->custom_status_id = $remoteticket->custom_status_id;
        $ticket->lastremoteupdatedat = $remoteticket->updatedat;
        $ticket->lastsyncattemptat = time();
        $ticket->lastsyncat = time();
        $ticket->syncstate = $this->is_terminal_status($remoteticket->status) ? constants::STATE_CLOSED : constants::STATE_ACTIVE;
        $ticket->submissionerror = null;
        $ticket->timemodified = time();
        $this->db->update_record('local_zendesk_ticket', $ticket);

        return $this->get_ticket($localticketid);
    }

    /**
     * Apply a batch of remote snapshots to local tickets.
     *
     * @param array $tickets Array of normalised remote ticket objects.
     * @return int
     */
    public function apply_remote_snapshots(array $tickets): int {
        $processed = 0;

        foreach ($tickets as $ticket) {
            if (empty($ticket->zendesk_ticket_id)) {
                continue;
            }

            $local = $this->db->get_record('local_zendesk_ticket', ['zendesk_ticket_id' => $ticket->zendesk_ticket_id]);
            if (!$local) {
                continue;
            }

            $this->attach_remote_ticket((int) $local->id, $ticket);
            $processed++;
        }

        return $processed;
    }

    /**
     * Mark a sync attempt on a local ticket.
     *
     * @param int $localticketid Local ticket id.
     * @return void
     */
    public function mark_sync_attempt(int $localticketid): void {
        $ticket = (object) [
            'id' => $localticketid,
            'lastsyncattemptat' => time(),
            'timemodified' => time(),
        ];
        $this->db->update_record('local_zendesk_ticket', $ticket);
    }

    /**
     * Get the next tickets that require create confirmation.
     *
     * @param int $limit Max ticket count.
     * @return array
     */
    public function get_confirming_tickets(int $limit): array {
        $sql = "SELECT t.*, c.fullname AS coursefullname, c.shortname AS courseshortname
                  FROM {local_zendesk_ticket} t
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.syncstate = :syncstate
              ORDER BY COALESCE(t.lastsyncattemptat, 0) ASC, t.timecreated ASC";

        return $this->db->get_records_sql($sql, ['syncstate' => constants::STATE_CONFIRMINGCREATE], 0, $limit);
    }

    /**
     * Get active tickets due for status sync.
     *
     * @param int $limit Max ticket count.
     * @return array
     */
    public function get_active_tickets_for_sync(int $limit): array {
        $sql = "SELECT t.*, c.fullname AS coursefullname, c.shortname AS courseshortname
                  FROM {local_zendesk_ticket} t
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.syncstate = :syncstate
              ORDER BY COALESCE(t.lastsyncattemptat, 0) ASC, COALESCE(t.lastremoteupdatedat, 0) ASC";

        return $this->db->get_records_sql($sql, ['syncstate' => constants::STATE_ACTIVE], 0, $limit);
    }

    /**
     * Get tickets for a specific user.
     *
     * @param int $userid Moodle user id.
     * @param int $limit Max ticket count.
     * @param bool $activeonly If true, only return active or pending tickets.
     * @return array
     */
    public function get_user_tickets(int $userid, int $limit = 5, bool $activeonly = false): array {
        $params = ['userid' => $userid];
        $where = 't.userid = :userid';

        if ($activeonly) {
            $where .= " AND t.syncstate IN (:pendingcreate, :confirmingcreate, :active)";
            $params['pendingcreate'] = constants::STATE_PENDINGCREATE;
            $params['confirmingcreate'] = constants::STATE_CONFIRMINGCREATE;
            $params['active'] = constants::STATE_ACTIVE;
        }

        $sql = "SELECT t.*, c.fullname AS coursefullname, c.shortname AS courseshortname
                  FROM {local_zendesk_ticket} t
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE {$where}
              ORDER BY t.timecreated DESC";

        return $this->db->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Get a ticket by its local id.
     *
     * @param int $ticketid Local ticket id.
     * @return \stdClass
     */
    public function get_ticket(int $ticketid): \stdClass {
        $sql = "SELECT t.*, c.fullname AS coursefullname, c.shortname AS courseshortname
                  FROM {local_zendesk_ticket} t
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.id = :id";

        return $this->db->get_record_sql($sql, ['id' => $ticketid], MUST_EXIST);
    }

    /**
     * Get a Zendesk user mapping by local map id.
     *
     * @param int $usermapid Local user map id.
     * @return \stdClass|null
     */
    public function get_user_map_by_id(int $usermapid): ?\stdClass {
        return $this->db->get_record('local_zendesk_usermap', ['id' => $usermapid]) ?: null;
    }

    /**
     * Insert or refresh a row in the per-ticket attachment manifest.
     *
     * The manifest is the allow-list the attachment proxy consults before
     * fetching a Zendesk-hosted file: a URL that does not appear here for the
     * supplied local ticket cannot be downloaded through Moodle, even if the
     * URL passes the host-allow-list check. Metadata fields are nullable so
     * inline-image rewriters that only know the URL can still register entries;
     * structured callers (the attachments list) backfill metadata when known.
     *
     * @param int $localticketid Local ticket id.
     * @param string $remoteurl Zendesk-hosted asset URL.
     * @param string|null $filename Optional file name.
     * @param string|null $contenttype Optional MIME type reported by Zendesk.
     * @param int|null $filesize Optional byte size reported by Zendesk.
     * @return string The SHA-256 hex hash of the URL.
     */
    public function upsert_attachment_manifest(
        int $localticketid,
        string $remoteurl,
        ?string $filename = null,
        ?string $contenttype = null,
        ?int $filesize = null
    ): string {
        $urlhash = hash('sha256', $remoteurl);
        $existing = $this->db->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => $localticketid,
            'urlhash' => $urlhash,
        ]);

        if ($existing) {
            $update = [];
            if ($filename !== null && empty($existing->filename)) {
                $update['filename'] = $filename;
            }
            if ($contenttype !== null && empty($existing->contenttype)) {
                $update['contenttype'] = $contenttype;
            }
            if ($filesize !== null && empty($existing->filesize)) {
                $update['filesize'] = $filesize;
            }
            if (!empty($update)) {
                $update['id'] = (int) $existing->id;
                $this->db->update_record('local_zendesk_ticket_attachment', (object) $update);
            }
            return $urlhash;
        }

        $this->db->insert_record('local_zendesk_ticket_attachment', (object) [
            'localticketid' => $localticketid,
            'urlhash' => $urlhash,
            'remoteurl' => $remoteurl,
            'filename' => $filename,
            'contenttype' => $contenttype,
            'filesize' => $filesize,
            'timecreated' => time(),
        ]);

        return $urlhash;
    }

    /**
     * Get the manifest row for a (ticket, URL) pair if one exists.
     *
     * @param int $localticketid Local ticket id.
     * @param string $remoteurl Zendesk-hosted asset URL.
     * @return \stdClass|null
     */
    public function get_attachment_manifest(int $localticketid, string $remoteurl): ?\stdClass {
        $urlhash = hash('sha256', $remoteurl);
        $record = $this->db->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => $localticketid,
            'urlhash' => $urlhash,
        ]);

        return $record ?: null;
    }

    /**
     * Delete every manifest row for the supplied local ticket ids.
     *
     * Used by the privacy provider when ticket data is removed for a user.
     *
     * @param array $localticketids Local ticket ids.
     * @return void
     */
    public function delete_attachment_manifest_for_tickets(array $localticketids): void {
        if (empty($localticketids)) {
            return;
        }

        [$insql, $params] = $this->db->get_in_or_equal(
            array_map('intval', $localticketids),
            SQL_PARAMS_NAMED
        );
        $this->db->delete_records_select(
            'local_zendesk_ticket_attachment',
            "localticketid {$insql}",
            $params
        );
    }

    /**
     * Determine whether a Zendesk status is terminal for frequent sync.
     *
     * @param string $status Zendesk ticket status.
     * @return bool
     */
    private function is_terminal_status(string $status): bool {
        return in_array(strtolower($status), ['solved', 'closed'], true);
    }
}
