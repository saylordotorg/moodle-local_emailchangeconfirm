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

/**
 * Event observer for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle the core user_updated event.
     *
     * Two responsibilities:
     *  1. Fallback interception: when the core flow has just set the 'newemail'
     *     preference, intercept it and require old-email verification instead.
     *  2. Completion: when a previously verified request's new email is now the
     *     user's live email, send the completion security notification.
     *
     * @param \core\event\user_updated $event
     * @return void
     */
    public static function user_updated(\core\event\user_updated $event): void {
        global $DB, $CFG;

        $userid = (int)$event->objectid;
        if ($userid <= 0) {
            return;
        }

        // Completion detection runs first and is independent of the enabled flag.
        $verified = $DB->get_record(manager::TABLE, ['userid' => $userid, 'status' => 'verified']);
        if ($verified) {
            $user = $DB->get_record('user', ['id' => $userid], 'id, email', IGNORE_MISSING);
            if ($user && \core_text::strtolower($user->email) === \core_text::strtolower($verified->newemail)) {
                // The email change has completed through the core flow.
                manager::send_completion_notification($userid, $verified->oldemail, $verified->newemail);
                event\email_change_completed::create_from_request($verified)->trigger();

                // Clean up the completed request and our preference.
                $DB->delete_records(manager::TABLE, ['id' => $verified->id]);
                unset_user_preference(manager::PREF_PENDING, $userid);
            }
            return;
        }

        // Interception path begins here.
        if (empty($CFG->emailchangeconfirmation)) {
            return;
        }
        if (!manager::is_enabled()) {
            return;
        }

        // The core flow sets the 'newemail' preference and a key. Detect it.
        $newemail = get_user_preferences('newemail', '', $userid);
        if (empty($newemail)) {
            return;
        }

        // If we already have a pending request for this newemail, do not re-intercept
        // (this avoids a loop when we re-create the preference during resume).
        $pending = manager::get_pending_request($userid);
        if ($pending && \core_text::strtolower($pending->newemail) === \core_text::strtolower($newemail)) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, email', IGNORE_MISSING);
        if (!$user) {
            return;
        }

        // Users who can update profiles directly are not subject to interception
        // (core already bypasses confirmation for them, but guard regardless).
        $systemcontext = \context_system::instance();
        if (has_capability('moodle/user:update', $systemcontext)) {
            return;
        }

        $oldemail = $user->email;
        if (\core_text::strtolower($oldemail) === \core_text::strtolower($newemail)) {
            return;
        }

        manager::intercept_email_change($userid, $oldemail, $newemail);
    }
}
