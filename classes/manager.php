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
 * Central business logic for the email change confirmation plugin.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Database table for requests. */
    const TABLE = 'local_emailchangeconfirm_requests';

    /** @var string User key script identifier (matches core email change). */
    const KEYSCRIPT = 'core_user/email_change';

    /** @var string Plugin user preference flag. */
    const PREF_PENDING = 'emailchangeconfirm_pending';

    /** @var int Length of generated raw tokens. */
    const TOKEN_LENGTH = 40;

    /**
     * Whether old-email verification is enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool)get_config('local_emailchangeconfirm', 'enabled');
    }

    /**
     * Get the configured verification window in seconds.
     *
     * @return int
     */
    public static function get_window_seconds(): int {
        $minutes = (int)get_config('local_emailchangeconfirm', 'verification_window');
        if ($minutes < 5 || $minutes > 1440) {
            $minutes = 30;
        }
        return $minutes * 60;
    }

    /**
     * Get the configured maximum attempts.
     *
     * @return int
     */
    public static function get_max_attempts(): int {
        $attempts = (int)get_config('local_emailchangeconfirm', 'max_attempts');
        if ($attempts < 1 || $attempts > 10) {
            $attempts = 3;
        }
        return $attempts;
    }

    /**
     * Generate a cryptographically suitable token.
     *
     * @return string A 40-character alphanumeric token.
     */
    public static function generate_token(): string {
        return random_string(self::TOKEN_LENGTH);
    }

    /**
     * Hash a raw token for storage.
     *
     * @param string $token Raw token.
     * @return string SHA-256 hex hash.
     */
    public static function hash_token(string $token): string {
        return hash('sha256', $token);
    }

    /**
     * Get the pending request for a user, if any.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_pending_request(int $userid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['userid' => $userid, 'status' => 'pending']);
        return $record ?: null;
    }

    /**
     * Intercept an email change initiated through the core flow.
     *
     * Cancels the core flow (removes the newemail preference and key), stores a
     * plugin request, generates a token and sends the old-email verification.
     *
     * @param int $userid
     * @param string $oldemail Email currently stored before the change.
     * @param string $newemail Requested new email.
     * @return \stdClass|null The created request, or null if not intercepted.
     */
    public static function intercept_email_change(int $userid, string $oldemail, string $newemail): ?\stdClass {
        global $DB;

        if (!self::is_enabled()) {
            return null;
        }

        // Same address - nothing to do.
        if (\core_text::strtolower(trim($oldemail)) === \core_text::strtolower(trim($newemail))) {
            return null;
        }

        // Remove any previous pending request for this user before creating a new one.
        self::delete_requests_for_user($userid);

        // Cancel the core flow that user/edit.php just initiated.
        self::suppress_core_flow($userid, true);

        $now = time();
        $token = self::generate_token();

        $request = new \stdClass();
        $request->userid = $userid;
        $request->oldemail = $oldemail;
        $request->newemail = $newemail;
        $request->tokenhash = self::hash_token($token);
        $request->status = 'pending';
        $request->attemptsleft = self::get_max_attempts();
        $request->timecreated = $now;
        $request->timeexpires = $now + self::get_window_seconds();
        $request->timeverified = null;
        $request->timemodified = $now;

        $request->id = $DB->insert_record(self::TABLE, $request);

        // Flag the pending state via user preference for lightweight checks.
        set_user_preference(self::PREF_PENDING, $request->id, $userid);

        // Fire audit event.
        event\email_change_requested::create_from_request($request)->trigger();

        // Send the verification email (token only available here, before hashing discards it).
        $sent = self::send_old_email_verification($request, $token);
        if (!$sent) {
            debugging('local_emailchangeconfirm: failed to send verification email to ' . $oldemail, DEBUG_NORMAL);
        }

        return $request;
    }

    /**
     * Remove the core email-change preferences and key, suppressing the core flow.
     *
     * @param int $userid
     * @param bool $suppresscurrentrequest Whether to suppress core's in-memory send flag for this request.
     * @return void
     */
    public static function suppress_core_flow(int $userid, bool $suppresscurrentrequest = false): void {
        global $CFG, $DB;

        unset_user_preference('newemail', $userid);
        unset_user_preference('newemailattemptsleft', $userid);
        delete_user_key(self::KEYSCRIPT, $userid);

        // Backstop the Moodle API call above so any already-created native key cannot remain usable.
        $DB->delete_records('user_private_key', ['script' => self::KEYSCRIPT, 'userid' => $userid]);

        if ($suppresscurrentrequest) {
            // user/edit.php checks this again immediately before sending the native new-email message.
            $CFG->emailchangeconfirmation = 0;
        }
    }

    /**
     * Validate a submitted raw token against a user's pending request.
     *
     * On success, returns the request. On failure decrements attempts and may cancel.
     *
     * @param int $userid
     * @param string $token Raw token from the link.
     * @return \stdClass|null The verified request, or null on failure.
     */
    public static function validate_token(int $userid, string $token): ?\stdClass {
        global $DB;

        $request = self::get_pending_request($userid);
        if (!$request) {
            return null;
        }

        // Expiry takes precedence over token correctness.
        if (time() > $request->timeexpires) {
            self::mark_expired($request);
            return null;
        }

        if ($request->attemptsleft <= 0) {
            self::cancel_request($request->id, 'max_attempts_exceeded');
            return null;
        }

        $expected = $request->tokenhash;
        $actual = self::hash_token($token);
        if (!hash_equals($expected, $actual)) {
            // Failed attempt.
            $request->attemptsleft--;
            $request->timemodified = time();
            $DB->update_record(self::TABLE, $request);

            event\token_validation_failed::create_from_request($request)->trigger();

            if ($request->attemptsleft <= 0) {
                self::cancel_request($request->id, 'max_attempts_exceeded');
            }
            return null;
        }

        return $request;
    }

    /**
     * Mark a request as verified and resume the core flow.
     *
     * @param \stdClass $request
     * @return void
     */
    public static function mark_verified(\stdClass $request): void {
        global $DB;

        $request->status = 'verified';
        $request->timeverified = time();
        $request->timemodified = time();
        $DB->update_record(self::TABLE, $request);

        event\old_email_verified::create_from_request($request)->trigger();

        self::resume_core_flow($request);
    }

    /**
     * Re-create the core email change preference and key, then send the new-email
     * confirmation, replicating what user/edit.php does.
     *
     * @param \stdClass $request
     * @return void
     */
    public static function resume_core_flow(\stdClass $request): void {
        global $CFG, $DB;

        $user = $DB->get_record('user', ['id' => $request->userid], '*', MUST_EXIST);

        // Core uses a 10-minute key window for the new-email confirmation.
        $validuntil = time() + 600;
        $key = create_user_key(self::KEYSCRIPT, $user->id, null, null, $validuntil);

        set_user_preference('newemail', $request->newemail, $user->id);
        set_user_preference('newemailattemptsleft', 3, $user->id);

        // Compose and send the new-email confirmation (mirrors core auth behaviour).
        $a = new \stdClass();
        $a->newemail = $request->newemail;
        $a->oldemail = $request->oldemail;
        $a->link = $CFG->wwwroot . '/user/emailupdate.php?key=' . $key . '&id=' . $user->id;
        $a->sitename = format_string($CFG->wwwroot);

        $supportuser = \core_user::get_support_user();
        $subject = get_string('emailupdate', 'auth');
        // Fall back to the changing-address message body used by core.
        $messagebody = get_string('auth_changingemailaddress', 'auth', $a) . "\n\n" . $a->link;

        // Send to the NEW email so the user can confirm it.
        $touser = clone($user);
        $touser->email = $request->newemail;
        email_to_user($touser, $supportuser, $subject, $messagebody);
    }

    /**
     * Cancel a request with a reason.
     *
     * @param int $requestid
     * @param string $reason One of the audit reason values.
     * @return void
     */
    public static function cancel_request(int $requestid, string $reason): void {
        global $DB;

        $request = $DB->get_record(self::TABLE, ['id' => $requestid]);
        if (!$request) {
            return;
        }

        $request->status = 'cancelled';
        $request->timemodified = time();
        $DB->update_record(self::TABLE, $request);

        // Clean up the core flow artefacts and our preference.
        self::suppress_core_flow($request->userid);
        unset_user_preference(self::PREF_PENDING, $request->userid);

        event\email_change_cancelled::create_from_request($request, $reason)->trigger();
    }

    /**
     * Mark a request as expired.
     *
     * @param \stdClass $request
     * @return void
     */
    public static function mark_expired(\stdClass $request): void {
        global $DB;

        $request->status = 'expired';
        $request->timemodified = time();
        $DB->update_record(self::TABLE, $request);

        self::suppress_core_flow($request->userid);
        unset_user_preference(self::PREF_PENDING, $request->userid);

        event\email_change_cancelled::create_from_request($request, 'token_expired')->trigger();
    }

    /**
     * Delete all request records and artefacts for a user.
     *
     * @param int $userid
     * @return void
     */
    public static function delete_requests_for_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
        unset_user_preference(self::PREF_PENDING, $userid);
    }

    /**
     * Scheduled cleanup: expire and delete requests whose window has elapsed.
     *
     * Idempotent: already-terminal expired records are simply deleted.
     *
     * @return int Number of records deleted.
     */
    public static function cleanup_expired(): int {
        global $DB;

        $now = time();

        // First, transition still-pending but elapsed requests to expired (fires events once).
        $pendingexpired = $DB->get_records_select(
            self::TABLE,
            "status = :status AND timeexpires < :now",
            ['status' => 'pending', 'now' => $now]
        );
        foreach ($pendingexpired as $request) {
            self::mark_expired($request);
        }

        // Then delete all elapsed records regardless of terminal status.
        $count = $DB->count_records_select(self::TABLE, "timeexpires < :now", ['now' => $now]);
        $DB->delete_records_select(self::TABLE, "timeexpires < :now", ['now' => $now]);

        return $count;
    }

    /**
     * Send the old-email verification message.
     *
     * @param \stdClass $request
     * @param string $token Raw token to embed in the link.
     * @return bool Whether the email was sent.
     */
    public static function send_old_email_verification(\stdClass $request, string $token): bool {
        return notification::send_verification($request, $token);
    }

    /**
     * Send the completion security notification to the old email address.
     *
     * @param int $userid
     * @param string $oldemail
     * @param string $newemail
     * @return void
     */
    public static function send_completion_notification(int $userid, string $oldemail, string $newemail): void {
        if (!get_config('local_emailchangeconfirm', 'notify_completion')) {
            return;
        }
        notification::send_completion($userid, $oldemail, $newemail);
    }
}
