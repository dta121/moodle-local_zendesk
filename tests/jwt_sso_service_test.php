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

        $this->assertSame(
            ['iat', 'nbf', 'exp', 'jti', 'name', 'email', 'external_id'],
            array_keys($claims)
        );
        $this->assertSame('Zendesk Student', $claims['name']);
        $this->assertSame('student@example.com', $claims['email']);
        $this->assertSame(
            'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:user:' . $user->id,
            $claims['external_id']
        );
        $this->assertSame($claims['iat'], $claims['nbf']);
        $this->assertSame($claims['iat'] + 60, $claims['exp']);
        $this->assertGreaterThanOrEqual(time() - 5, $claims['iat']);
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
     * Test that safe absolute Help Center URLs on the configured Zendesk host are accepted.
     *
     * @return void
     */
    public function test_resolve_return_to_accepts_absolute_help_center_path(): void {
        $service = new jwt_sso_service();

        $this->assertSame(
            'https://sayloruniversity.zendesk.com/hc/en-us/requests?status=open',
            $service->resolve_return_to('https://sayloruniversity.zendesk.com/hc/en-us/requests?status=open', null)
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
     * Test that same-host absolute URLs outside Help Center are rejected.
     *
     * @return void
     */
    public function test_resolve_return_to_rejects_absolute_non_help_center_path(): void {
        $this->expectException(\moodle_exception::class);

        $service = new jwt_sso_service();
        $service->resolve_return_to('https://sayloruniversity.zendesk.com/agent/tickets/123', null);
    }

    /**
     * Provide path-traversal inputs that the validator must reject.
     *
     * @return array
     */
    public static function traversal_return_to_provider(): array {
        return [
            'literal dot dot in /hc/ path' => ['/hc/../etc'],
            'percent-encoded dot dot at start' => ['/hc/%2e%2e/etc'],
            'percent-encoded dot dot mixed case' => ['/hc/%2E%2E/etc'],
            'literal dot dot mid-path' => ['/hc/en-us/../requests'],
            'single dot segment' => ['/hc/./requests'],
            'percent-encoded single dot' => ['/hc/%2e/requests'],
            'trailing dot dot' => ['/hc/en-us/..'],
            'absolute literal dot dot in /hc/ path' => ['https://sayloruniversity.zendesk.com/hc/../etc'],
            'absolute percent-encoded dot dot' => ['https://sayloruniversity.zendesk.com/hc/%2e%2e/etc'],
        ];
    }

    /**
     * Test that path-traversal style return URLs are rejected.
     *
     * The original validator only checked the /hc/ prefix on the raw path,
     * so inputs like /hc/../etc and /hc/%2e%2e/etc satisfied the check while
     * resolving (after Zendesk normalisation) to locations outside the Help
     * Center namespace.
     *
     * @dataProvider traversal_return_to_provider
     * @param string $returnto Candidate return target.
     * @return void
     */
    public function test_resolve_return_to_rejects_traversal(string $returnto): void {
        $this->expectException(\moodle_exception::class);

        $service = new jwt_sso_service();
        $service->resolve_return_to($returnto, null);
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
