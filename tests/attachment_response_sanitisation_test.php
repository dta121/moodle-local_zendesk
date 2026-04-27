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
 * PHPUnit tests for the Zendesk attachment proxy response sanitisation.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\local\service\zendesk_service;

/**
 * Tests for the static response-sanitisation helpers used by attachment.php.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\service\zendesk_service
 */
final class attachment_response_sanitisation_test extends \advanced_testcase {
    /**
     * Inline-safe content types pass through unchanged.
     *
     * @dataProvider safe_content_type_provider
     * @param string $upstream Upstream Content-Type.
     * @return void
     */
    public function test_safe_content_type_passes_through(string $upstream): void {
        $this->assertSame($upstream, zendesk_service::safe_response_content_type($upstream));
    }

    /**
     * Provider for inline-safe content types.
     *
     * @return array
     */
    public static function safe_content_type_provider(): array {
        return [
            'png' => ['image/png'],
            'jpeg' => ['image/jpeg'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
        ];
    }

    /**
     * Anything not on the allow-list is downgraded to application/octet-stream.
     *
     * @dataProvider unsafe_content_type_provider
     * @param string $upstream Upstream Content-Type.
     * @return void
     */
    public function test_unsafe_content_type_is_downgraded(string $upstream): void {
        $this->assertSame(
            'application/octet-stream',
            zendesk_service::safe_response_content_type($upstream)
        );
    }

    /**
     * Provider for content types that must be downgraded.
     *
     * @return array
     */
    public static function unsafe_content_type_provider(): array {
        return [
            'svg' => ['image/svg+xml'],
            'html' => ['text/html'],
            'plain' => ['text/plain'],
            'javascript' => ['application/javascript'],
            'xml' => ['application/xml'],
            'unknown' => ['application/x-some-thing'],
            'empty' => [''],
        ];
    }

    /**
     * Trailing parameters and odd casing on the upstream value still resolve
     * correctly against the allow-list.
     *
     * @return void
     */
    public function test_safe_content_type_strips_parameters_and_lowercases(): void {
        $this->assertSame(
            'image/png',
            zendesk_service::safe_response_content_type('Image/PNG; charset=binary')
        );
    }

    /**
     * Carriage returns and line feeds in upstream values are stripped before
     * the value can be applied to a header() call.
     *
     * @return void
     */
    public function test_sanitise_header_value_strips_crlf(): void {
        $injected = "image/png\r\nX-Injected: yes";

        $this->assertSame('image/pngX-Injected: yes', zendesk_service::sanitise_header_value($injected));
    }

    /**
     * Other ASCII control characters are also stripped.
     *
     * @return void
     */
    public function test_sanitise_header_value_strips_ascii_control_chars(): void {
        $this->assertSame(
            'value',
            zendesk_service::sanitise_header_value("\x00\x01\x07value\x1F\x7F")
        );
    }

    /**
     * Content-Disposition is always "attachment" with the manifest filename.
     *
     * @return void
     */
    public function test_build_content_disposition_forces_attachment(): void {
        $this->assertSame(
            'attachment; filename="report.pdf"; filename*=UTF-8\'\'report.pdf',
            zendesk_service::build_content_disposition('report.pdf')
        );
    }

    /**
     * A blank or empty filename falls back to a generic name.
     *
     * @return void
     */
    public function test_build_content_disposition_falls_back_for_empty_name(): void {
        $this->assertSame(
            'attachment; filename="attachment"; filename*=UTF-8\'\'attachment',
            zendesk_service::build_content_disposition('')
        );
    }

    /**
     * Path components in a filename are stripped (no directory traversal in
     * the rendered Content-Disposition).
     *
     * @return void
     */
    public function test_build_content_disposition_strips_path_components(): void {
        $disposition = zendesk_service::build_content_disposition('../../etc/passwd');

        $this->assertStringContainsString('filename="passwd"', $disposition);
        $this->assertStringContainsString("filename*=UTF-8''passwd", $disposition);
    }

    /**
     * CR/LF in the filename cannot escape the header.
     *
     * @return void
     */
    public function test_build_content_disposition_strips_crlf_from_filename(): void {
        $disposition = zendesk_service::build_content_disposition("foo.txt\r\nX-Injected: yes");

        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
    }

    /**
     * Non-ASCII filenames are preserved via the RFC 5987 filename* parameter
     * while the ASCII-only filename= parameter substitutes underscores.
     *
     * @return void
     */
    public function test_build_content_disposition_preserves_unicode_via_rfc_5987(): void {
        $disposition = zendesk_service::build_content_disposition('résumé.pdf');

        $this->assertStringContainsString("filename*=UTF-8''r%C3%A9sum%C3%A9.pdf", $disposition);
        $this->assertMatchesRegularExpression('/filename="r_sum_\\.pdf"/', $disposition);
    }

    /**
     * SVG is no longer treated as a safe inline image.
     *
     * @return void
     */
    public function test_svg_is_not_safe_inline_image(): void {
        $this->assertFalse(zendesk_service::is_safe_inline_image_content_type('image/svg+xml'));
        $this->assertTrue(zendesk_service::is_safe_inline_image_content_type('image/png'));
        $this->assertTrue(zendesk_service::is_safe_inline_image_content_type('image/jpeg'));
        $this->assertFalse(zendesk_service::is_safe_inline_image_content_type('text/html'));
    }
}
