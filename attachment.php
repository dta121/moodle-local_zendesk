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

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $attachment['contenttype']);
if (!empty($attachment['contentlength'])) {
    header('Content-Length: ' . (int) $attachment['contentlength']);
}
if (!empty($attachment['contentdisposition'])) {
    header('Content-Disposition: ' . $attachment['contentdisposition']);
}

echo $attachment['body'];
die;
