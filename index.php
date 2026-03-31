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

use local_zendesk\local\service\jwt_sso_service;
use local_zendesk\local\service\zendesk_service;

require_login();

$context = context_system::instance();
require_capability('local/zendesk:viewownrequests', $context);

$url = new moodle_url('/local/zendesk/index.php');
$service = new zendesk_service();

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_zendesk'));
$PAGE->set_heading(get_string('requestlistheading', 'local_zendesk'));

$requests = $service->get_user_requests($USER->id, 20);
$ssoservice = new jwt_sso_service();
$hashelpcenterlink = has_capability('local/zendesk:usehelpcenter', $context)
    && $ssoservice->is_enabled()
    && $ssoservice->is_configured();
$notification = '';
if (!$service->is_enabled()) {
    $notification = get_string('zendeskdisabled', 'local_zendesk');
} else if (!$service->is_configured()) {
    $notification = get_string('notconfiguredmessage', 'local_zendesk');
}

$templatecontext = [
    'newrequesturl' => (new moodle_url('/local/zendesk/request.php'))->out(false),
    'newrequestlabel' => get_string('newrequest', 'local_zendesk'),
    'hashelpcenterlink' => $hashelpcenterlink,
    'helpcenterurl' => (new moodle_url('/local/zendesk/sso.php', ['target' => 'requests']))->out(false),
    'helpcenterlabel' => $ssoservice->get_button_label(),
    'createdlabel' => get_string('createdlabel', 'local_zendesk'),
    'updatedlabel' => get_string('updatedlabel', 'local_zendesk'),
    'emptylabel' => get_string('norequests', 'local_zendesk'),
    'requests' => $requests,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('requestlistheading', 'local_zendesk'));

if ($notification !== '') {
    echo $OUTPUT->notification($notification, 'warning');
}

echo $OUTPUT->render_from_template('local_zendesk/request_list', $templatecontext);
echo $OUTPUT->footer();
