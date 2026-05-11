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
 * PHPUnit tests for Zendesk comment HTML asset rewriting.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\local\service\zendesk_service;

/**
 * Tests for the comment HTML asset rewrite path.
 *
 * Comment HTML must only proxy URLs Zendesk also reported in the structured
 * attachments payload. Arbitrary same-host links found in the HTML must stay
 * untouched so they cannot widen the manifest allow-list (security review P2).
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\service\zendesk_service
 */
final class comment_asset_rewrite_test extends \advanced_testcase {
    /**
     * Reset state before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('subdomain', 'test', 'local_zendesk');
    }

    /**
     * Same-host non-attachment links in comment HTML are left untouched and do
     * not create manifest rows.
     *
     * @return void
     */
    public function test_non_attachment_same_host_comment_link_is_not_rewritten(): void {
        global $DB;

        $service = new zendesk_service();
        $remoteurl = 'https://test.zendesk.com/api/v2/users.json';

        $html = $this->invoke_format_comment_body_html($service, 42, [
            'html_body' => '<p><a href="' . $remoteurl . '">API</a></p>',
            'attachments' => [],
        ]);

        $this->assertStringContainsString($remoteurl, $html);
        $this->assertStringNotContainsString('/local/zendesk/attachment.php', $html);
        $this->assertSame(0, $DB->count_records('local_zendesk_ticket_attachment', [
            'localticketid' => 42,
        ]));
    }

    /**
     * Attachment URLs present in the structured payload are still proxied and
     * recorded in the manifest for later access checks.
     *
     * @return void
     */
    public function test_structured_attachment_comment_link_is_rewritten(): void {
        global $DB;

        $service = new zendesk_service();
        $remoteurl = 'https://test.zendesk.com/attachments/token/trusted/?name=report.pdf';

        $html = $this->invoke_format_comment_body_html($service, 73, [
            'html_body' => '<p><a href="' . $remoteurl . '">report.pdf</a></p>',
            'attachments' => [
                [
                    'content_url' => $remoteurl,
                    'file_name' => 'report.pdf',
                    'content_type' => 'application/pdf',
                    'size' => 321,
                ],
            ],
        ]);

        $this->assertStringContainsString('/local/zendesk/attachment.php', $html);

        $row = $DB->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => 73,
            'urlhash' => hash('sha256', $remoteurl),
        ], '*', MUST_EXIST);
        $this->assertSame($remoteurl, $row->remoteurl);
        $this->assertSame('report.pdf', $row->filename);
        $this->assertSame('application/pdf', $row->contenttype);
        $this->assertSame(321, (int) $row->filesize);
    }

    /**
     * Thumbnail URLs reported in the structured payload remain eligible for
     * proxying from comment HTML.
     *
     * @return void
     */
    public function test_structured_thumbnail_comment_link_is_rewritten(): void {
        global $DB;

        $service = new zendesk_service();
        $contenturl = 'https://test.zendesk.com/attachments/token/main/?name=report.pdf';
        $thumbnailurl = 'https://test.zendesk.com/attachments/token/thumb/?name=preview.png';

        $html = $this->invoke_format_comment_body_html($service, 99, [
            'html_body' => '<p><a href="' . $thumbnailurl . '">Preview</a></p>',
            'attachments' => [
                [
                    'content_url' => $contenturl,
                    'file_name' => 'report.pdf',
                    'content_type' => 'application/pdf',
                    'thumbnails' => [
                        [
                            'content_url' => $thumbnailurl,
                            'content_type' => 'image/png',
                            'size' => 64,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('/local/zendesk/attachment.php', $html);

        $row = $DB->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => 99,
            'urlhash' => hash('sha256', $thumbnailurl),
        ], '*', MUST_EXIST);
        $this->assertSame($thumbnailurl, $row->remoteurl);
        $this->assertSame('report.pdf', $row->filename);
        $this->assertSame('image/png', $row->contenttype);
        $this->assertSame(64, (int) $row->filesize);
    }

    /**
     * Invoke the private format_comment_body_html() method for focused tests.
     *
     * @param zendesk_service $service Service under test.
     * @param int $localticketid Local ticket id.
     * @param array $comment Raw comment payload.
     * @return string
     */
    private function invoke_format_comment_body_html(
        zendesk_service $service,
        int $localticketid,
        array $comment
    ): string {
        $method = new \ReflectionMethod($service, 'format_comment_body_html');
        $method->setAccessible(true);

        return (string) $method->invoke($service, $localticketid, $comment);
    }
}
