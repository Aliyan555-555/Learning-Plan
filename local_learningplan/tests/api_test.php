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
 * Unit tests for local_learningplan API functions.
 *
 * @package    local_learningplan
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

/**
 * API testcase.
 *
 * @coversDefaultClass \local_learningplan\api
 */
class api_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Build a plan with $chapters chapters of $steps url steps each.
     *
     * @param int $chapters
     * @param int $steps
     * @param string $mode 'sequential' or 'open'
     * @return int plan id
     */
    protected function make_plan(int $chapters = 2, int $steps = 2, string $mode = 'sequential'): int {
        global $USER;
        $planid = api::save_plan((object)[
            'name' => 'Plan',
            'description' => '',
            'progressionmode' => $mode,
            'visible' => 1,
        ]);
        for ($c = 0; $c < $chapters; $c++) {
            $chapterid = api::save_chapter((object)['planid' => $planid, 'title' => 'Chapter ' . $c]);
            for ($s = 0; $s < $steps; $s++) {
                api::save_step((object)[
                    'planid' => $planid,
                    'chapterid' => $chapterid,
                    'title' => "Step $c-$s",
                    'steptype' => 'url',
                    'courseid' => 0,
                    'cmid' => 0,
                    'url' => 'https://example.com/' . $c . '/' . $s,
                    'points' => 10,
                    'starthreshold1' => 70,
                    'starthreshold2' => 90,
                    'icon' => 'icon-01.png',
                ]);
            }
        }
        return $planid;
    }

    /**
     * Test creating, fetching, updating, and deleting a plan.
     *
     * @covers ::save_plan
     * @covers ::get_plan
     * @covers ::delete_plan
     */
    public function test_plan_crud_lifecycle() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $planid = api::save_plan((object)[
            'name' => 'Fullstack Developer Path',
            'description' => 'From beginner to senior engineer',
            'progressionmode' => 'sequential',
            'visible' => 1,
        ]);
        $this->assertGreaterThan(0, $planid);

        $fetched = api::get_plan($planid);
        $this->assertEquals('Fullstack Developer Path', $fetched->name);

        api::save_plan((object)['id' => $planid, 'name' => 'Senior Fullstack Path']);
        $this->assertEquals('Senior Fullstack Path', api::get_plan($planid)->name);

        api::delete_plan($planid);
        $this->assertFalse($DB->get_record('local_learningplan_plan', ['id' => $planid]));
    }

    /**
     * Deleting a plan must also remove its auto-enrolment audit rows.
     *
     * @covers ::delete_plan
     */
    public function test_delete_plan_clears_enrol_audit() {
        global $DB;
        $this->setAdminUser();

        $planid = $this->make_plan(1, 1);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_learningplan_enrol', (object)[
            'planid' => $planid,
            'courseid' => $course->id,
            'userid' => $user->id,
            'timecreated' => time(),
        ]);

        api::delete_plan($planid);

        $this->assertEquals(0, $DB->count_records('local_learningplan_enrol', ['planid' => $planid]));
    }

    /**
     * Assigning a plan seeds progress rows: in sequential mode only the very first
     * step is available, everything else is locked.
     *
     * @covers ::assign_plan
     * @covers ::ensure_user_progress
     */
    public function test_assign_plan_seeds_sequential_progress() {
        global $DB;
        $this->setAdminUser();

        $planid = $this->make_plan(2, 2, 'sequential');
        $user = $this->getDataGenerator()->create_user();

        api::assign_plan($planid, 'user', (int)$user->id);

        $rows = $DB->get_records_sql(
            "SELECT pr.* FROM {local_learningplan_progress} pr
               JOIN {local_learningplan_step} s ON s.id = pr.stepid
              WHERE pr.userid = ? AND s.planid = ?
           ORDER BY s.sortorder, pr.id",
            [$user->id, $planid]
        );
        $this->assertCount(4, $rows);
        $statuses = array_values(array_map(function($r) {
            return $r->status;
        }, $rows));
        $this->assertSame('available', $statuses[0]);
        $this->assertSame(['locked', 'locked', 'locked'], array_slice($statuses, 1));
    }

    /**
     * Open-mode plans make every step available immediately.
     *
     * @covers ::ensure_user_progress
     */
    public function test_open_mode_unlocks_all_steps() {
        global $DB;
        $this->setAdminUser();

        $planid = $this->make_plan(1, 3, 'open');
        $user = $this->getDataGenerator()->create_user();
        api::assign_plan($planid, 'user', (int)$user->id);

        $statuses = $DB->get_fieldset_sql(
            "SELECT pr.status FROM {local_learningplan_progress} pr
               JOIN {local_learningplan_step} s ON s.id = pr.stepid
              WHERE pr.userid = ? AND s.planid = ?",
            [$user->id, $planid]
        );
        $this->assertSame(['available', 'available', 'available'], $statuses);
    }

    /**
     * ensure_user_progress is cheap and idempotent once seeded.
     *
     * @covers ::ensure_user_progress
     */
    public function test_ensure_user_progress_is_idempotent() {
        global $DB;
        $this->setAdminUser();

        $planid = $this->make_plan(1, 2);
        $user = $this->getDataGenerator()->create_user();

        api::ensure_user_progress((int)$user->id, $planid);
        $first = $DB->count_records('local_learningplan_progress', ['userid' => $user->id]);
        api::ensure_user_progress((int)$user->id, $planid);
        api::ensure_user_progress((int)$user->id, $planid);
        $this->assertEquals($first, $DB->count_records('local_learningplan_progress', ['userid' => $user->id]));
        $this->assertEquals(2, $first);
    }

    /**
     * Assignment display labels for individual learners must be HTML-escaped so a
     * crafted user name cannot inject markup into the assignment table.
     *
     * @covers ::get_assignments_display
     */
    public function test_get_assignments_display_escapes_user_name() {
        $this->setAdminUser();

        $planid = $this->make_plan(1, 1);
        $user = $this->getDataGenerator()->create_user([
            'firstname' => '<script>alert(1)</script>',
            'lastname' => 'Tester',
        ]);
        api::assign_plan($planid, 'user', (int)$user->id);

        $display = api::get_assignments_display($planid);
        $this->assertCount(1, $display);
        $label = reset($display)->label;
        $this->assertStringNotContainsString('<script>', $label);
        $this->assertStringContainsString('&lt;script&gt;', $label);
    }

    /**
     * The leaderboard is a pure read: viewing it must not create progress rows for
     * other learners.
     *
     * @covers ::get_plan_leaderboard
     */
    public function test_leaderboard_does_not_write_progress() {
        global $DB;
        $this->setAdminUser();

        $planid = $this->make_plan(1, 2);
        $user = $this->getDataGenerator()->create_user();
        // Assign without seeding, then delete the seeded rows to simulate an
        // un-started learner.
        api::assign_plan($planid, 'user', (int)$user->id);
        $DB->delete_records('local_learningplan_progress', ['userid' => $user->id]);

        api::get_plan_leaderboard($planid);

        $this->assertEquals(0, $DB->count_records('local_learningplan_progress', ['userid' => $user->id]));
    }
}
