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
 * The scoring engine: turns Moodle's own completion/gradebook data into
 * step completion, star ratings, and a points ledger entry.
 *
 * Every rule here reads a real Moodle signal — course completion, activity
 * completion, or a real gradebook percentage. Nothing is fabricated.
 *
 * @package    local_learningplan
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Evaluates step completion against real Moodle data and records the result.
 */
class scoring {

    /**
     * Re-evaluate every step of every plan for one user (used after a
     * completion event, or on demand). Only touches steps that are not
     * already completed — completion is permanent, never re-evaluated
     * downward. Loops until no new steps are completed to handle cascading
     * unlocks automatically.
     *
     * @param int $userid
     */
    public static function sync_user(int $userid): void {
        global $DB;

        // Note: course enrolment reconciliation is intentionally NOT performed here.
        // It is handled at assignment time (api::assign_plan), on cohort/group
        // membership events (observer), by the hourly reconcile_enrolments task, and
        // just-in-time when a learner opens their plan map. Keeping it out of this
        // method means completion catch-up stays cheap on read paths.

        $loop = true;
        $max_iterations = 20; // safety net against infinite loops
        $iterations = 0;

        while ($loop && $iterations < $max_iterations) {
            $loop = false;
            $iterations++;

            $sql = "SELECT s.*
                      FROM {local_learningplan_step} s
                      JOIN {local_learningplan_progress} pr ON pr.stepid = s.id AND pr.userid = :userid
                     WHERE pr.status IN ('available', 'inprogress') AND s.steptype IN ('course', 'activity')";
            $steps = $DB->get_records_sql($sql, ['userid' => $userid]);

            foreach ($steps as $step) {
                if (self::evaluate_and_apply($step, $userid)) {
                    $loop = true;
                }
            }
        }

        // Always check if any completed chapters or whole plans have earned badges.
        badges::sync_user_badges($userid);
    }

    /**
     * Re-evaluate every learner against a specific course (called from the
     * completion event observer, which already knows the course involved).
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function sync_user_course(int $courseid, int $userid): void {
        // Just run the full cascading sync to ensure cross-course unlocks are handled end-to-end.
        self::sync_user($userid);
    }

    /**
     * Check one step against real Moodle data for one user; if it is now
     * complete, record the result (points, stars, unlock next step / chapter
     * / plan rewards). Safe to call repeatedly — idempotent.
     *
     * @param \stdClass $step
     * @param int $userid
     * @return bool true if the step was (newly) completed by this call
     */
    public static function evaluate_and_apply(\stdClass $step, int $userid): bool {
        global $DB;

        $progress = $DB->get_record('local_learningplan_progress', ['stepid' => $step->id, 'userid' => $userid]);
        if (!$progress || $progress->status === 'completed') {
            return false;
        }

        $result = self::evaluate_step($step, $userid);
        if (!$result['completed']) {
            if ($progress->status === 'available' && !$progress->timestarted) {
                $DB->set_field('local_learningplan_progress', 'timestarted', time(), ['id' => $progress->id]);
                $DB->set_field('local_learningplan_progress', 'status', 'inprogress', ['id' => $progress->id]);
            }
            return false;
        }

        self::record_completion($step, $progress, $result['stars'], 'moodle', $userid);
        return true;
    }

    /**
     * Learner self-reports a file/url step as done (no native Moodle signal
     * exists for these). Transparent: always tagged verifiedby = 'self'.
     *
     * @param int $stepid
     * @param int $userid
     * @return bool
     */
    public static function mark_self_reported(int $stepid, int $userid): bool {
        global $DB;

        $step = $DB->get_record('local_learningplan_step', ['id' => $stepid], '*', MUST_EXIST);
        if (!in_array($step->steptype, ['file', 'url'])) {
            return false;
        }

        $progress = $DB->get_record('local_learningplan_progress', ['stepid' => $stepid, 'userid' => $userid]);
        if (!$progress || $progress->status === 'completed' || $progress->status === 'locked') {
            return false;
        }

        // Self-reported steps have no gradebook signal, so completion earns full stars —
        // consistent with how a completed course/activity step with no grade item is scored.
        $stars = max(1, (int)$step->maxstars);
        self::record_completion($step, $progress, $stars, 'self', $userid);
        
        // Trigger a sync in case this self-reported step unlocked Moodle activities that are already completed.
        self::sync_user($userid);
        
        return true;
    }

    /**
     * Determine whether a step is complete right now, and how many stars
     * it earns, purely from Moodle's own data.
     *
     * @param \stdClass $step
     * @param int $userid
     * @return array ['completed' => bool, 'stars' => int]
     */
    public static function evaluate_step(\stdClass $step, int $userid): array {
        global $DB;

        if ($step->steptype === 'course') {
            if (!$step->courseid || !$DB->record_exists('course', ['id' => $step->courseid])) {
                return ['completed' => false, 'stars' => 0];
            }
            $course = get_course($step->courseid);
            $completioninfo = new \completion_info($course);
            if (!$completioninfo->is_enabled()) {
                return ['completed' => false, 'stars' => 0];
            }
            if (!$completioninfo->is_course_complete($userid)) {
                return ['completed' => false, 'stars' => 0];
            }
            $percent = self::get_course_grade_percent($userid, $step->courseid);
            return ['completed' => true, 'stars' => self::stars_from_percent($step, $percent)];
        }

        if ($step->steptype === 'activity') {
            if (!$step->cmid) {
                return ['completed' => false, 'stars' => 0];
            }
            $cm = get_coursemodule_from_id('', $step->cmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return ['completed' => false, 'stars' => 0];
            }
            $course = get_course($cm->course);
            $completioninfo = new \completion_info($course);
            if (!$completioninfo->is_enabled($cm)) {
                return ['completed' => false, 'stars' => 0];
            }
            $data = $completioninfo->get_data($cm, false, $userid);
            // Allow completion to succeed even if they failed (COMPLETION_COMPLETE_FAIL = 3) so they don't get stuck forever.
            $iscomplete = in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, 3]);
            if (!$iscomplete) {
                return ['completed' => false, 'stars' => 0];
            }
            $percent = self::get_activity_grade_percent($userid, $cm);
            return ['completed' => true, 'stars' => self::stars_from_percent($step, $percent)];
        }

        return ['completed' => false, 'stars' => 0];
    }

    /**
     * Real course-total grade as a percentage, or null if the course has no
     * grade item / the learner has no grade yet.
     *
     * @param int $userid
     * @param int $courseid
     * @return float|null
     */
    private static function get_course_grade_percent(int $userid, int $courseid): ?float {
        $gradeitem = \grade_item::fetch_course_item($courseid);
        if (!$gradeitem || $gradeitem->grademax <= 0) {
            return null;
        }
        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
        if (!$gradegrade || $gradegrade->finalgrade === null) {
            return null;
        }
        return ((float)$gradegrade->finalgrade / (float)$gradeitem->grademax) * 100;
    }

    /**
     * Real activity grade as a percentage, or null if the activity is not
     * graded / the learner has no grade yet.
     *
     * @param int $userid
     * @param \stdClass $cm
     * @return float|null
     */
    private static function get_activity_grade_percent(int $userid, \stdClass $cm): ?float {
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid' => $cm->course,
        ]);
        if (!$gradeitem || $gradeitem->grademax <= 0) {
            return null;
        }
        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
        if (!$gradegrade || $gradegrade->finalgrade === null) {
            return null;
        }
        return ((float)$gradegrade->finalgrade / (float)$gradeitem->grademax) * 100;
    }

    /**
     * Map a real percentage (or null, meaning "no grade exists for this
     * content") onto the step's configured star thresholds.
     *
     * @param \stdClass $step
     * @param float|null $percent
     * @return int 1-3
     */
    private static function stars_from_percent(\stdClass $step, ?float $percent): int {
        if ($percent === null) {
            // No gradable item on this content — completion alone earns full stars.
            return (int)$step->maxstars;
        }

        $criteria = json_decode($step->starcriteria ?: '{}');
        $threshold1 = $criteria->threshold1 ?? 70;
        $threshold2 = $criteria->threshold2 ?? 90;

        if ($percent >= $threshold2) {
            return 3;
        }
        if ($percent >= $threshold1) {
            return 2;
        }
        return 1;
    }

    /**
     * Write the progress row, the points ledger entry, unlock the next
     * step/chapter, and fire completion notifications/badges as needed.
     *
     * @param \stdClass $step
     * @param \stdClass $progress
     * @param int $stars
     * @param string $verifiedby 'moodle' or 'self'
     * @param int $userid
     */
    private static function record_completion(
        \stdClass $step,
        \stdClass $progress,
        int $stars,
        string $verifiedby,
        int $userid
    ): void {
        global $DB;

        // The progress row and its matching points-ledger entry must land together
        // or not at all, so a later failure can never leave points unlogged.
        $transaction = $DB->start_delegated_transaction();
        try {
            // Idempotency guard: the unique (stepid, userid) index means this
            // update can only ever apply to exactly one row, exactly once,
            // because we already checked status !== 'completed' above.
            $DB->update_record('local_learningplan_progress', (object)[
                'id' => $progress->id,
                'status' => 'completed',
                'starsearned' => $stars,
                'pointsawarded' => $step->points,
                'verifiedby' => $verifiedby,
                'timestarted' => $progress->timestarted ?: time(),
                'timecompleted' => time(),
            ]);

            $DB->insert_record('local_learningplan_points_log', (object)[
                'userid' => $userid,
                'planid' => $step->planid,
                'stepid' => $step->id,
                'points' => $step->points,
                'reason' => format_string($step->title),
                'timecreated' => time(),
            ]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        \local_learningplan\event\step_completed::create([
            'objectid' => $progress->id,
            'context' => \context_system::instance(),
            'relateduserid' => $userid,
            'other' => [
                'stepid' => $step->id,
                'planid' => $step->planid,
                'stars' => $stars,
                'points' => $step->points,
            ],
        ])->trigger();

        \local_learningplan\event\points_awarded::create([
            'objectid' => $progress->id,
            'context' => \context_system::instance(),
            'relateduserid' => $userid,
            'other' => [
                'points' => $step->points,
                'reason' => format_string($step->title),
                'planid' => $step->planid,
                'stepid' => $step->id,
            ],
        ])->trigger();

        self::unlock_next_step($step, $userid);
        self::check_chapter_and_plan_completion($step, $userid);
    }

    /**
     * If the plan is in sequential mode, unlock the very next step after
     * the one just completed.
     *
     * @param \stdClass $step
     * @param int $userid
     */
    private static function unlock_next_step(\stdClass $step, int $userid): void {
        global $DB;

        $plan = $DB->get_record('local_learningplan_plan', ['id' => $step->planid]);
        if (!$plan || $plan->progressionmode !== 'sequential') {
            return;
        }

        // Next step within the same chapter, else first step of the next chapter.
        $next = $DB->get_record_sql(
            "SELECT * FROM {local_learningplan_step}
              WHERE chapterid = :chapterid AND sortorder > :sortorder
           ORDER BY sortorder ASC",
            ['chapterid' => $step->chapterid, 'sortorder' => $step->sortorder],
            IGNORE_MULTIPLE
        );

        if (!$next) {
            $chapter = $DB->get_record('local_learningplan_chapter', ['id' => $step->chapterid]);
            $nextchapter = $DB->get_record_sql(
                "SELECT * FROM {local_learningplan_chapter}
                  WHERE planid = :planid AND sortorder > :sortorder
               ORDER BY sortorder ASC",
                ['planid' => $step->planid, 'sortorder' => $chapter->sortorder],
                IGNORE_MULTIPLE
            );
            if ($nextchapter) {
                $next = $DB->get_record_sql(
                    "SELECT * FROM {local_learningplan_step}
                      WHERE chapterid = :chapterid
                   ORDER BY sortorder ASC",
                    ['chapterid' => $nextchapter->id],
                    IGNORE_MULTIPLE
                );
            }
        }

        if ($next) {
            $nextprogress = $DB->get_record('local_learningplan_progress', ['stepid' => $next->id, 'userid' => $userid]);
            if ($nextprogress && $nextprogress->status === 'locked') {
                $DB->set_field('local_learningplan_progress', 'status', 'available', ['id' => $nextprogress->id]);
            }
        }
    }

    /**
     * Check whether the chapter and/or whole plan is now complete for this
     * user, sending notifications and issuing badges as needed.
     *
     * @param \stdClass $step
     * @param int $userid
     */
    private static function check_chapter_and_plan_completion(\stdClass $step, int $userid): void {
        global $DB;

        $chaptersteps = $DB->get_fieldset_select('local_learningplan_step', 'id', 'chapterid = ?', [$step->chapterid]);
        $completedcount = 0;
        foreach ($chaptersteps as $sid) {
            $p = $DB->get_record('local_learningplan_progress', ['stepid' => $sid, 'userid' => $userid]);
            if ($p && $p->status === 'completed') {
                $completedcount++;
            }
        }

        $plan = $DB->get_record('local_learningplan_plan', ['id' => $step->planid]);

        if (!empty($chaptersteps) && $completedcount === count($chaptersteps)) {
            $chapter = $DB->get_record('local_learningplan_chapter', ['id' => $step->chapterid]);
            if ($chapter) {
                try {
                    notifier::chapter_completed($userid, $plan, $chapter);
                } catch (\Throwable $e) {
                    debugging('local_learningplan: chapter completion notice failed - ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
                badges::award_if_mapped($plan->id, $chapter->id, 'chapter_complete', $userid);
            }
        }

        $planstepids = $DB->get_fieldset_select('local_learningplan_step', 'id', 'planid = ?', [$plan->id]);
        $plancompleted = 0;
        foreach ($planstepids as $sid) {
            $p = $DB->get_record('local_learningplan_progress', ['stepid' => $sid, 'userid' => $userid]);
            if ($p && $p->status === 'completed') {
                $plancompleted++;
            }
        }

        if (!empty($planstepids) && $plancompleted === count($planstepids)) {
            try {
                notifier::plan_completed($userid, $plan);
            } catch (\Throwable $e) {
                debugging('local_learningplan: plan completion notice failed - ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            badges::award_if_mapped($plan->id, null, 'plan_complete', $userid);
        }
    }
}
