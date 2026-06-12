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
 * Event fired when an old email address is successfully verified.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class old_email_verified extends \core\event\base {

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
     * @return \core\event\base
     */
    public static function create_from_request(\stdClass $request): \core\event\base {
        return self::create([
            'objectid' => $request->id,
            'relateduserid' => $request->userid,
            'context' => \context_user::instance($request->userid),
            'other' => [
                'oldemail' => $request->oldemail,
                'newemail' => $request->newemail,
                'reason' => 'old_email_confirmed',
            ],
        ]);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_old_email_verified', 'local_emailchangeconfirm');
    }

    /**
     * Return non-localised description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->relateduserid}' confirmed ownership of their current email address.";
    }
}
