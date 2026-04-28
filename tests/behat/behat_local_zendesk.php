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
}
