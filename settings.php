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
 * Admin settings for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Place the settings page under Site administration > Security.
    $settings = new admin_settingpage(
        'local_emailchangeconfirm',
        get_string('pluginname', 'local_emailchangeconfirm')
    );

    $ADMIN->add('security', $settings);

    $settings->add(new admin_setting_heading(
        'local_emailchangeconfirm/heading',
        get_string('settings_heading', 'local_emailchangeconfirm'),
        get_string('settings_heading_desc', 'local_emailchangeconfirm')
    ));

    // Enable old-email verification.
    $settings->add(new admin_setting_configcheckbox(
        'local_emailchangeconfirm/enabled',
        get_string('setting_enabled', 'local_emailchangeconfirm'),
        get_string('setting_enabled_desc', 'local_emailchangeconfirm'),
        1
    ));

    // Verification window (minutes), range 5-1440.
    $settings->add(new \local_emailchangeconfirm\admin\setting_configint_range(
        'local_emailchangeconfirm/verification_window',
        get_string('setting_verification_window', 'local_emailchangeconfirm'),
        get_string('setting_verification_window_desc', 'local_emailchangeconfirm'),
        30,
        5,
        1440,
        'error_window_range'
    ));

    // Maximum attempts, range 1-10.
    $settings->add(new \local_emailchangeconfirm\admin\setting_configint_range(
        'local_emailchangeconfirm/max_attempts',
        get_string('setting_max_attempts', 'local_emailchangeconfirm'),
        get_string('setting_max_attempts_desc', 'local_emailchangeconfirm'),
        3,
        1,
        10,
        'error_attempts_range'
    ));

    // Completion notification.
    $settings->add(new admin_setting_configcheckbox(
        'local_emailchangeconfirm/notify_completion',
        get_string('setting_notify_completion', 'local_emailchangeconfirm'),
        get_string('setting_notify_completion_desc', 'local_emailchangeconfirm'),
        1
    ));
}
