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
 * Unit tests for local_learningplan scoring engine.
 *
 * @package    local_learningplan
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

/**
 * Scoring testcase.
 *
 * @coversDefaultClass \local_learningplan\scoring
 */
class scoring_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Create a single-chapter plan with $count self-reported url steps.
     *
     * @param int $count
     * @param string $mode
     * @return array [planid, chapterid, int[] stepids]
     */
    protected function make_url_plan(int $count = 2, string $mode = 'sequential'): array {
        global $DB;

        $planid = $DB->insert_record('local_learningplan_plan', (object)[
            'name' => 'Plan',
            'progressionmode' => $mode,
            'createdby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
            'visible' => 1,
        ]);
        $chapterid = $DB->insert_record('local_learningplan_chapter', (object)[
            'planid' => $planid,
            'title' => 'Chapter',
            'sortorder' => 0,
        ]);
        $stepids = [];
        for ($i = 0; $i < $count; $i++) {
            $stepids[] = (int)$DB->insert_record('local_learningplan_step', (object)[
                'planid' => $planid,
                'chapterid' => $chapterid,
                'title' => 'Step ' . $i,
                'steptype' => 'url',
                'url' => 'https://example.com/' . $i,
                'sortorder' => $i,
                'points' => 25,
                'maxstars' => 3,
            ]);
        }
        return [$planid, $chapterid, $stepids];
    }

    /**
     * Marking a self-reported step as done records completion, full stars, points
     * and a ledger entry.
     *
     * @covers ::mark_self_reported
     */
    public function test_mark_self_reported_step() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        [$planid, $chapterid, $stepids] = $this->make_url_plan(1);
        api::ensure_user_progress((int)$user->id, $planid);

        $this->assertTrue(scoring::mark_self_reported($stepids[0], (int)$user->id));

        $progress = $DB->get_record('local_learningplan_progress',
            ['userid' => $user->id, 'stepid' => $stepids[0]]);
        $this->assertEquals('completed', $progress->status);
        $this->assertEquals(25, $progress->pointsawarded);
        $this->assertEquals(3, $progress->starsearned);
        $this->assertEquals('self', $progress->verifiedby);

        $log = $DB->get_records('local_learningplan_points_log',
            ['userid' => $user->id, 'stepid' => $stepids[0]]);
        $this->assertCount(1, $log);
        $this->assertEquals(25, reset($log)->points);
    }

    /**
     * Completing a step is idempotent — a second call does not double the points
     * ledger.
     *
     * @covers ::mark_self_reported
     * @covers ::record_completion
     */
    public function test_completion_is_idempotent() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        [$planid, $chapterid, $stepids] = $this->make_url_plan(1);
        api::ensure_user_progress((int)$user->id, $planid);

        $this->assertTrue(scoring::mark_self_reported($stepids[0], (int)$user->id));
        $this->assertFalse(scoring::mark_self_reported($stepids[0], (int)$user->id));

        $this->assertEquals(1, $DB->count_records('local_learningplan_points_log',
            ['userid' => $user->id, 'stepid' => $stepids[0]]));
    }

    /**
     * In sequential mode, completing a step unlocks the next one.
     *
     * @covers ::mark_self_reported
     * @covers ::unlock_next_step
     */
    public function test_sequential_unlock() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        [$planid, $chapterid, $stepids] = $this->make_url_plan(3, 'sequential');
        api::ensure_user_progress((int)$user->id, $planid);

        // Step 2 and 3 start locked.
        $this->assertEquals('locked', $DB->get_field('local_learningplan_progress', 'status',
            ['userid' => $user->id, 'stepid' => $stepids[1]]));

        scoring::mark_self_reported($stepids[0], (int)$user->id);

        $this->assertEquals('available', $DB->get_field('local_learningplan_progress', 'status',
            ['userid' => $user->id, 'stepid' => $stepids[1]]));
        // The one after that stays locked until step 2 is done.
        $this->assertEquals('locked', $DB->get_field('local_learningplan_progress', 'status',
            ['userid' => $user->id, 'stepid' => $stepids[2]]));
    }

    /**
     * A locked step cannot be self-reported.
     *
     * @covers ::mark_self_reported
     */
    public function test_cannot_self_report_locked_step() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        [$planid, $chapterid, $stepids] = $this->make_url_plan(2, 'sequential');
        api::ensure_user_progress((int)$user->id, $planid);

        $this->assertFalse(scoring::mark_self_reported($stepids[1], (int)$user->id));
    }

    /**
     * sync_user no longer performs course enrolment as a side effect.
     *
     * @covers ::sync_user
     */
    public function test_sync_user_does_not_enrol() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->setAdminUser();

        $planid = $DB->insert_record('local_learningplan_plan', (object)[
            'name' => 'Course plan', 'progressionmode' => 'open', 'createdby' => 2,
            'timecreated' => time(), 'timemodified' => time(), 'visible' => 1,
        ]);
        $chapterid = $DB->insert_record('local_learningplan_chapter',
            (object)['planid' => $planid, 'title' => 'C', 'sortorder' => 0]);
        $DB->insert_record('local_learningplan_step', (object)[
            'planid' => $planid, 'chapterid' => $chapterid, 'title' => 'Course step',
            'steptype' => 'course', 'courseid' => $course->id, 'sortorder' => 0,
            'points' => 10, 'maxstars' => 3,
        ]);
        $DB->insert_record('local_learningplan_assignment', (object)[
            'planid' => $planid, 'userid' => $user->id, 'assignedby' => 2, 'timeassigned' => time(),
        ]);
        api::ensure_user_progress((int)$user->id, $planid);

        scoring::sync_user((int)$user->id);

        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
    }

    /**
     * Test prerequisite gap detection when a learner completes Course 2 before Step 1,
     * and verify cascading auto-unlock/completion once Step 1 is subsequently completed.
     *
     * @covers \local_learningplan\api::get_sequential_prereq_gaps
     * @covers ::sync_user
     */
    public function test_sequential_prerequisite_gap_and_cascade() {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $user = $this->getDataGenerator()->create_user();
        $this->setAdminUser();

        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course3 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $planid = $DB->insert_record('local_learningplan_plan', (object)[
            'name' => 'Sequential 3-Course Plan',
            'progressionmode' => 'sequential',
            'createdby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
            'visible' => 1,
        ]);
        $chapterid = $DB->insert_record('local_learningplan_chapter', (object)[
            'planid' => $planid,
            'title' => 'Main Chapter',
            'sortorder' => 0,
        ]);

        $step1id = (int)$DB->insert_record('local_learningplan_step', (object)[
            'planid' => $planid,
            'chapterid' => $chapterid,
            'title' => 'Course 1 Step',
            'steptype' => 'course',
            'courseid' => $course1->id,
            'sortorder' => 0,
            'points' => 10,
            'maxstars' => 3,
        ]);
        $step2id = (int)$DB->insert_record('local_learningplan_step', (object)[
            'planid' => $planid,
            'chapterid' => $chapterid,
            'title' => 'Course 2 Step',
            'steptype' => 'course',
            'courseid' => $course2->id,
            'sortorder' => 1,
            'points' => 20,
            'maxstars' => 3,
        ]);
        $step3id = (int)$DB->insert_record('local_learningplan_step', (object)[
            'planid' => $planid,
            'chapterid' => $chapterid,
            'title' => 'Course 3 Step',
            'steptype' => 'course',
            'courseid' => $course3->id,
            'sortorder' => 2,
            'points' => 30,
            'maxstars' => 3,
        ]);

        // Student has already completed Course 2 in Moodle before finishing Step 1.
        $ccompletion2 = new \completion_completion(['course' => $course2->id, 'userid' => $user->id]);
        $ccompletion2->mark_complete();

        // Admin assigns the learning plan to student.
        api::assign_plan($planid, 'user', (int)$user->id);

        $plan = api::get_plan($planid);
        $chapters = api::get_chapters_with_steps($planid);
        $progress = api::get_user_progress((int)$user->id, $planid);

        // Verify Step 1 is available and Step 2 is locked in progress.
        $this->assertEquals('available', $progress[$step1id]->status);
        $this->assertEquals('locked', $progress[$step2id]->status);
        $this->assertEquals('locked', $progress[$step3id]->status);

        // Detect prerequisite gaps.
        $gaps = api::get_sequential_prereq_gaps($plan, $chapters, $progress, (int)$user->id);
        $this->assertArrayHasKey($step2id, $gaps);
        $this->assertEquals(1, $gaps[$step2id]['prereq_step_num']);
        $this->assertEquals($step1id, $gaps[$step2id]['prereq_step']->id);
        $this->assertEquals('Course 1 Step', $gaps[$step2id]['prereq_step']->title);

        // Now learner completes Course 1 in Moodle.
        $ccompletion1 = new \completion_completion(['course' => $course1->id, 'userid' => $user->id]);
        $ccompletion1->mark_complete();

        // Run user sync.
        scoring::sync_user((int)$user->id);

        $progressAfter = api::get_user_progress((int)$user->id, $planid);
        // Both Step 1 and Step 2 should now be completed automatically!
        $this->assertEquals('completed', $progressAfter[$step1id]->status);
        $this->assertEquals('completed', $progressAfter[$step2id]->status);
        // Step 3 should now be unlocked and available.
        $this->assertEquals('available', $progressAfter[$step3id]->status);

        // Prerequisite gap should now be cleared.
        $gapsAfter = api::get_sequential_prereq_gaps($plan, $chapters, $progressAfter, (int)$user->id);
        $this->assertEmpty($gapsAfter);
    }
}
