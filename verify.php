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
 * Old-email verification and cancellation endpoint.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_emailchangeconfirm\manager;

require_login(null, false);

if (isguestuser()) {
    throw new \moodle_exception('verify_notauthorised', 'local_emailchangeconfirm');
}

$action = optional_param('action', 'verify', PARAM_ALPHA);
$token = optional_param('token', '', PARAM_ALPHANUM);
$targetid = optional_param('id', 0, PARAM_INT);

$context = context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/emailchangeconfirm/verify.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_emailchangeconfirm'));
$PAGE->set_heading(get_string('pluginname', 'local_emailchangeconfirm'));

$returnurl = new moodle_url('/user/preferences.php', ['userid' => $USER->id]);

/**
 * Render a single-message result page and exit.
 *
 * @param string $stringid The language string identifier in local_emailchangeconfirm.
 * @param string $type One of success, error, info.
 */
function local_emailchangeconfirm_show_result(string $stringid, string $type = 'info'): void {
    global $OUTPUT, $returnurl;
    $message = get_string($stringid, 'local_emailchangeconfirm');
    echo $OUTPUT->header();
    $notifytype = ($type === 'success') ? \core\output\notification::NOTIFY_SUCCESS
        : (($type === 'error') ? \core\output\notification::NOTIFY_ERROR : \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->notification($message, $notifytype);
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'cancel') {
    // Cancellation requires sesskey (CSRF protection).
    require_sesskey();

    $request = manager::get_pending_request($USER->id);
    if (!$request) {
        local_emailchangeconfirm_show_result('cancel_notfound', 'info');
    }

    // Ownership is implicit (we fetched by $USER->id), but guard explicitly.
    if ((int)$request->userid !== (int)$USER->id) {
        local_emailchangeconfirm_show_result('cancel_notauthorised', 'error');
    }

    manager::cancel_request($request->id, 'user_cancelled');
    local_emailchangeconfirm_show_result('cancel_success', 'success');
}

if ($action === 'resend') {
    require_sesskey();

    $request = manager::get_pending_request($USER->id);
    if (!$request) {
        local_emailchangeconfirm_show_result('cancel_notfound', 'info');
    }
    // A new token is required to resend (the previous raw token is not recoverable).
    $token = manager::generate_token();
    $request->tokenhash = manager::hash_token($token);
    $DB->update_record(manager::TABLE, $request);
    manager::send_old_email_verification($request, $token);
    local_emailchangeconfirm_show_result('verify_resent', 'success');
}

// Default action: verify token.
if ($targetid && (int)$targetid !== (int)$USER->id) {
    // The link was issued for a different user than the one logged in.
    local_emailchangeconfirm_show_result('verify_notauthorised', 'error');
}

$request = manager::get_pending_request($USER->id);
if (!$request) {
    // No pending request - either already handled or never existed.
    local_emailchangeconfirm_show_result('verify_notpending', 'info');
}

if (time() > $request->timeexpires) {
    manager::mark_expired($request);
    local_emailchangeconfirm_show_result('verify_expired', 'error');
}

$verified = manager::validate_token($USER->id, $token);
if ($verified) {
    manager::mark_verified($verified);
    local_emailchangeconfirm_show_result('verify_success', 'success');
} else {
    // Re-fetch to determine the failure mode after the attempt was processed.
    $after = manager::get_pending_request($USER->id);
    if (!$after) {
        // The request was cancelled (e.g. attempts exhausted) during validation.
        local_emailchangeconfirm_show_result('verify_maxattempts', 'error');
    }
    local_emailchangeconfirm_show_result('verify_invalid', 'error');
}
