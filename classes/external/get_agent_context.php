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
 * External function for Zendesk agent context lookups.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_zendesk\local\service\agent_context_service;

defined('MOODLE_INTERNAL') || die();

/**
 * External function for Zendesk agent context lookups.
 *
 * @package   local_zendesk
 */
final class get_agent_context extends external_api {
    /**
     * Describe the request parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'externalid' => new external_value(PARAM_RAW_TRIMMED, 'Zendesk external ID.', VALUE_DEFAULT, ''),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Zendesk requester email.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return Moodle context for a Zendesk requester.
     *
     * @param string $externalid Zendesk external id.
     * @param string $email Zendesk requester email.
     * @return array
     */
    public static function execute(string $externalid = '', string $email = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'externalid' => $externalid,
            'email' => $email,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/zendesk:viewallrequests', $context);

        $service = new agent_context_service();

        return $service->get_agent_context($params['externalid'], $params['email']);
    }

    /**
     * Describe the response payload.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Moodle user id.'),
            'externalid' => new external_value(PARAM_RAW_TRIMMED, 'Zendesk external ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Moodle full name.'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Moodle email address.'),
            'profileurl' => new external_value(PARAM_URL, 'Moodle profile URL.'),
            'auth' => new external_value(PARAM_ALPHANUMEXT, 'Moodle authentication type.'),
            'ipaddress' => new external_value(PARAM_RAW_TRIMMED, 'Moodle last known IP address.', VALUE_DEFAULT, ''),
            'location' => new external_value(PARAM_TEXT, 'Location resolved from the last known IP address.', VALUE_DEFAULT, ''),
            'lastaccess' => new external_value(PARAM_INT, 'Unix timestamp of the user last access time.'),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course id.'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
            ]), 'Current or recent Moodle courses.', VALUE_DEFAULT, []),
        ]);
    }
}
