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
 * Handles support request submission.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_zendesk\form\request_form;
use local_zendesk\local\constants;
use local_zendesk\local\service\zendesk_service;

require_login();

$context = context_system::instance();
require_capability('local/zendesk:submitrequest', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);

// Drop courseid silently if the caller cannot view that course. Without this
// check a tampered URL parameter would be accepted purely on existence and
// then serialised into the Zendesk ticket as a "moodle_course_<id>" tag,
// polluting downstream routing and reporting.
if ($courseid > 0) {
    if (!$DB->record_exists('course', ['id' => $courseid])) {
        $courseid = 0;
    } else {
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext || !has_capability('moodle/course:view', $coursecontext)) {
            $courseid = 0;
        }
    }
}

// Same idea for contextid: it must exist and the caller must have at least
// read-style access at it. moodle/course:view resolves at any context level
// (system, course category, course, module, block) and is the permissive but
// non-trivial gate used elsewhere for "can this user see something here?".
if ($contextid > 0) {
    $ticketcontext = context::instance_by_id($contextid, IGNORE_MISSING);
    if (!$ticketcontext || !has_capability('moodle/course:view', $ticketcontext)) {
        $contextid = 0;
    }
}

$url = new moodle_url('/local/zendesk/request.php', ['courseid' => $courseid, 'contextid' => $contextid]);
$service = new zendesk_service();
$form = new request_form($url, ['defaults' => ['courseid' => $courseid, 'contextid' => $contextid]]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('newrequest', 'local_zendesk'));
$PAGE->set_heading(get_string('newrequest', 'local_zendesk'));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/zendesk/index.php'));
}

$notification = '';
if ($data = $form->get_data()) {
    try {
        $ticket = $service->submit_request($USER->id, (array) $data);
        $message = get_string('requestsubmitted', 'local_zendesk');
        $type = \core\output\notification::NOTIFY_SUCCESS;

        if ($ticket->syncstate === constants::STATE_CONFIRMINGCREATE) {
            $message = get_string('submissionpendingconfirmation', 'local_zendesk');
            $type = \core\output\notification::NOTIFY_WARNING;
        }

        redirect(new moodle_url('/local/zendesk/view.php', ['id' => $ticket->id]), $message, null, $type);
    } catch (Throwable $e) {
        debugging('[local_zendesk] Request submission failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $notification = get_string('requestsubmissionfailed', 'local_zendesk');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('newrequest', 'local_zendesk'));

if (!$service->is_enabled()) {
    echo $OUTPUT->notification(get_string('zendeskdisabled', 'local_zendesk'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (!$service->is_configured()) {
    echo $OUTPUT->notification(get_string('notconfiguredmessage', 'local_zendesk'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if ($notification !== '') {
    echo $OUTPUT->notification($notification, 'danger');
}

$form->display();
echo $OUTPUT->footer();
