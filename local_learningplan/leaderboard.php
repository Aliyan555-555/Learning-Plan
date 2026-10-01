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
 * Learning Plan Leaderboard — Gamified rankings, scores, steps & chapter progress.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_learningplan\api;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();

$canmanage = has_capability('local/learningplan:manage', $context);
$canreport = has_capability('local/learningplan:viewreports', $context);
$isadmin = ($canmanage || $canreport);

if (!$isadmin) {
    require_capability('local/learningplan:view', $context);
}

global $USER, $DB, $OUTPUT, $PAGE;

$planid = optional_param('planid', optional_param('id', 0, PARAM_INT), PARAM_INT);
$search = optional_param('search', '', PARAM_NOTAGS);
$export = optional_param('export', 0, PARAM_BOOL);

// The leaderboard is a read-only view. Progress catch-up for the current learner
// happens on their own plan map and via the sync_progress scheduled task, never
// as a side effect of opening this page (which would also touch other learners'
// rows via the ranking computation).

// Get available plans.
if ($isadmin) {
    $plans = api::get_plans();
} else {
    $plans = api::get_user_plans((int)$USER->id);
}

if (empty($plans)) {
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/learningplan/leaderboard.php'));
    $PAGE->set_title(local_learningplan_str('leaderboard', 'Leaderboard'));
    $PAGE->set_heading(local_learningplan_str('leaderboard', 'Leaderboard'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(local_learningplan_str('nolearningplans', 'No learning plans available.'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Select active plan.
$activeplan = null;
if ($planid && isset($plans[$planid])) {
    $activeplan = $plans[$planid];
} else if ($planid && $isadmin) {
    try {
        $activeplan = api::get_plan($planid);
    } catch (\Exception $e) {
        $activeplan = reset($plans);
    }
} else {
    $activeplan = reset($plans);
}
$planid = $activeplan->id;

// Fetch full leaderboard rankings for active plan.
$leaderboard = api::get_plan_leaderboard($planid);

// Handle CSV export for admins.
if ($export && $isadmin) {
    require_sesskey();
    $csvsafe = function($value) {
        $value = (string)$value;
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }
        return $value;
    };
    $filename = 'leaderboard_plan_' . $planid . '_' . userdate(time(), '%Y%m%d_%H%M%S') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Rank', 'Learner', 'Email', 'XP Points', 'Stars', 'Completed Steps', 'Total Steps', 'Completed Chapters', 'Total Chapters', 'Current Chapter', 'Progress %']);
    foreach ($leaderboard as $row) {
        fputcsv($out, array_map($csvsafe, [
            $row->rank,
            $row->fullname,
            $row->email,
            $row->points,
            $row->stars,
            $row->completedsteps,
            $row->totalsteps,
            $row->completedchapters,
            $row->totalchapters,
            $row->currentchapter->title ?? '',
            $row->percent . '%',
        ]));
    }
    fclose($out);
    exit;
}

// Current student rank details — computed from the full ranking, before any
// search filter narrows the visible table, and reusing the leaderboard already
// built above rather than recomputing it.
$mystats = null;
if (!$isadmin || in_array((int)$USER->id, api::get_plan_userids($planid))) {
    $mystats = api::get_user_leaderboard_rank((int)$USER->id, $planid, $leaderboard);
}

// Search filter if submitted.
if (!empty($search)) {
    $searchterm = mb_strtolower(trim($search));
    $leaderboard = array_filter($leaderboard, function($row) use ($searchterm) {
        return mb_stripos($row->fullname, $searchterm) !== false || mb_stripos($row->email, $searchterm) !== false;
    });
}

$pageurl = new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $planid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout($isadmin ? 'admin' : 'standard');
$PAGE->set_title(format_string($activeplan->name) . ' — ' . local_learningplan_str('leaderboard', 'Leaderboard'));
$PAGE->set_heading(format_string($activeplan->name) . ' — ' . local_learningplan_str('leaderboard', 'Leaderboard'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

echo $OUTPUT->header();

echo html_writer::start_div('lp-wrap lp-leaderboard-page');

// Navigation Bar / Header.
if ($isadmin) {
    echo navigation::render_plan_context_header($activeplan->id, 'leaderboard');
} else {
    // Student Top Nav Bar.
    echo html_writer::start_div('lp-nav-bar mb-3');
    echo html_writer::link(
        new moodle_url('/local/learningplan/index.php', ['id' => $activeplan->id]),
        '‹ ' . local_learningplan_str('mylearningpath', 'My Learning Path'),
        ['class' => 'lp-back-btn']
    );

    if (count($plans) > 1) {
        echo html_writer::start_div('lp-plan-tabs');
        foreach ($plans as $plan) {
            $classes = 'lp-plan-tab' . ($plan->id == $activeplan->id ? ' active' : '');
            echo html_writer::link(
                new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $plan->id]),
                format_string($plan->name),
                ['class' => $classes]
            );
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // .lp-nav-bar
}

// --------------------------------------------------------------------------
// STUDENT PERSONAL SHOWCASE HERO CARD (Shown for learners)
// --------------------------------------------------------------------------
if (!$isadmin && $mystats && $mystats->found) {
    $myentry = $mystats->entry;
    $rankmedal = '🏅';
    $rankclass = 'lp-rank-pill-general';
    if ($mystats->rank === 1) {
        $rankmedal = '🥇 1st Place Champion';
        $rankclass = 'lp-rank-pill-gold';
    } else if ($mystats->rank === 2) {
        $rankmedal = '🥈 2nd Place';
        $rankclass = 'lp-rank-pill-silver';
    } else if ($mystats->rank === 3) {
        $rankmedal = '🥉 3rd Place';
        $rankclass = 'lp-rank-pill-bronze';
    } else {
        $rankmedal = 'Rank #' . $mystats->rank . ' of ' . $mystats->total;
    }

    echo html_writer::start_div('lp-student-rank-card mb-4');
    echo html_writer::start_div('lp-student-rank-hero-top');
    
    // Left: Avatar + Title.
    echo html_writer::start_div('lp-student-rank-left');
    echo $OUTPUT->user_picture($USER, ['size' => 64, 'class' => 'lp-student-rank-avatar mr-3']);
    echo html_writer::start_div('lp-student-rank-text');
    echo html_writer::tag('span', $rankmedal, ['class' => 'lp-rank-hero-badge ' . $rankclass]);
    echo html_writer::tag('h2', local_learningplan_str('yourranking', 'Your Standing in ') . format_string($activeplan->name), ['class' => 'lp-student-rank-title mt-1 mb-0']);
    echo html_writer::tag('p', local_learningplan_str('toprank_desc', 'You are ranked #{$a->rank} out of {$a->total} learners on this learning plan!', (object)['rank' => $mystats->rank, 'total' => $mystats->total]), ['class' => 'lp-student-rank-sub text-muted small mt-1 mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Right: Action button to continue journey.
    echo html_writer::start_div('lp-student-rank-actions');
    echo html_writer::link(
        new moodle_url('/local/learningplan/index.php', ['id' => $activeplan->id]),
        local_learningplan_str('continuejourney', 'Continue Journey') . ' ➔',
        ['class' => 'btn btn-primary lp-btn-continue-journey px-4 py-2 font-weight-bold shadow-sm']
    );
    echo html_writer::end_div();
    echo html_writer::end_div(); // .lp-student-rank-hero-top

    // 4 Key Metric Tiles.
    echo html_writer::start_div('lp-student-rank-grid mt-3');

    // Tile 1: Points Score.
    echo html_writer::start_div('lp-student-stat-box');
    echo html_writer::div('🏆', 'lp-stat-box-icon text-warning');
    echo html_writer::start_div('lp-stat-box-content');
    echo html_writer::tag('span', $myentry->points . ' XP', ['class' => 'lp-stat-box-value']);
    echo html_writer::tag('span', local_learningplan_str('score', 'Score / Total XP'), ['class' => 'lp-stat-box-label']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Tile 2: Completed Steps.
    echo html_writer::start_div('lp-student-stat-box');
    echo html_writer::div('🎯', 'lp-stat-box-icon text-info');
    echo html_writer::start_div('lp-stat-box-content');
    echo html_writer::tag('span', $myentry->completedsteps . ' / ' . $myentry->totalsteps, ['class' => 'lp-stat-box-value']);
    echo html_writer::tag('span', local_learningplan_str('completedsteps', 'Completed Steps') . ' (' . $myentry->percent . '%)', ['class' => 'lp-stat-box-label']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Tile 3: Completed Chapters.
    echo html_writer::start_div('lp-student-stat-box');
    echo html_writer::div('📚', 'lp-stat-box-icon text-success');
    echo html_writer::start_div('lp-stat-box-content');
    echo html_writer::tag('span', $myentry->completedchapters . ' / ' . $myentry->totalchapters, ['class' => 'lp-stat-box-value']);
    echo html_writer::tag('span', local_learningplan_str('completedchapters', 'Completed Chapters'), ['class' => 'lp-stat-box-label']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Tile 4: Current Chapter Milestone.
    echo html_writer::start_div('lp-student-stat-box');
    echo html_writer::div('🚩', 'lp-stat-box-icon text-primary');
    echo html_writer::start_div('lp-stat-box-content');
    $currenttitle = $myentry->currentchapter->title ?? get_string('none');
    echo html_writer::tag('span', format_string($currenttitle), ['class' => 'lp-stat-box-value lp-stat-box-truncate', 'title' => $currenttitle]);
    echo html_writer::tag('span', local_learningplan_str('currentchapter', 'Current Chapter Milestone'), ['class' => 'lp-stat-box-label']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div(); // .lp-student-rank-grid
    echo html_writer::end_div(); // .lp-student-rank-card
}

// --------------------------------------------------------------------------
// TOP 3 PODIUM / HALL OF FAME CARDS (3D Championship Pedestal Layout)
// --------------------------------------------------------------------------
$top3 = array_slice($leaderboard, 0, 3);
if (!empty($top3)) {
    echo html_writer::start_div('lp-hall-of-fame-wrapper mb-5');
    echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center mb-4');
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::div('🏆', 'lp-hof-title-icon mr-2');
    echo html_writer::start_div();
    echo html_writer::tag('h3', local_learningplan_str('halloffame', 'Hall of Fame') . ' — ' . format_string($activeplan->name), ['class' => 'h5 font-weight-bold text-dark mb-0']);
    echo html_writer::tag('p', 'Top ranked learners leading the leaderboard journey', ['class' => 'text-muted small mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    
    if ($isadmin) {
        echo html_writer::link(
            new moodle_url($pageurl, ['export' => 1, 'sesskey' => sesskey()]),
            '<i class="fa fa-download mr-1"></i> ' . get_string('export', 'local_learningplan') . ' CSV',
            ['class' => 'btn btn-sm btn-outline-success font-weight-bold shadow-sm']
        );
    }
    echo html_writer::end_div();

    echo html_writer::start_div('lp-podium-stage');
    echo html_writer::start_div('lp-podium-grid');

    // Rank 2 (Left - Silver)
    if (isset($top3[1])) {
        $p2 = $top3[1];
        $is_me2 = ($p2->userid == $USER->id);
        echo html_writer::start_div('lp-podium-column lp-col-silver' . ($is_me2 ? ' lp-col-is-me' : ''));
        echo html_writer::start_div('lp-podium-card lp-card-silver');
        
        echo html_writer::start_div('lp-podium-header-badge lp-badge-silver');
        echo html_writer::tag('span', '🥈', ['class' => 'lp-badge-icon']);
        echo html_writer::tag('span', local_learningplan_str('podium_2nd', '2nd Place'), ['class' => 'lp-badge-text']);
        echo html_writer::end_div();

        echo html_writer::start_div('lp-avatar-wrapper');
        echo $OUTPUT->user_picture($p2->user, ['size' => 68, 'class' => 'lp-podium-avatar']);
        echo html_writer::tag('span', '2', ['class' => 'lp-rank-circle lp-circle-silver']);
        echo html_writer::end_div();

        echo html_writer::tag('h4', format_string($p2->fullname), ['class' => 'lp-podium-name font-weight-bold mt-2 mb-0', 'title' => $p2->fullname]);
        if ($is_me2) {
            echo html_writer::span('⭐ ' . local_learningplan_str('you', 'YOU'), 'badge badge-primary lp-you-badge mt-1');
        }

        echo html_writer::start_div('lp-podium-score-pill lp-pill-silver mt-2');
        echo html_writer::tag('i', '', ['class' => 'fa fa-bolt mr-1 text-warning']);
        echo html_writer::tag('strong', $p2->points . ' XP');
        echo html_writer::end_div();

        echo html_writer::start_div('lp-podium-stats-row mt-2');
        echo html_writer::start_div('lp-pstat-item');
        echo html_writer::tag('span', $p2->completedchapters . '/' . $p2->totalchapters, ['class' => 'lp-pstat-val']);
        echo html_writer::tag('span', 'Chapters', ['class' => 'lp-pstat-lbl']);
        echo html_writer::end_div();
        echo html_writer::div('', 'lp-pstat-sep');
        echo html_writer::start_div('lp-pstat-item');
        echo html_writer::tag('span', $p2->completedsteps . '/' . $p2->totalsteps, ['class' => 'lp-pstat-val']);
        echo html_writer::tag('span', $p2->percent . '% Steps', ['class' => 'lp-pstat-lbl']);
        echo html_writer::end_div();
        echo html_writer::end_div(); // .lp-podium-stats-row

        echo html_writer::end_div(); // .lp-podium-card
        
        echo html_writer::start_div('lp-podium-pedestal lp-pedestal-silver');
        echo html_writer::tag('span', '2', ['class' => 'lp-pedestal-num']);
        echo html_writer::end_div();
        echo html_writer::end_div(); // .lp-podium-column
    } else {
        echo html_writer::div('', 'lp-podium-placeholder');
    }

    // Rank 1 (Center Champion - Gold)
    if (isset($top3[0])) {
        $p1 = $top3[0];
        $is_me1 = ($p1->userid == $USER->id);
        echo html_writer::start_div('lp-podium-column lp-col-gold lp-col-champion' . ($is_me1 ? ' lp-col-is-me' : ''));
        echo html_writer::start_div('lp-podium-card lp-card-gold lp-card-champion');
        
        echo html_writer::div('👑', 'lp-floating-crown');
        echo html_writer::start_div('lp-podium-header-badge lp-badge-gold');
        echo html_writer::tag('span', '🥇', ['class' => 'lp-badge-icon']);
        echo html_writer::tag('span', local_learningplan_str('podium_1st', '1st Champion'), ['class' => 'lp-badge-text']);
        echo html_writer::end_div();

        echo html_writer::start_div('lp-avatar-wrapper lp-avatar-champion');
        echo $OUTPUT->user_picture($p1->user, ['size' => 84, 'class' => 'lp-podium-avatar']);
        echo html_writer::tag('span', '1', ['class' => 'lp-rank-circle lp-circle-gold']);
        echo html_writer::end_div();

        echo html_writer::tag('h4', format_string($p1->fullname), ['class' => 'lp-podium-name lp-champion-name font-weight-bold mt-2 mb-0', 'title' => $p1->fullname]);
        if ($is_me1) {
            echo html_writer::span('⭐ ' . local_learningplan_str('you', 'YOU'), 'badge badge-warning text-dark lp-you-badge mt-1');
        }

        echo html_writer::start_div('lp-podium-score-pill lp-pill-gold mt-2');
        echo html_writer::tag('i', '', ['class' => 'fa fa-bolt mr-1 text-warning']);
        echo html_writer::tag('strong', $p1->points . ' XP');
        echo html_writer::end_div();

        echo html_writer::start_div('lp-podium-stats-row mt-2');
        echo html_writer::start_div('lp-pstat-item');
        echo html_writer::tag('span', $p1->completedchapters . '/' . $p1->totalchapters, ['class' => 'lp-pstat-val']);
        echo html_writer::tag('span', 'Chapters', ['class' => 'lp-pstat-lbl']);
        echo html_writer::end_div();
        echo html_writer::div('', 'lp-pstat-sep');
        echo html_writer::start_div('lp-pstat-item');
        echo html_writer::tag('span', $p1->completedsteps . '/' . $p1->totalsteps, ['class' => 'lp-pstat-val']);
        echo html_writer::tag('span', $p1->percent . '% Steps', ['class' => 'lp-pstat-lbl']);
        echo html_writer::end_div();
        echo html_writer::end_div(); // .lp-podium-stats-row

        echo html_writer::end_div(); // .lp-podium-card
        
        echo html_writer::start_div('lp-podium-pedestal lp-pedestal-gold');
        echo html_writer::tag('span', '1', ['class' => 'lp-pedestal-num']);
        echo html_writer::end_div();
        echo html_writer::end_div(); // .lp-podium-column
    }

    // Rank 3 (Right - Bronze)
    if (isset($top3[2])) {
        $p3 = $top3[2];
        $is_me3 = ($p3->userid == $USER->id);
        echo html_writer::start_div('lp-podium-column lp-col-bronze' . ($is_me3 ? ' lp-col-is-me' : ''));
        echo html_writer::start_div('lp-podium-card lp-card-bronze');
        
        echo html_writer::start_div('lp-podium-header-badge lp-badge-bronze');
        echo html_writer::tag('span', '🥉', ['class' => 'lp-badge-icon']);
        echo html_writer::tag('span', local_learningplan_str('podium_3rd', '3rd Place'), ['class' => 'lp-badge-text']);
        echo html_writer::end_div();

        echo html_writer::start_div('lp-avatar-wrapper');
        echo $OUTPUT->user_picture($p3->user, ['size' => 64, 'class' => 'lp-podium-avatar']);
        echo html_writer::tag('span', '3', ['class' => 'lp-rank-circle lp-circle-bronze']);
        echo html_writer::end_div();

        echo html_writer::tag('h4', format_string($p3->fullname), ['class' => 'lp-podium-name font-weight-bold mt-2 mb-0', 'title' => $p3->fullname]);
        if ($is_me3) {
            echo html_writer::span('⭐ ' . local_learningplan_str('you', 'YOU'), 'badge badge-primary lp-you-badge mt-1');
        }

        echo html_writer::start_div('lp-podium-score-pill lp-pill-bronze mt-2');
        echo html_writer::tag('i', '', ['class' => 'fa fa-bolt mr-1 text-warning']);
        echo html_writer::tag('strong', $p3->points . ' XP');
        echo html_writer::end_div();

        echo html_writer::start_div('lp-podium-stats-row mt-2');
        echo html_writer::start_div('lp-pstat-item');
        echo html_writer::tag('span', $p3->completedchapters . '/' . $p3->totalchapters, ['class' => 'lp-pstat-val']);
        echo html_writer::tag('span', 'Chapters', ['class' => 'lp-pstat-lbl']);
        echo html_writer::end_div();
        echo html_writer::div('', 'lp-pstat-sep');
        echo html_writer::start_div('lp-pstat-item');
        echo html_writer::tag('span', $p3->completedsteps . '/' . $p3->totalsteps, ['class' => 'lp-pstat-val']);
        echo html_writer::tag('span', $p3->percent . '% Steps', ['class' => 'lp-pstat-lbl']);
        echo html_writer::end_div();
        echo html_writer::end_div(); // .lp-podium-stats-row

        echo html_writer::end_div(); // .lp-podium-card
        
        echo html_writer::start_div('lp-podium-pedestal lp-pedestal-bronze');
        echo html_writer::tag('span', '3', ['class' => 'lp-pedestal-num']);
        echo html_writer::end_div();
        echo html_writer::end_div(); // .lp-podium-column
    } else {
        echo html_writer::div('', 'lp-podium-placeholder');
    }

    echo html_writer::end_div(); // .lp-podium-grid
    echo html_writer::end_div(); // .lp-podium-stage
    echo html_writer::end_div(); // .lp-hall-of-fame-wrapper
}

// --------------------------------------------------------------------------
// LEADERBOARD TABLE (Full Standings)
// --------------------------------------------------------------------------
echo html_writer::start_div('card border shadow-sm lp-leaderboard-table-card mb-4');

// Card Header with Search / Filters.
echo html_writer::start_div('card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2');
echo html_writer::tag('h3', '📊 ' . local_learningplan_str('classstandings', 'Class Standings') . ' (' . count($leaderboard) . ' Learners)', ['class' => 'h6 font-weight-bold text-dark mb-0']);

// Search & Filter Form.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl, 'class' => 'form-inline d-flex flex-wrap align-items-center gap-2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'planid', 'value' => $planid]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'value' => $search,
    'placeholder' => local_learningplan_str('searchlearners', 'Search learners...'),
    'class' => 'form-control form-control-sm mr-2 mb-2 mb-md-0',
]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-sm btn-secondary mr-2 mb-2 mb-md-0']);
if (!empty($search)) {
    echo html_writer::link(
        new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $planid]),
        '<i class="fa fa-times mr-1"></i> ' . get_string('clear'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-2 mb-md-0']
    );
}
echo html_writer::end_tag('form');
echo html_writer::end_div(); // .card-header

echo html_writer::start_div('card-body p-0');

if (empty($leaderboard)) {
    echo html_writer::div(
        '<i class="fa fa-info-circle mr-1"></i> ' . local_learningplan_str('noleaderboardentries', 'No learners have scored points on this plan yet.'),
        'alert alert-light border text-muted small p-4 text-center m-3'
    );
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table table-hover table-striped mb-0 lp-leaderboard-table';
    $table->head = [
        local_learningplan_str('rank', 'Rank'),
        local_learningplan_str('learner', 'Learner'),
        local_learningplan_str('score', 'Score (XP)'),
        local_learningplan_str('completedsteps', 'Completed Steps'),
        local_learningplan_str('completedchapters', 'Completed Chapters'),
        local_learningplan_str('currentchapter', 'Current Chapter Milestone'),
        local_learningplan_str('stars', 'Stars'),
        local_learningplan_str('badges', 'Badges'),
    ];

    foreach ($leaderboard as $row) {
        $is_me = ($row->userid == $USER->id);
        $rankbadge = '';
        if ($row->rank === 1) {
            $rankbadge = '<span class="lp-rank-badge lp-rank-1">🥇 #1</span>';
        } else if ($row->rank === 2) {
            $rankbadge = '<span class="lp-rank-badge lp-rank-2">🥈 #2</span>';
        } else if ($row->rank === 3) {
            $rankbadge = '<span class="lp-rank-badge lp-rank-3">🥉 #3</span>';
        } else {
            $rankbadge = '<span class="lp-rank-badge lp-rank-other">#' . $row->rank . '</span>';
        }

        $usercell = html_writer::start_div('d-flex align-items-center');
        $usercell .= $OUTPUT->user_picture($row->user, ['size' => 36, 'class' => 'mr-2 rounded-circle']);
        $usercell .= html_writer::start_div();
        $usercell .= html_writer::tag('span', format_string($row->fullname), ['class' => 'font-weight-bold text-dark d-block']);
        if ($isadmin) {
            $usercell .= html_writer::tag('span', s($row->email), ['class' => 'text-muted small d-block']);
        }
        if ($is_me) {
            $usercell .= html_writer::span('⭐ ' . local_learningplan_str('you', 'YOU'), 'badge badge-primary mt-1');
        }
        $usercell .= html_writer::end_div();
        $usercell .= html_writer::end_div();

        $pointscell = '<span class="badge badge-warning text-dark px-2 py-1 font-weight-bold" style="font-size: 0.9rem;"><i class="fa fa-bolt mr-1"></i>' . $row->points . ' XP</span>';

        $stepscell = html_writer::start_div();
        $stepscell .= html_writer::tag('span', $row->completedsteps . ' / ' . $row->totalsteps . ' Steps', ['class' => 'font-weight-bold small d-block']);
        $stepscell .= html_writer::start_div('progress mt-1', ['style' => 'height: 6px; width: 100px;']);
        $stepscell .= html_writer::div('', 'progress-bar bg-success', ['style' => 'width: ' . $row->percent . '%;']);
        $stepscell .= html_writer::end_div();
        $stepscell .= html_writer::end_div();

        $chapterscell = '<span class="badge badge-light border font-weight-bold">' . $row->completedchapters . ' / ' . $row->totalchapters . '</span>';

        $currtitle = $row->currentchapter->title ?? get_string('none');
        $currcell = html_writer::start_div();
        $currcell .= html_writer::tag('span', format_string($currtitle), ['class' => 'font-weight-bold small text-dark d-block', 'style' => 'max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;', 'title' => $currtitle]);
        if (!empty($row->currentchapter->steps_total)) {
            $currcell .= html_writer::tag('span', $row->currentchapter->steps_completed . ' of ' . $row->currentchapter->steps_total . ' steps', ['class' => 'text-muted small']);
        }
        $currcell .= html_writer::end_div();

        $starscell = '<span class="badge badge-light border text-warning font-weight-bold"><i class="fa fa-star text-warning mr-1"></i>' . $row->stars . '</span>';

        $earnedbadges = $DB->count_records_sql(
            "SELECT COUNT(bi.id)
               FROM {badge_issued} bi
               JOIN {local_learningplan_badge} lb ON lb.badgeid = bi.badgeid
              WHERE bi.userid = :uid AND lb.planid = :pid",
            ['uid' => $row->userid, 'pid' => $activeplan->id]
        );
        $badgescell = '<span class="badge badge-light border text-warning font-weight-bold"><i class="fa fa-trophy text-warning mr-1"></i>' . $earnedbadges . '</span>';

        $tablerow = new html_table_row([
            $rankbadge,
            $usercell,
            $pointscell,
            $stepscell,
            $chapterscell,
            $currcell,
            $starscell,
            $badgescell,
        ]);

        if ($is_me) {
            $tablerow->attributes['class'] = 'lp-leaderboard-myrow font-weight-bold';
        }

        $table->data[] = $tablerow;
    }

    echo html_writer::table($table);
}

echo html_writer::end_div(); // .card-body
echo html_writer::end_div(); // .card

echo html_writer::end_div(); // .lp-wrap

echo $OUTPUT->footer();
