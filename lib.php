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
 * Library callbacks for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Inject a pending-request notice and cancellation link before the standard footer.
 *
 * This callback is invoked by Moodle on every page render. It only renders output
 * when the current user has a pending email change request.
 *
 * @return string HTML to be injected before the footer.
 */
function local_emailchangeconfirm_before_footer() {
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
    if (!$isrelevant) {
        return '';
    }

    if (!\local_emailchangeconfirm\manager::is_enabled()) {
        return '';
    }

    $request = \local_emailchangeconfirm\manager::get_pending_request($USER->id);
    if (!$request) {
        return '';
    }

    $cancelurl = new moodle_url('/local/emailchangeconfirm/verify.php', [
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
