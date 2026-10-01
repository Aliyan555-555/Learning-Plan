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
 * Filterable progress report across all plans/learners.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:viewreports', $context);

global $DB;

$filterplan = optional_param('planid', 0, PARAM_INT);
$filterstatus = optional_param('status', '', PARAM_ALPHA);
$export = optional_param('export', 0, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$plans = api::get_plans();

/**
 * Neutralise a value that a spreadsheet could interpret as a formula before it is
 * written to a CSV cell.
 *
 * @param string $value
 * @return string
 */
$csvsafe = function($value) {
    $value = (string)$value;
    if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
        return "'" . $value;
    }
    return $value;
};

// Build the row set with a bounded number of queries: for each in-scope plan we
// read the whole-plan step/star totals once and every learner's aggregated
// progress in a single grouped query, rather than one query per (plan, learner).
$rows = [];
foreach ($plans as $plan) {
    if ($filterplan && $plan->id != $filterplan) {
        continue;
    }

    $userids = api::get_plan_userids($plan->id);
    if (!$userids) {
        continue;
    }

    $totalsteps = (int)$DB->count_records('local_learningplan_step', ['planid' => $plan->id]);
    $maxstars = (int)$DB->get_field_sql(
        "SELECT COALESCE(SUM(maxstars), 0) FROM {local_learningplan_step} WHERE planid = :planid",
        ['planid' => $plan->id]
    );

    $aggrows = $DB->get_records_sql(
        "SELECT pr.userid,
                COALESCE(SUM(CASE WHEN pr.status = 'completed' THEN 1 ELSE 0 END), 0) AS completedsteps,
                COALESCE(SUM(pr.pointsawarded), 0) AS points,
                COALESCE(SUM(pr.starsearned), 0) AS stars
           FROM {local_learningplan_progress} pr
           JOIN {local_learningplan_step} s ON s.id = pr.stepid
          WHERE s.planid = :planid
       GROUP BY pr.userid",
        ['planid' => $plan->id]
    );

    $planbadgecount = (int)$DB->count_records('local_learningplan_badge', ['planid' => $plan->id]);

    foreach ($userids as $userid) {
        $userid = (int)$userid;
        $user = \core_user::get_user($userid, 'id, deleted, firstname, lastname, firstnamephonetic, '
            . 'lastnamephonetic, middlename, alternatename, email');
        if (!$user || $user->deleted) {
            continue;
        }

        $agg = $aggrows[$userid] ?? (object)['completedsteps' => 0, 'points' => 0, 'stars' => 0];
        $completed = (int)$agg->completedsteps;
        $percent = $totalsteps > 0 ? (int)round(($completed / $totalsteps) * 100) : 0;
        $status = ($totalsteps > 0 && $completed >= $totalsteps)
            ? 'completed'
            : ($completed > 0 ? 'inprogress' : 'notstarted');

        if ($filterstatus && $filterstatus !== $status) {
            continue;
        }

        $rows[] = (object)[
            'plan' => $plan->name,
            'planid' => (int)$plan->id,
            'learner' => fullname($user),
            'userid' => $userid,
            'status' => $status,
            'percent' => $percent,
            'points' => (int)$agg->points,
            'stars' => (int)$agg->stars,
            'maxstars' => $maxstars,
            'planbadgecount' => $planbadgecount,
        ];
    }
}

// Stable ordering so pagination is deterministic.
usort($rows, function($a, $b) {
    return [$a->plan, $a->learner, $a->userid] <=> [$b->plan, $b->learner, $b->userid];
});

$totalrows = count($rows);

if ($export) {
    require_sesskey();
    $filename = 'learningplan_report_' . userdate(time(), '%Y%m%d_%H%M%S') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Plan', 'Learner', 'Status', 'Completion %', 'Points', 'Stars', 'Badges']);
    foreach ($rows as $row) {
        $earnedbadges = $DB->count_records_sql(
            "SELECT COUNT(bi.id)
               FROM {badge_issued} bi
               JOIN {local_learningplan_badge} lb ON lb.badgeid = bi.badgeid
              WHERE bi.userid = :uid AND lb.planid = :pid",
            ['uid' => $row->userid, 'pid' => $row->planid]
        );
        fputcsv($out, array_map($csvsafe, [
            $row->plan,
            $row->learner,
            $row->status,
            $row->percent,
            $row->points,
            $row->stars . '/' . $row->maxstars,
            $earnedbadges . '/' . $row->planbadgecount,
        ]));
    }
    fclose($out);
    exit;
}

$rows = array_slice($rows, $page * $perpage, $perpage);

$pageurl = new moodle_url('/local/learningplan/manage/report.php', array_filter(['planid' => $filterplan, 'status' => $filterstatus]));
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('reports', 'local_learningplan'));
$PAGE->set_heading(get_string('reports', 'local_learningplan'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

echo $OUTPUT->header();

if ($filterplan) {
    echo navigation::render_plan_context_header($filterplan, 'report');
} else {
    echo navigation::render_global_header('report');
}

// Filter Card.
echo html_writer::start_div('card border shadow-sm mb-4');
echo html_writer::start_div('card-body py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl, 'class' => 'form-inline d-flex flex-wrap align-items-center gap-2']);
$planoptions = [0 => get_string('filterbyplan', 'local_learningplan')];
foreach ($plans as $plan) {
    $planoptions[$plan->id] = format_string($plan->name);
}
echo html_writer::select($planoptions, 'planid', $filterplan, false, ['class' => 'custom-select mr-2 mb-2 mb-md-0', 'onchange' => 'this.form.submit()']);

$statusoptions = [
    '' => get_string('filterbystatus', 'local_learningplan'),
    'notstarted' => get_string('locked', 'local_learningplan'),
    'inprogress' => get_string('inprogress', 'local_learningplan'),
    'completed' => get_string('completed', 'local_learningplan'),
];
echo html_writer::select($statusoptions, 'status', $filterstatus, false, ['class' => 'custom-select mr-2 mb-2 mb-md-0', 'onchange' => 'this.form.submit()']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary mr-2 mb-2 mb-md-0']);

if ($filterplan || $filterstatus) {
    echo html_writer::link(
        new moodle_url('/local/learningplan/manage/report.php'),
        '<i class="fa fa-times mr-1"></i> ' . get_string('clear'),
        ['class' => 'btn btn-outline-secondary mb-2 mb-md-0']
    );
}
echo html_writer::end_tag('form');

echo html_writer::start_div('d-flex align-items-center gap-2');
echo html_writer::link(
    new moodle_url('/local/learningplan/dashboard.php', $filterplan ? ['planid' => $filterplan] : []),
    '<i class="fa fa-pie-chart mr-1"></i> ' . local_learningplan_str('switchtovisualdashboard', 'Visual Status Dashboard'),
    ['class' => 'btn btn-primary mr-2']
);
echo html_writer::link(
    new moodle_url($pageurl, ['export' => 1, 'sesskey' => sesskey()]),
    '<i class="fa fa-download mr-1"></i> ' . get_string('export', 'local_learningplan'),
    ['class' => 'btn btn-outline-success']
);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('p',
    get_string('recordcount', 'local_learningplan', $totalrows),
    ['class' => 'text-muted small mb-2']
);

if (!$rows) {
    echo html_writer::tag('div', '<i class="fa fa-info-circle mr-1"></i> ' . get_string('norecordsfound', 'local_learningplan'), ['class' => 'alert alert-light border text-muted small p-4 text-center my-3']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('plan', 'local_learningplan'),
        get_string('learner', 'local_learningplan'),
        get_string('status', 'local_learningplan'),
        get_string('completionpercent', 'local_learningplan'),
        get_string('points', 'local_learningplan'),
        get_string('stars', 'local_learningplan'),
        get_string('badges', 'local_learningplan'),
    ];
    $table->attributes['class'] = 'generaltable table-hover align-middle shadow-sm border bg-white';

    foreach ($rows as $row) {
        $statusbadge = '';
        if ($row->status === 'completed') {
            $statusbadge = '<span class="badge badge-success"><i class="fa fa-check-circle mr-1"></i>' . get_string('completed', 'local_learningplan') . '</span>';
        } else if ($row->status === 'inprogress') {
            $statusbadge = '<span class="badge badge-primary"><i class="fa fa-spinner fa-spin mr-1"></i>' . get_string('inprogress', 'local_learningplan') . '</span>';
        } else {
            $statusbadge = '<span class="badge badge-secondary"><i class="fa fa-lock mr-1"></i>' . get_string('locked', 'local_learningplan') . '</span>';
        }

        $progresscell = html_writer::start_div('d-flex align-items-center gap-2');
        $progresscell .= html_writer::tag('span', $row->percent . '%', ['class' => 'small font-weight-bold mr-2', 'style' => 'min-width: 35px;']);
        $progresscell .= html_writer::start_div('progress flex-grow-1', ['style' => 'height: 6px; min-width: 80px;']);
        $progresscell .= html_writer::tag('div', '', [
            'class' => 'progress-bar ' . ($row->percent >= 100 ? 'bg-success' : 'bg-primary'),
            'style' => 'width: ' . min(100, max(0, $row->percent)) . '%;',
        ]);
        $progresscell .= html_writer::end_div();
        $progresscell .= html_writer::end_div();

        $earnedbadges = $DB->count_records_sql(
            "SELECT COUNT(bi.id)
               FROM {badge_issued} bi
               JOIN {local_learningplan_badge} lb ON lb.badgeid = bi.badgeid
              WHERE bi.userid = :uid AND lb.planid = :pid",
            ['uid' => $row->userid, 'pid' => $row->planid]
        );
        $badgescell = '<span class="badge badge-pill badge-light border px-2 py-1 font-weight-bold ' . ($earnedbadges > 0 ? 'text-warning' : 'text-muted') . '"><i class="fa fa-trophy mr-1 text-warning"></i>' . $earnedbadges . ' / ' . (int)$row->planbadgecount . '</span>';

        $table->data[] = [
            html_writer::link(new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $row->planid]), format_string($row->plan), ['class' => 'font-weight-bold']),
            s($row->learner),
            $statusbadge,
            $progresscell,
            '<span class="font-weight-bold text-primary"><i class="fa fa-bolt mr-1"></i>' . $row->points . '</span>',
            '<span class="badge badge-warning text-dark"><i class="fa fa-star mr-1"></i>' . $row->stars . ' / ' . $row->maxstars . '</span>',
            $badgescell,
        ];
    }

    echo html_writer::table($table);

    echo $OUTPUT->paging_bar($totalrows, $page, $perpage, $pageurl);
}

echo $OUTPUT->footer();
