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
 * Shows the request conversation view.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_zendesk\form\reply_form;
use local_zendesk\local\service\jwt_sso_service;
use local_zendesk\local\service\zendesk_service;

require_login();

$context = context_system::instance();
require_capability('local/zendesk:viewownrequests', $context);

$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/zendesk/view.php', ['id' => $id]);
$service = new zendesk_service();
$ssoservice = new jwt_sso_service();
$canviewall = has_capability('local/zendesk:viewallrequests', $context);
$cansubmit = has_capability('local/zendesk:submitrequest', $context);
$canusehelpcenter = has_capability('local/zendesk:usehelpcenter', $context)
    && $ssoservice->is_enabled()
    && $ssoservice->is_configured();
$notification = '';
$notificationtype = 'danger';

try {
    $ticket = $service->get_request_for_user($id, $USER->id, $canviewall);
} catch (dml_missing_record_exception $e) {
    throw new moodle_exception('invalidticketid', 'local_zendesk');
}

if (!$cansubmit) {
    $ticket['hasreplyform'] = false;
}

$replyformhtml = '';
if (!empty($ticket['hasreplyform'])) {
    $replyform = new reply_form($url, ['buttonlabel' => $ticket['replyactionlabel']]);

    if ($data = $replyform->get_data()) {
        try {
            $result = $service->reply_to_request($id, $USER->id, (string) $data->replymessage, $canviewall);
            $redirecturl = new moodle_url('/local/zendesk/view.php', ['id' => $result->localticketid]);

            if ($result->action === 'followup') {
                $message = $result->pendingconfirmation
                    ? get_string('followupcreatedpending', 'local_zendesk')
                    : get_string('followupcreated', 'local_zendesk');
            } else if ($result->action === 'reply') {
                $message = get_string('replysent', 'local_zendesk');
            } else {
                $message = get_string('ticketreopened', 'local_zendesk');
            }

            redirect($redirecturl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (Throwable $e) {
            $notification = $e instanceof moodle_exception
                ? $e->getMessage()
                : get_string('replysubmissionfailed', 'local_zendesk');
        }
    }

    ob_start();
    $replyform->display();
    $replyformhtml = ob_get_clean();
}

$ticket['backurl'] = (new moodle_url('/local/zendesk/index.php'))->out(false);
$ticket['backlabel'] = get_string('backtorequests', 'local_zendesk');
$ticket['conversationeyebrow'] = get_string('conversationeyebrow', 'local_zendesk');
$ticket['createdlabel'] = get_string('createdlabel', 'local_zendesk');
$ticket['messageauthorinitial'] = get_string('studentreplyinitial', 'local_zendesk');
$ticket['messageauthorlabel'] = get_string('studentreplyauthor', 'local_zendesk');
$ticket['messagesubtitle'] = get_string('messagesubtitle', 'local_zendesk');
$ticket['updatedlabel'] = get_string('updatedlabel', 'local_zendesk');
$ticket['requestnumberlabel'] = get_string('requestnumberlabel', 'local_zendesk');
$ticket['replyformhtml'] = $replyformhtml;
$ticket['hasreplyformhtml'] = $replyformhtml !== '';
$ticket['supportreplyinitial'] = get_string('supportreplyinitial', 'local_zendesk');
$ticket['hashelpcenterlink'] = $canusehelpcenter;
$ticket['helpcenterurl'] = (new moodle_url('/local/zendesk/sso.php', ['target' => 'requests']))->out(false);
$ticket['helpcenterlabel'] = $ssoservice->get_button_label();

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($ticket['subject']);
$PAGE->set_heading(get_string('requestdetailsheading', 'local_zendesk'));
$PAGE->requires->css(new moodle_url('/local/zendesk/request_detail.css'));

echo $OUTPUT->header();
if ($notification !== '') {
    echo $OUTPUT->notification($notification, $notificationtype);
}
echo $OUTPUT->render_from_template('local_zendesk/request_detail', $ticket);
echo $OUTPUT->footer();
