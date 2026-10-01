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
 * Learning plan list (create / edit / delete entry point).
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:manage', $context);

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/learningplan/manage/plans.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(local_learningplan_str('managelearningplans', 'Manage Learning Plans'));
$PAGE->set_heading(local_learningplan_str('managelearningplans', 'Manage Learning Plans'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

if ($delete) {
    $plan = api::get_plan($delete);
    if (!$confirm) {
        echo $OUTPUT->header();
        echo navigation::render_global_header('plans', $delete);
        echo $OUTPUT->confirm(
            local_learningplan_str('deleteplanconfirm', 'Are you sure you want to delete the learning plan "{$a}"? This will remove all chapters, steps, assignments and progress data for every learner.', format_string($plan->name)),
            new moodle_url($pageurl, ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    api::delete_plan($delete);
    redirect($pageurl, local_learningplan_str('plandeleted', 'Learning plan deleted successfully.'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo navigation::render_global_header('plans');

$plans = api::get_plans();

// Top action row.
echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center mb-3');
echo html_writer::tag('h4', local_learningplan_str('managelearningplans', 'Manage Learning Plans') . ' <span class="badge badge-secondary ml-1">' . count($plans) . '</span>', ['class' => 'mb-2 mb-md-0 font-weight-bold']);
echo html_writer::start_div('d-flex align-items-center gap-2');
echo html_writer::link(
    new moodle_url('/local/learningplan/dashboard.php'),
    '<i class="fa fa-pie-chart mr-1"></i> ' . local_learningplan_str('lpdashboard', 'LP Dashboard'),
    ['class' => 'btn btn-outline-primary mr-2']
);
$createicon = '<i class="fa fa-plus-circle"></i>';
echo html_writer::link(
    new moodle_url('/local/learningplan/manage/edit_plan.php'),
    $createicon . ' ' . local_learningplan_str('createplan', 'Create Learning Plan'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();
echo html_writer::end_div();

if (!$plans) {
    echo html_writer::start_div('lp-empty-card p-5 text-center bg-light rounded border my-4');
    $emptyicon = '🗺️';
    echo html_writer::tag('div', $emptyicon, ['class' => 'mb-3', 'style' => 'font-size: 3rem;']);
    echo html_writer::tag('h5', local_learningplan_str('noplans', 'No learning plans have been created yet.'), ['class' => 'font-weight-bold']);
    echo html_writer::tag('p', local_learningplan_str('createfirstplan', 'Create your first learning plan to build a gamified adventure for your learners.'), ['class' => 'text-muted mb-4']);
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_plan.php'),
        $createicon . ' ' . local_learningplan_str('createplan', 'Create Learning Plan'),
        ['class' => 'btn btn-lg btn-primary']
    );
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    local_learningplan_str('planname', 'Plan name'),
    local_learningplan_str('steps', 'Steps'),
    local_learningplan_str('assignedusers', 'Assigned learners'),
    local_learningplan_str('status', 'Status'),
    local_learningplan_str('actions', 'Actions'),
];
$table->attributes['class'] = 'generaltable lp-plans-table table-hover align-middle';

$themeemojis = [
    'ocean'  => '🌊',
    'sunset' => '🌅',
    'forest' => '🌲',
    'candy'  => '🍭',
];

foreach ($plans as $plan) {
    $stepcount = 0;
    foreach (api::get_chapters_with_steps($plan->id) as $chapter) {
        $stepcount += count($chapter->steps);
    }
    $usercount = count(api::get_plan_userids($plan->id));

    $statusbadge = $plan->visible
        ? '<span class="badge badge-success">' . get_string('visible', 'block_learningplan_admin') . '</span>'
        : '<span class="badge badge-secondary">' . get_string('hidden', 'block_learningplan_admin') . '</span>';

    $actions = html_writer::start_div('lp-table-actions d-flex flex-wrap gap-1');
    
    // Preview Journey Map Button.
    $actions .= html_writer::link(
        new moodle_url('/local/learningplan/index.php', ['id' => $plan->id]),
        '<i class="fa fa-map mr-1"></i> ' . local_learningplan_str('previewmap', 'Map'),
        ['class' => 'btn btn-sm btn-outline-primary mr-1 mb-1', 'title' => local_learningplan_str('previewmap', 'Preview Map')]
    );
    // Steps Builder Button.
    $actions .= html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $plan->id]),
        '<i class="fa fa-list-ol mr-1"></i> ' . local_learningplan_str('steps', 'Steps'),
        ['class' => 'btn btn-sm btn-outline-secondary mr-1 mb-1', 'title' => get_string('managesteps', 'local_learningplan')]
    );
    // Assign Learners Button.
    $actions .= html_writer::link(
        new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $plan->id]),
        '<i class="fa fa-user-plus mr-1"></i> ' . local_learningplan_str('assign', 'Assign'),
        ['class' => 'btn btn-sm btn-outline-info mr-1 mb-1', 'title' => get_string('assign', 'local_learningplan')]
    );
    // Report Button.
    $actions .= html_writer::link(
        new moodle_url('/local/learningplan/manage/report.php', ['planid' => $plan->id]),
        '<i class="fa fa-bar-chart"></i>',
        ['class' => 'btn btn-sm btn-outline-dark mr-1 mb-1', 'title' => get_string('reports', 'local_learningplan')]
    );
    // Edit Settings Button.
    $actions .= html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_plan.php', ['id' => $plan->id]),
        '<i class="fa fa-cog"></i>',
        ['class' => 'btn btn-sm btn-outline-secondary mr-1 mb-1', 'title' => get_string('edit')]
    );
    // Delete Button.
    $actions .= html_writer::link(
        new moodle_url('/local/learningplan/manage/plans.php', ['delete' => $plan->id]),
        '<i class="fa fa-trash"></i>',
        ['class' => 'btn btn-sm btn-outline-danger mb-1', 'title' => get_string('delete')]
    );
    $actions .= html_writer::end_div();

    $planicon_html = api::render_plan_icon($plan, '', 'width: 24px; height: 24px; max-width: 100%; max-height: 100%; object-fit: contain;', false);
    $namecell = html_writer::start_div('d-flex align-items-center');
    $namecell .= html_writer::span($planicon_html, 'lp-table-plan-icon mr-2', ['style' => 'font-size: 1.25rem; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: #f1f5f9; border-radius: 8px; overflow: hidden; flex-shrink: 0;']);
    $namecell .= html_writer::start_div();
    $namecell .= html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $plan->id]),
        format_string($plan->name),
        ['class' => 'font-weight-bold text-dark']
    );
    if (!empty($plan->description)) {
        $namecell .= html_writer::tag('div', shorten_text(strip_tags($plan->description), 100), ['class' => 'text-muted small']);
    }
    $namecell .= html_writer::end_div();
    $namecell .= html_writer::end_div();

    $table->data[] = [
        $namecell,
        '<span class="badge badge-light p-2">' . $stepcount . ' ' . local_learningplan_str('steps', 'steps') . '</span>',
        '<span class="badge badge-light p-2">' . $usercount . ' ' . local_learningplan_str('learners', 'learners') . '</span>',
        $statusbadge,
        $actions,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
