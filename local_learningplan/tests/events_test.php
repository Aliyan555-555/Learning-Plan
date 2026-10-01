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
 * Unit tests for local_learningplan Moodle logging events.
 *
 * @package    local_learningplan
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

/**
 * Events testcase.
 */
class events_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test plan creation, update, and delete events trigger and log to Moodle.
     */
    public function test_plan_events() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // 1. Create plan event.
        $sink = $this->redirectEvents();
        $planid = api::save_plan((object)[
            'name' => 'Event Test Plan',
            'description' => 'Testing audit logs',
            'progressionmode' => 'sequential',
            'visible' => 1,
        ]);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\local_learningplan\event\plan_created', $events[0]);
        $this->assertEquals($planid, $events[0]->objectid);
        $sink->clear();

        // 2. Update plan event.
        api::save_plan((object)[
            'id' => $planid,
            'name' => 'Updated Event Test Plan',
        ]);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\local_learningplan\event\plan_updated', $events[0]);
        $this->assertEquals($planid, $events[0]->objectid);
        $sink->clear();

        // 3. Delete plan event.
        api::delete_plan($planid);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\local_learningplan\event\plan_deleted', $events[0]);
        $this->assertEquals($planid, $events[0]->objectid);
        $sink->close();
    }

    /**
     * Test step completion and points awarded events trigger.
     */
    public function test_step_and_points_events() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $planid = $DB->insert_record('local_learningplan_plan', (object)[
            'name' => 'Audit Plan',
            'createdby' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
            'visible' => 1,
        ]);

        $chapterid = $DB->insert_record('local_learningplan_chapter', (object)[
            'planid' => $planid,
            'title' => 'Audit Chapter',
            'sortorder' => 1,
        ]);

        $stepid = $DB->insert_record('local_learningplan_step', (object)[
            'planid' => $planid,
            'chapterid' => $chapterid,
            'title' => 'Audit Step',
            'steptype' => 'url',
            'url' => 'https://example.com',
            'sortorder' => 1,
            'points' => 50,
            'maxstars' => 3,
        ]);

        api::ensure_user_progress((int)$user->id, (int)$planid);

        $sink = $this->redirectEvents();
        scoring::mark_self_reported($stepid, $user->id);
        $events = $sink->get_events();

        // We expect at least step_completed and points_awarded.
        $this->assertGreaterThanOrEqual(2, count($events));

        $eventclasses = array_map(function($e) {
            return get_class($e);
        }, $events);

        $this->assertContains('local_learningplan\event\step_completed', $eventclasses);
        $this->assertContains('local_learningplan\event\points_awarded', $eventclasses);
        $sink->close();
    }
}
