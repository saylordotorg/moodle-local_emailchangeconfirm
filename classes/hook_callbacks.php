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
 * Hook callbacks for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Intercept Moodle's profile email-change flow before the user update completes.
     *
     * @param \core_user\hook\before_user_updated $hook
     * @return void
     */
    public static function before_user_updated(\core_user\hook\before_user_updated $hook): void {
        global $CFG;

        if (empty($CFG->emailchangeconfirmation) || !manager::is_enabled()) {
            return;
        }

        $userid = (int)($hook->user->id ?? 0);
        if ($userid <= 0 || empty($hook->currentuserdata->email)) {
            return;
        }

        $oldemail = (string)$hook->currentuserdata->email;
        $submittedemail = isset($hook->user->email) ? (string)$hook->user->email : $oldemail;

        // Match core user/edit.php: users with this capability bypass confirmation.
        if (has_capability('moodle/user:update', \context_system::instance())) {
            if (self::normalise_email($oldemail) !== self::normalise_email($submittedemail)) {
                self::cancel_pending_request($userid, 'admin_override');
            }
            return;
        }

        $newemail = get_user_preferences('newemail', '', $userid);
        if ($newemail === '') {
            return;
        }

        if (self::normalise_email($oldemail) === self::normalise_email($newemail)) {
            return;
        }

        $pending = manager::get_pending_request($userid);
        if ($pending && self::normalise_email($pending->newemail) === self::normalise_email($newemail)) {
            manager::suppress_core_flow($userid, true);
            $hook->user->email = $oldemail;
            return;
        }

        $request = manager::intercept_email_change($userid, $oldemail, $newemail);
        if ($request) {
            $hook->user->email = $oldemail;
        }
    }

    /**
     * Inject the pending-request notice through the Moodle output hook API.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        $hook->add_html(self::pending_notice_html());
    }

    /**
     * Build the pending request notice.
     *
     * @return string
     */
    private static function pending_notice_html(): string {
        global $USER, $PAGE, $OUTPUT;

        if (!isloggedin() || isguestuser()) {
            return '';
        }

        // Only render on the profile edit / preferences pages to avoid noise elsewhere.
        $relevantpaths = ['/user/edit.php', '/user/preferences.php'];
        $path = $PAGE->url ? $PAGE->url->get_path() : '';
        $isrelevant = false;
        foreach ($relevantpaths as $relevant) {
            if (substr($path, -strlen($relevant)) === $relevant) {
                $isrelevant = true;
                break;
            }
        }
        if (!$isrelevant || !manager::is_enabled()) {
            return '';
        }

        $request = manager::get_pending_request($USER->id);
        if (!$request) {
            return '';
        }

        $cancelurl = new \moodle_url('/local/emailchangeconfirm/verify.php', [
            'action' => 'cancel',
            'sesskey' => sesskey(),
        ]);

        $context = [
            'newemail' => s($request->newemail),
            'message' => get_string('notice_pending', 'local_emailchangeconfirm', s($request->newemail)),
            'cancelurl' => $cancelurl->out(false),
            'cancellabel' => get_string('notice_cancel', 'local_emailchangeconfirm'),
        ];

        return $OUTPUT->render_from_template('local_emailchangeconfirm/pending_notice', $context);
    }

    /**
     * Normalise an email address for comparisons.
     *
     * @param string $email
     * @return string
     */
    private static function normalise_email(string $email): string {
        return \core_text::strtolower(trim($email));
    }

    /**
     * Cancel a user's pending request if one exists.
     *
     * @param int $userid
     * @param string $reason
     * @return void
     */
    private static function cancel_pending_request(int $userid, string $reason): void {
        $pending = manager::get_pending_request($userid);
        if ($pending) {
            manager::cancel_request($pending->id, $reason);
        }
    }
}
