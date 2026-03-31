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
$url = new moodle_url('/local/zendesk/view.php', ['id' => $id]);
$service = new zendesk_service();
$canviewall = has_capability('local/zendesk:viewallrequests', $context);

try {
    $ticket = $service->get_request_for_user($id, $USER->id, $canviewall);
} catch (dml_missing_record_exception $e) {
    throw new moodle_exception('invalidticketid', 'local_zendesk');
}

$ticket['backurl'] = (new moodle_url('/local/zendesk/index.php'))->out(false);
$ticket['backlabel'] = get_string('backtorequests', 'local_zendesk');
$ticket['createdlabel'] = get_string('createdlabel', 'local_zendesk');
$ticket['updatedlabel'] = get_string('updatedlabel', 'local_zendesk');
$ticket['requestnumberlabel'] = get_string('requestnumberlabel', 'local_zendesk');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($ticket['subject']);
$PAGE->set_heading(get_string('requestdetailsheading', 'local_zendesk'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('requestdetailsheading', 'local_zendesk'));
echo $OUTPUT->render_from_template('local_zendesk/request_detail', $ticket);
echo $OUTPUT->footer();
