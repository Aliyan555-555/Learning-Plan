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
 * "My Learning Path" — gamified learner map and admin management hub.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_learningplan\api;
use local_learningplan\scoring;
use local_learningplan\badges;

require_login();
$context = context_system::instance();

$canmanage = has_capability('local/learningplan:manage', $context);
$canassign = has_capability('local/learningplan:assign', $context);
$canreport = has_capability('local/learningplan:viewreports', $context);

if (!$canmanage) {
    require_capability('local/learningplan:view', $context);
}

global $USER, $DB;

$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/learningplan/index.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(local_learningplan_str('mylearningpath', 'My Learning Path'));
$PAGE->set_heading(local_learningplan_str('mylearningpath', 'My Learning Path'));

// Handle plan deletion with confirmation and sesskey security.
if ($delete && $canmanage) {
    $plan = api::get_plan($delete);
    if (!$confirm) {
        $PAGE->set_url(new moodle_url('/local/learningplan/index.php', ['delete' => $delete]));
        $PAGE->set_title(local_learningplan_str('deleteplan', 'Delete Learning Plan'));
        $PAGE->set_heading(local_learningplan_str('deleteplan', 'Delete Learning Plan'));
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            local_learningplan_str('deleteplanconfirm', 'Are you sure you want to delete the learning plan "{$a}"? This will remove all chapters, steps, assignments and progress data for every learner.', format_string($plan->name)),
            new moodle_url('/local/learningplan/index.php', ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]),
            new moodle_url('/local/learningplan/index.php')
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    api::delete_plan($delete);
    redirect(new moodle_url('/local/learningplan/index.php'), local_learningplan_str('plandeleted', 'Learning plan deleted successfully.'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));
$PAGE->requires->js_call_amd('local_learningplan/map', 'init');

// For admins/managers, fetch all learning plans; for students, fetch assigned plans.
if ($canmanage) {
    $plans = api::get_plans();
} else {
    $plans = api::get_user_plans((int)$USER->id);
}

// Learners: lazily seed their own progress rows for their assigned plans and catch
// up completions recorded elsewhere in Moodle. This is bounded to the current user
// and their own plans (cheap once seeded); site-wide reconciliation is the job of
// the scheduled tasks, not a page view.
if (!$canmanage && $plans) {
    try {
        foreach ($plans as $lpplan) {
            api::ensure_user_progress((int)$USER->id, (int)$lpplan->id);
        }
        scoring::sync_user((int)$USER->id);
    } catch (\Throwable $e) {
        debugging('local_learningplan: map view catch-up failed - ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

echo $OUTPUT->header();


// Empty state handling.
if (!$plans) {
    if ($canmanage) {
        echo html_writer::start_div('lp-wrap');

        // Admin top bar.
        echo html_writer::start_div('lp-admin-bar');
        echo html_writer::start_div('lp-admin-bar-left');
        echo html_writer::span('🛡️ ' . local_learningplan_str('admincontrols', 'Admin Controls'), 'lp-admin-badge');
        echo html_writer::tag('h3', local_learningplan_str('allplans_admin', 'Learning Plans Management'), ['class' => 'lp-admin-bar-title']);
        echo html_writer::end_div();

        echo html_writer::start_div('lp-admin-bar-actions');
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/edit_plan.php'),
            '➕ ' . local_learningplan_str('createnewplan', 'Create Learning Plan'),
            ['class' => 'lp-admin-btn lp-admin-btn-primary']
        );

        // Hamburger Menu for additional admin management tools.
        echo html_writer::start_div('lp-hamburger-wrap');
        echo html_writer::tag('button',
            html_writer::span(
                html_writer::span('', 'lp-hb-bar') .
                html_writer::span('', 'lp-hb-bar') .
                html_writer::span('', 'lp-hb-bar'),
                'lp-hb-icon'
            ) .
            html_writer::span(local_learningplan_str('actions', 'Actions'), 'lp-hb-label'),
            [
                'type' => 'button',
                'class' => 'lp-hamburger-btn',
                'aria-expanded' => 'false',
                'aria-haspopup' => 'true',
                'title' => local_learningplan_str('actions', 'Actions'),
            ]
        );
        echo html_writer::start_div('lp-hamburger-dropdown', ['hidden' => 'hidden', 'role' => 'menu']);
        echo html_writer::start_div('lp-dropdown-header');
        echo html_writer::div(local_learningplan_str('admincontrols', 'Admin Controls'), 'lp-dropdown-header-title');
        echo html_writer::div(local_learningplan_str('managelearningplans', 'Management Tools'), 'lp-dropdown-header-subtitle');
        echo html_writer::end_div();

        echo html_writer::start_div('lp-dropdown-items');
        if ($canreport || $canmanage) {
            echo html_writer::link(
                new moodle_url('/local/learningplan/dashboard.php'),
                html_writer::span('📈', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('lpdashboard', 'LP Dashboard'), 'lp-dropdown-item-title') .
                    html_writer::span('Multi-chart executive status dashboard', 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
            );
        }
        if ($canreport) {
            echo html_writer::link(
                new moodle_url('/local/learningplan/manage/report.php'),
                html_writer::span('📊', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('reports', 'Reports'), 'lp-dropdown-item-title') .
                    html_writer::span(local_learningplan_str('report', 'Analytics and completions'), 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
            );
        }
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/badges.php'),
            html_writer::span('🏆', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(local_learningplan_str('badges', 'Badges'), 'lp-dropdown-item-title') .
                html_writer::span(local_learningplan_str('halloffame', 'Gamification rewards'), 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
        echo html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'local_learningplan_settings']),
            html_writer::span('⚙️', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(get_string('settings', 'moodle'), 'lp-dropdown-item-title') .
                html_writer::span('Plugin configuration & rules', 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
        echo html_writer::end_div(); // lp-dropdown-items.
        echo html_writer::end_div(); // lp-hamburger-dropdown.
        echo html_writer::end_div(); // lp-hamburger-wrap.

        echo html_writer::end_div();
        echo html_writer::end_div(); // lp-admin-bar.

        // Empty Hero.
        echo html_writer::start_div('lp-admin-empty-state');
        echo html_writer::div('🗺️', 'lp-admin-empty-icon');
        echo html_writer::tag('h2', local_learningplan_str('noplans', 'No learning plans have been created yet.'), ['class' => 'lp-admin-empty-title']);
        echo html_writer::tag('p', local_learningplan_str('createfirstplan', 'Create your first learning plan to build a gamified adventure for your learners.'), ['class' => 'lp-admin-empty-desc']);
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/edit_plan.php'),
            '➕ ' . local_learningplan_str('createnewplan', 'Create Learning Plan'),
            ['class' => 'lp-admin-btn lp-admin-btn-primary', 'style' => 'font-size: 1.05rem; padding: 14px 28px;']
        );
        echo html_writer::end_div(); // lp-admin-empty-state.

        echo html_writer::end_div(); // lp-wrap.
    } else {
        echo $OUTPUT->notification(local_learningplan_str('nolearningplans', 'You do not have any learning plans assigned yet.'), 'info');
    }
    echo $OUTPUT->footer();
    exit;
}

// Multi-Plan Hub Overview:
// Shown when id=0 (or for admins with plans, or learners with >1 plans).
if ($id === 0 && (count($plans) > 1 || $canmanage)) {
    echo html_writer::start_div('lp-wrap lp-hub-wrap');

    // Admin Control Bar.
    if ($canmanage) {
        echo html_writer::start_div('lp-admin-bar');
        echo html_writer::start_div('lp-admin-bar-left');
        echo html_writer::span('🛡️ ' . local_learningplan_str('admincontrols', 'Admin Controls'), 'lp-admin-badge');
        echo html_writer::tag('h3', local_learningplan_str('allplans_admin', 'Learning Plans Management'), ['class' => 'lp-admin-bar-title']);
        echo html_writer::end_div();

        echo html_writer::start_div('lp-admin-bar-actions');
        if ($canreport || $canmanage) {
            echo html_writer::link(
                new moodle_url('/local/learningplan/dashboard.php'),
                '📈 ' . local_learningplan_str('lpdashboard', 'LP Dashboard'),
                ['class' => 'lp-admin-btn lp-admin-btn-secondary mr-2']
            );
        }
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/edit_plan.php'),
            '➕ ' . local_learningplan_str('createnewplan', 'Create Learning Plan'),
            ['class' => 'lp-admin-btn lp-admin-btn-primary']
        );

        // Hamburger Menu for additional admin management tools.
        echo html_writer::start_div('lp-hamburger-wrap');
        echo html_writer::tag('button',
            html_writer::span(
                html_writer::span('', 'lp-hb-bar') .
                html_writer::span('', 'lp-hb-bar') .
                html_writer::span('', 'lp-hb-bar'),
                'lp-hb-icon'
            ) .
            html_writer::span(local_learningplan_str('actions', 'Actions'), 'lp-hb-label'),
            [
                'type' => 'button',
                'class' => 'lp-hamburger-btn',
                'aria-expanded' => 'false',
                'aria-haspopup' => 'true',
                'title' => local_learningplan_str('actions', 'Actions'),
            ]
        );
        echo html_writer::start_div('lp-hamburger-dropdown', ['hidden' => 'hidden', 'role' => 'menu']);
        echo html_writer::start_div('lp-dropdown-header');
        echo html_writer::div(local_learningplan_str('admincontrols', 'Admin Controls'), 'lp-dropdown-header-title');
        echo html_writer::div(local_learningplan_str('managelearningplans', 'Management Tools'), 'lp-dropdown-header-subtitle');
        echo html_writer::end_div();

        echo html_writer::start_div('lp-dropdown-items');
        if ($canreport || $canmanage) {
            echo html_writer::link(
                new moodle_url('/local/learningplan/dashboard.php'),
                html_writer::span('📈', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('lpdashboard', 'LP Dashboard'), 'lp-dropdown-item-title') .
                    html_writer::span('Executive multi-chart status dashboard', 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
            );
        }
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/plans.php'),
            html_writer::span('📋', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(local_learningplan_str('managelearningplans', 'Manage Learning Plans'), 'lp-dropdown-item-title') .
                html_writer::span(local_learningplan_str('allplans', 'Table view & reordering of all plans'), 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
        if ($canreport) {
            echo html_writer::link(
                new moodle_url('/local/learningplan/manage/report.php'),
                html_writer::span('📊', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('reports', 'Reports'), 'lp-dropdown-item-title') .
                    html_writer::span(local_learningplan_str('report', 'System-wide learner progress analytics'), 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
            );
        }
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/badges.php'),
            html_writer::span('🏆', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(local_learningplan_str('badges', 'Badges'), 'lp-dropdown-item-title') .
                html_writer::span(local_learningplan_str('halloffame', 'Gamification rewards & achievements'), 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
        echo html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'local_learningplan_settings']),
            html_writer::span('⚙️', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(get_string('settings', 'moodle'), 'lp-dropdown-item-title') .
                html_writer::span('Plugin configuration & rules', 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
        echo html_writer::end_div(); // lp-dropdown-items.
        echo html_writer::end_div(); // lp-hamburger-dropdown.
        echo html_writer::end_div(); // lp-hamburger-wrap.

        echo html_writer::end_div();
        echo html_writer::end_div(); // lp-admin-bar.
    }

    // Calculate statistics across plans.
    $total_plans = count($plans);
    $total_all_points = 0;
    $total_all_stars = 0;
    $total_all_steps = 0;
    $total_all_learners = 0;
    $plans_data = [];

    foreach ($plans as $p) {
        $ptotals = api::get_user_plan_totals((int)$USER->id, $p->id);
        $pchapters = api::get_chapters_with_steps($p->id);
        $userids = api::get_plan_userids($p->id);
        $pstepcount = 0;
        foreach ($pchapters as $ch) {
            $pstepcount += count($ch->steps);
        }
        $is_complete = ($ptotals->totalsteps > 0 && $ptotals->completedsteps === $ptotals->totalsteps);

        $total_all_points += $ptotals->points;
        $total_all_stars += $ptotals->stars;
        $total_all_steps += $pstepcount;
        $total_all_learners += count($userids);

        $plans_data[] = [
            'plan' => $p,
            'totals' => $ptotals,
            'chapters_count' => count($pchapters),
            'steps_count' => $pstepcount,
            'learners_count' => count($userids),
            'is_complete' => $is_complete,
        ];
    }

    // Hero banner.
    echo html_writer::start_div('lp-hub-hero');
    echo html_writer::start_div('lp-hub-hero-text');
    $herotitle = $canmanage ? local_learningplan_str('allplans_admin', 'Learning Plans Management') : local_learningplan_str('yourplans', 'Your Learning Adventures');
    $herodesc = $canmanage ? local_learningplan_str('allplans_admin_desc', 'Create, customize, assign learners, and monitor gamified learning paths across your platform.') : local_learningplan_str('yourplans_desc', 'Select a learning path below to explore your gamified journey and unlock new milestones.');
    echo html_writer::tag('h1', $herotitle, ['class' => 'lp-hub-hero-title']);
    echo html_writer::tag('p', $herodesc, ['class' => 'lp-hub-hero-desc']);
    echo html_writer::end_div();

    echo html_writer::start_div('lp-hub-hero-stats');
    echo html_writer::div(
        html_writer::div('🗺️', 'lp-hub-hero-stat-icon') .
        html_writer::start_div('lp-hub-hero-stat-body') .
        html_writer::tag('span', $total_plans, ['class' => 'lp-hub-hero-stat-val']) .
        html_writer::tag('span', $canmanage ? 'Total Plans' : 'Assigned Plans', ['class' => 'lp-hub-hero-stat-lbl']) .
        html_writer::end_div(),
        'lp-hub-hero-stat'
    );
    if ($canmanage) {
        echo html_writer::div(
            html_writer::div('👥', 'lp-hub-hero-stat-icon') .
            html_writer::start_div('lp-hub-hero-stat-body') .
            html_writer::tag('span', $total_all_learners, ['class' => 'lp-hub-hero-stat-val']) .
            html_writer::tag('span', 'Active Learners', ['class' => 'lp-hub-hero-stat-lbl']) .
            html_writer::end_div(),
            'lp-hub-hero-stat'
        );
        echo html_writer::div(
            html_writer::div('🎯', 'lp-hub-hero-stat-icon') .
            html_writer::start_div('lp-hub-hero-stat-body') .
            html_writer::tag('span', $total_all_steps, ['class' => 'lp-hub-hero-stat-val']) .
            html_writer::tag('span', 'Total Steps', ['class' => 'lp-hub-hero-stat-lbl']) .
            html_writer::end_div(),
            'lp-hub-hero-stat'
        );
    } else {
        echo html_writer::div(
            html_writer::div('🏆', 'lp-hub-hero-stat-icon') .
            html_writer::start_div('lp-hub-hero-stat-body') .
            html_writer::tag('span', $total_all_points . ' XP', ['class' => 'lp-hub-hero-stat-val']) .
            html_writer::tag('span', 'Total Points', ['class' => 'lp-hub-hero-stat-lbl']) .
            html_writer::end_div(),
            'lp-hub-hero-stat'
        );
        echo html_writer::div(
            html_writer::div('⭐', 'lp-hub-hero-stat-icon') .
            html_writer::start_div('lp-hub-hero-stat-body') .
            html_writer::tag('span', $total_all_stars, ['class' => 'lp-hub-hero-stat-val']) .
            html_writer::tag('span', 'Stars Collected', ['class' => 'lp-hub-hero-stat-lbl']) .
            html_writer::end_div(),
            'lp-hub-hero-stat'
        );
    }
    echo html_writer::end_div(); // lp-hub-hero-stats.
    echo html_writer::end_div(); // lp-hub-hero.

    // Plan cards grid.
    echo html_writer::start_div('lp-plan-cards-grid');
    foreach ($plans_data as $item) {
        $p = $item['plan'];
        $ptotals = $item['totals'];
        $theme = !empty($p->coverimage) ? $p->coverimage : 'ocean';
        $theme_icon = api::render_plan_icon($p);

        echo html_writer::start_div('lp-plan-card lp-card-theme-' . (in_array($theme, ['ocean', 'sunset', 'forest', 'candy']) ? $theme : 'ocean'));

        // Card header.
        echo html_writer::start_div('lp-card-header');
        echo html_writer::div($theme_icon, 'lp-card-theme-icon');

        echo html_writer::start_div('lp-card-header-right');
        if ($canmanage) {
            $vislabel = $p->visible ? 'Visible' : 'Hidden';
            $visclass = $p->visible ? 'visible-badge' : 'hidden-badge';
            echo html_writer::tag('span', $vislabel, ['class' => 'lp-card-status-badge ' . $visclass]);

            // Hamburger Actions Menu on Card.
            echo html_writer::start_div('lp-hamburger-wrap lp-card-menu-wrap');
            echo html_writer::tag('button',
                html_writer::span(
                    html_writer::span('', 'lp-hb-bar') .
                    html_writer::span('', 'lp-hb-bar') .
                    html_writer::span('', 'lp-hb-bar'),
                    'lp-hb-icon'
                ),
                [
                    'type' => 'button',
                    'class' => 'lp-hamburger-btn lp-card-hamburger-btn',
                    'aria-expanded' => 'false',
                    'aria-haspopup' => 'true',
                    'title' => local_learningplan_str('actions', 'Actions'),
                ]
            );

            echo html_writer::start_div('lp-hamburger-dropdown lp-card-dropdown', ['hidden' => 'hidden', 'role' => 'menu']);
            echo html_writer::start_div('lp-dropdown-header');
            echo html_writer::div(local_learningplan_str('actions', 'Actions'), 'lp-dropdown-header-title');
            echo html_writer::div(format_string($p->name), 'lp-dropdown-header-subtitle');
            echo html_writer::end_div();

            echo html_writer::start_div('lp-dropdown-items');
            echo html_writer::link(
                new moodle_url('/local/learningplan/manage/edit_plan.php', ['id' => $p->id]),
                html_writer::span('✏️', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('editplan', 'Edit Learning Plan'), 'lp-dropdown-item-title') .
                    html_writer::span(get_string('description'), 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
            );
            echo html_writer::link(
                new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $p->id]),
                html_writer::span('📑', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('managesteps', 'Manage Steps'), 'lp-dropdown-item-title') .
                    html_writer::span(local_learningplan_str('steps', 'Steps and chapters configuration'), 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
            );
            if ($canassign) {
                echo html_writer::link(
                    new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $p->id]),
                    html_writer::span('👥', 'lp-dropdown-item-icon') .
                    html_writer::span(
                        html_writer::span(local_learningplan_str('assign', 'Assign Learning Plan'), 'lp-dropdown-item-title') .
                        html_writer::span(local_learningplan_str('assignedlearners', 'Assign users & cohorts'), 'lp-dropdown-item-desc'),
                        'lp-dropdown-item-text'
                    ),
                    ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
                );
            }
            if ($canreport) {
                echo html_writer::link(
                    new moodle_url('/local/learningplan/manage/report.php', ['planid' => $p->id]),
                    html_writer::span('📊', 'lp-dropdown-item-icon') .
                    html_writer::span(
                        html_writer::span(local_learningplan_str('report', 'Progress Report'), 'lp-dropdown-item-title') .
                        html_writer::span(local_learningplan_str('reports', 'Analytics and completions'), 'lp-dropdown-item-desc'),
                        'lp-dropdown-item-text'
                    ),
                    ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
                );
            }
            echo html_writer::div('', 'lp-dropdown-divider', ['role' => 'separator']);
            echo html_writer::link(
                new moodle_url('/local/learningplan/index.php', ['delete' => $p->id]),
                html_writer::span('🗑️', 'lp-dropdown-item-icon') .
                html_writer::span(
                    html_writer::span(local_learningplan_str('deleteplan', 'Delete Learning Plan'), 'lp-dropdown-item-title') .
                    html_writer::span('Remove plan permanently', 'lp-dropdown-item-desc'),
                    'lp-dropdown-item-text'
                ),
                ['class' => 'lp-dropdown-item lp-dropdown-item-danger', 'role' => 'menuitem']
            );
            echo html_writer::end_div(); // lp-dropdown-items.
            echo html_writer::end_div(); // lp-hamburger-dropdown.
            echo html_writer::end_div(); // lp-hamburger-wrap.
        } else {
            $status_label = $item['is_complete'] ? 'Completed' : (($ptotals->completedsteps > 0) ? 'In Progress' : 'Not Started');
            $status_class = $item['is_complete'] ? 'complete' : (($ptotals->completedsteps > 0) ? 'inprogress' : 'new');
            echo html_writer::tag('span', $status_label, ['class' => 'lp-card-status-badge ' . $status_class]);
        }
        echo html_writer::end_div(); // lp-card-header-right.
        echo html_writer::end_div(); // lp-card-header.

        echo html_writer::start_div('lp-card-body');
        echo html_writer::tag('h3', format_string($p->name), ['class' => 'lp-card-title']);
        if (!empty($p->description)) {
            echo html_writer::div(format_text($p->description, FORMAT_HTML), 'lp-card-desc');
        }

        // Progress section (for learner) or Stats section (for admin).
        if (!$canmanage) {
            echo html_writer::start_div('lp-card-progress-section');
            echo html_writer::start_div('lp-card-progress-header');
            echo html_writer::tag('span', $ptotals->completedsteps . ' of ' . $ptotals->totalsteps . ' steps', ['class' => 'lp-card-progress-sub']);
            echo html_writer::tag('span', $ptotals->percent . '%', ['class' => 'lp-card-progress-pct']);
            echo html_writer::end_div();
            echo html_writer::start_div('lp-card-progress-track');
            echo html_writer::div('', 'lp-card-progress-fill', ['style' => 'width:' . $ptotals->percent . '%']);
            echo html_writer::end_div();
            echo html_writer::end_div();
        }

        // Stats pills.
        echo html_writer::start_div('lp-card-stats-row');
        echo html_writer::div('📚 ' . $item['chapters_count'] . ' Chapters', 'lp-card-stat-pill');
        echo html_writer::div('🎯 ' . $item['steps_count'] . ' Steps', 'lp-card-stat-pill');
        if ($canmanage) {
            echo html_writer::div('👥 ' . $item['learners_count'] . ' Learners', 'lp-card-stat-pill lp-pill-points');
        } else {
            echo html_writer::div('🏆 ' . $ptotals->points . ' XP', 'lp-card-stat-pill lp-pill-points');
            echo html_writer::div('⭐ ' . $ptotals->stars . '/' . $ptotals->maxstars . ' Stars', 'lp-card-stat-pill lp-pill-stars');
        }
        echo html_writer::end_div();

        echo html_writer::end_div(); // lp-card-body.

        // Card CTA button.
        echo html_writer::start_div('lp-card-footer');
        echo html_writer::link(
            new moodle_url('/local/learningplan/index.php', ['id' => $p->id]),
            ($canmanage ? local_learningplan_str('previewmap', 'Preview Map') : local_learningplan_str('exploremap', 'Explore Map')) . ' ➔',
            ['class' => 'lp-btn lp-btn-card-action']
        );
        echo html_writer::end_div();

        echo html_writer::end_div(); // lp-plan-card.
    }
    echo html_writer::end_div(); // lp-plan-cards-grid.

    echo html_writer::end_div(); // lp-wrap.
    // Behaviour (action menus etc.) is initialised by the local_learningplan/map
    // AMD module loaded near the top of this script.
    echo $OUTPUT->footer();
    exit;
}

// --------------------------------------------------------------------------
// Single Plan Gamified Map View.
// --------------------------------------------------------------------------
$activeplan = null;
if ($id && isset($plans[$id])) {
    $activeplan = $plans[$id];
} else if ($id && $canmanage) {
    $activeplan = api::get_plan($id);
} else {
    $activeplan = reset($plans);
}

$userids = api::get_plan_userids($activeplan->id);
$isassigned = in_array((int)$USER->id, $userids);

// A learner opening a specific plan is the natural point to make sure they are
// enrolled into that plan's courses (in case the assignment adhoc task has not run
// yet) and that their progress rows exist. Bounded to this one user and one plan.
if ($isassigned) {
    try {
        api::ensure_user_progress((int)$USER->id, (int)$activeplan->id);
        \local_learningplan\enrolment::sync_users_for_plan((int)$activeplan->id, [(int)$USER->id]);
        if ($canmanage) {
            // Non-manager learners were already synced above; a manager who is also
            // assigned still needs their own completion catch-up here.
            scoring::sync_user((int)$USER->id);
        }
    } catch (\Throwable $e) {
        debugging('local_learningplan: plan view just-in-time sync failed - ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

\local_learningplan\event\plan_viewed::create([
    'objectid' => $activeplan->id,
    'context' => $context,
])->trigger();

echo html_writer::start_div('lp-wrap');

// Unified Modern Top Header Bar (All Plans navigation on top + Status badges + Hamburger Actions Menu).
echo html_writer::start_div('lp-detail-top-bar');

// Left: Back button & badges.
echo html_writer::start_div('lp-detail-top-left');
echo html_writer::link(
    new moodle_url('/local/learningplan/index.php'),
    html_writer::span('←', 'lp-back-arrow') . html_writer::span(local_learningplan_str('backtoallplans', 'All Learning Plans'), 'lp-back-label'),
    ['class' => 'lp-btn-all-plans', 'title' => local_learningplan_str('backtoallplans', 'All Learning Plans')]
);

if ($canmanage) {
    echo html_writer::span('🛡️ ' . local_learningplan_str('admincontrols', 'Admin Controls'), 'lp-top-badge lp-top-badge-admin');
    if (!$isassigned) {
        echo html_writer::span('👁️ ' . local_learningplan_str('adminpreview', 'Admin Preview Mode'), 'lp-top-badge lp-top-badge-preview');
    }
}
echo html_writer::end_div(); // lp-detail-top-left.

// Right: Hamburger Action Menu.
echo html_writer::start_div('lp-detail-top-right');
echo html_writer::start_div('lp-hamburger-wrap');
echo html_writer::tag('button', 
    html_writer::span(
        html_writer::span('', 'lp-hb-bar') .
        html_writer::span('', 'lp-hb-bar') .
        html_writer::span('', 'lp-hb-bar'),
        'lp-hb-icon'
    ) .
    html_writer::span(local_learningplan_str('actions', 'Actions'), 'lp-hb-label'),
    [
        'type' => 'button',
        'class' => 'lp-hamburger-btn',
        'id' => 'lpHamburgerBtn',
        'aria-expanded' => 'false',
        'aria-haspopup' => 'true',
        'aria-controls' => 'lpHamburgerDropdown',
        'title' => local_learningplan_str('actions', 'Actions'),
    ]
);

// Dropdown container.
echo html_writer::start_div('lp-hamburger-dropdown', ['id' => 'lpHamburgerDropdown', 'hidden' => 'hidden', 'role' => 'menu']);

// Header inside dropdown.
echo html_writer::start_div('lp-dropdown-header');
echo html_writer::div(local_learningplan_str('actions', 'Actions'), 'lp-dropdown-header-title');
echo html_writer::div(format_string($activeplan->name), 'lp-dropdown-header-subtitle');
echo html_writer::end_div();

echo html_writer::start_div('lp-dropdown-items');

if ($canmanage) {
    // 1. Edit Plan.
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_plan.php', ['id' => $activeplan->id]),
        html_writer::span('✏️', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('editplan', 'Edit Learning Plan'), 'lp-dropdown-item-title') .
            html_writer::span(get_string('description'), 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );

    // 2. Manage Steps.
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $activeplan->id]),
        html_writer::span('📑', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('managesteps', 'Manage Steps'), 'lp-dropdown-item-title') .
            html_writer::span(local_learningplan_str('steps', 'Steps and chapters configuration'), 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );

    // 3. Assign Learners.
    if ($canassign) {
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $activeplan->id]),
            html_writer::span('👥', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(local_learningplan_str('assign', 'Assign Learning Plan'), 'lp-dropdown-item-title') .
                html_writer::span(local_learningplan_str('assignedlearners', 'Assign users & cohorts'), 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
    }

    // 4. Status Dashboard.
    if ($canreport || $canmanage) {
        echo html_writer::link(
            new moodle_url('/local/learningplan/dashboard.php', ['planid' => $activeplan->id]),
            html_writer::span('📈', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(local_learningplan_str('lpdashboard', 'LP Dashboard'), 'lp-dropdown-item-title') .
                html_writer::span('Status charts and metrics for this plan', 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
    }

    // 5. Progress Report.
    if ($canreport) {
        echo html_writer::link(
            new moodle_url('/local/learningplan/manage/report.php', ['planid' => $activeplan->id]),
            html_writer::span('📊', 'lp-dropdown-item-icon') .
            html_writer::span(
                html_writer::span(local_learningplan_str('report', 'Progress Report'), 'lp-dropdown-item-title') .
                html_writer::span(local_learningplan_str('reports', 'Analytics and completions'), 'lp-dropdown-item-desc'),
                'lp-dropdown-item-text'
            ),
            ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
        );
    }

    // 5. Badges Management.
    echo html_writer::tag('button',
        html_writer::span('🏅', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('badges', 'Badges & Achievements'), 'lp-dropdown-item-title') .
            html_writer::span('Preview plan milestone badges', 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        [
            'type' => 'button',
            'class' => 'lp-dropdown-item lp-btn-open-badges-modal',
            'style' => 'background:none; border:none; width:100%; text-align:left; cursor:pointer;'
        ]
    );
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/badges.php', ['planid' => $activeplan->id]),
        html_writer::span('⚙️', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('badgemapping', 'Manage Badges'), 'lp-dropdown-item-title') .
            html_writer::span('Milestones & achievements', 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );

    // 6. Leaderboard.
    echo html_writer::link(
        new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $activeplan->id]),
        html_writer::span('🏆', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('leaderboard', 'Leaderboard'), 'lp-dropdown-item-title') .
            html_writer::span(local_learningplan_str('classstandings', 'View rankings and stars'), 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );

    // 7. Create New Plan.
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/edit_plan.php'),
        html_writer::span('➕', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('createnewplan', 'Create Learning Plan'), 'lp-dropdown-item-title') .
            html_writer::span(local_learningplan_str('createfirstplan', 'Build a new learning pathway'), 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );

    // Divider.
    echo html_writer::div('', 'lp-dropdown-divider', ['role' => 'separator']);

    // 8. Delete Plan.
    echo html_writer::link(
        new moodle_url('/local/learningplan/index.php', ['delete' => $activeplan->id]),
        html_writer::span('🗑️', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('deleteplan', 'Delete Learning Plan'), 'lp-dropdown-item-title') .
            html_writer::span('Remove plan permanently', 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item lp-dropdown-item-danger', 'role' => 'menuitem']
    );
} else {
    // For learners:
    echo html_writer::tag('button',
        html_writer::span('🏅', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('badges', 'Badges & Achievements'), 'lp-dropdown-item-title') .
            html_writer::span('View unlocked & locked badges', 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        [
            'type' => 'button',
            'class' => 'lp-dropdown-item lp-btn-open-badges-modal',
            'style' => 'background:none; border:none; width:100%; text-align:left; cursor:pointer;'
        ]
    );
    echo html_writer::link(
        new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $activeplan->id]),
        html_writer::span('🏆', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('leaderboard', 'Leaderboard'), 'lp-dropdown-item-title') .
            html_writer::span(local_learningplan_str('classstandings', 'Check top achievers & rankings'), 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );
    echo html_writer::link(
        new moodle_url('/local/learningplan/index.php'),
        html_writer::span('🗺️', 'lp-dropdown-item-icon') .
        html_writer::span(
            html_writer::span(local_learningplan_str('allplans', 'All Learning Plans'), 'lp-dropdown-item-title') .
            html_writer::span(local_learningplan_str('switchplan', 'Explore your other pathways'), 'lp-dropdown-item-desc'),
            'lp-dropdown-item-text'
        ),
        ['class' => 'lp-dropdown-item', 'role' => 'menuitem']
    );
}

echo html_writer::end_div(); // lp-dropdown-items.
echo html_writer::end_div(); // lp-hamburger-dropdown.
echo html_writer::end_div(); // lp-hamburger-wrap.
echo html_writer::end_div(); // lp-detail-top-right.

echo html_writer::end_div(); // lp-detail-top-bar.

$totals = api::get_user_plan_totals((int)$USER->id, $activeplan->id);
$progress = api::get_user_progress((int)$USER->id, $activeplan->id);
$chapters = api::get_chapters_with_steps($activeplan->id);
$prereqgaps = api::get_sequential_prereq_gaps($activeplan, $chapters, $progress, (int)$USER->id);
$planbadges = badges::get_user_plan_badges((int)$USER->id, $activeplan->id);
$earnedbadgescount = count(array_filter($planbadges, function($b) { return !empty($b->isearned); }));

$chapterbadges = [];
$plancompletionbadge = null;
foreach ($planbadges as $pb) {
    if ($pb->criteria === 'chapter_complete' && $pb->chapterid) {
        $chapterbadges[$pb->chapterid] = $pb;
    } else if ($pb->criteria === 'plan_complete') {
        $plancompletionbadge = $pb;
    }
}

$theme = $activeplan->coverimage ?: 'ocean';
$theme_icon = api::render_plan_icon($activeplan);

echo html_writer::start_div('lp-map lp-theme-' . $theme, ['id' => 'lp-map']);

// HUD: plan title, progress bar, points, stars, level info.
echo html_writer::start_div('lp-hud');

// Plan Title & Description (Full width, spacious & unconstrained).
echo html_writer::start_div('lp-hud-intro');
echo html_writer::div($theme_icon . ' ' . local_learningplan_str('plan', 'Learning Plan'), 'lp-hud-badge');
echo html_writer::tag('h1', format_string($activeplan->name), ['class' => 'lp-hud-title']);
if (!empty($activeplan->description)) {
    echo html_writer::div(format_text($activeplan->description, FORMAT_HTML), 'lp-hud-desc');
}
echo html_writer::end_div(); // lp-hud-intro.

// Plan Stats Bar.
echo html_writer::start_div('lp-hud-stats');
if ($canmanage && !$isassigned) {
    echo html_writer::div(
        html_writer::tag('span', '👁️', ['class' => 'lp-hud-icon']) .
        html_writer::tag('span', local_learningplan_str('adminpreview', 'Admin Preview Mode'), ['class' => 'lp-hud-stat-text']),
        'lp-hud-stat lp-stat-stars'
    );
    echo html_writer::div(
        html_writer::tag('span', '👥', ['class' => 'lp-hud-icon']) .
        html_writer::tag('span', count($userids) . ' Assigned Learners', ['class' => 'lp-hud-stat-text']),
        'lp-hud-stat lp-stat-points'
    );
    if (!empty($planbadges)) {
        echo html_writer::tag('button',
            html_writer::tag('span', '🏅', ['class' => 'lp-hud-icon']) .
            html_writer::tag('span', count($planbadges) . ' ' . local_learningplan_str('badges', 'Badges Linked'), ['class' => 'lp-hud-stat-text']),
            [
                'type' => 'button',
                'class' => 'lp-hud-stat lp-stat-badges',
                'id' => 'lpOpenBadgesModalBtn',
                'title' => 'View Badges & Achievements',
            ]
        );
    }
} else {
    echo html_writer::div(
        html_writer::tag('span', '⭐', ['class' => 'lp-hud-icon']) .
        html_writer::tag('span', local_learningplan_str('starsearned', '{$a->earned} / {$a->max} stars', (object)['earned' => $totals->stars, 'max' => $totals->maxstars]), ['class' => 'lp-hud-stat-text']),
        'lp-hud-stat lp-stat-stars'
    );
    echo html_writer::div(
        html_writer::tag('span', '🏆', ['class' => 'lp-hud-icon']) .
        html_writer::tag('span', local_learningplan_str('totalpoints', '{$a} points', $totals->points), ['class' => 'lp-hud-stat-text']),
        'lp-hud-stat lp-stat-points'
    );
    if (!empty($planbadges)) {
        echo html_writer::tag('button',
            html_writer::tag('span', '🏅', ['class' => 'lp-hud-icon']) .
            html_writer::tag('span', $earnedbadgescount . '/' . count($planbadges) . ' ' . local_learningplan_str('badges', 'Badges'), ['class' => 'lp-hud-stat-text']),
            [
                'type' => 'button',
                'class' => 'lp-hud-stat lp-stat-badges',
                'id' => 'lpOpenBadgesModalBtn',
                'title' => 'View Badges & Achievements',
            ]
        );
    }
    if ($totals->totalsteps > 0) {
        echo html_writer::div(
            html_writer::tag('span', '🎯', ['class' => 'lp-hud-icon']) .
            html_writer::tag('span', $totals->completedsteps . '/' . $totals->totalsteps . ' Steps', ['class' => 'lp-hud-stat-text']),
            'lp-hud-stat lp-stat-steps'
        );
    }
    echo html_writer::link(
        new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $activeplan->id]),
        html_writer::tag('span', '📊', ['class' => 'lp-hud-icon']) .
        html_writer::tag('span', local_learningplan_str('leaderboard', 'Leaderboard'), ['class' => 'lp-hud-stat-text']),
        ['class' => 'lp-hud-stat lp-stat-leaderboard', 'title' => local_learningplan_str('planleaderboard', 'Plan Leaderboard')]
    );
}
echo html_writer::end_div(); // lp-hud-stats.

if ($isassigned || !$canmanage) {
    echo html_writer::start_div('lp-progress-section');
    echo html_writer::start_div('lp-progress-track');
    echo html_writer::div('', 'lp-progress-fill', ['style' => 'width:' . $totals->percent . '%']);
    echo html_writer::end_div();
    echo html_writer::start_div('lp-progress-info');
    echo html_writer::tag('span', $totals->completedsteps . ' of ' . $totals->totalsteps . ' completed', ['class' => 'lp-progress-sub']);
    echo html_writer::tag('span', $totals->percent . '%', ['class' => 'lp-progress-label']);
    echo html_writer::end_div();
    echo html_writer::end_div(); // lp-progress-section.
}

echo html_writer::end_div(); // lp-hud.

// Prerequisite Gap Informational Banner:
// If the learner in sequential mode has already completed downstream courses/activities in Moodle
// before finishing earlier steps, inform them with a clear, user-level message.
if (!empty($prereqgaps) && ($isassigned || !$canmanage)) {
    $firstgap = reset($prereqgaps);
    $gapstep = $firstgap['step'];
    $prereqstep = $firstgap['prereq_step'];
    $bannerdata = (object)[
        'completedstep' => format_string($gapstep->title),
        'prereqnum' => $firstgap['prereq_step_num'],
        'prereqtitle' => format_string($prereqstep->title),
    ];
    echo html_writer::start_div('lp-prereq-journey-banner');
    echo html_writer::div('⏳', 'lp-prereq-journey-banner-icon');
    echo html_writer::start_div('lp-prereq-journey-banner-content');
    echo html_writer::tag('h4', '⚠️ ' . local_learningplan_str('prereq_gap_banner_title', 'Sequential Path Notice'), ['class' => 'lp-prereq-banner-title']);
    echo html_writer::tag('p', local_learningplan_str('prereq_gap_banner_desc', 'You have already completed one or more downstream courses (such as "{$a->completedstep}"), but this learning plan follows a sequential path. Please finish earlier steps (such as Step {$a->prereqnum}: "{$a->prereqtitle}") first. Once finished, your downstream progress and rewards will unlock automatically!', $bannerdata), ['class' => 'lp-prereq-banner-desc']);
    echo html_writer::end_div(); // lp-prereq-journey-banner-content.
    echo html_writer::end_div(); // lp-prereq-journey-banner.
}

// --- Procedural / Randomized Map Layout Engine -----------------------
$planseed = ((int)$activeplan->id * 2654435761) ^ ((int)crc32($activeplan->name));
$seeded_random = function() use (&$planseed) {
    $planseed = ($planseed * 1103515245 + 12345) & 0x7fffffff;
    return $planseed / 2147483647.0;
};

// Select one of 5 distinct path layout archetypes deterministically.
$archetype = ((int)$activeplan->id + abs((int)crc32($activeplan->name))) % 5;
$phase = $seeded_random() * 2 * M_PI;
$freq1 = 0.85 + $seeded_random() * 0.45;
$freq2 = 0.35 + $seeded_random() * 0.30;
$amp1 = 28 + $seeded_random() * 8;
$amp2 = 5 + $seeded_random() * 7;
$center_drift = ($seeded_random() - 0.5) * 6;

$rowheight = 154;
$bannerheight = 92;
$y = 44;
$globalindex = 0;
$layout = [];
$decorations = [];

// Island theme: assign every step to an island "group" (1 or 2 steps per island graphic,
// cycling through the 4 exported islands) up front, so both the banner and node markup can
// carry the group/slot indices the client-side layout engine needs to anchor nodes to land.
// Slot percentages below were derived programmatically (not guessed): each PNG was decoded
// pixel-by-pixel in-browser, grass-colored land was isolated from the teal water halo/rocks,
// then the land blob(s) were eroded down to their most interior point so nodes never land
// near a thin edge or gap. See conversation history for the analysis script if these ever
// need to be re-derived after the source art changes.
$islandtemplates = [
    ['file' => 'island-a2.png', 'steps' => 2, 'width' => 46, 'aspect' => 1329 / 1333,
        'slots' => [['x' => 42.9, 'y' => 23.8], ['x' => 75.4, 'y' => 52.1]]],
    ['file' => 'island-b1.png', 'steps' => 1, 'width' => 34, 'aspect' => 863 / 1245,
        'slots' => [['x' => 29.4, 'y' => 29.9]]],
    ['file' => 'island-c2.png', 'steps' => 2, 'width' => 48, 'aspect' => 871 / 1471,
        'slots' => [['x' => 78.8, 'y' => 22.3], ['x' => 30.5, 'y' => 56.9]]],
    ['file' => 'island-d1.png', 'steps' => 1, 'width' => 32, 'aspect' => 811 / 857,
        'slots' => [['x' => 21.6, 'y' => 18.6]]],
];
$stepislandinfo = [];
if ($theme === 'island') {
    $gindex = 0;
    $sidx = 0;
    foreach ($chapters as $chapter) {
        foreach ($chapter->steps as $step) {
            $tpl = $islandtemplates[$gindex % count($islandtemplates)];
            $stepislandinfo[$step->id] = ['group' => $gindex, 'slot' => $sidx];
            $sidx++;
            if ($sidx >= $tpl['steps']) {
                $gindex++;
                $sidx = 0;
            }
        }
    }
}

foreach ($chapters as $cindex => $chapter) {
    $chaptersteps = $chapter->steps;
    $chaptercomplete = $chaptersteps && array_reduce($chaptersteps, function($carry, $step) use ($progress) {
        return $carry && isset($progress[$step->id]) && $progress[$step->id]->status === 'completed';
    }, true);

    $chbadge = $chapterbadges[$chapter->id] ?? null;
    $firststep = $chaptersteps ? reset($chaptersteps) : null;

    $layout[] = [
        'type' => 'banner',
        'y' => $y,
        'num' => $cindex + 1,
        'title' => $chapter->title,
        'complete' => $chaptercomplete,
        'badge' => $chbadge,
        'beforegroup' => $firststep ? ($stepislandinfo[$firststep->id]['group'] ?? null) : null,
    ];
    $y += $bannerheight;

    $chapter_offset = ($cindex % 2 == 1) ? 3.14159 : 0;

    foreach ($chaptersteps as $step) {
        $t = $globalindex;
        switch ($archetype) {
            case 0:
                $x_offset = $amp1 * sin($t * $freq1 + $phase) + $amp2 * cos($t * $freq2 + $phase);
                break;
            case 1:
                $tri = 2 * abs(fmod(($t * $freq1 / M_PI) + ($phase / M_PI), 2.0) - 1.0) - 1.0;
                $x_offset = $amp1 * $tri + $amp2 * sin($t * 1.5);
                break;
            case 2:
                $x_offset = ($amp1 * 0.8) * sin($t * $freq1 + $phase + $chapter_offset) + ($amp1 * 0.4) * sin($t * 2.1 + $phase * 0.5);
                break;
            case 3:
                $x_offset = $amp1 * sin($t * $freq1 + $phase) + 8 * sin($t * 0.6);
                if ($t % 4 == 0) {
                    $x_offset *= 0.6;
                }
                break;
            case 4:
            default:
                $jitter = ($seeded_random() - 0.5) * 6;
                $x_offset = $amp1 * sin($t * $freq1 + $phase) + $amp2 * sin($t * $freq2 * 1.8) + $jitter;
                break;
        }

        $x = 50 + $center_drift + $x_offset;
        $x = max(16, min(84, $x));

        $layout[] = [
            'type' => 'node',
            'y' => $y,
            'x' => round($x, 2),
            'step' => $step,
            'stepnumber' => $globalindex + 1,
        ];

        if ($globalindex % 2 === 0 || $seeded_random() > 0.45) {
            $decor_side = ($x > 50) ? 'left' : 'right';
            $decor_x = ($decor_side === 'left') ? (10 + $seeded_random() * 22) : (68 + $seeded_random() * 22);
            $decor_y = $y + ($seeded_random() - 0.5) * 60;
            $decor_types = ['island', 'cloud', 'crystal', 'flora', 'star', 'mountain', 'signpost'];
            $decor_type = $decor_types[($archetype + $globalindex + (int)($seeded_random() * 4)) % count($decor_types)];
            $decor_scale = 0.75 + $seeded_random() * 0.5;

            $decorations[] = [
                'type' => $decor_type,
                'x' => round($decor_x, 2),
                'y' => round($decor_y, 0),
                'scale' => round($decor_scale, 2),
                'rotate' => round(($seeded_random() - 0.5) * 24, 1),
            ];
        }

        $y += $rowheight;
        $globalindex++;
    }
}
$finishy = $y + 24;
$totalheight = $finishy + 160;

echo html_writer::start_div('lp-path-container', ['id' => 'lp-path-container', 'style' => 'height:' . $totalheight . 'px']);

// Decorative environment landmarks. Skipped for the island theme: its own artwork (water
// texture + island illustrations) already supplies all the scenery, so the generic
// emoji/CSS-shape props (clouds, mini islands, mountains, glow blobs) would just clutter it.
if ($theme !== 'island') {
    echo html_writer::start_div('lp-decorations-layer');
    foreach ($decorations as $d) {
        $style = sprintf('left:%.2f%%; top:%dpx; transform:scale(%.2f) rotate(%.1fdeg);', $d['x'], $d['y'], $d['scale'], $d['rotate']);
        echo html_writer::div('', 'lp-decor lp-decor-' . $d['type'] . ' lp-decor-theme-' . $theme, ['style' => $style]);
    }
    echo html_writer::div('', 'lp-blob lp-blob-1');
    echo html_writer::div('', 'lp-blob lp-blob-2');
    echo html_writer::div('', 'lp-blob lp-blob-3');
    echo html_writer::div('', 'lp-blob lp-blob-4');
    echo html_writer::end_div(); // lp-decorations-layer.
}

// Island theme: one <img> per island group, carrying the data the client-side layout
// engine (LPMap.layoutIslandTheme) needs to size/position it responsively and to snap
// each of its 1-2 step nodes onto the artwork's actual land area.
if ($theme === 'island' && !empty($stepislandinfo)) {
    $groupcount = 1 + max(array_column($stepislandinfo, 'group'));
    echo html_writer::start_div('lp-island-layer');
    for ($g = 0; $g < $groupcount; $g++) {
        $tpl = $islandtemplates[$g % count($islandtemplates)];
        $imgurl = new moodle_url('/local/learningplan/pix/themes/island/' . $tpl['file']);
        echo html_writer::empty_tag('img', [
            'src' => $imgurl->out(false),
            'class' => 'lp-island-art',
            'alt' => '',
            'data-group' => $g,
            'data-width' => $tpl['width'],
            'data-aspect' => round($tpl['aspect'], 4),
            'data-slots' => json_encode($tpl['slots']),
        ]);
    }
    echo html_writer::end_div(); // lp-island-layer.
}

echo html_writer::tag('svg', '', ['id' => 'lp-path-svg', 'class' => 'lp-path-svg']);

foreach ($layout as $item) {
    if ($item['type'] === 'banner') {
        $badgehtml = '';
        if (!empty($item['badge'])) {
            $b = $item['badge'];
            $badgetitle = s($b->name);
            if (!empty($b->isearned)) {
                $badgehtml = html_writer::div(
                    html_writer::empty_tag('img', ['src' => $b->imageurl, 'alt' => $badgetitle, 'class' => 'lp-chapter-badge-img']) .
                    html_writer::span('✓ Earned', 'lp-chapter-badge-label'),
                    'lp-chapter-badge-tag earned',
                    ['title' => $badgetitle . ' (Earned!)']
                );
            } else {
                $badgehtml = html_writer::div(
                    html_writer::empty_tag('img', ['src' => $b->imageurl, 'alt' => $badgetitle, 'class' => 'lp-chapter-badge-img locked']) .
                    html_writer::span('🔒 Chapter Badge', 'lp-chapter-badge-label'),
                    'lp-chapter-badge-tag locked',
                    ['title' => $badgetitle . ' (Complete this chapter to earn)']
                );
            }
        }

        $bannerattrs = ['style' => 'top:' . $item['y'] . 'px'];
        if ($theme === 'island' && isset($item['beforegroup'])) {
            $bannerattrs['data-beforegroup'] = $item['beforegroup'];
        }
        echo html_writer::div(
            html_writer::tag('span', $item['num'], ['class' => 'lp-chapter-num']) .
            html_writer::tag('span', format_string($item['title']), ['class' => 'lp-chapter-title']) .
            $badgehtml,
            'lp-chapter-banner' . ($item['complete'] ? ' complete' : ''),
            $bannerattrs
        );
        continue;
    }

    $step = $item['step'];
    $stepnumber = $item['stepnumber'];
    $p = $progress[$step->id] ?? null;

    // For admin preview mode on unassigned plan, make nodes accessible for preview.
    if ($canmanage && !$isassigned) {
        $status = 'available';
        $stars = 0;
    } else {
        $status = $p->status ?? 'locked';
        $stars = $p->starsearned ?? 0;
    }
    $isselfreport = in_array($step->steptype, ['file', 'url']);

    $iconmarkup = api::render_step_icon_html($step->icon ?? 'icon-01.png', format_string($step->title));
    $typelabel = local_learningplan_str('steptype_' . $step->steptype, ucfirst($step->steptype));

    $href = '#';
    $target = '_self';
    if ($step->steptype === 'course' && $step->courseid) {
        $href = (new moodle_url('/course/view.php', ['id' => $step->courseid]))->out(false);
    } else if ($step->steptype === 'activity' && $step->cmid) {
        $cm = get_coursemodule_from_id('', $step->cmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $href = (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false);
        }
    } else if ($isselfreport) {
        $href = $step->url;
        $target = '_blank';
    }

    $prereqgap = $prereqgaps[$step->id] ?? null;
    $hasprereqgap = !empty($prereqgap);

    $nodeclasses = 'lp-node lp-node-' . $status . ($isselfreport ? ' lp-node-self' : '') . ($hasprereqgap ? ' lp-node-prereq-pending' : '');

    $nodeattrs = [
        'class' => $nodeclasses,
        'style' => 'left:' . $item['x'] . '%; top:' . $item['y'] . 'px;',
        'data-stepid' => $step->id,
        'data-stepnumber' => $stepnumber,
        'data-status' => $status,
        'data-title' => format_string($step->title),
        'data-type' => $typelabel,
        'data-points' => $step->points,
        'data-stars' => $stars,
        'data-maxstars' => $step->maxstars,
        'data-selfreport' => $isselfreport ? '1' : '0',
        'data-verifiedby' => $p->verifiedby ?? '',
        'data-href' => $href,
        'data-target' => $target,
        'tabindex' => $status === 'locked' ? '-1' : '0',
        'role' => 'button',
        'aria-label' => format_string($step->title),
    ];

    if ($theme === 'island' && isset($stepislandinfo[$step->id])) {
        $nodeattrs['data-groupindex'] = $stepislandinfo[$step->id]['group'];
        $nodeattrs['data-slotindex'] = $stepislandinfo[$step->id]['slot'];
    }

    if ($hasprereqgap) {
        $nodeattrs['data-prereqgap'] = '1';
        $nodeattrs['data-prereqnum'] = $prereqgap['prereq_step_num'];
        $nodeattrs['data-prereqtitle'] = format_string($prereqgap['prereq_step']->title);
        $nodeattrs['data-prereqstepid'] = $prereqgap['prereq_step']->id;
    }

    if ($canmanage) {
        $nodeattrs['data-stepediturl'] = (new moodle_url('/local/learningplan/manage/edit_step.php', ['id' => $step->id, 'chapterid' => $step->chapterid]))->out(false);
    }

    echo html_writer::start_div('', $nodeattrs);
    echo html_writer::div($stepnumber, 'lp-node-stepbadge');
    echo html_writer::start_div('lp-node-ring');
    echo html_writer::div($iconmarkup, 'lp-node-icon');
    if ($status === 'completed') {
        echo html_writer::div('✓', 'lp-node-check');
    } else if ($hasprereqgap) {
        $hint = local_learningplan_str('prereq_pending_node_hint', 'Course completed! Finish Step {$a} first to unlock and claim rewards.', $prereqgap['prereq_step_num']);
        echo html_writer::div('⏳', 'lp-node-lockbadge lp-node-lockbadge-prereq', ['title' => $hint]);
    } else if ($status === 'locked') {
        echo html_writer::div('🔒', 'lp-node-lockbadge');
    }
    echo html_writer::end_div(); // lp-node-ring.
    echo html_writer::div('+' . $step->points, 'lp-node-pointschip');
    if ($status === 'completed') {
        $starmarkup = '';
        for ($i = 1; $i <= $step->maxstars; $i++) {
            $starmarkup .= html_writer::tag('span', '★', ['class' => 'lp-star' . ($i <= $stars ? ' filled' : '')]);
        }
        echo html_writer::div($starmarkup, 'lp-node-ministars');
    }
    echo html_writer::div(format_string($step->title), 'lp-node-caption');
    echo html_writer::end_div(); // lp-node.
}

// Finish node.
$plancomplete = $totals->totalsteps > 0 && $totals->completedsteps == $totals->totalsteps;
$finishbadgehtml = '';
if (!empty($plancompletionbadge)) {
    $pb = $plancompletionbadge;
    $badgename = s($pb->name);
    if (!empty($pb->isearned)) {
        $finishbadgehtml = html_writer::div(
            html_writer::empty_tag('img', ['src' => $pb->imageurl, 'alt' => $badgename, 'class' => 'lp-finish-badge-img']) .
            html_writer::span('🏆 ' . $badgename . ' (Earned!)', 'lp-finish-badge-label'),
            'lp-finish-badge-tag earned',
            ['title' => $badgename . ' — Plan Completion Badge (Earned!)']
        );
    } else {
        $finishbadgehtml = html_writer::div(
            html_writer::empty_tag('img', ['src' => $pb->imageurl, 'alt' => $badgename, 'class' => 'lp-finish-badge-img locked']) .
            html_writer::span('🔒 ' . $badgename, 'lp-finish-badge-label'),
            'lp-finish-badge-tag locked',
            ['title' => $badgename . ' — Grand Champion Badge (Complete 100% to unlock)']
        );
    }
}

echo html_writer::start_div('lp-finish-node' . ($plancomplete ? ' reached' : '') . (!empty($finishbadgehtml) ? ' has-badge' : ''), ['style' => 'top:' . $finishy . 'px']);
echo html_writer::start_div('lp-finish-main');
echo html_writer::tag('span', '🏆', ['class' => 'lp-finish-icon']);
echo html_writer::tag('span', $plancomplete ? local_learningplan_str('plancomplete', 'Plan complete!') : local_learningplan_str('finishplan', 'Finish'), ['class' => 'lp-finish-text']);
echo html_writer::end_div(); // lp-finish-main.
if ($finishbadgehtml) {
    echo html_writer::span('', 'lp-finish-divider');
    echo $finishbadgehtml;
}
echo html_writer::end_div(); // lp-finish-node.

// Level detail popup card.
echo html_writer::start_div('lp-popup', ['id' => 'lp-popup', 'hidden' => 'hidden']);
echo html_writer::tag('button', '×', ['type' => 'button', 'class' => 'lp-popup-close', 'id' => 'lp-popup-close', 'aria-label' => 'Close']);
echo html_writer::div('', 'lp-popup-type', ['id' => 'lp-popup-type']);
echo html_writer::tag('h3', '', ['id' => 'lp-popup-title']);
echo html_writer::div('', 'lp-popup-meta', ['id' => 'lp-popup-meta']);
echo html_writer::div('', 'lp-popup-stars', ['id' => 'lp-popup-stars']);
echo html_writer::div('', 'lp-popup-actions', ['id' => 'lp-popup-actions']);
echo html_writer::end_div();

echo html_writer::end_div(); // lp-path-container.

// Badges & Achievements Modal.
echo html_writer::start_div('lp-badges-modal-backdrop', [
    'id' => 'lpBadgesModalBackdrop',
    'style' => 'display:none;',
]);
echo html_writer::start_div('lp-badges-modal', ['role' => 'dialog', 'aria-modal' => 'true', 'aria-labelledby' => 'lpBadgesModalTitle']);

// Modal Header.
echo html_writer::start_div('lp-badges-modal-header');
echo html_writer::start_div();
echo html_writer::tag('h3', '🏅 Badges & Achievements', ['id' => 'lpBadgesModalTitle', 'class' => 'lp-badges-modal-title']);
echo html_writer::div(format_string($activeplan->name) . ' • ' . $earnedbadgescount . ' of ' . count($planbadges) . ' earned', 'lp-badges-modal-sub');
echo html_writer::end_div();
echo html_writer::tag('button', '×', [
    'type' => 'button',
    'class' => 'lp-badges-modal-close',
    'id' => 'lpBadgesModalClose',
    'aria-label' => 'Close'
]);
echo html_writer::end_div();

// Modal Body.
echo html_writer::start_div('lp-badges-modal-body');
if (empty($planbadges)) {
    echo html_writer::div('No badges have been mapped to this learning plan yet.', 'lp-badges-empty text-muted p-4 text-center');
} else {
    echo html_writer::start_div('lp-badges-grid');
    foreach ($planbadges as $b) {
        $cardclass = 'lp-badge-card ' . (!empty($b->isearned) ? 'earned' : 'locked');
        echo html_writer::start_div($cardclass);

        echo html_writer::start_div('lp-badge-card-icon-wrap');
        echo html_writer::empty_tag('img', [
            'src' => $b->imageurl,
            'alt' => $b->name,
            'class' => 'lp-badge-card-img'
        ]);
        if (!empty($b->isearned)) {
            echo html_writer::span('✓', 'lp-badge-card-check');
        } else {
            echo html_writer::span('🔒', 'lp-badge-card-lock');
        }
        echo html_writer::end_div();

        echo html_writer::start_div('lp-badge-card-content');
        echo html_writer::tag('h5', $b->name, ['class' => 'lp-badge-card-name']);

        $milestone = ($b->criteria === 'plan_complete')
            ? '🏆 Plan Grand Champion'
            : ('🔖 ' . ($b->chaptertitle ?: 'Chapter Milestone'));
        echo html_writer::div($milestone, 'lp-badge-card-milestone');

        if (!empty($b->description)) {
            echo html_writer::div($b->description, 'lp-badge-card-desc');
        }

        if (!empty($b->isearned)) {
            $date = $b->dateissued ? date('M j, Y', $b->dateissued) : 'Completed';
            echo html_writer::div('⭐ Earned on ' . $date, 'lp-badge-card-status earned');
        } else {
            $req = ($b->criteria === 'plan_complete') ? 'Complete 100% of all steps' : 'Complete all steps in this chapter';
            echo html_writer::div('🔒 Locked — ' . $req, 'lp-badge-card-status locked');
        }
        echo html_writer::end_div(); // lp-badge-card-content.

        echo html_writer::end_div(); // lp-badge-card.
    }
    echo html_writer::end_div(); // lp-badges-grid.
}
echo html_writer::end_div(); // lp-badges-modal-body.

echo html_writer::end_div(); // lp-badges-modal.
echo html_writer::end_div(); // lp-badges-modal-backdrop.

echo html_writer::end_div(); // lp-map.
echo html_writer::end_div(); // lp-wrap.

// Hand the client the data (sesskey, endpoint, translated strings) the
// local_learningplan/map AMD module needs. All behaviour lives in that module.
$PAGE->requires->js_init_code('
    M.local_learningplan = M.local_learningplan || {};
    M.local_learningplan.sesskey = ' . json_encode(sesskey()) . ';
    M.local_learningplan.ajaxurl = ' . json_encode((new moodle_url('/local/learningplan/ajax.php'))->out(false)) . ';
    M.local_learningplan.strings = ' . json_encode([
        'open' => local_learningplan_str('openstep', 'Open'),
        'markasdone' => local_learningplan_str('markasdone', 'Mark as done'),
        'locked' => local_learningplan_str('locked', 'Locked'),
        'lockedhint' => local_learningplan_str('lockedhint', 'Complete the previous step to unlock this one.'),
        'selfreported' => local_learningplan_str('selfreported', 'Self-reported'),
        'points' => local_learningplan_str('points', 'Points'),
        'completed' => local_learningplan_str('completed', 'Completed'),
        'editstep' => local_learningplan_str('editstep', 'Edit Step'),
        'prereq_gap_title' => local_learningplan_str('prereq_gap_title', 'Prerequisite Steps Required'),
        'prereq_gap_msg' => local_learningplan_str('prereq_gap_msg', 'You have already completed this course/activity! However, to progress on this sequential learning journey and earn your XP & stars, you must complete the earlier step(s) first (such as Step {$a->stepnum}: "{$a->steptitle}").'),
        'prereq_gap_autonotice' => local_learningplan_str('prereq_gap_autonotice', 'Once you finish the preceding step(s), this step will unlock and complete automatically without needing to redo it.'),
        'gotoprereq' => local_learningplan_str('gotoprereq', 'Go to Step {$a}'),
    ]) . ';
');

echo $OUTPUT->footer();
