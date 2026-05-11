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
 * Builds Moodle context payloads for the Zendesk agent app.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\local\service;

use local_zendesk\local\constants;

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
     * Identity is bound to the Zendesk requester ID recorded on
     * local_zendesk_usermap.zendesk_user_id. The caller may also supply the
     * current external id and email as optional consistency checks, but neither
     * value is trusted as the primary selector because mutable caller-supplied
     * identifiers previously let the agent app look up the wrong Moodle user
     * (security review F1).
     *
     * @param int $zendeskuserid Zendesk requester user id.
     * @param string|null $externalid Zendesk external id.
     * @param string|null $email Zendesk requester email.
     * @return array
     */
    public function get_agent_context(int $zendeskuserid, ?string $externalid, ?string $email): array {
        $zendeskuserid = (int) $zendeskuserid;
        $externalid = trim((string) $externalid);
        $email = trim((string) $email);

        if ($zendeskuserid <= 0) {
            throw new \invalid_parameter_exception(get_string('agentcontextmissinginput', constants::COMPONENT));
        }

        if ($email !== '' && !validate_email($email)) {
            throw new \invalid_parameter_exception(get_string('agentcontextinvalidemail', constants::COMPONENT));
        }

        $user = $this->find_user_via_usermap($zendeskuserid, $externalid, $email);

        if (!$user) {
            throw new \moodle_exception('agentcontextnotfound', constants::COMPONENT);
        }

        return $this->build_context_payload($user);
    }

    /**
     * Resolve a Moodle user from the bound Zendesk requester id via the local
     * mapping table.
     *
     * Optional external id and email values act only as consistency checks on
     * the mapping row already selected by zendesk_user_id. Ambiguous mappings
     * are rejected so the service never returns a context payload for an
     * identity that is not uniquely bound.
     *
     * @param int $zendeskuserid Zendesk requester user id.
     * @param string $externalid Optional Zendesk requester external id.
     * @param string $email Optional Zendesk requester email.
     * @return \stdClass|null
     */
    private function find_user_via_usermap(int $zendeskuserid, string $externalid, string $email): ?\stdClass {
        $maps = $this->db->get_records('local_zendesk_usermap', ['zendesk_user_id' => $zendeskuserid], 'id ASC');
        if (count($maps) !== 1) {
            return null;
        }

        $map = reset($maps);
        if (!$map) {
            return null;
        }

        if ($externalid !== '' && (string) $map->zendesk_external_id !== $externalid) {
            return null;
        }

        if ($email !== '' && strcasecmp((string) $map->zendesk_email, $email) !== 0) {
            return null;
        }

        return $this->db->get_record('user', ['id' => $map->userid, 'deleted' => 0]) ?: null;
    }

    /**
     * Build the agent-context response payload.
     *
     * @param \stdClass $user Moodle user record.
     * @return array
     */
    private function build_context_payload(\stdClass $user): array {
        $ipcontext = $this->get_ip_context($user);

        // Trimmed to exactly the fields the Zendesk Student Lookup sidebar app
        // still needs after F1. The sidebar now binds lookups to the Zendesk
        // requester ID, so the stable external id no longer needs to cross the
        // trust boundary back to agents.
        return [
            'fullname' => fullname($user),
            'email' => (string) $user->email,
            'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
            'auth' => (string) $user->auth,
            'ipaddress' => $ipcontext['ipaddress'],
            'location' => $ipcontext['location'],
            'courses' => $this->get_courses((int) $user->id),
        ];
    }

    /**
     * Build the visible IP address and location details for a user when allowed.
     *
     * @param \stdClass $user Moodle user record.
     * @return array{ipaddress:string, location:string}
     */
    private function get_ip_context(\stdClass $user): array {
        $context = \context_system::instance();
        if (!has_capability('moodle/user:viewlastip', $context)) {
            return [
                'ipaddress' => '',
                'location' => '',
            ];
        }

        $ipaddress = trim((string) ($user->lastip ?? ''));
        if ($ipaddress === '') {
            return [
                'ipaddress' => '',
                'location' => '',
            ];
        }

        return [
            'ipaddress' => clean_param($ipaddress, PARAM_NOTAGS),
            'location' => $this->format_location($this->lookup_location_parts($ipaddress)),
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
     * Resolve city, state/region, and country for an IP address using Moodle's configured provider.
     *
     * @param string $ipaddress IP address to resolve.
     * @return array{city:string, region:string, country:string}
     */
    private function lookup_location_parts(string $ipaddress): array {
        global $CFG;

        $parts = [
            'city' => '',
            'region' => '',
            'country' => '',
        ];

        if ($ipaddress === '' || !filter_var($ipaddress, FILTER_VALIDATE_IP)) {
            return $parts;
        }

        try {
            if (!empty($CFG->geoip2file) && file_exists($CFG->geoip2file)) {
                return $this->lookup_geoip2_location($ipaddress, (string) $CFG->geoip2file);
            }

            if (!empty($CFG->geopluginapikey)) {
                return $this->lookup_geoplugin_location($ipaddress, (string) $CFG->geopluginapikey);
            }

            require_once($CFG->dirroot . '/iplookup/lib.php');
            $info = iplookup_find_location($ipaddress);
            if (!empty($info['error'])) {
                return $parts;
            }

            return [
                'city' => $this->clean_location_part($info['city'] ?? ''),
                'region' => '',
                'country' => $this->clean_location_part($info['country'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return $parts;
        }
    }

    /**
     * Resolve location parts using a local GeoIP2 database.
     *
     * @param string $ipaddress IP address to resolve.
     * @param string $databasepath GeoIP database path.
     * @return array{city:string, region:string, country:string}
     */
    private function lookup_geoip2_location(string $ipaddress, string $databasepath): array {
        $reader = new \GeoIp2\Database\Reader($databasepath);
        $record = $reader->city($ipaddress);
        $countries = get_string_manager()->get_list_of_countries(true);
        $countrycode = trim((string) ($record->country->isoCode ?? ''));
        $country = $countrycode !== '' && isset($countries[$countrycode])
            ? (string) $countries[$countrycode]
            : (string) ($record->country->name ?? '');

        return [
            'city' => $this->clean_location_part($record->city->name ?? ''),
            'region' => $this->clean_location_part($record->mostSpecificSubdivision->name ?? ''),
            'country' => $this->clean_location_part($country),
        ];
    }

    /**
     * Resolve location parts using the configured geoPlugin integration.
     *
     * @param string $ipaddress IP address to resolve.
     * @param string $apikey geoPlugin API key.
     * @return array{city:string, region:string, country:string}
     */
    private function lookup_geoplugin_location(string $ipaddress, string $apikey): array {
        global $CFG;

        $parts = [
            'city' => '',
            'region' => '',
            'country' => '',
        ];

        if (strpos($ipaddress, ':') !== false) {
            return $parts;
        }

        require_once($CFG->libdir . '/filelib.php');

        $requesturl = new \moodle_url('https://api.geoplugin.com', [
            'ip' => $ipaddress,
            'auth' => $apikey,
        ]);
        $response = download_file_content($requesturl->out(false), null, null, true);
        if (!is_object($response) || (int) ($response->response_code ?? 0) !== 200) {
            return $parts;
        }

        $ipdata = json_decode((string) ($response->results ?? ''), true);
        if (!is_array($ipdata)) {
            return $parts;
        }

        $countries = get_string_manager()->get_list_of_countries(true);
        $countrycode = clean_param((string) ($ipdata['geoplugin_countryCode'] ?? ''), PARAM_ALPHANUMEXT);
        $country = $countrycode !== '' && isset($countries[$countrycode])
            ? (string) $countries[$countrycode]
            : (string) ($ipdata['geoplugin_countryName'] ?? '');

        return [
            'city' => $this->clean_location_part($ipdata['geoplugin_city'] ?? ''),
            'region' => $this->clean_location_part($ipdata['geoplugin_regionName'] ?? ''),
            'country' => $this->clean_location_part($country),
        ];
    }

    /**
     * Build a human-friendly location label from city, region, and country parts.
     *
     * @param array $parts Location parts with city, region, and country keys.
     * @return string
     */
    private function format_location(array $parts): string {
        $values = [];
        foreach (['city', 'region', 'country'] as $key) {
            $value = trim((string) ($parts[$key] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode(', ', $values);
    }

    /**
     * Clean a city, region, or country value before returning it to Zendesk.
     *
     * @param mixed $value Raw location value.
     * @return string
     */
    private function clean_location_part($value): string {
        return clean_param(trim((string) $value), PARAM_TEXT);
    }
}
