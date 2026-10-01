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
 * Unit tests for local_learningplan Privacy Provider.
 *
 * @package    local_learningplan
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider testcase.
 *
 * @coversDefaultClass \local_learningplan\privacy\provider
 */
class privacy_provider_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test get_contexts_for_userid returns system context only when user has data.
     *
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid() {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // User 1 has no records.
        $contextlist1 = provider::get_contexts_for_userid($user1->id);
        $this->assertCount(0, $contextlist1);

        // Add a progress record for user 2.
        $DB->insert_record('local_learningplan_progress', [
            'userid' => $user2->id,
            'stepid' => 101,
            'status' => 'completed',
            'starsearned' => 3,
            'pointsawarded' => 10,
            'timecompleted' => time(),
        ]);

        $contextlist2 = provider::get_contexts_for_userid($user2->id);
        $this->assertCount(1, $contextlist2);
        $this->assertEquals(\context_system::instance()->id, $contextlist2->current()->id);
    }

    /**
     * Test delete_data_for_user removes user data.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_data_for_user() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $DB->insert_record('local_learningplan_progress', [
            'userid' => $user->id,
            'stepid' => 101,
            'status' => 'completed',
            'starsearned' => 3,
            'pointsawarded' => 10,
            'timecompleted' => time(),
        ]);

        $approvedlist = new approved_contextlist($user, 'local_learningplan', [$context->id]);
        provider::delete_data_for_user($approvedlist);

        $records = $DB->get_records('local_learningplan_progress', ['userid' => $user->id]);
        $this->assertEmpty($records);
    }
}
