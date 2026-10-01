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
 * Adhoc task: seed progress, enrol learners and notify them for one plan
 * assignment. Queued from api::assign_plan() so the web request stays fast even
 * for a 10k-20k member cohort/group.
 *
 * @package    local_learningplan
 */

namespace local_learningplan\task;

defined('MOODLE_INTERNAL') || die();

use local_learningplan\api;
use local_learningplan\enrolment;
use local_learningplan\notifier;

/**
 * Batched post-assignment processing for a single assignment record.
 */
class sync_assignment_enrolments extends \core\task\adhoc_task {

    /**
     * Process the assignment in batches.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $assignmentid = isset($data->assignmentid) ? (int)$data->assignmentid : 0;
        if (!$assignmentid) {
            return;
        }

        $assignment = $DB->get_record('local_learningplan_assignment', ['id' => $assignmentid]);
        if (!$assignment) {
            // Assignment removed before the task ran - nothing to do.
            return;
        }

        $plan = $DB->get_record('local_learningplan_plan', ['id' => $assignment->planid]);
        if (!$plan) {
            return;
        }

        $userids = api::expand_assignment_userids($assignment);
        if (!$userids) {
            return;
        }
        $userids = array_values(array_unique(array_map('intval', $userids)));

        $trace = new \text_progress_trace();
        $batchsize = enrolment::get_batch_size();
        $total = count($userids);
        $done = 0;

        $trace->output("local_learningplan: processing assignment {$assignmentid} (plan {$plan->id}, {$total} learner(s))");

        foreach (array_chunk($userids, $batchsize) as $batch) {
            api::initialize_progress_for_assignment($assignment, $batch);
            enrolment::sync_users_for_plan($assignment->planid, $batch, $trace);

            foreach ($batch as $userid) {
                try {
                    notifier::plan_assigned($userid, $plan);
                } catch (\Throwable $e) {
                    // A messaging misconfiguration must not roll back enrolment
                    // progress or fail the whole batch.
                    $trace->output("notify failed for user {$userid}: " . $e->getMessage());
                }
            }

            $done += count($batch);
            $trace->output("local_learningplan: {$done}/{$total} learner(s) done");
            gc_collect_cycles();
        }

        $trace->finished();
    }
}
