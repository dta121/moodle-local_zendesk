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
 * Handles Zendesk JWT single sign-on for Moodle-authenticated users.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\local\service;

use local_zendesk\local\constants;

/**
 * Handles Zendesk JWT single sign-on for Moodle-authenticated users.
 *
 * @package   local_zendesk
 */
final class jwt_sso_service {
    /**
     * Check whether Zendesk Help Center SSO is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        $config = $this->get_config();
        return !empty($config->enabled) && !empty($config->ssoenabled);
    }

    /**
     * Check whether Zendesk Help Center SSO has the required configuration.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $config = $this->get_config();
        return !empty($config->subdomain) && !empty($config->jwtsharedsecret);
    }

    /**
     * Get the configured Help Center button label.
     *
     * @return string
     */
    public function get_button_label(): string {
        $label = trim((string) get_config(constants::COMPONENT, 'ssobuttonlabel'));
        if ($label !== '') {
            return $label;
        }

        return get_string('openhelpcenter', constants::COMPONENT);
    }

    /**
     * Build the minimal Zendesk JWT claims for a Moodle user.
     *
     * @param \stdClass $user Moodle user record.
     * @return array
     */
    public function build_claims(\stdClass $user): array {
        if (empty($user->email)) {
            throw new \moodle_exception('missingemail', constants::COMPONENT);
        }

        return [
            'iat' => time(),
            'jti' => bin2hex(random_bytes(16)),
            'name' => fullname($user),
            'email' => (string) $user->email,
            'external_id' => $this->build_user_external_id((int) $user->id),
        ];
    }

    /**
     * Build a signed Zendesk JWT token.
     *
     * @param array $claims JWT claim payload.
     * @return string
     */
    public function build_token(array $claims): string {
        $this->assert_ready();

        foreach (['iat', 'jti', 'name', 'email', 'external_id'] as $requiredclaim) {
            if (!array_key_exists($requiredclaim, $claims)) {
                throw new \coding_exception('Missing required JWT claim: ' . $requiredclaim);
            }
        }

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $segments = [
            $this->base64_url_encode($this->json_encode($header)),
            $this->base64_url_encode($this->json_encode($claims)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $this->get_config()->jwtsharedsecret, true);
        $segments[] = $this->base64_url_encode($signature);

        return implode('.', $segments);
    }

    /**
     * Get the Zendesk JWT endpoint.
     *
     * @return string
     */
    public function get_zendesk_jwt_endpoint(): string {
        $this->assert_ready();
        $subdomain = $this->normalise_subdomain($this->get_config()->subdomain);

        return 'https://' . $subdomain . '.zendesk.com/access/jwt';
    }

    /**
     * Resolve a safe Zendesk return target.
     *
     * @param string|null $returnto Zendesk-provided return target.
     * @param string|null $target Moodle-side target hint.
     * @return string
     */
    public function resolve_return_to(?string $returnto, ?string $target): string {
        $candidate = trim((string) $returnto);
        if ($candidate === '') {
            $candidate = $this->map_target_to_default_path($target);
        }

        $validated = $this->validate_return_to($candidate);
        if ($validated === '') {
            throw new \moodle_exception('invalidreturnto', constants::COMPONENT);
        }

        return $validated;
    }

    /**
     * Get the Moodle redirect target after a Zendesk logout redirect.
     *
     * @param string|null $message Zendesk message.
     * @param string|null $kind Zendesk message kind.
     * @return \moodle_url
     */
    public function get_logout_redirect(?string $message, ?string $kind): \moodle_url {
        unset($message, $kind);
        $context = \context_system::instance();

        if (isloggedin() && !isguestuser() && has_capability('local/zendesk:viewownrequests', $context)) {
            return new \moodle_url('/local/zendesk/index.php');
        }

        if (isloggedin() && !isguestuser()) {
            return new \moodle_url('/my/');
        }

        return new \moodle_url('/');
    }

    /**
     * Write a redacted Zendesk SSO logout event to the PHP error log.
     *
     * @param string|null $message Zendesk message.
     * @param string|null $kind Zendesk message kind.
     * @param string|null $brandid Zendesk brand id.
     * @param string|null $email Zendesk email parameter.
     * @param string|null $externalid Zendesk external id parameter.
     * @return void
     */
    public function log_logout_event(
        ?string $message,
        ?string $kind,
        ?string $brandid = null,
        ?string $email = null,
        ?string $externalid = null
    ): void {
        $parts = [
            'kind=' . $this->sanitise_log_value($kind),
            'brand_id=' . $this->sanitise_log_value($brandid),
            'email=' . $this->redact_email($email),
            'external_id=' . $this->mask_value($externalid),
            'message=' . $this->sanitise_log_value($message),
        ];

        debugging(
            '[local_zendesk] Zendesk SSO logout redirect ' . implode(' ', $parts),
            DEBUG_DEVELOPER
        );
    }

    /**
     * Validate that the service can perform SSO.
     *
     * @return void
     */
    private function assert_ready(): void {
        if (!$this->is_enabled()) {
            throw new \moodle_exception('ssodisabled', constants::COMPONENT);
        }
        if (!$this->is_configured()) {
            throw new \moodle_exception('ssonotconfigured', constants::COMPONENT);
        }
    }

    /**
     * Get plugin config values relevant to JWT SSO.
     *
     * @return \stdClass
     */
    private function get_config(): \stdClass {
        return (object) [
            'enabled' => (int) get_config(constants::COMPONENT, 'enabled'),
            'ssoenabled' => (int) get_config(constants::COMPONENT, 'ssoenabled'),
            'subdomain' => trim((string) get_config(constants::COMPONENT, 'subdomain')),
            'jwtsharedsecret' => trim((string) get_config(constants::COMPONENT, 'jwtsharedsecret')),
            'ssodefaultpath' => trim((string) get_config(constants::COMPONENT, 'ssodefaultpath')),
            'instanceuuid' => trim((string) get_config(constants::COMPONENT, 'instanceuuid')),
        ];
    }

    /**
     * Map a Moodle-side target to the configured Help Center path.
     *
     * @param string|null $target Moodle-side target hint.
     * @return string
     */
    private function map_target_to_default_path(?string $target): string {
        $config = $this->get_config();
        $defaultpath = $config->ssodefaultpath !== '' ? $config->ssodefaultpath : '/hc/en-us/requests';

        if ($target === 'requests' || $target === null || $target === '') {
            return $defaultpath;
        }

        return $defaultpath;
    }

    /**
     * Validate and normalise an allowed Zendesk return target.
     *
     * @param string $returnto Candidate return target.
     * @return string
     */
    private function validate_return_to(string $returnto): string {
        $returnto = trim($returnto);
        if ($returnto === '' || preg_match('/[\r\n]/', $returnto)) {
            return '';
        }

        if (preg_match('#^https?://#i', $returnto)) {
            return $this->validate_absolute_return_to($returnto);
        }

        return $this->validate_relative_return_to($returnto);
    }

    /**
     * Validate an absolute Zendesk return target.
     *
     * @param string $returnto Absolute return target.
     * @return string
     */
    private function validate_absolute_return_to(string $returnto): string {
        $parts = parse_url($returnto);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            return '';
        }

        $expectedhost = $this->normalise_subdomain($this->get_config()->subdomain) . '.zendesk.com';
        if (strtolower((string) $parts['host']) !== $expectedhost) {
            return '';
        }

        return $returnto;
    }

    /**
     * Validate a relative Zendesk Help Center path.
     *
     * @param string $returnto Relative return target.
     * @return string
     */
    private function validate_relative_return_to(string $returnto): string {
        if ($returnto[0] !== '/') {
            return '';
        }

        $parts = parse_url($returnto);
        if ($parts === false || !empty($parts['scheme']) || !empty($parts['host'])) {
            return '';
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || strpos($path, '/hc/') !== 0) {
            return '';
        }

        // Reject "." and ".." path segments (including percent-encoded variants
        // such as %2e%2e). The /hc/ prefix check alone is satisfied by inputs
        // like /hc/../something and /hc/%2e%2e/something, which Zendesk could
        // re-resolve to a location outside the Help Center namespace.
        $decodedpath = rawurldecode($path);
        foreach (explode('/', $decodedpath) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return '';
            }
        }

        $validated = $path;
        if (!empty($parts['query'])) {
            $validated .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $validated .= '#' . $parts['fragment'];
        }

        return $validated;
    }

    /**
     * Normalise a Zendesk subdomain setting to the subdomain portion.
     *
     * @param string $subdomain Configured subdomain or host.
     * @return string
     */
    private function normalise_subdomain(string $subdomain): string {
        $subdomain = trim($subdomain);
        $subdomain = preg_replace('#^https?://#i', '', $subdomain);
        $subdomain = preg_replace('#/.*$#', '', $subdomain);
        $subdomain = preg_replace('#\.zendesk\.com$#i', '', $subdomain);

        return trim($subdomain);
    }

    /**
     * Base64-url encode a binary or JSON string.
     *
     * @param string $value Value to encode.
     * @return string
     */
    private function base64_url_encode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * JSON encode an array for JWT output.
     *
     * @param array $data Data to encode.
     * @return string
     */
    private function json_encode(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \coding_exception('Unable to JSON encode Zendesk JWT payload.');
        }

        return $json;
    }

    /**
     * Build the stable Zendesk user external id.
     *
     * @param int $userid Moodle user id.
     * @return string
     */
    private function build_user_external_id(int $userid): string {
        return 'mdl:' . $this->get_instance_uuid() . ':user:' . $userid;
    }

    /**
     * Get or generate the plugin instance UUID.
     *
     * @return string
     */
    private function get_instance_uuid(): string {
        $config = $this->get_config();
        if (!empty($config->instanceuuid)) {
            return $config->instanceuuid;
        }

        $uuid = $this->generate_uuid();
        set_config('instanceuuid', $uuid, constants::COMPONENT);

        return $uuid;
    }

    /**
     * Generate a RFC4122-compatible UUIDv4.
     *
     * @return string
     */
    private function generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Redact an email address for logs.
     *
     * @param string|null $email Email address.
     * @return string
     */
    private function redact_email(?string $email): string {
        $email = trim((string) $email);
        if ($email === '' || strpos($email, '@') === false) {
            return '[redacted]';
        }

        [$localpart, $domain] = explode('@', $email, 2);
        $prefix = substr($localpart, 0, 1);

        return $prefix . '***@' . $domain;
    }

    /**
     * Mask an arbitrary value for logs.
     *
     * @param string|null $value Raw value.
     * @return string
     */
    private function mask_value(?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '[blank]';
        }

        if (strlen($value) <= 8) {
            return '[redacted]';
        }

        return substr($value, 0, 4) . '...' . substr($value, -4);
    }

    /**
     * Sanitise an arbitrary log value.
     *
     * @param string|null $value Raw log value.
     * @return string
     */
    private function sanitise_log_value(?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '[blank]';
        }

        $value = preg_replace('/[\r\n]+/', ' ', $value);

        return substr($value, 0, 160);
    }
}
