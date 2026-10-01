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
 * Scheduled task: reconcile plan assignments with course enrolments.
 *
 * The durable safety net behind the real-time adhoc sync - catches cohort/group
 * membership changes, steps added to a plan after assignment, and any adhoc run
 * that failed part way through.
 *
 * @package    local_learningplan
 */

namespace local_learningplan\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Hourly reconciliation of every plan that has assignments and course steps.
 */
class reconcile_enrolments extends \core\task\scheduled_task {

    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_reconcile_enrolments', 'local_learningplan');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        \local_learningplan\enrolment::reconcile_all(new \text_progress_trace());
    }
}
