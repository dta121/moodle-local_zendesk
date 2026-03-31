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
 * External service definitions for the plugin.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_zendesk_get_agent_context' => [
        'classname' => 'local_zendesk\external\get_agent_context',
        'methodname' => 'execute',
        'description' => 'Return Moodle agent context for a Zendesk requester.',
        'type' => 'read',
        'capabilities' => 'local/zendesk:viewallrequests',
        'ajax' => false,
    ],
];

$services = [
    'local_zendesk_agent_context_service' => [
        'functions' => [
            'local_zendesk_get_agent_context',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
        'shortname' => 'local_zendesk_agent_context_service',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
