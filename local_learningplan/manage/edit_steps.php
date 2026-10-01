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
 * Manage chapters and steps for a plan.
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

$planid = required_param('planid', PARAM_INT);
$deletechapter = optional_param('deletechapter', 0, PARAM_INT);
$deletestep = optional_param('deletestep', 0, PARAM_INT);
$movechapter = optional_param('movechapter', 0, PARAM_INT);
$movestep = optional_param('movestep', 0, PARAM_INT);
$direction = optional_param('dir', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$plan = api::get_plan($planid);
$pageurl = new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $planid]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(format_string($plan->name) . ': ' . get_string('managesteps', 'local_learningplan'));
$PAGE->set_heading(format_string($plan->name) . ': ' . get_string('managesteps', 'local_learningplan'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

global $DB;

if ($deletechapter) {
    // Scope the id to the plan in the URL so a mistyped/forged id from another
    // plan cannot be deleted from here.
    $chapter = $DB->get_record('local_learningplan_chapter',
        ['id' => $deletechapter, 'planid' => $planid], '*', MUST_EXIST);
    if (!$confirm) {
        echo $OUTPUT->header();
        echo navigation::render_plan_context_header($planid, 'steps');
        echo $OUTPUT->confirm(
            get_string('deletechapterconfirm', 'local_learningplan', format_string($chapter->title)),
            new moodle_url($pageurl, ['deletechapter' => $deletechapter, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    api::delete_chapter($deletechapter);
    redirect($pageurl, get_string('chapterdeleted', 'local_learningplan'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($deletestep) {
    $step = $DB->get_record('local_learningplan_step',
        ['id' => $deletestep, 'planid' => $planid], '*', MUST_EXIST);
    if (!$confirm) {
        echo $OUTPUT->header();
        echo navigation::render_plan_context_header($planid, 'steps');
        echo $OUTPUT->confirm(
            get_string('deletestepconfirm', 'local_learningplan', format_string($step->title)),
            new moodle_url($pageurl, ['deletestep' => $deletestep, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    api::delete_step($deletestep);
    redirect($pageurl, get_string('stepdeleted', 'local_learningplan'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($movechapter && in_array($direction, ['up', 'down'])) {
    require_sesskey();
    $chapters = $DB->get_records('local_learningplan_chapter', ['planid' => $planid], 'sortorder ASC');
    $ids = array_keys($chapters);
    $pos = array_search($movechapter, $ids);
    $swapwith = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($pos !== false && isset($ids[$swapwith])) {
        [$ids[$pos], $ids[$swapwith]] = [$ids[$swapwith], $ids[$pos]];
        api::reorder('local_learningplan_chapter', $ids);
    }
    redirect($pageurl);
}

if ($movestep && in_array($direction, ['up', 'down'])) {
    require_sesskey();
    $step = $DB->get_record('local_learningplan_step', ['id' => $movestep], '*', MUST_EXIST);
    $steps = $DB->get_records('local_learningplan_step', ['chapterid' => $step->chapterid], 'sortorder ASC');
    $ids = array_keys($steps);
    $pos = array_search($movestep, $ids);
    $swapwith = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($pos !== false && isset($ids[$swapwith])) {
        [$ids[$pos], $ids[$swapwith]] = [$ids[$swapwith], $ids[$pos]];
        api::reorder('local_learningplan_step', $ids);
    }
    redirect($pageurl);
}

echo $OUTPUT->header();

// Universal Context Header.
echo navigation::render_plan_context_header($planid, 'steps');

$chapters = api::get_chapters_with_steps($planid);

// Action toolbar.
echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center mb-3');
echo html_writer::tag('h4', get_string('chapters', 'local_learningplan') . ' <span class="badge badge-secondary ml-1">' . count($chapters) . '</span>', ['class' => 'font-weight-bold mb-2 mb-md-0']);
echo html_writer::link(
    new moodle_url('/local/learningplan/manage/edit_chapter.php', ['planid' => $planid]),
    '<i class="fa fa-folder-open mr-1"></i> ' . get_string('addchapter', 'local_learningplan'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();

if (!$chapters) {
    echo html_writer::start_div('lp-empty-card p-5 text-center bg-light rounded border my-4');
    echo html_writer::tag('div', '<i class="fa fa-folder-o text-muted" style="font-size: 3rem;"></i>', ['class' => 'mb-3']);
    echo html_writer::tag('h5', get_string('nochapters', 'local_learningplan'), ['class' => 'font-weight-bold']);
    echo html_writer::tag('p', 'Chapters help you organize courses and steps into themed milestones (e.g. "Chapter 1: Basics", "Chapter 2: Advanced").', ['class' => 'text-muted mb-4']);
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_chapter.php', ['planid' => $planid]),
        '<i class="fa fa-plus-circle mr-1"></i> ' . get_string('addchapter', 'local_learningplan'),
        ['class' => 'btn btn-lg btn-primary']
    );
    echo html_writer::end_div();
} else {
    foreach ($chapters as $index => $chapter) {
        $stepcount = count($chapter->steps);

        echo html_writer::start_div('card mb-4 shadow-sm border');
        echo html_writer::start_div('card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2 px-3');
        
        // Chapter Title + Step Count Badge.
        echo html_writer::start_div('d-flex align-items-center');
        echo html_writer::tag('span', '<i class="fa fa-bookmark text-primary mr-2"></i>', ['class' => 'mr-1']);
        echo html_writer::tag('strong', format_string($chapter->title), ['class' => 'h6 mb-0 mr-2 font-weight-bold']);
        echo html_writer::tag('span', $stepcount . ' ' . get_string('steps', 'local_learningplan'), ['class' => 'badge badge-pill badge-light border']);
        echo html_writer::end_div();

        // Chapter Actions.
        $chapteractions = html_writer::start_div('d-flex align-items-center gap-1');
        if ($index > 0) {
            $chapteractions .= html_writer::link(
                new moodle_url($pageurl, ['movechapter' => $chapter->id, 'dir' => 'up', 'sesskey' => sesskey()]),
                '<i class="fa fa-arrow-up"></i>',
                ['class' => 'btn btn-sm btn-outline-secondary py-1 px-2 mr-1', 'title' => get_string('up')]
            );
        }
        if ($index < count($chapters) - 1) {
            $chapteractions .= html_writer::link(
                new moodle_url($pageurl, ['movechapter' => $chapter->id, 'dir' => 'down', 'sesskey' => sesskey()]),
                '<i class="fa fa-arrow-down"></i>',
                ['class' => 'btn btn-sm btn-outline-secondary py-1 px-2 mr-1', 'title' => get_string('down')]
            );
        }
        $chapteractions .= html_writer::link(
            new moodle_url('/local/learningplan/manage/edit_chapter.php', ['id' => $chapter->id]),
            '<i class="fa fa-pencil"></i>',
            ['class' => 'btn btn-sm btn-outline-secondary py-1 px-2 mr-1', 'title' => get_string('edit')]
        );
        $chapteractions .= html_writer::link(
            new moodle_url($pageurl, ['deletechapter' => $chapter->id, 'sesskey' => sesskey()]),
            '<i class="fa fa-trash"></i>',
            [
                'class' => 'btn btn-sm btn-outline-danger py-1 px-2',
                'title' => get_string('delete'),
            ]
        );
        $chapteractions .= html_writer::end_div();
        echo $chapteractions;

        echo html_writer::end_div(); // .card-header

        echo html_writer::start_div('card-body p-3');

        if ($chapter->steps) {
            $table = new html_table();
            $table->head = [
                '#',
                get_string('steptitle', 'local_learningplan'),
                get_string('steptype', 'local_learningplan'),
                get_string('steppoints', 'local_learningplan'),
                get_string('actions', 'local_learningplan'),
            ];
            $table->attributes['class'] = 'generaltable table-hover align-middle mb-3';

            foreach ($chapter->steps as $sindex => $step) {
                $stepactions = html_writer::start_div('d-flex gap-1');
                if ($sindex > 0) {
                    $stepactions .= html_writer::link(
                        new moodle_url($pageurl, ['movestep' => $step->id, 'dir' => 'up', 'sesskey' => sesskey()]),
                        '<i class="fa fa-arrow-up"></i>',
                        ['class' => 'btn btn-sm btn-outline-secondary py-0 px-2 mr-1', 'title' => get_string('up')]
                    );
                }
                if ($sindex < count($chapter->steps) - 1) {
                    $stepactions .= html_writer::link(
                        new moodle_url($pageurl, ['movestep' => $step->id, 'dir' => 'down', 'sesskey' => sesskey()]),
                        '<i class="fa fa-arrow-down"></i>',
                        ['class' => 'btn btn-sm btn-outline-secondary py-0 px-2 mr-1', 'title' => get_string('down')]
                    );
                }
                $stepactions .= html_writer::link(
                    new moodle_url('/local/learningplan/manage/edit_step.php', ['id' => $step->id]),
                    '<i class="fa fa-pencil"></i>',
                    ['class' => 'btn btn-sm btn-outline-secondary py-0 px-2 mr-1', 'title' => get_string('edit')]
                );
                $stepactions .= html_writer::link(
                    new moodle_url($pageurl, ['deletestep' => $step->id, 'sesskey' => sesskey()]),
                    '<i class="fa fa-trash"></i>',
                    [
                        'class' => 'btn btn-sm btn-outline-danger py-0 px-2',
                        'title' => get_string('delete'),
                    ]
                );
                $stepactions .= html_writer::end_div();

                $typeicon = 'fa fa-cube';
                if ($step->steptype === 'course') {
                    $typeicon = 'fa fa-graduation-cap text-primary';
                } else if ($step->steptype === 'activity') {
                    $typeicon = 'fa fa-tasks text-success';
                } else if ($step->steptype === 'file') {
                    $typeicon = 'fa fa-file-text-o text-info';
                } else if ($step->steptype === 'url') {
                    $typeicon = 'fa fa-link text-warning';
                }

                $iconthumb = html_writer::span(
                    api::render_step_icon_html($step->icon ?? 'icon-01.png', '', 'lp-step-row-img', 'width: 24px; height: 24px; max-width: 100%; max-height: 100%; object-fit: contain; vertical-align: middle;', false),
                    'lp-step-row-thumb mr-2'
                );

                $table->data[] = [
                    '<span class="badge badge-secondary">' . ($sindex + 1) . '</span>',
                    $iconthumb . html_writer::tag('span', '<i class="' . $typeicon . ' mr-1"></i>', ['class' => 'mr-1']) . format_string($step->title),
                    '<span class="badge badge-light border text-capitalize">' . get_string('steptype_' . $step->steptype, 'local_learningplan') . '</span>',
                    '<span class="badge badge-warning text-dark"><i class="fa fa-bolt mr-1"></i>' . $step->points . ' pts</span>',
                    $stepactions,
                ];
            }
            echo html_writer::table($table);
        } else {
            echo html_writer::div(get_string('nosteps', 'local_learningplan'), 'alert alert-light border text-muted small py-2 px-3 mb-3');
        }

        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/edit_step.php', ['chapterid' => $chapter->id]),
            '<i class="fa fa-plus-circle mr-1"></i> ' . get_string('addstep', 'local_learningplan'),
            ['class' => 'btn btn-sm btn-outline-primary']
        );

        echo html_writer::end_div(); // .card-body
        echo html_writer::end_div(); // .card
    }
}

// Forward Workflow Guidance.
echo navigation::render_workflow_footer('edit_steps', $planid);

echo $OUTPUT->footer();
