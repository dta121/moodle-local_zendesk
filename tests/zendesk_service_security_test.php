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
 * Security-focused PHPUnit tests for the Zendesk service.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\local\constants;
use local_zendesk\local\repository\ticket_repository;
use local_zendesk\local\service\zendesk_service;

/**
 * Security-focused tests for the Zendesk service.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\service\zendesk_service
 */
final class zendesk_service_security_test extends \advanced_testcase {
    /**
     * Reset plugin config before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, constants::COMPONENT);
        set_config('subdomain', 'sayloruniversity', constants::COMPONENT);
        set_config('serviceemail', 'service@example.com', constants::COMPONENT);
        set_config('apitoken', 'secret-token', constants::COMPONENT);
        set_config('instanceuuid', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', constants::COMPONENT);
    }

    /**
     * Test that read-all access cannot be reused to post replies to another user's ticket.
     *
     * @return void
     */
    public function test_reply_to_request_rejects_viewall_non_owner(): void {
        $owner = $this->getDataGenerator()->create_user(['email' => 'owner@example.com']);
        $viewer = $this->getDataGenerator()->create_user(['email' => 'viewer@example.com']);
        $this->grant_system_capabilities($viewer, [
            'local/zendesk:submitrequest',
            'local/zendesk:viewallrequests',
        ]);
        $this->setUser($viewer);

        $repository = new ticket_repository();
        $usermap = $repository->upsert_user_map(
            (int) $owner->id,
            12345,
            'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:user:' . $owner->id,
            $owner->email
        );
        $ticket = $repository->create_local_ticket(
            (int) $owner->id,
            (int) $usermap->id,
            null,
            null,
            '11111111-2222-4333-8444-555555555555',
            'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:ticket:security',
            'Student-owned ticket',
            'Original request'
        );
        $ticket = $repository->attach_remote_ticket((int) $ticket->id, (object) [
            'zendesk_ticket_id' => 5001,
            'status' => 'open',
            'custom_status_id' => null,
            'updatedat' => time(),
        ]);

        try {
            (new zendesk_service($repository))->reply_to_request((int) $ticket->id, (int) $viewer->id, 'Manager reply', true);
            $this->fail('Expected cross-user reply attempt to be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('replynotallowed', $e->errorcode);
        }
    }

    /**
     * Grant system-level capabilities to a user for a test.
     *
     * @param \stdClass $user Moodle user record.
     * @param array $capabilities Capability names.
     * @return void
     */
    private function grant_system_capabilities(\stdClass $user, array $capabilities): void {
        $context = \context_system::instance();
        $roleid = create_role('Zendesk security test role', 'zendesksecuritytest', '');

        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id);
        }

        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();
    }
}
