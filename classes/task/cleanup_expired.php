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

namespace local_emailchangeconfirm\task;

/**
 * Scheduled task to clean up expired email change requests.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_expired extends \core\task\scheduled_task {
    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'local_emailchangeconfirm');
    }

    /**
     * Execute the cleanup.
     *
     * @return void
     */
    public function execute() {
        $deleted = \local_emailchangeconfirm\manager::cleanup_expired();
        mtrace("local_emailchangeconfirm: cleaned up {$deleted} expired email change request(s).");
    }
}
