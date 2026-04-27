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
 * Adhoc task that suspends the Zendesk end user matching a deleted Moodle user.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\task;

use local_zendesk\local\service\zendesk_service;

/**
 * Suspend the Zendesk end user that was previously mapped to a now-deleted
 * Moodle user, then drop the local mapping row (security review F13).
 *
 * The task is queued asynchronously by the user_deleted observer so the
 * Moodle delete operation is never blocked on a Zendesk API call.
 */
final class suspend_zendesk_user extends \core\task\adhoc_task {
    /**
     * Get the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksuspendzendeskuser', 'local_zendesk');
    }

    /**
     * Run the suspend.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $zendeskuserid = isset($data->zendeskuserid) ? (int) $data->zendeskuserid : 0;
        $usermapid = isset($data->usermapid) ? (int) $data->usermapid : 0;

        if ($zendeskuserid <= 0) {
            mtrace('[local_zendesk] suspend_zendesk_user: missing Zendesk user id, skipping.');
            return;
        }

        $service = new zendesk_service();
        if (!$service->is_enabled()) {
            mtrace('[local_zendesk] suspend_zendesk_user: plugin disabled, skipping suspend for Zendesk user '
                . $zendeskuserid . '.');
            return;
        }

        if (!$service->is_configured()) {
            mtrace('[local_zendesk] suspend_zendesk_user: plugin not configured, skipping suspend for Zendesk user '
                . $zendeskuserid . '.');
            return;
        }

        $service->suspend_remote_user($zendeskuserid);
        mtrace('[local_zendesk] suspend_zendesk_user: suspended Zendesk user ' . $zendeskuserid . '.');

        if ($usermapid > 0) {
            $DB->delete_records('local_zendesk_usermap', ['id' => $usermapid]);
        }
    }
}
