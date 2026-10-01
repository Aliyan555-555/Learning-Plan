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
 * Create/edit a step.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\form\step_form;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:manage', $context);

$id = optional_param('id', 0, PARAM_INT);
$chapterid = optional_param('chapterid', 0, PARAM_INT);

global $DB;

if ($id) {
    $step = $DB->get_record('local_learningplan_step', ['id' => $id], '*', MUST_EXIST);
    $chapterid = $step->chapterid;
    $criteria = json_decode($step->starcriteria ?: '{}');
    $step->starthreshold1 = $criteria->threshold1 ?? 70;
    $step->starthreshold2 = $criteria->threshold2 ?? 90;
    if (empty($step->icon)) {
        $step->icon = 'icon-01.png';
    }
} else {
    $step = new stdClass();
    $step->chapterid = $chapterid;
    $step->icon = 'icon-01.png';
}

$chapter = $DB->get_record('local_learningplan_chapter', ['id' => $chapterid], '*', MUST_EXIST);
$plan = api::get_plan($chapter->planid);
$step->planid = $chapter->planid;

$returnurl = new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $chapter->planid]);

$pageurl = new moodle_url('/local/learningplan/manage/edit_step.php', ['id' => $id, 'chapterid' => $chapterid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$title = $id ? get_string('editstep', 'local_learningplan') : get_string('addstep', 'local_learningplan');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($plan->name) . ' / ' . format_string($chapter->title) . ': ' . $title);
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

$mform = new step_form($pageurl, ['icon' => $step->icon]);
$mform->set_data($step);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $rawicon = optional_param('icon', '', PARAM_RAW);
    if (!empty($rawicon)) {
        $data->icon = $rawicon;
    }
    api::save_step($data);
    redirect($returnurl, get_string('stepsaved', 'local_learningplan'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo navigation::render_plan_context_header($step->planid, 'steps');

echo html_writer::start_div('lp-form-card p-4 bg-white rounded border');
$mform->display();
echo html_writer::end_div();

echo $OUTPUT->footer();
