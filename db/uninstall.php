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
 * Uninstallation routine for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Custom uninstall steps.
 *
 * Deletes user preferences and user keys created by the plugin. Database tables are
 * dropped by Moodle's standard uninstall process after this function runs. Preference
 * and key deletions performed here are not rolled back if a later table drop fails.
 *
 * @return bool True on success.
 */
function xmldb_local_emailchangeconfirm_uninstall() {
    global $DB;

    // Delete all plugin user preferences.
    $DB->delete_records('user_preferences', ['name' => 'emailchangeconfirm_pending']);

    // Delete all user keys created with our script identifier.
    // NOTE: the plugin re-uses the core 'core_user/email_change' script to resume the
    // core flow, so we only remove keys we can attribute to ourselves is not reliable.
    // We therefore key cleanup on our own dedicated marker preference deletion above.
    // If a dedicated script identifier is later adopted, delete those keys here:
    $DB->delete_records('user_private_key', ['script' => 'local_emailchangeconfirm']);

    return true;
}
