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
 * Create/edit a Learning Plan header.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\form\plan_form;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:manage', $context);

$id = optional_param('id', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$pageurl = new moodle_url('/local/learningplan/manage/edit_plan.php', array_filter(['id' => $id, 'returnurl' => $returnurl]));
$listurl = $returnurl ? new moodle_url($returnurl) : new moodle_url('/local/learningplan/manage/plans.php');

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$title = $id ? get_string('editplan', 'local_learningplan') : get_string('createplan', 'local_learningplan');
$PAGE->set_title($title);
$PAGE->set_heading($title);
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

$plan = $id ? api::get_plan($id) : new stdClass();

$plan->description_editor = [
    'text' => $plan->description ?? '',
    'format' => FORMAT_HTML,
];

$mform = new plan_form($pageurl, ['icon' => $plan->icon ?? '']);
$mform->set_data($plan);

if ($mform->is_cancelled()) {
    redirect($listurl);
} else if ($data = $mform->get_data()) {
    $data->description = $data->description_editor['text'] ?? '';
    $rawicon = optional_param('icon', '', PARAM_RAW);
    if (!empty($rawicon)) {
        $data->icon = $rawicon;
    }
    $isnew = empty($data->id);
    $planid = api::save_plan($data);

    if ($isnew) {
        // Forward guidance: guide admin directly into the Step Builder.
        redirect(
            new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $planid]),
            get_string('plancreated_nextsteps', 'local_learningplan'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect($listurl, get_string('plansaved', 'local_learningplan'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

if ($id) {
    echo navigation::render_plan_context_header($id, 'edit');
} else {
    echo navigation::render_global_header('create');
}

echo html_writer::start_div('lp-form-card p-4 bg-white rounded border');
$mform->display();
echo html_writer::end_div();

if ($id) {
    echo navigation::render_workflow_footer('edit_plan', $id);
}

echo $OUTPUT->footer();
