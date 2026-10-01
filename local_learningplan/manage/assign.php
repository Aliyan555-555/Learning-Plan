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
 * Assign a plan to learners/cohort/group.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\form\assign_form;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:assign', $context);

$planid = required_param('planid', PARAM_INT);
$remove = optional_param('remove', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$plan = api::get_plan($planid);
$pageurl = new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $planid]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(format_string($plan->name) . ': ' . get_string('assign', 'local_learningplan'));
$PAGE->set_heading(format_string($plan->name) . ': ' . get_string('assign', 'local_learningplan'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

if ($remove) {
    global $DB;
    // The assignment must belong to the plan named in the URL.
    $assignment = $DB->get_record('local_learningplan_assignment',
        ['id' => $remove, 'planid' => $planid], '*', MUST_EXIST);
    $display = api::get_assignments_display($planid);
    $label = '';
    foreach ($display as $d) {
        if ((int)$d->id === (int)$remove) {
            $label = $d->label;
            break;
        }
    }
    if (!$confirm) {
        echo $OUTPUT->header();
        echo navigation::render_plan_context_header($planid, 'assign');
        echo $OUTPUT->confirm(
            get_string('removeassignmentconfirm', 'local_learningplan', $label),
            new moodle_url($pageurl, ['remove' => $remove, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    api::remove_assignment($remove);
    redirect($pageurl, get_string('assignmentremoved', 'local_learningplan'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new assign_form($pageurl, ['planid' => $planid]);

if ($data = $mform->get_data()) {
    if ($data->assigntype === 'user') {
        foreach ($data->userids as $userid) {
            api::assign_plan($planid, 'user', (int)$userid);
        }
    } else if ($data->assigntype === 'cohort') {
        api::assign_plan($planid, 'cohort', (int)$data->cohortid);
    } else if ($data->assigntype === 'group') {
        api::assign_plan($planid, 'group', (int)$data->groupid);
    }

    redirect($pageurl, get_string('assignqueued', 'local_learningplan'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Universal Context Header.
echo navigation::render_plan_context_header($planid, 'assign');

echo html_writer::start_div('row');

// Left Column: Assignment Form.
echo html_writer::start_div('col-lg-6 mb-4');
echo html_writer::start_div('card border shadow-sm h-100');
echo html_writer::start_div('card-header bg-light py-2 px-3');
echo html_writer::tag('h5', '<i class="fa fa-user-plus text-primary mr-2"></i>' . get_string('assign', 'local_learningplan'), ['class' => 'h6 mb-0 font-weight-bold']);
echo html_writer::end_div();
echo html_writer::start_div('card-body p-3');
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Right Column: Existing Assignments.
echo html_writer::start_div('col-lg-6 mb-4');
echo html_writer::start_div('card border shadow-sm h-100');
echo html_writer::start_div('card-header bg-light d-flex justify-content-between align-items-center py-2 px-3');
echo html_writer::tag('h5', '<i class="fa fa-users text-info mr-2"></i>' . get_string('assignedusers', 'local_learningplan'), ['class' => 'h6 mb-0 font-weight-bold']);
$assignments = api::get_assignments_display($planid);
echo html_writer::tag('span', count($assignments) . ' records', ['class' => 'badge badge-pill badge-light border']);
echo html_writer::end_div();
echo html_writer::start_div('card-body p-3');

if (!$assignments) {
    echo html_writer::tag('div', '<i class="fa fa-info-circle mr-1"></i> ' . get_string('noassignments', 'local_learningplan'), ['class' => 'alert alert-light border text-muted small mb-0']);
} else {
    $table = new html_table();
    $table->head = [get_string('assignto', 'local_learningplan'), get_string('type', 'moodle'), ''];
    $table->attributes['class'] = 'generaltable table-hover align-middle mb-0';
    foreach ($assignments as $row) {
        $table->data[] = [
            html_writer::tag('strong', $row->label),
            '<span class="badge badge-light border">' . $row->type . '</span>',
            html_writer::link(
                new moodle_url($pageurl, ['remove' => $row->id]),
                '<i class="fa fa-trash"></i> ' . get_string('removeassignment', 'local_learningplan'),
                [
                    'class' => 'btn btn-sm btn-outline-danger py-0 px-2',
                ]
            ),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // .row

// Forward Workflow Guidance.
echo navigation::render_workflow_footer('assign', $planid);

echo $OUTPUT->footer();
