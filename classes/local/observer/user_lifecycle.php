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
 * Moodle user-lifecycle observer for local_zendesk.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\local\observer;

use core\event\user_deleted;
use local_zendesk\task\suspend_zendesk_user;

/**
 * Observer that suspends the matching Zendesk end user when a Moodle user is
 * deleted, so a future user with the same email cannot accidentally inherit
 * the deleted Zendesk identity (security review F13).
 *
 * The work runs in an adhoc task rather than inline to keep the user-delete
 * code path fast and resilient to Zendesk API outages.
 */
final class user_lifecycle {
    /**
     * Handle the core user_deleted event.
     *
     * @param user_deleted $event Moodle event payload.
     * @return void
     */
    public static function user_deleted(user_deleted $event): void {
        global $DB;

        $userid = (int) $event->objectid;
        if ($userid <= 0) {
            return;
        }

        $usermap = $DB->get_record('local_zendesk_usermap', ['userid' => $userid]);
        if (!$usermap || empty($usermap->zendesk_user_id)) {
            return;
        }

        $task = new suspend_zendesk_user();
        $task->set_custom_data((object) [
            'zendeskuserid' => (int) $usermap->zendesk_user_id,
            'usermapid' => (int) $usermap->id,
        ]);
        $task->set_component('local_zendesk');

        \core\task\manager::queue_adhoc_task($task);
    }
}
