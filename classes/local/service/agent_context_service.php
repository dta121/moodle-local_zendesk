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

namespace local_zendesk\local\service;

use local_zendesk\local\constants;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only Moodle context lookup for Zendesk agent tooling.
 *
 * @package   local_zendesk
 */
final class agent_context_service {
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
     * Get Moodle context data for a Zendesk requester.
     *
     * @param string|null $externalid Zendesk external id.
     * @param string|null $email Zendesk requester email.
     * @return array
     */
    public function get_agent_context(?string $externalid, ?string $email): array {
        $externalid = trim((string) $externalid);
        $email = trim((string) $email);

        if ($externalid === '' && $email === '') {
            throw new \invalid_parameter_exception(get_string('agentcontextmissinginput', constants::COMPONENT));
        }

        if ($email !== '' && !validate_email($email)) {
            throw new \invalid_parameter_exception(get_string('agentcontextinvalidemail', constants::COMPONENT));
        }

        $user = null;
        if ($externalid !== '') {
            $user = $this->find_user_by_external_id($externalid);
        }

        if (!$user && $email !== '') {
            $user = $this->find_user_by_email($email);
        }

        if (!$user) {
            throw new \moodle_exception('agentcontextnotfound', constants::COMPONENT);
        }

        return $this->build_context_payload($user);
    }

    /**
     * Find a Moodle user by the Zendesk external id.
     *
     * @param string $externalid Zendesk external id.
     * @return \stdClass|null
     */
    private function find_user_by_external_id(string $externalid): ?\stdClass {
        $map = $this->db->get_record('local_zendesk_usermap', ['zendesk_external_id' => $externalid]);
        if ($map) {
            return $this->db->get_record('user', ['id' => $map->userid, 'deleted' => 0]) ?: null;
        }

        $userid = $this->parse_userid_from_external_id($externalid);
        if ($userid <= 0) {
            return null;
        }

        return $this->db->get_record('user', ['id' => $userid, 'deleted' => 0]) ?: null;
    }

    /**
     * Find a Moodle user by email address.
     *
     * @param string $email Email address.
     * @return \stdClass|null
     */
    private function find_user_by_email(string $email): ?\stdClass {
        $sql = "SELECT *
                  FROM {user}
                 WHERE deleted = 0
                   AND " . $this->db->sql_equal('email', ':email', false, true);

        return $this->db->get_record_sql($sql, ['email' => $email]) ?: null;
    }

    /**
     * Build the agent-context response payload.
     *
     * @param \stdClass $user Moodle user record.
     * @return array
     */
    private function build_context_payload(\stdClass $user): array {
        return [
            'userid' => (int) $user->id,
            'externalid' => $this->build_user_external_id((int) $user->id),
            'fullname' => fullname($user),
            'email' => (string) $user->email,
            'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
            'auth' => (string) $user->auth,
            'lastaccess' => (int) ($user->lastaccess ?? 0),
            'courses' => $this->get_courses((int) $user->id),
        ];
    }

    /**
     * Get current or recent courses for the given user.
     *
     * @param int $userid Moodle user id.
     * @return array
     */
    private function get_courses(int $userid): array {
        $sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, COALESCE(ula.timeaccess, 0) AS timeaccess
                  FROM {course} c
                  JOIN {enrol} e
                    ON e.courseid = c.id
                   AND e.status = 0
                  JOIN {user_enrolments} ue
                    ON ue.enrolid = e.id
                   AND ue.userid = :userid
                   AND ue.status = 0
             LEFT JOIN {user_lastaccess} ula
                    ON ula.courseid = c.id
                   AND ula.userid = :useridlastaccess
                 WHERE c.id <> :siteid
              ORDER BY COALESCE(ula.timeaccess, 0) DESC, c.fullname ASC";
        $records = $this->db->get_records_sql($sql, [
            'userid' => $userid,
            'useridlastaccess' => $userid,
            'siteid' => SITEID,
        ], 0, 10);

        $courses = [];
        foreach ($records as $record) {
            $courses[] = [
                'id' => (int) $record->id,
                'shortname' => (string) $record->shortname,
                'fullname' => (string) $record->fullname,
            ];
        }

        return $courses;
    }

    /**
     * Parse a Moodle user id from a Zendesk external id.
     *
     * @param string $externalid External id from Zendesk.
     * @return int
     */
    private function parse_userid_from_external_id(string $externalid): int {
        $pattern = '#^mdl:' . preg_quote($this->get_instance_uuid(), '#') . ':user:(\d+)$#';
        if (!preg_match($pattern, trim($externalid), $matches)) {
            return 0;
        }

        return (int) ($matches[1] ?? 0);
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
     * Get or create the plugin instance UUID.
     *
     * @return string
     */
    private function get_instance_uuid(): string {
        $instanceuuid = trim((string) get_config(constants::COMPONENT, 'instanceuuid'));
        if ($instanceuuid !== '') {
            return $instanceuuid;
        }

        $uuid = $this->generate_uuid();
        set_config('instanceuuid', $uuid, constants::COMPONENT);

        return $uuid;
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
