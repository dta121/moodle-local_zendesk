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
 * Secure proxy for Zendesk-hosted attachment downloads.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_zendesk\local\service\zendesk_service;

require_login();

$context = context_system::instance();
require_capability('local/zendesk:viewownrequests', $context);

$id = required_param('id', PARAM_INT);
$encodedurl = required_param('url', PARAM_RAW_TRIMMED);
$canviewall = has_capability('local/zendesk:viewallrequests', $context);

$service = new zendesk_service();
$attachment = $service->get_attachment_response_for_user($id, $USER->id, $encodedurl, $canviewall);

// All header values are sanitised again here as a belt-and-braces measure;
// the service layer already strips CR/LF and forces the Content-Type into a
// safe allow-list and Content-Disposition into "attachment" form. The CSP
// sandbox + nosniff combination ensures even a slipped-through text/html
// cannot execute on the Moodle origin.
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Content-Type: ' . zendesk_service::sanitise_header_value((string) $attachment['contenttype']));
if (!empty($attachment['contentlength'])) {
    header('Content-Length: ' . (int) $attachment['contentlength']);
}
if (!empty($attachment['contentdisposition'])) {
    header('Content-Disposition: ' . zendesk_service::sanitise_header_value((string) $attachment['contentdisposition']));
}

echo $attachment['body'];
die;
