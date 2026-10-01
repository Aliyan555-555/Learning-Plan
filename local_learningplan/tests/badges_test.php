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
 * Unit tests for local_learningplan badge integration.
 *
 * @package    local_learningplan
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/badgeslib.php');

/**
 * Badge integration testcase.
 *
 * @coversDefaultClass \local_learningplan\badges
 */
class badges_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Create a site badge in the given status.
     *
     * @param int $status
     * @return int badge id
     */
    protected function make_site_badge(int $status = BADGE_STATUS_INACTIVE): int {
        global $DB, $CFG;
        $now = time();
        return (int)$DB->insert_record('badge', (object)[
            'name' => 'Test badge',
            'description' => 'desc',
            'timecreated' => $now,
            'timemodified' => $now,
            'usercreated' => 2,
            'usermodified' => 2,
            'issuername' => 'Test',
            'issuerurl' => $CFG->wwwroot,
            'issuercontact' => 'admin@example.com',
            'type' => BADGE_TYPE_SITE,
            'status' => $status,
            'nextcron' => 0,
            'version' => '',
            'language' => 'en',
        ]);
    }

    /**
     * A mapped but inactive badge must never be issued to a learner.
     *
     * @covers ::award_if_mapped
     */
    public function test_award_if_mapped_skips_inactive_badge() {
        global $DB;
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();

        $planid = $DB->insert_record('local_learningplan_plan', (object)[
            'name' => 'P', 'progressionmode' => 'open', 'createdby' => 2,
            'timecreated' => time(), 'timemodified' => time(), 'visible' => 1,
        ]);
        $badgeid = $this->make_site_badge(BADGE_STATUS_INACTIVE);
        $DB->insert_record('local_learningplan_badge', (object)[
            'planid' => $planid, 'chapterid' => null,
            'badgeid' => $badgeid, 'criteria' => 'plan_complete',
        ]);

        $this->assertDebuggingNotCalled();
        $result = badges::award_if_mapped($planid, null, 'plan_complete', (int)$user->id);
        $this->assertFalse($result);
        $this->assertDebuggingCalled();
        $this->assertEquals(0, $DB->count_records('badge_issued',
            ['badgeid' => $badgeid, 'userid' => $user->id]));
    }

    /**
     * get_available_badges only offers site badges, never course badges.
     *
     * @covers ::get_available_badges
     */
    public function test_get_available_badges_is_site_only() {
        global $DB;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $siteid = $this->make_site_badge(BADGE_STATUS_ACTIVE);
        $courseid = (int)$DB->insert_record('badge', (object)[
            'name' => 'Course badge', 'description' => 'd', 'timecreated' => time(),
            'timemodified' => time(), 'usercreated' => 2, 'usermodified' => 2,
            'issuername' => 'T', 'issuerurl' => '', 'issuercontact' => '',
            'type' => BADGE_TYPE_COURSE, 'courseid' => $course->id,
            'status' => BADGE_STATUS_ACTIVE, 'nextcron' => 0, 'version' => '', 'language' => 'en',
        ]);

        $options = badges::get_available_badges();
        $this->assertArrayHasKey($siteid, $options);
        $this->assertArrayNotHasKey($courseid, $options);
    }
}
