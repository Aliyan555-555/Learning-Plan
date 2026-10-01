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
 * Auto-enrolment engine: enrols learners into the courses referenced by a
 * learning plan when the plan is assigned to them.
 *
 * Enrolment is add-only. Unassigning a plan, or a learner leaving a cohort or
 * group, never removes or suspends an enrolment created here.
 *
 * @package    local_learningplan
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

/**
 * Static helpers that turn plan assignments into course enrolments, safely and
 * in batches so a single cohort assignment of 10k-20k members does not stall
 * the web request or exhaust memory in cron.
 */
class enrolment {

    /** @var int Fallback batch size for streaming large member lists. */
    const DEFAULT_BATCH_SIZE = 1000;

    /** @var array<int,\stdClass|null> Per-request cache of manual enrol instances, keyed by courseid. */
    protected static $instancecache = [];

    /**
     * Effective batch size from plugin config.
     *
     * @return int
     */
    public static function get_batch_size(): int {
        $size = (int)get_config('local_learningplan', 'enrolbatchsize');
        return $size > 0 ? $size : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Whether auto-enrolment is switched on for the site.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $cfg = get_config('local_learningplan', 'autoenrol');
        // Default on when the setting has never been saved.
        return ($cfg === false || $cfg === null) ? true : (bool)$cfg;
    }

    /**
     * Role to assign when enrolling. Configurable, defaults to the student archetype.
     *
     * @return int role id, or 0 to enrol without a role
     */
    public static function get_enrol_roleid(): int {
        $roleid = (int)get_config('local_learningplan', 'enrolrole');
        if ($roleid > 0) {
            return $roleid;
        }
        $studentroles = get_archetype_roles('student');
        if ($studentroles) {
            return (int)reset($studentroles)->id;
        }
        return 0;
    }

    /**
     * Queue the batched adhoc task that seeds progress and enrols learners for
     * one assignment. Identical pending tasks are de-duplicated.
     *
     * @param int $assignmentid
     */
    public static function queue_assignment_sync(int $assignmentid): void {
        $task = new \local_learningplan\task\sync_assignment_enrolments();
        $task->set_custom_data(['assignmentid' => $assignmentid]);
        $task->set_component('local_learningplan');
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Get (creating if needed) the manual enrolment instance for a course.
     *
     * @param \stdClass $course
     * @param \progress_trace|null $trace
     * @return \stdClass|null the enrol row, or null if manual enrolment is unavailable
     */
    public static function get_manual_instance(\stdClass $course, ?\progress_trace $trace = null): ?\stdClass {
        global $DB;

        if (array_key_exists($course->id, self::$instancecache)) {
            return self::$instancecache[$course->id];
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            if ($trace) {
                $trace->output("manual enrolment plugin is disabled - cannot enrol into course {$course->id}");
            }
            return self::$instancecache[$course->id] = null;
        }

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instanceid = $plugin->add_default_instance($course);
            if ($instanceid === null) {
                $instanceid = $plugin->add_instance($course);
            }
            $instance = $instanceid ? $DB->get_record('enrol', ['id' => $instanceid]) : null;
        }

        return self::$instancecache[$course->id] = ($instance ?: null);
    }

    /**
     * Enrol a batch of learners into every course referenced by a plan.
     *
     * Idempotent: users already enrolled (by any method) are left untouched, and
     * a unique bookkeeping row per (plan, course, user) prevents repeat work.
     *
     * @param int $planid
     * @param int[] $userids batch of user ids (already de-duplicated by the caller)
     * @param \progress_trace|null $trace
     */
    public static function sync_users_for_plan(int $planid, array $userids, ?\progress_trace $trace = null): void {
        global $DB;

        if (!self::is_enabled() || !$userids) {
            return;
        }

        $courseids = api::get_plan_courseids($planid);
        if (!$courseids) {
            return;
        }

        $plan = api::get_plan($planid);
        $roleid = self::get_enrol_roleid();
        $timestart = (int)($plan->startdate ?? 0);
        $timeend = (int)($plan->enddate ?? 0);
        $manualplugin = enrol_get_plugin('manual');
        $userids = array_values(array_unique(array_map('intval', $userids)));

        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }
            $instance = self::get_manual_instance($course, $trace);

            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');

            // Users we have already recorded in local_learningplan_enrol for this plan+course.
            $handled = $DB->get_fieldset_sql(
                "SELECT userid FROM {local_learningplan_enrol}
                  WHERE planid = :planid AND courseid = :courseid AND userid $insql",
                ['planid' => $planid, 'courseid' => $courseid] + $inparams
            );
            $handled = array_flip(array_map('intval', $handled));

            // Users already enrolled in course_enrolments by any method.
            $enrolled = $DB->get_fieldset_sql(
                "SELECT DISTINCT ue.userid
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = :courseid AND ue.userid $insql",
                ['courseid' => $courseid] + $inparams
            );
            $enrolled = array_flip(array_map('intval', $enrolled));

            $now = time();
            $created = 0;

            foreach ($userids as $userid) {
                if (isset($enrolled[$userid])) {
                    // Already enrolled by manual or another method - ensure our bookkeeping record exists.
                    if (!isset($handled[$userid])) {
                        $record = (object)[
                            'planid' => $planid,
                            'courseid' => $courseid,
                            'userid' => $userid,
                            'enrolid' => null,
                            'timecreated' => $now,
                        ];
                        $DB->insert_record('local_learningplan_enrol', $record);
                    }
                    continue;
                }

                if (!$instance || !$manualplugin) {
                    continue;
                }

                try {
                    $manualplugin->enrol_user($instance, $userid, $roleid, $timestart, $timeend, ENROL_USER_ACTIVE);
                    $created++;

                    $record = (object)[
                        'planid' => $planid,
                        'courseid' => $courseid,
                        'userid' => $userid,
                        'enrolid' => $instance->id,
                        'timecreated' => $now,
                    ];

                    if (isset($handled[$userid])) {
                        $DB->set_field('local_learningplan_enrol', 'enrolid', $instance->id, [
                            'planid' => $planid,
                            'courseid' => $courseid,
                            'userid' => $userid,
                        ]);
                    } else {
                        $enrolid = $DB->insert_record('local_learningplan_enrol', $record);
                        try {
                            \local_learningplan\event\user_enrolled::create([
                                'objectid' => $enrolid,
                                'context' => \context_course::instance($courseid),
                                'courseid' => $courseid,
                                'relateduserid' => $userid,
                                'other' => [
                                    'planid' => $planid,
                                    'courseid' => $courseid,
                                ],
                            ])->trigger();
                        } catch (\Throwable $e) {
                            // Non-fatal event trigger error.
                        }
                    }
                } catch (\Throwable $e) {
                    if ($trace) {
                        $trace->output("Error enrolling user {$userid} into course {$courseid}: " . $e->getMessage());
                    }
                }
            }

            if ($trace && $created) {
                $trace->output("plan $planid: enrolled $created learner(s) into course $courseid");
            }
        }
    }

    /**
     * Enrol a specific learner into all courses for all plans assigned to them.
     *
     * @param int $userid
     */
    public static function sync_user_enrolments(int $userid): void {
        global $DB;

        if (!self::is_enabled() || !$userid) {
            return;
        }

        $plans = api::get_user_plans($userid);
        foreach ($plans as $plan) {
            self::sync_users_for_plan($plan->id, [$userid]);
        }
    }

    /**
     * Reconcile every plan that has at least one assignment. Catches cohort/group
     * membership changes, steps added after assignment, and adhoc runs that died
     * mid-way. Safe to run repeatedly.
     *
     * @param \progress_trace|null $trace
     */
    public static function reconcile_all(?\progress_trace $trace = null): void {
        global $DB;

        if (!self::is_enabled()) {
            if ($trace) {
                $trace->output('local_learningplan auto-enrolment is disabled - nothing to reconcile');
            }
            return;
        }

        $planids = $DB->get_fieldset_sql(
            "SELECT DISTINCT a.planid FROM {local_learningplan_assignment} a"
        );

        $batchsize = self::get_batch_size();

        foreach ($planids as $planid) {
            $planid = (int)$planid;
            $userids = api::get_plan_userids($planid);
            if (!$userids) {
                continue;
            }
            if ($trace) {
                $trace->output("reconciling plan $planid for " . count($userids) . ' learner(s)');
            }
            foreach (array_chunk($userids, $batchsize) as $batch) {
                self::sync_users_for_plan($planid, $batch, $trace);
                gc_collect_cycles();
            }
        }
    }

    /**
     * Plans assigned to a cohort (directly) that reference at least one course.
     *
     * @param int $cohortid
     * @return int[] plan ids
     */
    public static function get_course_plans_for_cohort(int $cohortid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT a.planid
               FROM {local_learningplan_assignment} a
               JOIN {local_learningplan_step} s ON s.planid = a.planid
              WHERE a.cohortid = :cohortid AND s.courseid IS NOT NULL AND s.courseid <> 0",
            ['cohortid' => $cohortid]
        ));
    }

    /**
     * Plans assigned to a group (directly) that reference at least one course.
     *
     * @param int $groupid
     * @return int[] plan ids
     */
    public static function get_course_plans_for_group(int $groupid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT a.planid
               FROM {local_learningplan_assignment} a
               JOIN {local_learningplan_step} s ON s.planid = a.planid
              WHERE a.groupid = :groupid AND s.courseid IS NOT NULL AND s.courseid <> 0",
            ['groupid' => $groupid]
        ));
    }
}
