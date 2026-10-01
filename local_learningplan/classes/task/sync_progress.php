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
 * Scheduled fallback sync — catches anything the real-time event observer
 * missed (e.g. completion tracking enabled after enrolment, or an event
 * that fired before this plugin was installed).
 *
 * @package    local_learningplan
 */

namespace local_learningplan\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Re-evaluates every learner's incomplete steps against real Moodle data.
 */
class sync_progress extends \core\task\scheduled_task {

    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync_progress', 'local_learningplan');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_learningplan_progress} WHERE status IN ('available', 'inprogress')"
        );

        mtrace('local_learningplan: syncing progress for ' . count($userids) . ' learner(s)');

        $done = 0;
        $failed = 0;
        foreach ($userids as $userid) {
            try {
                \local_learningplan\scoring::sync_user((int)$userid);
                $done++;
            } catch (\Throwable $e) {
                // One learner's data problem must not abort the whole run (which
                // would then restart from scratch and never get past them).
                $failed++;
                mtrace('  ! sync failed for user ' . (int)$userid . ': ' . $e->getMessage());
            }
        }

        mtrace("local_learningplan: progress sync finished - {$done} ok, {$failed} failed");
    }
}
