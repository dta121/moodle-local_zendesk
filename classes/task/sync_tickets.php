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
 * Scheduled task to sync Zendesk ticket state.
 *
 * @package    local_zendesk
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zendesk\task;

use local_zendesk\local\service\zendesk_service;

/**
 * Scheduled task for syncing Zendesk tickets back into Moodle.
 *
 * @package   local_zendesk
 */
final class sync_tickets extends \core\task\scheduled_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksynctickets', 'local_zendesk');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $service = new zendesk_service();
        if (!$service->is_enabled()) {
            mtrace('[local_zendesk] Synchronisation skipped because the plugin is disabled.');
            return;
        }

        if (!$service->is_configured()) {
            mtrace('[local_zendesk] Synchronisation skipped because the plugin is not configured.');
            return;
        }

        $processed = $service->sync_batch($service->get_sync_batch_size());
        mtrace('[local_zendesk] Synced ' . $processed . ' ticket(s).');
    }
}
