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

namespace local_emailchangeconfirm\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when an email change request is cancelled or expired.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_change_cancelled extends \core\event\base {

    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = \local_emailchangeconfirm\manager::TABLE;
    }

    /**
     * Build an event instance from a request record.
     *
     * @param \stdClass $request
     * @param string $reason One of user_cancelled, token_expired, max_attempts_exceeded.
     * @return \core\event\base
     */
    public static function create_from_request(\stdClass $request, string $reason = 'user_cancelled'): \core\event\base {
        return self::create([
            'objectid' => $request->id,
            'relateduserid' => $request->userid,
            'context' => \context_user::instance($request->userid),
            'other' => [
                'oldemail' => $request->oldemail,
                'newemail' => $request->newemail,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_email_change_cancelled', 'local_emailchangeconfirm');
    }

    /**
     * Return non-localised description.
     *
     * @return string
     */
    public function get_description() {
        return "The email change request '{$this->objectid}' for user '{$this->relateduserid}' was cancelled "
            . "(reason: {$this->other['reason']}).";
    }
}
