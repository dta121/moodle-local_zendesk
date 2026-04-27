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
 * Launches Zendesk Help Center through JWT SSO.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_zendesk\local\service\jwt_sso_service;

require_login();

$context = context_system::instance();
require_capability('local/zendesk:usehelpcenter', $context);

$target = optional_param('target', '', PARAM_ALPHAEXT);
$returnto = optional_param('return_to', '', PARAM_RAW_TRIMMED);
$url = new moodle_url('/local/zendesk/sso.php', [
    'target' => $target,
    'return_to' => $returnto,
]);
$service = new jwt_sso_service();

try {
    if (isguestuser()) {
        throw new moodle_exception('noguest', 'moodle');
    }

    $claims = $service->build_claims($USER);
    $token = $service->build_token($claims);
    $resolvedreturnto = $service->resolve_return_to($returnto, $target);
} catch (Throwable $e) {
    $redirecturl = new moodle_url('/local/zendesk/index.php');
    $message = $e instanceof moodle_exception
        ? $e->getMessage()
        : get_string('ssofailed', 'local_zendesk');

    redirect($redirecturl, $message, null, \core\output\notification::NOTIFY_ERROR);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('openhelpcenter', 'local_zendesk'));
$PAGE->set_heading(get_string('openhelpcenter', 'local_zendesk'));

// Block iframing of the SSO page so a clickjacking attacker cannot force a
// silent JWT submission to Zendesk on behalf of the authenticated user.
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");

$templatecontext = [
    'formid' => 'local-zendesk-sso-form',
    'formaction' => $service->get_zendesk_jwt_endpoint(),
    'jwt' => $token,
    'hasreturnto' => $resolvedreturnto !== '',
    'returnto' => $resolvedreturnto,
    'heading' => get_string('ssoredirectheading', 'local_zendesk'),
    'body' => get_string('ssoredirectbody', 'local_zendesk'),
    'submitlabel' => get_string('ssocontinuebutton', 'local_zendesk'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_zendesk/sso_redirect', $templatecontext);
echo $OUTPUT->footer();
