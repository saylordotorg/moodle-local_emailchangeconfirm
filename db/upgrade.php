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
 * Upgrade steps for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_emailchangeconfirm upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_emailchangeconfirm_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061700) {
        $table = new xmldb_table('local_emailchangeconfirm_requests');
        $field = new xmldb_field('mfaverified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'attemptsleft');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        unset_config('require_mfa', 'local_emailchangeconfirm');

        upgrade_plugin_savepoint(true, 2026061700, 'local', 'emailchangeconfirm');
    }

    return true;
}
