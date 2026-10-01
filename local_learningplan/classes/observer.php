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
 * Event observer: reacts to Moodle's own completion events in real time.
 *
 * @package    local_learningplan
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

/**
 * Listens for course and activity completion and re-scores affected steps.
 */
class observer {

    /**
     * A course was marked complete for a user.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        $userid = (int)($event->relateduserid ?: $event->userid);
        $courseid = (int)$event->courseid;
        if (!$userid || !$courseid) {
            return;
        }
        // Ignore completions in courses that no learning plan of this user touches.
        if (!self::user_has_plan_step_for_course($userid, $courseid)) {
            return;
        }
        scoring::sync_user_course($courseid, $userid);
    }

    /**
     * Triggered when a student completes (or is marked complete for) any activity.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function activity_completion_updated(\core\event\course_module_completion_updated $event): void {
        $userid = (int)($event->relateduserid ?: $event->userid);
        $courseid = (int)$event->courseid;
        if (!$userid || !$courseid) {
            return;
        }
        // The overwhelming majority of activity-completion events on a site have
        // nothing to do with a learning plan. Bail out early unless this learner is
        // on a plan whose steps reference this course, so unrelated courses pay no
        // cost. Activity-type steps are re-scored immediately by sync_user; any
        // course-type step that now qualifies is picked up by the sync_progress
        // scheduled task within its 15-minute window.
        if (!self::user_has_plan_step_for_course($userid, $courseid)) {
            return;
        }
        scoring::sync_user_course($courseid, $userid);
    }

    /**
     * Whether the user is on at least one learning plan (directly, or via a cohort
     * or group) that has a step referencing the given course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    protected static function user_has_plan_step_for_course(int $userid, int $courseid): bool {
        global $DB;

        $sql = "SELECT 1
                  FROM {local_learningplan_assignment} a
                  JOIN {local_learningplan_step} s ON s.planid = a.planid
                 WHERE (s.courseid = :courseid1
                        OR s.cmid IN (SELECT cm.id FROM {course_modules} cm WHERE cm.course = :courseid2))
                   AND (a.userid = :userid1
                        OR a.cohortid IN (SELECT cm2.cohortid FROM {cohort_members} cm2 WHERE cm2.userid = :userid2)
                        OR a.groupid IN (SELECT gm.groupid FROM {groups_members} gm WHERE gm.userid = :userid3))";

        return $DB->record_exists_sql($sql, [
            'courseid1' => $courseid,
            'courseid2' => $courseid,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);
    }

    /**
     * A user was added to a cohort - enrol them into the courses of any plan
     * assigned to that cohort, without waiting for the hourly reconcile task.
     *
     * @param \core\event\cohort_member_added $event
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event): void {
        $userid = (int)$event->relateduserid;
        $cohortid = (int)$event->objectid;
        if (!$userid || !$cohortid) {
            return;
        }
        foreach (enrolment::get_course_plans_for_cohort($cohortid) as $planid) {
            self::seed_and_enrol_single($planid, $cohortid, 'cohortid', $userid);
        }
    }

    /**
     * A user was added to a group - enrol them into the courses of any plan
     * assigned to that group.
     *
     * @param \core\event\group_member_added $event
     */
    public static function group_member_added(\core\event\group_member_added $event): void {
        $userid = (int)$event->relateduserid;
        $groupid = (int)$event->objectid;
        if (!$userid || !$groupid) {
            return;
        }
        foreach (enrolment::get_course_plans_for_group($groupid) as $planid) {
            self::seed_and_enrol_single($planid, $groupid, 'groupid', $userid);
        }
    }

    /**
     * Seed progress rows and enrol a single freshly-added member for one plan.
     *
     * @param int $planid
     * @param int $targetid cohort id or group id
     * @param string $targetfield 'cohortid' or 'groupid'
     * @param int $userid
     */
    protected static function seed_and_enrol_single(int $planid, int $targetid, string $targetfield, int $userid): void {
        try {
            $assignment = (object)['planid' => $planid, $targetfield => $targetid];
            api::initialize_progress_for_assignment($assignment, [$userid]);
            enrolment::sync_users_for_plan($planid, [$userid]);
        } catch (\Exception $e) {
            // Never let enrolment bookkeeping break the membership change that
            // triggered us; the hourly reconcile task will pick it up.
            debugging('local_learningplan enrol on member-add failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
