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
 * PHPUnit tests for the Zendesk agent context service.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\local\service\agent_context_service;

/**
 * Tests for the Zendesk agent context service.
 *
 * The agent context service is the primary cross-trust-boundary surface for
 * the local_zendesk plugin: it returns Moodle PII to a Zendesk-side caller
 * authenticated as the dedicated service user. Identity must be resolved only
 * through the local_zendesk_usermap table; any path that lets a caller
 * enumerate Moodle accounts by email or by guessing a user id is a security
 * regression.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\service\agent_context_service
 */
final class agent_context_service_test extends \advanced_testcase {
    /** @var string Stable instance uuid used in fixtures. */
    private const INSTANCE_UUID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

    /**
     * Configure plugin settings used by the service.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_zendesk');
        set_config('instanceuuid', self::INSTANCE_UUID, 'local_zendesk');
    }

    /**
     * A known Zendesk requester id with a matching usermap row returns Moodle
     * context.
     *
     * @return void
     */
    public function test_get_agent_context_returns_payload_for_bound_requester_id(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Mapped',
            'lastname' => 'User',
            'email' => 'mapped.user@example.com',
        ]);
        $map = $this->insert_usermap($user, 'mapped.user@example.com');

        $service = new agent_context_service();
        $payload = $service->get_agent_context($map->zendeskuserid, '', '');

        $this->assertSame('mapped.user@example.com', $payload['email']);
        $this->assertSame('Mapped User', $payload['fullname']);
        $this->assertArrayNotHasKey('externalid', $payload);
        $this->assertArrayNotHasKey('userid', $payload);
        $this->assertArrayNotHasKey('lastaccess', $payload);
    }

    /**
     * Matching external id and email are accepted as optional consistency
     * checks after the bound Zendesk requester id has selected the mapping row.
     *
     * @return void
     */
    public function test_get_agent_context_accepts_matching_consistency_checks(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Match',
            'lastname' => 'Email',
            'email' => 'match.email@example.com',
        ]);
        $map = $this->insert_usermap($user, 'match.email@example.com');

        $service = new agent_context_service();
        $payload = $service->get_agent_context(
            $map->zendeskuserid,
            $map->externalid,
            'MATCH.EMAIL@example.com'
        );

        $this->assertSame('match.email@example.com', $payload['email']);
        $this->assertArrayNotHasKey('externalid', $payload);
    }

    /**
     * An unknown Zendesk requester id is rejected as not found.
     *
     * @return void
     */
    public function test_get_agent_context_rejects_unknown_requester_id(): void {
        $this->expectException(\moodle_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context(999999, '', '');
    }

    /**
     * External-id-only calls are rejected now that requester identity must be
     * bound through zendesk_user_id.
     *
     * @return void
     */
    public function test_get_agent_context_rejects_external_id_only_lookup(): void {
        $user = $this->getDataGenerator()->create_user();
        $craftedexternalid = 'mdl:' . self::INSTANCE_UUID . ':user:' . $user->id;

        $this->expectException(\invalid_parameter_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context(0, $craftedexternalid, '');
    }

    /**
     * An email-only call is rejected even when the email matches a Moodle user.
     *
     * @return void
     */
    public function test_get_agent_context_rejects_email_only_lookup(): void {
        $user = $this->getDataGenerator()->create_user([
            'email' => 'lookup.target@example.com',
        ]);
        $this->insert_usermap($user, 'lookup.target@example.com');

        $this->expectException(\invalid_parameter_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context(0, '', 'lookup.target@example.com');
    }

    /**
     * A bound Zendesk requester id paired with a mismatched external id is
     * rejected as not found.
     *
     * @return void
     */
    public function test_get_agent_context_rejects_external_id_mismatch(): void {
        $user = $this->getDataGenerator()->create_user([
            'email' => 'real.user@example.com',
        ]);
        $map = $this->insert_usermap($user, 'real.user@example.com');

        $this->expectException(\moodle_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context($map->zendeskuserid, 'mdl:' . self::INSTANCE_UUID . ':user:99999', '');
    }

    /**
     * A bound Zendesk requester id paired with a non-matching email is
     * rejected as not found.
     *
     * @return void
     */
    public function test_get_agent_context_rejects_email_mismatch(): void {
        $user = $this->getDataGenerator()->create_user([
            'email' => 'real.user@example.com',
        ]);
        $map = $this->insert_usermap($user, 'real.user@example.com');

        $this->expectException(\moodle_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context($map->zendeskuserid, $map->externalid, 'someone.else@example.com');
    }

    /**
     * Ambiguous requester ids are rejected instead of returning whichever map
     * row happens to be read first.
     *
     * @return void
     */
    public function test_get_agent_context_rejects_ambiguous_requester_id(): void {
        global $DB;

        $first = $this->getDataGenerator()->create_user(['email' => 'first@example.com']);
        $second = $this->getDataGenerator()->create_user(['email' => 'second@example.com']);
        $sharedzendeskuserid = 424242;
        $now = time();

        $DB->insert_record('local_zendesk_usermap', (object) [
            'userid' => $first->id,
            'zendesk_user_id' => $sharedzendeskuserid,
            'zendesk_external_id' => 'mdl:' . self::INSTANCE_UUID . ':user:' . $first->id,
            'zendesk_email' => 'first@example.com',
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_zendesk_usermap', (object) [
            'userid' => $second->id,
            'zendesk_user_id' => $sharedzendeskuserid,
            'zendesk_external_id' => 'mdl:' . self::INSTANCE_UUID . ':user:' . $second->id,
            'zendesk_email' => 'second@example.com',
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->expectException(\moodle_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context($sharedzendeskuserid, '', '');
    }

    /**
     * A user mapped in usermap but whose Moodle account is deleted is treated
     * as not found.
     *
     * @return void
     */
    public function test_get_agent_context_skips_deleted_user(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user([
            'email' => 'deleted.user@example.com',
        ]);
        $map = $this->insert_usermap($user, 'deleted.user@example.com');
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        $this->expectException(\moodle_exception::class);

        $service = new agent_context_service();
        $service->get_agent_context($map->zendeskuserid, $map->externalid, '');
    }

    /**
     * Insert a local_zendesk_usermap row for the given Moodle user.
     *
     * @param \stdClass $user Moodle user record.
     * @param string $zendeskemail Email recorded on the Zendesk side.
     * @param int|null $zendeskuserid Optional fixed Zendesk requester id.
     * @return \stdClass Mapping fixture values used by the tests.
     */
    private function insert_usermap(\stdClass $user, string $zendeskemail, ?int $zendeskuserid = null): \stdClass {
        global $DB;

        $zendeskuserid = $zendeskuserid ?? (100000 + $user->id);
        $externalid = 'mdl:' . self::INSTANCE_UUID . ':user:' . $user->id;
        $now = time();
        $DB->insert_record('local_zendesk_usermap', (object) [
            'userid' => $user->id,
            'zendesk_user_id' => $zendeskuserid,
            'zendesk_external_id' => $externalid,
            'zendesk_email' => $zendeskemail,
            'lastsyncedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return (object) [
            'zendeskuserid' => $zendeskuserid,
            'externalid' => $externalid,
            'email' => $zendeskemail,
        ];
    }
}
