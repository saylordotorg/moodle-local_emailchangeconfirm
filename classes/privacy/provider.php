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

namespace local_emailchangeconfirm\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_emailchangeconfirm.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string Table name. */
    const TABLE = 'local_emailchangeconfirm_requests';

    /**
     * Describe the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            self::TABLE,
            [
                'userid' => 'privacy:metadata:local_emailchangeconfirm_requests:userid',
                'oldemail' => 'privacy:metadata:local_emailchangeconfirm_requests:oldemail',
                'newemail' => 'privacy:metadata:local_emailchangeconfirm_requests:newemail',
                'status' => 'privacy:metadata:local_emailchangeconfirm_requests:status',
                'timecreated' => 'privacy:metadata:local_emailchangeconfirm_requests:timecreated',
            ],
            'privacy:metadata:local_emailchangeconfirm_requests'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {" . self::TABLE . "} ecc ON ecc.userid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND ecc.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        $sql = "SELECT userid FROM {" . self::TABLE . "} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || $context->instanceid != $userid) {
                continue;
            }
            $records = $DB->get_records(self::TABLE, ['userid' => $userid]);
            foreach ($records as $record) {
                $data = (object)[
                    'oldemail' => $record->oldemail,
                    'newemail' => $record->newemail,
                    'status' => $record->status,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                ];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_emailchangeconfirm'), $record->id],
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the given context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_user) {
            return;
        }
        $DB->delete_records(self::TABLE, ['userid' => $context->instanceid]);
    }

    /**
     * Delete all data for the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && $context->instanceid == $userid) {
                $DB->delete_records(self::TABLE, ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete data for multiple users in a single context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            if ($userid == $context->instanceid) {
                $DB->delete_records(self::TABLE, ['userid' => $userid]);
            }
        }
    }
}
