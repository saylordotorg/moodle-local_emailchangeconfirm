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
 * Email composition and sending for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification {
    /**
     * Send the old-email verification message.
     *
     * @param \stdClass $request The request record.
     * @param string $token Raw token to embed in the link.
     * @return bool Whether the message was sent.
     */
    public static function send_verification(\stdClass $request, string $token): bool {
        global $CFG, $DB, $SITE;

        $user = $DB->get_record('user', ['id' => $request->userid], '*', IGNORE_MISSING);
        if (!$user) {
            return false;
        }

        $link = new \moodle_url('/local/emailchangeconfirm/verify.php', [
            'token' => $token,
            'id' => $user->id,
        ]);

        $a = new \stdClass();
        $a->sitename = format_string($SITE->fullname);
        $a->oldemail = $request->oldemail;
        $a->newemail = $request->newemail;
        $a->link = $link->out(false);
        $a->minutes = (int)round(($request->timeexpires - $request->timecreated) / 60);
        $a->admin = generate_email_signoff();

        $subject = get_string('verifyemail_subject', 'local_emailchangeconfirm');
        $body = get_string('verifyemail_body', 'local_emailchangeconfirm', $a);

        // Send to the OLD email address to prove current ownership.
        $recipient = clone($user);
        $recipient->email = $request->oldemail;

        $supportuser = \core_user::get_support_user();

        return email_to_user($recipient, $supportuser, $subject, $body);
    }

    /**
     * Send the completion security notification to the old email.
     *
     * @param int $userid
     * @param string $oldemail
     * @param string $newemail
     * @return bool Whether the message was sent.
     */
    public static function send_completion(int $userid, string $oldemail, string $newemail): bool {
        global $DB, $SITE, $CFG;

        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
        if (!$user) {
            return false;
        }

        $a = new \stdClass();
        $a->sitename = format_string($SITE->fullname);
        $a->newemail = $newemail;
        $a->changedate = userdate(time(), '', \core_date::get_server_timezone());
        $a->support = !empty($CFG->supportemail) ? $CFG->supportemail : $CFG->wwwroot;
        $a->admin = generate_email_signoff();

        $subject = get_string('completionemail_subject', 'local_emailchangeconfirm');
        $body = get_string('completionemail_body', 'local_emailchangeconfirm', $a);

        // Send to the OLD email address as a security alert.
        $recipient = clone($user);
        $recipient->email = $oldemail;

        $supportuser = \core_user::get_support_user();

        $sent = email_to_user($recipient, $supportuser, $subject, $body);
        if (!$sent) {
            debugging('local_emailchangeconfirm: failed to send completion notification to ' . $oldemail, DEBUG_NORMAL);
        }
        return $sent;
    }
}
