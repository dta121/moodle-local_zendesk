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
 * PHPUnit tests for the suspend_zendesk_user adhoc task.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\task\suspend_zendesk_user;

/**
 * Tests for the deleted-user Zendesk suspend task.
 *
 * Temporary misconfiguration must re-queue the cleanup instead of treating it
 * as a success, otherwise deleted-user Zendesk identities can remain active.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\task\suspend_zendesk_user
 */
final class suspend_zendesk_user_task_test extends \advanced_testcase {
    /**
     * Reset state before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A disabled plugin re-queues the suspend task instead of dropping it.
     *
     * @return void
     */
    public function test_execute_requeues_when_plugin_disabled(): void {
        global $DB;

        $usermapid = $this->create_usermap_row(5555);
        $task = new suspend_zendesk_user();
        $task->set_custom_data((object) [
            'zendeskuserid' => 5555,
            'usermapid' => $usermapid,
        ]);

        $before = time();
        $this->expectOutputRegex('/plugin disabled, re-queued suspend for Zendesk user 5555 in 900 seconds\./');
        $task->execute();

        $this->assertTrue($DB->record_exists('local_zendesk_usermap', ['id' => $usermapid]));

        $records = $DB->get_records('task_adhoc');
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame('\\local_zendesk\\task\\suspend_zendesk_user', (string) $record->classname);
        $this->assertGreaterThanOrEqual($before + 900, (int) $record->nextruntime);
        $this->assertLessThanOrEqual(time() + 905, (int) $record->nextruntime);

        $custom = json_decode((string) $record->customdata);
        $this->assertSame(5555, (int) $custom->zendeskuserid);
        $this->assertSame($usermapid, (int) $custom->usermapid);
    }

    /**
     * A temporarily unconfigured plugin also re-queues the suspend task.
     *
     * @return void
     */
    public function test_execute_requeues_when_plugin_unconfigured(): void {
        global $DB;

        set_config('enabled', 1, 'local_zendesk');

        $usermapid = $this->create_usermap_row(7777);
        $task = new suspend_zendesk_user();
        $task->set_custom_data((object) [
            'zendeskuserid' => 7777,
            'usermapid' => $usermapid,
        ]);

        $before = time();
        $this->expectOutputRegex('/plugin not configured, re-queued suspend for Zendesk user 7777 in 900 seconds\./');
        $task->execute();

        $this->assertTrue($DB->record_exists('local_zendesk_usermap', ['id' => $usermapid]));

        $records = $DB->get_records('task_adhoc');
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame('\\local_zendesk\\task\\suspend_zendesk_user', (string) $record->classname);
        $this->assertGreaterThanOrEqual($before + 900, (int) $record->nextruntime);
        $this->assertLessThanOrEqual(time() + 905, (int) $record->nextruntime);

        $custom = json_decode((string) $record->customdata);
        $this->assertSame(7777, (int) $custom->zendeskuserid);
        $this->assertSame($usermapid, (int) $custom->usermapid);
    }

    /**
     * Seed a local Zendesk user-map row for task tests.
     *
     * @param int $zendeskuserid Remote Zendesk user id.
     * @return int
     */
    private function create_usermap_row(int $zendeskuserid): int {
        global $DB;

        $user = $this->getDataGenerator()->create_user([
            'email' => 'mapped' . $zendeskuserid . '@example.com',
        ]);
        $now = time();

        return (int) $DB->insert_record('local_zendesk_usermap', (object) [
            'userid' => $user->id,
            'zendesk_user_id' => $zendeskuserid,
            'zendesk_external_id' => 'mdl:test:user:' . $user->id,
            'zendesk_email' => $user->email,
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
