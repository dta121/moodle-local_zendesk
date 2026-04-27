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
 * Install hook for local_zendesk.
 *
 * Seeds the instanceuuid setting so the value is fixed at install time and
 * cannot race between two concurrent first-hit requests on the front end
 * (security review F12). Subsequent upgrades and runtime callers treat this
 * value as immutable.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run on fresh install of local_zendesk.
 *
 * @return void
 */
function xmldb_local_zendesk_install(): void {
    if (trim((string) get_config('local_zendesk', 'instanceuuid')) === '') {
        set_config('instanceuuid', \core\uuid::generate(), 'local_zendesk');
    }
}
