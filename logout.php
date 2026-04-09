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
 * Handles the Zendesk SSO logout return flow.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignoreFile -- Zendesk may redirect here after end-user sign-out.
require_once(__DIR__ . '/../../config.php');

use local_zendesk\local\service\jwt_sso_service;

if (isloggedin() && !isguestuser()) {
    require_login(null, false);
}

$message = optional_param('message', '', PARAM_RAW_TRIMMED);
$kind = optional_param('kind', '', PARAM_ALPHAEXT);
$brandid = optional_param('brand_id', '', PARAM_RAW_TRIMMED);
$email = optional_param('email', '', PARAM_RAW_TRIMMED);
$externalid = optional_param('external_id', '', PARAM_RAW_TRIMMED);

$service = new jwt_sso_service();
$service->log_logout_event($message, $kind, $brandid, $email, $externalid);

$redirecturl = $service->get_logout_redirect($message, $kind);
$notificationmessage = get_string('ssologoutinfo', 'local_zendesk');
$notificationtype = \core\output\notification::NOTIFY_INFO;

if ($kind === 'error') {
    $notificationmessage = $message !== ''
        ? get_string('ssologouterror', 'local_zendesk', $message)
        : get_string('ssofailed', 'local_zendesk');
    $notificationtype = \core\output\notification::NOTIFY_ERROR;
}

redirect($redirecturl, $notificationmessage, null, $notificationtype);
