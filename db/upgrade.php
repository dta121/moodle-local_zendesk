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
 * Schema upgrade steps for local_zendesk.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply local_zendesk schema upgrades.
 *
 * @param int $oldversion The plugin version before this upgrade.
 * @return bool
 */
function xmldb_local_zendesk_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042708) {
        // Define table local_zendesk_ticket_attachment to be created.
        $table = new xmldb_table('local_zendesk_ticket_attachment');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('localticketid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('urlhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('remoteurl', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('contenttype', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('localticketid_fk', XMLDB_KEY_FOREIGN, ['localticketid'], 'local_zendesk_ticket', ['id']);

        $table->add_index('ticketurl_uix', XMLDB_INDEX_UNIQUE, ['localticketid', 'urlhash']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026042708, 'local', 'zendesk');
    }

    if ($oldversion < 2026042715) {
        // Seed instanceuuid on existing installs so we can drop the lazy
        // generate-and-write fallback in the service layer (security review
        // F12). Idempotent: skips when a UUID is already configured.
        if (trim((string) get_config('local_zendesk', 'instanceuuid')) === '') {
            set_config('instanceuuid', \core\uuid::generate(), 'local_zendesk');
        }

        upgrade_plugin_savepoint(true, 2026042715, 'local', 'zendesk');
    }

    return true;
}
