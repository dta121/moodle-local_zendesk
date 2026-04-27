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
 * PHPUnit tests for the Zendesk ticket repository.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk;

use local_zendesk\local\repository\ticket_repository;

/**
 * Tests for the Zendesk ticket repository, focusing on the per-ticket
 * attachment manifest that backs the proxy access check (security review F7).
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_zendesk\local\repository\ticket_repository
 */
final class ticket_repository_test extends \advanced_testcase {
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
     * upsert_attachment_manifest creates a row keyed on (ticket, url hash).
     *
     * @return void
     */
    public function test_upsert_attachment_manifest_inserts_row(): void {
        global $DB;

        $repo = new ticket_repository();
        $url = 'https://saylor.zendesk.com/attachments/token/aaaa/?name=foo.png';
        $hash = $repo->upsert_attachment_manifest(
            42,
            $url,
            'foo.png',
            'image/png',
            12345
        );

        $this->assertSame(hash('sha256', $url), $hash);
        $row = $DB->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => 42,
            'urlhash' => $hash,
        ], '*', MUST_EXIST);
        $this->assertSame($url, $row->remoteurl);
        $this->assertSame('foo.png', $row->filename);
        $this->assertSame('image/png', $row->contenttype);
        $this->assertSame(12345, (int) $row->filesize);
    }

    /**
     * Re-issuing upsert with new metadata backfills previously-null fields.
     *
     * @return void
     */
    public function test_upsert_attachment_manifest_backfills_metadata(): void {
        global $DB;

        $repo = new ticket_repository();
        $url = 'https://saylor.zendesk.com/attachments/token/bbbb/';

        $repo->upsert_attachment_manifest(7, $url);
        $row = $DB->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => 7,
            'urlhash' => hash('sha256', $url),
        ], '*', MUST_EXIST);
        $this->assertNull($row->filename);
        $this->assertNull($row->contenttype);

        $repo->upsert_attachment_manifest(7, $url, 'late.png', 'image/png', 999);
        $row = $DB->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => 7,
            'urlhash' => hash('sha256', $url),
        ], '*', MUST_EXIST);
        $this->assertSame('late.png', $row->filename);
        $this->assertSame('image/png', $row->contenttype);
        $this->assertSame(999, (int) $row->filesize);
    }

    /**
     * Re-issuing upsert with replacement metadata keeps the first-known values.
     *
     * @return void
     */
    public function test_upsert_attachment_manifest_does_not_overwrite_known_metadata(): void {
        global $DB;

        $repo = new ticket_repository();
        $url = 'https://saylor.zendesk.com/attachments/token/cccc/';

        $repo->upsert_attachment_manifest(11, $url, 'first.png', 'image/png', 111);
        $repo->upsert_attachment_manifest(11, $url, 'second.png', 'image/jpeg', 222);

        $row = $DB->get_record('local_zendesk_ticket_attachment', [
            'localticketid' => 11,
            'urlhash' => hash('sha256', $url),
        ], '*', MUST_EXIST);
        $this->assertSame('first.png', $row->filename);
        $this->assertSame('image/png', $row->contenttype);
        $this->assertSame(111, (int) $row->filesize);
    }

    /**
     * The manifest is scoped per ticket: a row for ticket A is invisible to
     * ticket B even if the URL is identical.
     *
     * @return void
     */
    public function test_get_attachment_manifest_is_scoped_per_ticket(): void {
        $repo = new ticket_repository();
        $url = 'https://saylor.zendesk.com/attachments/token/dddd/';

        $repo->upsert_attachment_manifest(1, $url, 'a.png', 'image/png', 1);

        $this->assertNotNull($repo->get_attachment_manifest(1, $url));
        $this->assertNull($repo->get_attachment_manifest(2, $url));
    }

    /**
     * Looking up a URL that was never registered returns null even when the
     * ticket id matches a populated manifest entry.
     *
     * @return void
     */
    public function test_get_attachment_manifest_returns_null_for_unknown_url(): void {
        $repo = new ticket_repository();

        $repo->upsert_attachment_manifest(
            5,
            'https://saylor.zendesk.com/attachments/token/eeee/',
            null,
            null,
            null
        );

        $this->assertNull($repo->get_attachment_manifest(
            5,
            'https://saylor.zendesk.com/attachments/token/ffff/'
        ));
    }

    /**
     * Bulk delete by ticket removes manifest rows only for the specified ids.
     *
     * @return void
     */
    public function test_delete_attachment_manifest_for_tickets(): void {
        global $DB;

        $repo = new ticket_repository();
        $repo->upsert_attachment_manifest(1, 'https://saylor.zendesk.com/attachments/token/g1/');
        $repo->upsert_attachment_manifest(2, 'https://saylor.zendesk.com/attachments/token/g2/');
        $repo->upsert_attachment_manifest(3, 'https://saylor.zendesk.com/attachments/token/g3/');

        $repo->delete_attachment_manifest_for_tickets([1, 3]);

        $remaining = $DB->get_records('local_zendesk_ticket_attachment');
        $this->assertCount(1, $remaining);
        $row = reset($remaining);
        $this->assertSame(2, (int) $row->localticketid);
    }
}
