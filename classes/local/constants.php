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

namespace local_zendesk\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared plugin constants.
 *
 * @package   local_zendesk
 */
final class constants {
    /** @var string */
    public const COMPONENT = 'local_zendesk';

    /** @var string */
    public const STATE_PENDINGCREATE = 'pendingcreate';

    /** @var string */
    public const STATE_CONFIRMINGCREATE = 'confirmingcreate';

    /** @var string */
    public const STATE_ACTIVE = 'active';

    /** @var string */
    public const STATE_CLOSED = 'closed';

    /** @var string */
    public const STATE_ERROR = 'error';

    /** @var array */
    public const DEFAULT_TAGS = [
        'moodle',
        'moodle_local_zendesk',
    ];

    /**
     * This class should not be instantiated.
     */
    private function __construct() {
    }
}
