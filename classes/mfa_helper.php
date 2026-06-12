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

namespace local_emailchangeconfirm;

defined('MOODLE_INTERNAL') || die();

/**
 * Optional MFA re-authentication integration for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mfa_helper {

    /**
     * Whether MFA re-authentication is required by plugin configuration.
     *
     * @return bool
     */
    public static function is_mfa_required(): bool {
        if (!get_config('local_emailchangeconfirm', 'require_mfa')) {
            return false;
        }
        // The MFA tool must be installed and enabled.
        if (!self::mfa_available()) {
            return false;
        }
        return true;
    }

    /**
     * Whether the tool_mfa plugin is installed and enabled.
     *
     * @return bool
     */
    public static function mfa_available(): bool {
        global $CFG;
        if (!file_exists($CFG->dirroot . '/admin/tool/mfa/classes/manager.php')) {
            return false;
        }
        return (bool)get_config('tool_mfa', 'enabled');
    }

    /**
     * Whether the given user has at least one active MFA factor configured.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_user_mfa_enabled(int $userid): bool {
        global $DB;

        if (!self::mfa_available()) {
            return false;
        }

        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
        if (!$user) {
            return false;
        }

        $factors = \tool_mfa\plugininfo\factor::get_active_user_factor_types($user);
        return count($factors) > 0;
    }

    /**
     * Whether the current session has passed MFA.
     *
     * @return bool
     */
    public static function verify_mfa_passed(): bool {
        if (!self::mfa_available()) {
            // If MFA is unavailable, treat as passed (skip).
            return true;
        }
        return \tool_mfa\manager::get_status() === \tool_mfa\plugininfo\factor::STATE_PASS;
    }

    /**
     * Whether an MFA challenge should be enforced for this user before verification.
     *
     * Combines the plugin setting with the user's MFA status: only users with an
     * active factor are challenged.
     *
     * @param int $userid
     * @return bool
     */
    public static function should_challenge(int $userid): bool {
        return self::is_mfa_required() && self::is_user_mfa_enabled($userid);
    }
}
