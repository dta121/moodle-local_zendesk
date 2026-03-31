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
 * Privacy subsystem implementation for local_zendesk.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy subsystem implementation for local_zendesk.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the user data stored and processed by this plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_zendesk_usermap', [
            'userid' => 'privacy:metadata:local_zendesk_usermap:userid',
            'zendesk_user_id' => 'privacy:metadata:local_zendesk_usermap:zendesk_user_id',
            'zendesk_external_id' => 'privacy:metadata:local_zendesk_usermap:zendesk_external_id',
            'zendesk_email' => 'privacy:metadata:local_zendesk_usermap:zendesk_email',
            'lastsyncedat' => 'privacy:metadata:local_zendesk_usermap:lastsyncedat',
            'timecreated' => 'privacy:metadata:local_zendesk_usermap:timecreated',
            'timemodified' => 'privacy:metadata:local_zendesk_usermap:timemodified',
        ], 'privacy:metadata:local_zendesk_usermap');

        $collection->add_database_table('local_zendesk_ticket', [
            'userid' => 'privacy:metadata:local_zendesk_ticket:userid',
            'usermapid' => 'privacy:metadata:local_zendesk_ticket:usermapid',
            'courseid' => 'privacy:metadata:local_zendesk_ticket:courseid',
            'contextid' => 'privacy:metadata:local_zendesk_ticket:contextid',
            'zendesk_ticket_id' => 'privacy:metadata:local_zendesk_ticket:zendesk_ticket_id',
            'zendesk_ticket_external_id' => 'privacy:metadata:local_zendesk_ticket:zendesk_ticket_external_id',
            'subject' => 'privacy:metadata:local_zendesk_ticket:subject',
            'body' => 'privacy:metadata:local_zendesk_ticket:body',
            'status' => 'privacy:metadata:local_zendesk_ticket:status',
            'custom_status_id' => 'privacy:metadata:local_zendesk_ticket:custom_status_id',
            'syncstate' => 'privacy:metadata:local_zendesk_ticket:syncstate',
            'lastremoteupdatedat' => 'privacy:metadata:local_zendesk_ticket:lastremoteupdatedat',
            'lastsyncattemptat' => 'privacy:metadata:local_zendesk_ticket:lastsyncattemptat',
            'lastsyncat' => 'privacy:metadata:local_zendesk_ticket:lastsyncat',
            'submissionerror' => 'privacy:metadata:local_zendesk_ticket:submissionerror',
            'timecreated' => 'privacy:metadata:local_zendesk_ticket:timecreated',
            'timemodified' => 'privacy:metadata:local_zendesk_ticket:timemodified',
        ], 'privacy:metadata:local_zendesk_ticket');

        $collection->add_external_location_link('zendesk_support_api', [
            'fullname' => 'privacy:metadata:zendesk_support_api:fullname',
            'email' => 'privacy:metadata:zendesk_support_api:email',
            'externalid' => 'privacy:metadata:zendesk_support_api:externalid',
            'subject' => 'privacy:metadata:zendesk_support_api:subject',
            'message' => 'privacy:metadata:zendesk_support_api:message',
            'status' => 'privacy:metadata:zendesk_support_api:status',
            'ticketid' => 'privacy:metadata:zendesk_support_api:ticketid',
        ], 'privacy:metadata:zendesk_support_api');

        $collection->add_external_location_link('zendesk_agent_context', [
            'userid' => 'privacy:metadata:zendesk_agent_context:userid',
            'fullname' => 'privacy:metadata:zendesk_agent_context:fullname',
            'email' => 'privacy:metadata:zendesk_agent_context:email',
            'profileurl' => 'privacy:metadata:zendesk_agent_context:profileurl',
            'auth' => 'privacy:metadata:zendesk_agent_context:auth',
            'lastaccess' => 'privacy:metadata:zendesk_agent_context:lastaccess',
            'courses' => 'privacy:metadata:zendesk_agent_context:courses',
            'ipaddress' => 'privacy:metadata:zendesk_agent_context:ipaddress',
            'location' => 'privacy:metadata:zendesk_agent_context:location',
        ], 'privacy:metadata:zendesk_agent_context');

        return $collection;
    }

    /**
     * Get the list of contexts containing user information for the specified user.
     *
     * @param int $userid The user ID to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $hasdata = $DB->record_exists('local_zendesk_usermap', ['userid' => $userid])
            || $DB->record_exists('local_zendesk_ticket', ['userid' => $userid]);

        if ($hasdata) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the users in this context/component combination.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        $sql = "SELECT userid
                  FROM {local_zendesk_usermap}
                UNION
                SELECT userid
                  FROM {local_zendesk_ticket}";

        $userids = array_map('intval', $DB->get_fieldset_sql($sql));
        if ($userids !== []) {
            $userlist->add_users($userids);
        }
    }

    /**
     * Export personal data for the provided user and contexts.
     *
     * @param approved_contextlist $contextlist The approved context list.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0 || !self::contains_system_context($contextlist->get_contexts())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $systemcontext = \context_system::instance();
        $exportdata = [];
        $usermap = $DB->get_record('local_zendesk_usermap', ['userid' => $userid]);
        if ($usermap) {
            $exportdata['user_mapping'] = (object) [
                'zendesk_user_id' => (int) $usermap->zendesk_user_id,
                'zendesk_external_id' => $usermap->zendesk_external_id,
                'zendesk_email' => $usermap->zendesk_email,
                'lastsyncedat' => transform::datetime($usermap->lastsyncedat),
                'timecreated' => transform::datetime($usermap->timecreated),
                'timemodified' => transform::datetime($usermap->timemodified),
            ];
        }

        $tickets = $DB->get_records('local_zendesk_ticket', ['userid' => $userid], 'timecreated ASC');
        if ($tickets) {
            $coursenames = self::get_course_name_map($tickets);
            $exportedtickets = [];
            foreach ($tickets as $ticket) {
                $exportedtickets[] = (object) [
                    'local_ticket_id' => (int) $ticket->id,
                    'uuid' => $ticket->uuid,
                    'courseid' => $ticket->courseid ? (int) $ticket->courseid : null,
                    'coursename' => $ticket->courseid && isset($coursenames[(int) $ticket->courseid])
                        ? $coursenames[(int) $ticket->courseid]
                        : null,
                    'contextid' => $ticket->contextid ? (int) $ticket->contextid : null,
                    'zendesk_ticket_id' => $ticket->zendesk_ticket_id ? (int) $ticket->zendesk_ticket_id : null,
                    'zendesk_ticket_external_id' => $ticket->zendesk_ticket_external_id,
                    'subject' => $ticket->subject,
                    'body' => $ticket->body,
                    'status' => $ticket->status,
                    'custom_status_id' => $ticket->custom_status_id ? (int) $ticket->custom_status_id : null,
                    'syncstate' => $ticket->syncstate,
                    'lastremoteupdatedat' => $ticket->lastremoteupdatedat ? transform::datetime($ticket->lastremoteupdatedat) : null,
                    'lastsyncattemptat' => $ticket->lastsyncattemptat ? transform::datetime($ticket->lastsyncattemptat) : null,
                    'lastsyncat' => $ticket->lastsyncat ? transform::datetime($ticket->lastsyncat) : null,
                    'submissionerror' => $ticket->submissionerror,
                    'timecreated' => transform::datetime($ticket->timecreated),
                    'timemodified' => transform::datetime($ticket->timemodified),
                ];
            }

            $exportdata['tickets'] = $exportedtickets;
        }

        if ($exportdata !== []) {
            writer::with_context($systemcontext)->export_data(['zendesk_support'], (object) $exportdata);
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context to delete data from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('local_zendesk_ticket');
        $DB->delete_records('local_zendesk_usermap');
    }

    /**
     * Delete all user data for the specified user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0 || !self::contains_system_context($contextlist->get_contexts())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $DB->delete_records('local_zendesk_ticket', ['userid' => $userid]);
        $DB->delete_records('local_zendesk_usermap', ['userid' => $userid]);
    }

    /**
     * Delete data for multiple users in a single context.
     *
     * @param approved_userlist $userlist The approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        $userids = array_map('intval', $userlist->get_userids());
        if ($userids === []) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_zendesk_ticket', "userid {$insql}", $params);
        $DB->delete_records_select('local_zendesk_usermap', "userid {$insql}", $params);
    }

    /**
     * Determine whether the approved contexts include the system context.
     *
     * @param array $contexts The approved contexts.
     * @return bool
     */
    private static function contains_system_context(array $contexts): bool {
        foreach ($contexts as $context) {
            if ($context instanceof \context_system) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch a course name map for the exported tickets.
     *
     * @param array $tickets Ticket records.
     * @return array
     */
    private static function get_course_name_map(array $tickets): array {
        global $DB;

        $courseids = array_unique(array_filter(array_map(static function(\stdClass $ticket): int {
            return (int) ($ticket->courseid ?? 0);
        }, $tickets)));

        if ($courseids === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_select_menu('course', "id {$insql}", $params, '', 'id, fullname');

        return array_map('strval', $courses);
    }
}
