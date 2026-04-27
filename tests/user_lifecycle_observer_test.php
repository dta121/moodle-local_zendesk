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
 * PHPUnit tests for the user-lifecycle observer.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

/**
 * Tests for \local_zendesk\local\observer\user_lifecycle.
 *
 * The observer queues a suspend_zendesk_user adhoc task whenever a Moodle
 * user with a Zendesk mapping is deleted, so a future Moodle user with the
 * same email cannot inherit the deleted Zendesk identity via Zendesk's
 * email-based create-or-update upsert (security review F13).
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\observer\user_lifecycle
 */
final class user_lifecycle_observer_test extends \advanced_testcase {
    /**
     * Reset state before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('instanceuuid', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'local_zendesk');
    }

    /**
     * Deleting a Moodle user that has a usermap row queues exactly one
     * suspend task with the matching Zendesk user id.
     *
     * @return void
     */
    public function test_user_deleted_with_mapping_queues_suspend_task(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user(['email' => 'mapped@example.com']);
        $now = time();
        $DB->insert_record('local_zendesk_usermap', (object) [
            'userid' => $user->id,
            'zendesk_user_id' => 5555,
            'zendesk_external_id' => 'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:user:' . $user->id,
            'zendesk_email' => 'mapped@example.com',
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->assertCount(0, $DB->get_records('task_adhoc'));

        delete_user($user);

        $tasks = $DB->get_records('task_adhoc');
        $this->assertCount(1, $tasks);

        $task = reset($tasks);
        $this->assertSame(
            '\\local_zendesk\\task\\suspend_zendesk_user',
            (string) $task->classname
        );

        $custom = json_decode((string) $task->customdata);
        $this->assertSame(5555, (int) $custom->zendeskuserid);
        $this->assertGreaterThan(0, (int) $custom->usermapid);
    }

    /**
     * Deleting a Moodle user that has no usermap row queues nothing.
     *
     * @return void
     */
    public function test_user_deleted_without_mapping_queues_nothing(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        delete_user($user);

        $this->assertCount(0, $DB->get_records('task_adhoc'));
    }

    /**
     * A usermap row whose zendesk_user_id is 0 (placeholder / failed sync) is
     * skipped because there is no remote user to suspend.
     *
     * @return void
     */
    public function test_user_deleted_with_zero_zendesk_id_queues_nothing(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $DB->insert_record('local_zendesk_usermap', (object) [
            'userid' => $user->id,
            'zendesk_user_id' => 0,
            'zendesk_external_id' => 'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:user:' . $user->id,
            'zendesk_email' => $user->email,
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        delete_user($user);

        $this->assertCount(0, $DB->get_records('task_adhoc'));
    }
}
