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
 * English language strings for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cancel_notauthorised'] = 'You are not authorised to cancel this request.';
$string['cancel_notfound'] = 'No pending email change request was found.';
$string['cancel_success'] = 'Your email change request has been cancelled.';
$string['completionemail_body'] = 'Hello,

This is a security notification to inform you that the email address on your account at {$a->sitename} was changed to {$a->newemail} on {$a->changedate}.

If you made this change, no action is required.

If you did not make this change, please contact support immediately: {$a->support}

{$a->admin}';
$string['completionemail_subject'] = 'Your email address has been changed';
$string['error_attempts_range'] = 'The maximum attempts must be an integer between 1 and 10.';
$string['error_window_range'] = 'The verification window must be an integer between 5 and 1440 minutes.';
$string['event_email_change_cancelled'] = 'Email change cancelled';
$string['event_email_change_completed'] = 'Email change completed';
$string['event_email_change_requested'] = 'Email change requested';
$string['event_old_email_verified'] = 'Old email verified';
$string['event_token_validation_failed'] = 'Email change token validation failed';
$string['mfa_cancelled'] = 'The email change was cancelled because re-authentication was not completed.';
$string['notice_cancel'] = 'Cancel email change request';
$string['notice_pending'] = 'You have a pending email change to {$a}. Check your current email for the verification link.';
$string['notice_resend'] = 'Resend verification email';
$string['notice_verification_failed_send'] = 'The verification email could not be sent. Please try again or contact your administrator.';
$string['notice_verification_sent'] = 'A verification email has been sent to your current email address ({$a}). Please open the link in that email to confirm the change.';
$string['pluginname'] = 'Email change confirmation';
$string['privacy:metadata:local_emailchangeconfirm_requests'] = 'Pending email change verification requests.';
$string['privacy:metadata:local_emailchangeconfirm_requests:newemail'] = 'The requested new email address.';
$string['privacy:metadata:local_emailchangeconfirm_requests:oldemail'] = 'The email address at the time of the request.';
$string['privacy:metadata:local_emailchangeconfirm_requests:status'] = 'The status of the request.';
$string['privacy:metadata:local_emailchangeconfirm_requests:timecreated'] = 'The time the request was created.';
$string['privacy:metadata:local_emailchangeconfirm_requests:userid'] = 'The ID of the user requesting the email change.';
$string['setting_enabled'] = 'Enable old-email verification';
$string['setting_enabled_desc'] = 'When enabled, users must verify ownership of their current email address before the new-email confirmation is sent.';
$string['setting_max_attempts'] = 'Maximum attempts';
$string['setting_max_attempts_desc'] = 'Maximum number of failed verification attempts before the request is cancelled (1 to 10).';
$string['setting_notify_completion'] = 'Send completion notification';
$string['setting_notify_completion_desc'] = 'When enabled, a security notification is sent to the old email address after an email change completes.';
$string['setting_require_mfa'] = 'Require MFA re-authentication';
$string['setting_require_mfa_desc'] = 'When enabled, users with an active MFA factor must pass an MFA challenge before the old-email verification is sent.';
$string['setting_verification_window'] = 'Verification window (minutes)';
$string['setting_verification_window_desc'] = 'How long the verification link remains valid, in minutes (5 to 1440).';
$string['settings_heading'] = 'Email change confirmation settings';
$string['settings_heading_desc'] = 'Configure old-email verification for the email change process.';
$string['task_cleanup'] = 'Clean up expired email change requests';
$string['verify_expired'] = 'This email change request has expired. Please start the change again.';
$string['verify_invalid'] = 'The verification link is invalid or has expired.';
$string['verify_maxattempts'] = 'This email change request has been cancelled due to too many failed attempts.';
$string['verify_notauthorised'] = 'You are not authorised to perform this verification.';
$string['verify_notpending'] = 'This email change request is no longer pending.';
$string['verify_resent'] = 'The verification email has been resent to your current email address.';
$string['verify_success'] = 'Your current email address has been confirmed. A confirmation message has now been sent to your new email address. Please open the link in that message to complete the change.';
$string['verifyemail_body'] = 'Hello,

A request was made to change the email address on your account at {$a->sitename} from {$a->oldemail} to {$a->newemail}.

To approve this change, please confirm ownership of your current email address by opening the link below:

{$a->link}

This link will expire in {$a->minutes} minutes. After you confirm, a final confirmation message will be sent to the new address.

If you did not request this change, you can safely ignore this message and your email address will not be changed.

{$a->admin}';
$string['verifyemail_subject'] = 'Confirm your email address change';
