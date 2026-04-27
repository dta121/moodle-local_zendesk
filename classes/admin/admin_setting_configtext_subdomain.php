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
 * Strict admin setting for the Zendesk subdomain.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\admin;

/**
 * Admin setting that enforces a clean DNS label for the Zendesk subdomain.
 *
 * The runtime continues to apply normalisation (strip scheme, path, and a
 * trailing .zendesk.com), so legacy values such as
 * "https://myorg.zendesk.com/" remain acceptable; this class adds a strict
 * regex check so that malformed values like "evil.com#" or "host/path/extra"
 * cannot be saved silently and weaken the SSRF host allow-list used by the
 * attachment proxy and JWT SSO endpoint.
 */
class admin_setting_configtext_subdomain extends \admin_setting_configtext {
    /**
     * Validate the configured Zendesk subdomain.
     *
     * @param string $data Raw setting value.
     * @return true|string True when valid; an error string otherwise.
     */
    public function validate($data) {
        $parent = parent::validate($data);
        if ($parent !== true) {
            return $parent;
        }

        $data = trim((string) $data);
        if ($data === '') {
            return true;
        }

        $candidate = preg_replace('#^https?://#i', '', $data);
        $candidate = preg_replace('#/.*$#', '', (string) $candidate);
        $candidate = preg_replace('#\.zendesk\.com$#i', '', (string) $candidate);
        $candidate = trim((string) $candidate);

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,62}[a-z0-9])?$/i', $candidate)) {
            return get_string('subdomaininvalid', 'local_zendesk');
        }

        return true;
    }
}
