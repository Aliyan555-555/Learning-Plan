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
 * Privacy Subsystem implementation for local_learningplan.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for local_learningplan.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this component.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_learningplan_progress',
            [
                'userid' => 'privacy:metadata:progress:userid',
                'stepid' => 'privacy:metadata:progress:stepid',
                'status' => 'privacy:metadata:progress:status',
                'starsearned' => 'privacy:metadata:progress:starsearned',
                'pointsawarded' => 'privacy:metadata:progress:pointsawarded',
                'timestarted' => 'privacy:metadata:progress:timestarted',
                'timecompleted' => 'privacy:metadata:progress:timecompleted',
            ],
            'privacy:metadata:progress'
        );

        $collection->add_database_table(
            'local_learningplan_points_log',
            [
                'userid' => 'privacy:metadata:points_log:userid',
                'planid' => 'privacy:metadata:points_log:planid',
                'stepid' => 'privacy:metadata:points_log:stepid',
                'points' => 'privacy:metadata:points_log:points',
                'reason' => 'privacy:metadata:points_log:reason',
                'timecreated' => 'privacy:metadata:points_log:timecreated',
            ],
            'privacy:metadata:points_log'
        );

        $collection->add_database_table(
            'local_learningplan_assignment',
            [
                'userid' => 'privacy:metadata:assignment:userid',
                'planid' => 'privacy:metadata:assignment:planid',
                'assignedby' => 'privacy:metadata:assignment:assignedby',
                'timeassigned' => 'privacy:metadata:assignment:timeassigned',
            ],
            'privacy:metadata:assignment'
        );

        $collection->add_database_table(
            'local_learningplan_enrol',
            [
                'userid' => 'privacy:metadata:enrol:userid',
                'planid' => 'privacy:metadata:enrol:planid',
                'courseid' => 'privacy:metadata:enrol:courseid',
                'timecreated' => 'privacy:metadata:enrol:timecreated',
            ],
            'privacy:metadata:enrol'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                   AND (
                        EXISTS (SELECT 1 FROM {local_learningplan_progress} WHERE userid = :uid1)
                     OR EXISTS (SELECT 1 FROM {local_learningplan_points_log} WHERE userid = :uid2)
                     OR EXISTS (SELECT 1 FROM {local_learningplan_assignment} WHERE userid = :uid3)
                     OR EXISTS (SELECT 1 FROM {local_learningplan_enrol} WHERE userid = :uid4)
                   )";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'uid1' => $userid,
            'uid2' => $userid,
            'uid3' => $userid,
            'uid4' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_learningplan_progress}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_learningplan_points_log}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_learningplan_assignment} WHERE userid IS NOT NULL', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_learningplan_enrol}', []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $context = \context_system::instance();

        // 1. Export learner progress.
        $progressrecords = $DB->get_records('local_learningplan_progress', ['userid' => $user->id]);
        if (!empty($progressrecords)) {
            $progressdata = [];
            foreach ($progressrecords as $record) {
                $progressdata[] = (object)[
                    'stepid' => $record->stepid,
                    'status' => $record->status,
                    'starsearned' => (int)$record->starsearned,
                    'pointsawarded' => (int)$record->pointsawarded,
                    'timestarted' => !empty($record->timestarted) ? transform::datetime($record->timestarted) : null,
                    'timecompleted' => !empty($record->timecompleted) ? transform::datetime($record->timecompleted) : null,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_learningplan'), get_string('progress', 'local_learningplan')],
                (object)['progress' => $progressdata]
            );
        }

        // 2. Export points audit log.
        $pointsrecords = $DB->get_records('local_learningplan_points_log', ['userid' => $user->id], 'timecreated ASC');
        if (!empty($pointsrecords)) {
            $pointsdata = [];
            foreach ($pointsrecords as $record) {
                $pointsdata[] = (object)[
                    'planid' => $record->planid,
                    'stepid' => $record->stepid,
                    'points' => (int)$record->points,
                    'reason' => $record->reason,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_learningplan'), get_string('points', 'local_learningplan')],
                (object)['points_log' => $pointsdata]
            );
        }

        // 3. Export plan assignments.
        $assignmentrecords = $DB->get_records('local_learningplan_assignment', ['userid' => $user->id]);
        if (!empty($assignmentrecords)) {
            $assignmentdata = [];
            foreach ($assignmentrecords as $record) {
                $assignmentdata[] = (object)[
                    'planid' => $record->planid,
                    'assignedby' => $record->assignedby,
                    'timeassigned' => transform::datetime($record->timeassigned),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_learningplan'), get_string('assigned', 'local_learningplan')],
                (object)['assignments' => $assignmentdata]
            );
        }

        // 4. Export auto-enrolment history.
        $enrolrecords = $DB->get_records('local_learningplan_enrol', ['userid' => $user->id]);
        if (!empty($enrolrecords)) {
            $enroldata = [];
            foreach ($enrolrecords as $record) {
                $enroldata[] = (object)[
                    'planid' => $record->planid,
                    'courseid' => $record->courseid,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_learningplan'), get_string('autoenrol', 'local_learningplan')],
                (object)['enrolments' => $enroldata]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_learningplan_progress');
        $DB->delete_records('local_learningplan_points_log');
        $DB->delete_records('local_learningplan_assignment');
        $DB->delete_records('local_learningplan_enrol');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $DB->delete_records('local_learningplan_progress', ['userid' => $user->id]);
        $DB->delete_records('local_learningplan_points_log', ['userid' => $user->id]);
        $DB->delete_records('local_learningplan_assignment', ['userid' => $user->id]);
        $DB->delete_records('local_learningplan_enrol', ['userid' => $user->id]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_learningplan_progress', "userid $insql", $inparams);
        $DB->delete_records_select('local_learningplan_points_log', "userid $insql", $inparams);
        $DB->delete_records_select('local_learningplan_assignment', "userid $insql", $inparams);
        $DB->delete_records_select('local_learningplan_enrol', "userid $insql", $inparams);
    }
}
