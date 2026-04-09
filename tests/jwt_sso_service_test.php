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
 * PHPUnit tests for the Zendesk JWT SSO service.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\local\service\jwt_sso_service;

/**
 * Tests for the Zendesk JWT SSO service.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\service\jwt_sso_service
 */
final class jwt_sso_service_test extends \advanced_testcase {
    /**
     * Reset plugin config before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_zendesk');
        set_config('ssoenabled', 1, 'local_zendesk');
        set_config('subdomain', 'sayloruniversity', 'local_zendesk');
        set_config('jwtsharedsecret', 'super-secret-jwt-key', 'local_zendesk');
        set_config('ssodefaultpath', '/hc/en-us/requests', 'local_zendesk');
        set_config('instanceuuid', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'local_zendesk');
    }

    /**
     * Test that the generated claims contain only the expected payload keys.
     *
     * @return void
     */
    public function test_build_claims_returns_minimal_payload(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Zendesk',
            'lastname' => 'Student',
            'email' => 'student@example.com',
        ]);

        $service = new jwt_sso_service();
        $claims = $service->build_claims($user);

        $this->assertSame(['iat', 'jti', 'name', 'email', 'external_id'], array_keys($claims));
        $this->assertSame('Zendesk Student', $claims['name']);
        $this->assertSame('student@example.com', $claims['email']);
        $this->assertSame(
            'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:user:' . $user->id,
            $claims['external_id']
        );
    }

    /**
     * Test that safe Help Center paths are accepted.
     *
     * @return void
     */
    public function test_resolve_return_to_accepts_relative_help_center_path(): void {
        $service = new jwt_sso_service();

        $this->assertSame(
            '/hc/en-us/requests?status=open',
            $service->resolve_return_to('/hc/en-us/requests?status=open', null)
        );
    }

    /**
     * Test that off-site return URLs are rejected.
     *
     * @return void
     */
    public function test_resolve_return_to_rejects_other_hosts(): void {
        $this->expectException(\moodle_exception::class);

        $service = new jwt_sso_service();
        $service->resolve_return_to('https://example.com/hc/en-us/requests', null);
    }

    /**
     * Test that tokens are generated as standard three-part JWTs.
     *
     * @return void
     */
    public function test_build_token_returns_signed_jwt(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Zendesk',
            'lastname' => 'Student',
            'email' => 'student@example.com',
        ]);

        $service = new jwt_sso_service();
        $claims = $service->build_claims($user);
        $token = $service->build_token($claims);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertSame('HS256', $header['alg']);
        $this->assertSame($claims['external_id'], $payload['external_id']);
    }
}
