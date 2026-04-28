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
 * Behat steps for local_zendesk feature tests.
 *
 * @package    local_zendesk
 * @category   test
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Custom Behat steps for the local_zendesk plugin.
 *
 * Keeps shared scenario setup (plugin config, fixture seeding) reusable across
 * the feature files added under IDM-143. The class starts deliberately small
 * — additional steps will arrive alongside each new feature file.
 */
class behat_local_zendesk extends behat_base {
    /**
     * Apply a complete, valid local_zendesk configuration so scenarios can
     * exercise the integration without each one repeating the credentials.
     * Mirrors what an administrator would enter on a freshly-installed site.
     *
     * @Given /^the Zendesk integration is configured$/
     */
    public function the_zendesk_integration_is_configured(): void {
        set_config('enabled', 1, 'local_zendesk');
        set_config('subdomain', 'test', 'local_zendesk');
        set_config('serviceemail', 'service@example.com', 'local_zendesk');
        set_config('apitoken', 'TESTTOKEN', 'local_zendesk');
        set_config('skipverifyemail', 1, 'local_zendesk');
        set_config('instanceuuid', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'local_zendesk');
    }

    /**
     * Disable the local_zendesk integration without otherwise altering its
     * stored configuration.
     *
     * @Given /^the Zendesk integration is disabled$/
     */
    public function the_zendesk_integration_is_disabled(): void {
        set_config('enabled', 0, 'local_zendesk');
    }

    /**
     * Seed Zendesk ticket fixtures for Behat scenarios. Mirrors the
     * PHPUnit seed helper but accepts a Gherkin TableNode so each row
     * documents itself.
     *
     * Required columns: username, subject, body.
     * Optional columns: syncstate (default "active"), status (default "open").
     *
     * @Given /^the following Zendesk tickets exist:$/
     * @param \Behat\Gherkin\Node\TableNode $data Ticket rows.
     */
    public function the_following_zendesk_tickets_exist(\Behat\Gherkin\Node\TableNode $data): void {
        global $DB;

        $now = time();
        $instanceuuid = (string) get_config('local_zendesk', 'instanceuuid');
        if ($instanceuuid === '') {
            $instanceuuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
            set_config('instanceuuid', $instanceuuid, 'local_zendesk');
        }

        $counter = 0;
        foreach ($data->getHash() as $row) {
            $counter++;
            $user = $DB->get_record('user', ['username' => $row['username']], '*', MUST_EXIST);

            $existing = $DB->get_record('local_zendesk_usermap', ['userid' => $user->id]);
            if ($existing) {
                $usermapid = (int) $existing->id;
            } else {
                $usermapid = (int) $DB->insert_record('local_zendesk_usermap', (object) [
                    'userid' => $user->id,
                    'zendesk_user_id' => 100000 + (int) $user->id,
                    'zendesk_external_id' => 'mdl:' . $instanceuuid . ':user:' . $user->id,
                    'zendesk_email' => 'mapped' . $user->id . '@example.com',
                    'lastsyncedat' => $now,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }

            $DB->insert_record('local_zendesk_ticket', (object) [
                'uuid' => 'behat-uuid-' . $counter . '-' . $user->id,
                'userid' => $user->id,
                'usermapid' => $usermapid,
                'courseid' => null,
                'contextid' => null,
                'zendesk_ticket_id' => 5000 + $counter,
                'zendesk_ticket_external_id' => 'mdl:' . $instanceuuid . ':ticket:' . $counter,
                'subject' => $row['subject'],
                'body' => $row['body'] ?? '',
                'status' => $row['status'] ?? 'open',
                'syncstate' => $row['syncstate'] ?? 'active',
                'timecreated' => $now + $counter,
                'timemodified' => $now + $counter,
            ]);
        }
    }

    /**
     * Visit the local_zendesk ticket detail page identified by its subject.
     * Resolves the ticket id from the local table so feature files do not
     * have to know auto-increment values.
     *
     * @When /^I visit the Zendesk ticket page for "([^"]+)"$/
     * @param string $subject The ticket subject seeded by an earlier step.
     */
    public function i_visit_the_zendesk_ticket_page(string $subject): void {
        global $DB;

        $ticket = $DB->get_record('local_zendesk_ticket', ['subject' => $subject], 'id', MUST_EXIST);
        $this->execute('behat_general::i_visit', ['/local/zendesk/view.php?id=' . $ticket->id]);
    }

    /**
     * Visit a URL that is expected to surface a Moodle exception page whose
     * message contains the supplied substring. Required because Moodle's
     * Behat session inspects every navigation for exception markers and
     * fails the step if it finds one — so the F9 indistinguishability
     * invariant (and similar negative paths) need a step that whitelists
     * the expected error.
     *
     * @When /^I expect a Moodle exception containing "([^"]+)" when I visit "([^"]+)"$/
     * @param string $expectedmessage Substring that must appear in the
     *                                exception message.
     * @param string $url Target URL relative to wwwroot.
     */
    public function i_expect_a_moodle_exception_containing_when_i_visit(
        string $expectedmessage,
        string $url
    ): void {
        try {
            $this->execute('behat_general::i_visit', [$url]);
        } catch (\Exception $e) {
            if (stripos($e->getMessage(), $expectedmessage) !== false) {
                return;
            }
            throw $e;
        }

        throw new \Behat\Mink\Exception\ExpectationException(
            sprintf(
                'Expected a Moodle exception containing "%s" when visiting %s, but no exception was raised.',
                $expectedmessage,
                $url
            ),
            $this->getSession()
        );
    }

    /**
     * Visit the Zendesk ticket page for the given subject and expect a
     * Moodle exception containing the supplied substring. Used to pin the
     * F9 invariant (a non-owner sees the same generic error as a missing
     * ticket).
     *
     * @When /^I expect a Moodle exception containing "([^"]+)" when I visit the Zendesk ticket page for "([^"]+)"$/
     * @param string $expectedmessage Substring that must appear.
     * @param string $subject Ticket subject seeded by an earlier step.
     */
    public function i_expect_a_moodle_exception_when_visiting_zendesk_ticket(
        string $expectedmessage,
        string $subject
    ): void {
        global $DB;

        $ticket = $DB->get_record('local_zendesk_ticket', ['subject' => $subject], 'id', MUST_EXIST);
        $this->i_expect_a_moodle_exception_containing_when_i_visit(
            $expectedmessage,
            '/local/zendesk/view.php?id=' . $ticket->id
        );
    }
}
