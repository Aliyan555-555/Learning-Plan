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
 * Executive Management Information System (MIS) Report Page.
 *
 * Provides a high-fidelity interactive data table with live enterprise filtering,
 * KPI overview, rich formatted metrics with icons, and instant template-aligned Excel downloads.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_learningplan\api;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();

$canmanage = has_capability('local/learningplan:manage', $context);
$canreport = has_capability('local/learningplan:viewreports', $context);

if (!$canmanage && !$canreport) {
    require_capability('local/learningplan:view', $context);
}

global $USER, $DB, $PAGE, $OUTPUT, $SITE;

// Filters.
$planid = optional_param('planid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW);
$status = optional_param('status', '', PARAM_ALPHA);
$active21 = optional_param('active21', '', PARAM_ALPHA);
$department = optional_param('department', '', PARAM_RAW);
$days = optional_param('days', 21, PARAM_INT);
$exportmis = optional_param('exportmis', 0, PARAM_BOOL);

$filters = [
    'search' => trim($search),
    'status' => trim($status),
    'active21' => trim($active21),
    'department' => trim($department),
];

// Excel Export Handler (.xlsx).
if ($exportmis) {
    require_sesskey();
    api::export_mis_report($planid, $days, $filters);
    exit;
}

// Page setup.
$pageurl = new moodle_url('/local/learningplan/mis_report.php', array_filter([
    'planid' => $planid,
    'search' => $search,
    'status' => $status,
    'active21' => $active21,
    'department' => $department,
    'days' => $days,
]));

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(local_learningplan_str('misreport', 'Learning Plan MIS Report'));
$PAGE->set_heading(local_learningplan_str('misreport', 'Learning Plan MIS Report'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

// Fetch plans for dropdown.
$allplans = api::get_plans();

// Fetch distinct departments for dropdown.
$departments = $DB->get_fieldset_sql("
    SELECT DISTINCT department 
      FROM {user} 
     WHERE deleted = 0 AND department IS NOT NULL AND department <> '' 
  ORDER BY department ASC
");

// Fetch compiled MIS data records.
$records = api::get_mis_report_data($planid, $days, $filters);
$totalrecords = count($records);

// Compute summary KPI metrics from current record set.
$completedcount = 0;
$inprogresscount = 0;
$notstartedcount = 0;
$active21count = 0;
$totalpoints = 0;
$totalstars = 0;
$totalbadges = 0;
$uniquelearners = [];

foreach ($records as $r) {
    $uniquelearners[$r->userid] = true;
    if ($r->userstatus === 'Completed') {
        $completedcount++;
    } else if ($r->userstatus === 'In-Progress') {
        $inprogresscount++;
    } else {
        $notstartedcount++;
    }
    if ($r->activelast21days === 'Yes') {
        $active21count++;
    }
    $totalpoints += $r->points;
    $totalstars += $r->stars;
    $totalbadges += $r->badgescount;
}

$learnercount = count($uniquelearners);
$completedpct = $totalrecords > 0 ? round(($completedcount / $totalrecords) * 100, 1) : 0;
$inprogresspct = $totalrecords > 0 ? round(($inprogresscount / $totalrecords) * 100, 1) : 0;
$active21pct = $totalrecords > 0 ? round(($active21count / $totalrecords) * 100, 1) : 0;

echo $OUTPUT->header();

// Global Navigation Header.
if ($planid) {
    echo navigation::render_plan_context_header($planid, 'report');
} else {
    echo navigation::render_global_header('report');
}
?>

<div class="lp-mis-wrap" id="lpMisApp">

    <!-- ==================================================================== -->
    <!-- 1. SLEEK EXECUTIVE HERO HEADER                                       -->
    <!-- ==================================================================== -->
    <header class="lp-mis-hero">
        <div class="lp-hero-main">
            <div class="lp-hero-badge-wrap">
                <div class="lp-mis-hero-icon">
                    <i class="fa fa-table"></i>
                </div>
                <div class="lp-hero-headings">
                    <div class="lp-hero-pretitle">
                        <span class="lp-pulse-dot"></span>
                        <span class="lp-hero-mode-text">Enterprise Analytics Hub</span>
                        <span class="lp-hero-divider">&bull;</span>
                        <span class="lp-hero-sitename"><?php echo format_string($SITE->fullname); ?></span>
                    </div>
                    <h1 class="lp-hero-title">Management Information System (MIS) Report</h1>
                    <p class="lp-hero-desc">
                        Comprehensive multi-dimensional intelligence across Learning Plans, Learner Progression Velocity, and Enterprise Profile Attributes.
                    </p>
                </div>
            </div>
        </div>

        <div class="lp-hero-controls">
            <!-- Download & Navigation Actions -->
            <div class="lp-hero-actions">
                <a href="<?php echo new moodle_url($pageurl, ['exportmis' => 1, 'sesskey' => sesskey()]); ?>" 
                   class="lp-btn-hero-action lp-btn-mis-download-primary" 
                   title="Download current filtered MIS report as Excel .xlsx">
                    <i class="fa fa-file-excel-o mr-1"></i>
                    <span>Download MIS Report (.xlsx)</span>
                </a>

                <?php if ($planid > 0): ?>
                    <a href="<?php echo new moodle_url('/local/learningplan/mis_report.php', ['exportmis' => 1, 'sesskey' => sesskey()]); ?>" 
                       class="lp-btn-hero-action" 
                       title="Download full MIS report across all plans">
                        <i class="fa fa-download mr-1"></i><span>Download All Plans</span>
                    </a>
                <?php endif; ?>

                <a href="<?php echo new moodle_url('/local/learningplan/dashboard.php', $planid ? ['planid' => $planid] : []); ?>" 
                   class="lp-btn-hero-action" 
                   title="Return to Visual Dashboard">
                    <i class="fa fa-tachometer mr-1"></i><span>Dashboard</span>
                </a>

                <button type="button" class="lp-btn-hero-action" onclick="window.print()" title="Print MIS Report">
                    <i class="fa fa-print mr-1"></i><span>Print</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ==================================================================== -->
    <!-- 2. EXECUTIVE SUMMARY KPI CARDS                                       -->
    <!-- ==================================================================== -->
    <div class="lp-mis-kpi-grid">
        <div class="lp-mis-kpi-card">
            <div class="lp-mis-kpi-icon-wrap bg-blue-subtle text-blue">
                <i class="fa fa-id-card-o"></i>
            </div>
            <div class="lp-mis-kpi-data">
                <span class="lp-mis-kpi-lbl">Total Assignments</span>
                <div class="lp-mis-kpi-val"><?php echo number_format($totalrecords); ?></div>
                <span class="lp-mis-kpi-sub"><?php echo number_format($learnercount); ?> Unique Learners</span>
            </div>
        </div>

        <div class="lp-mis-kpi-card">
            <div class="lp-mis-kpi-icon-wrap bg-green-subtle text-success">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="lp-mis-kpi-data">
                <span class="lp-mis-kpi-lbl">Completed Plans</span>
                <div class="lp-mis-kpi-val text-success"><?php echo number_format($completedcount); ?></div>
                <span class="lp-mis-kpi-sub"><?php echo $completedpct; ?>% completion rate</span>
            </div>
        </div>

        <div class="lp-mis-kpi-card">
            <div class="lp-mis-kpi-icon-wrap bg-amber-subtle text-warning">
                <i class="fa fa-hourglass-half"></i>
            </div>
            <div class="lp-mis-kpi-data">
                <span class="lp-mis-kpi-lbl">In Progress</span>
                <div class="lp-mis-kpi-val text-warning"><?php echo number_format($inprogresscount); ?></div>
                <span class="lp-mis-kpi-sub"><?php echo $inprogresspct; ?>% in progression</span>
            </div>
        </div>

        <div class="lp-mis-kpi-card">
            <div class="lp-mis-kpi-icon-wrap bg-emerald-subtle text-emerald">
                <i class="fa fa-bolt"></i>
            </div>
            <div class="lp-mis-kpi-data">
                <span class="lp-mis-kpi-lbl">Active in 21 Days</span>
                <div class="lp-mis-kpi-val text-emerald"><?php echo number_format($active21count); ?></div>
                <span class="lp-mis-kpi-sub"><?php echo $active21pct; ?>% active velocity</span>
            </div>
        </div>

        <div class="lp-mis-kpi-card">
            <div class="lp-mis-kpi-icon-wrap bg-purple-subtle text-purple">
                <i class="fa fa-trophy"></i>
            </div>
            <div class="lp-mis-kpi-data">
                <span class="lp-mis-kpi-lbl">Gamification XP</span>
                <div class="lp-mis-kpi-val text-purple"><?php echo number_format($totalpoints); ?> XP</div>
                <span class="lp-mis-kpi-sub"><?php echo number_format($totalstars); ?> Stars &bull; <?php echo number_format($totalbadges); ?> Badges</span>
            </div>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- 3. ADVANCED FILTERING TOOLBAR                                        -->
    <!-- ==================================================================== -->
    <div class="lp-mis-filter-card">
        <form method="get" action="<?php echo new moodle_url('/local/learningplan/mis_report.php'); ?>" class="lp-mis-filter-form" id="misFilterForm">
            <div class="lp-filter-fields-wrap">
                <!-- 1. Learning Plan Filter -->
                <div class="lp-filter-item">
                    <label for="filter_planid" class="lp-filter-lbl"><i class="fa fa-map mr-1"></i>Learning Plan</label>
                    <select name="planid" id="filter_planid" class="form-control lp-filter-select" onchange="this.form.submit()">
                        <option value="0">All Learning Plans (<?php echo count($allplans); ?>)</option>
                        <?php foreach ($allplans as $p): ?>
                            <option value="<?php echo $p->id; ?>" <?php echo ($planid == $p->id) ? 'selected' : ''; ?>>
                                <?php echo s($p->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Learner Search Keyword -->
                <div class="lp-filter-item lp-filter-grow">
                    <label for="filter_search" class="lp-filter-lbl"><i class="fa fa-search mr-1"></i>Search Learner / ID / Email</label>
                    <div class="lp-search-input-wrap">
                        <i class="fa fa-search lp-search-icon"></i>
                        <input type="text" name="search" id="filter_search" 
                               class="form-control lp-filter-input" 
                               placeholder="Type name, email, employee code..." 
                               value="<?php echo s($search); ?>" 
                               autocomplete="off">
                        <?php if ($search !== ''): ?>
                            <button type="button" class="lp-search-clear" onclick="document.getElementById('filter_search').value=''; document.getElementById('misFilterForm').submit();" title="Clear search">
                                <i class="fa fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. User Plan Status Filter -->
                <div class="lp-filter-item">
                    <label for="filter_status" class="lp-filter-lbl"><i class="fa fa-flag mr-1"></i>Learner Status</label>
                    <select name="status" id="filter_status" class="form-control lp-filter-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo ($status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="inprogress" <?php echo ($status === 'inprogress') ? 'selected' : ''; ?>>In-Progress</option>
                        <option value="notstarted" <?php echo ($status === 'notstarted') ? 'selected' : ''; ?>>Not Started</option>
                    </select>
                </div>

                <!-- 4. Active in last 21 days -->
                <div class="lp-filter-item">
                    <label for="filter_active21" class="lp-filter-lbl"><i class="fa fa-bolt mr-1"></i>Active (21 Days)</label>
                    <select name="active21" id="filter_active21" class="form-control lp-filter-select" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="yes" <?php echo (strtolower($active21) === 'yes') ? 'selected' : ''; ?>>Active (Yes)</option>
                        <option value="no" <?php echo (strtolower($active21) === 'no') ? 'selected' : ''; ?>>Inactive (No)</option>
                    </select>
                </div>

                <!-- 5. Department Filter -->
                <?php if (!empty($departments)): ?>
                    <div class="lp-filter-item">
                        <label for="filter_department" class="lp-filter-lbl"><i class="fa fa-building-o mr-1"></i>Department</label>
                        <select name="department" id="filter_department" class="form-control lp-filter-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo s($dept); ?>" <?php echo ($department === $dept) ? 'selected' : ''; ?>>
                                    <?php echo s($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Buttons -->
                <div class="lp-filter-actions-group">
                    <button type="submit" class="btn btn-primary lp-btn-apply-filters">
                        <i class="fa fa-filter mr-1"></i><span>Filter</span>
                    </button>
                    <?php if ($planid || $search !== '' || $status !== '' || $active21 !== '' || $department !== ''): ?>
                        <a href="<?php echo new moodle_url('/local/learningplan/mis_report.php'); ?>" class="btn btn-outline-secondary lp-btn-reset-filters" title="Reset all filters">
                            <i class="fa fa-refresh mr-1"></i><span>Reset</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="lp-mis-filter-meta">
            <span class="lp-mis-count-badge">
                <i class="fa fa-database mr-1"></i>Showing <strong id="misVisibleCount"><?php echo number_format($totalrecords); ?></strong> Records
            </span>
            <span class="lp-mis-template-pill">
                <i class="fa fa-check-circle text-success mr-1"></i>Template: <strong>MIS Report for Learning Plan.xlsx</strong>
            </span>
        </div>
    </div>

    <!-- ==================================================================== -->
    <!-- 4. ENHANCED EXECUTIVE MIS DATA TABLE                                 -->
    <!-- ==================================================================== -->
    <div class="lp-mis-table-card">
        <div class="table-responsive lp-mis-table-responsive">
            <table class="table table-hover lp-mis-table" id="misDataTable">
                <thead>
                    <!-- Top Category Header Band (Exact Match to Excel Categories) -->
                    <tr class="lp-mis-category-row">
                        <th colspan="2" class="lp-mis-cat-empty"></th>
                        <th colspan="5" class="lp-mis-cat-header lp-cat-plan">
                            <i class="fa fa-map-o mr-1"></i>Learning Plan Attributes
                        </th>
                        <th colspan="5" class="lp-mis-cat-header lp-cat-user-stats">
                            <i class="fa fa-bar-chart mr-1"></i>Learning Plan Attributes with respect to User
                        </th>
                        <th colspan="8" class="lp-mis-cat-header lp-cat-profile">
                            <i class="fa fa-user-circle-o mr-1"></i>User Profile Attributes
                        </th>
                    </tr>

                    <!-- Exact 21 Columns from Template -->
                    <tr class="lp-mis-columns-row">
                        <!-- Plan & Learner Identifiers -->
                        <th class="lp-th-col" data-col="plan">Learning Plan</th>
                        <th class="lp-th-col" data-col="learner">Learner</th>

                        <!-- Learning Plan Attributes -->
                        <th class="lp-th-col" data-col="chapters">No. of Chapters</th>
                        <th class="lp-th-col" data-col="steps">No. of Steps</th>
                        <th class="lp-th-col" data-col="planstatus">Plan Status</th>
                        <th class="lp-th-col" data-col="modes">Mode of Trainings</th>
                        <th class="lp-th-col" data-col="dates">Start - End Date</th>

                        <!-- User Specific Plan Stats -->
                        <th class="lp-th-col" data-col="active21">Active (21 Days)</th>
                        <th class="lp-th-col" data-col="userstatus">Learner Status</th>
                        <th class="lp-th-col" data-col="completion">Completion %</th>
                        <th class="lp-th-col" data-col="points">Points</th>
                        <th class="lp-th-col" data-col="stars">Stars</th>
                        <th class="lp-th-col" data-col="badges">Badges</th>

                        <!-- User Profile Attributes -->
                        <th class="lp-th-col" data-col="dept">Department</th>
                        <th class="lp-th-col" data-col="position">Job Position</th>
                        <th class="lp-th-col" data-col="region">Region</th>
                        <th class="lp-th-col" data-col="city">City</th>
                        <th class="lp-th-col" data-col="branch">Branch</th>
                        <th class="lp-th-col" data-col="lob">Line of Business</th>
                        <th class="lp-th-col" data-col="empcode">Employee Code</th>
                        <th class="lp-th-col" data-col="email">Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="21" class="text-center py-5 text-muted">
                                <div class="lp-empty-state-wrap">
                                    <div class="lp-empty-icon mb-2"><i class="fa fa-folder-open-o fa-3x text-muted opacity-50"></i></div>
                                    <h5 class="font-weight-bold">No MIS Records Found</h5>
                                    <p class="text-muted small">No learning plan assignments match your active filter criteria.</p>
                                    <a href="<?php echo new moodle_url('/local/learningplan/mis_report.php'); ?>" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="fa fa-refresh mr-1"></i>Reset Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr class="lp-mis-row" data-search="<?php echo s(strtolower($r->fullname . ' ' . $r->email . ' ' . $r->planname . ' ' . $r->employeecode . ' ' . $r->department)); ?>">
                                <!-- 1. Learning Plan -->
                                <td class="lp-td-planname">
                                    <div class="d-flex align-items-center">
                                        <span class="lp-plan-indicator mr-2" style="background: <?php echo $r->isplanactive ? '#10b981' : '#94a3b8'; ?>;"></span>
                                        <div>
                                            <a href="<?php echo new moodle_url('/local/learningplan/index.php', ['id' => $r->planid]); ?>" class="lp-plan-link font-weight-bold text-dark" title="View Journey Map">
                                                <?php echo s($r->planname); ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Learner -->
                                <td class="lp-td-learner">
                                    <div class="d-flex align-items-center">
                                        <div class="lp-user-avatar-circle mr-2">
                                            <?php 
                                                $initials = mb_substr($r->user->firstname ?? 'U', 0, 1) . mb_substr($r->user->lastname ?? '', 0, 1);
                                                echo strtoupper($initials);
                                            ?>
                                        </div>
                                        <div class="lp-user-name-box">
                                            <span class="lp-user-fullname font-weight-bold text-dark"><?php echo s($r->fullname); ?></span>
                                            <span class="lp-user-username text-muted small d-block"><?php echo s($r->user->username); ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- 3. Chapters -->
                                <td class="lp-td-chapters">
                                    <span class="lp-pill lp-pill-neutral" title="<?php echo s($r->chapters_str); ?>">
                                        <i class="fa fa-bookmark text-indigo mr-1"></i>
                                        <?php echo s($r->chapters_str); ?>
                                    </span>
                                </td>

                                <!-- 4. Steps -->
                                <td class="lp-td-steps">
                                    <span class="lp-pill lp-pill-neutral" title="<?php echo s($r->steps_str); ?>">
                                        <i class="fa fa-list-ol text-cyan mr-1"></i>
                                        <?php echo s($r->steps_str); ?>
                                    </span>
                                </td>

                                <!-- 5. Plan Status -->
                                <td class="lp-td-planstatus">
                                    <?php if ($r->planstatus === 'Active'): ?>
                                        <span class="badge badge-success px-2 py-1"><i class="fa fa-check mr-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-2 py-1"><i class="fa fa-pause mr-1"></i>In-Active</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 6. Mode of Trainings -->
                                <td class="lp-td-modes">
                                    <span class="lp-modes-pill" title="<?php echo s($r->trainingmodes); ?>">
                                        <i class="fa fa-laptop text-primary mr-1"></i>
                                        <?php echo s($r->trainingmodes); ?>
                                    </span>
                                </td>

                                <!-- 7. Start - End Date -->
                                <td class="lp-td-dates">
                                    <span class="lp-dates-text text-muted small font-weight-bold">
                                        <i class="fa fa-calendar-o mr-1 text-slate"></i><?php echo s($r->startenddate); ?>
                                    </span>
                                </td>

                                <!-- 8. Active till last 21 days -->
                                <td class="lp-td-active21 text-center">
                                    <?php if ($r->activelast21days === 'Yes'): ?>
                                        <span class="badge badge-pill badge-success px-2 py-1 lp-badge-active-glow">
                                            <i class="fa fa-bolt mr-1"></i>Yes
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-pill badge-light border text-muted px-2 py-1">
                                            No
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 9. Learner Status -->
                                <td class="lp-td-userstatus">
                                    <?php if ($r->userstatus === 'Completed'): ?>
                                        <span class="lp-status-badge lp-status-completed">
                                            <i class="fa fa-check-circle mr-1"></i>Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="lp-status-badge <?php echo ($r->userstatus === 'In-Progress') ? 'lp-status-inprogress' : 'lp-status-notstarted'; ?>">
                                            <i class="fa <?php echo ($r->userstatus === 'In-Progress') ? 'fa-hourglass-half' : 'fa-circle-o'; ?> mr-1"></i>
                                            <?php echo s($r->userstatus); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 10. Completion % -->
                                <td class="lp-td-completion">
                                    <div class="d-flex align-items-center">
                                        <div class="progress mr-2 lp-mini-progress">
                                            <div class="progress-bar <?php echo ($r->completionpct >= 100) ? 'bg-success' : 'bg-primary'; ?>" 
                                                 style="width: <?php echo $r->completionpct; ?>%;"></div>
                                        </div>
                                        <span class="font-weight-bold small <?php echo ($r->completionpct >= 100) ? 'text-success' : 'text-dark'; ?>">
                                            <?php echo $r->completionpct; ?>%
                                        </span>
                                    </div>
                                </td>

                                <!-- 11. Points -->
                                <td class="lp-td-points text-center">
                                    <span class="lp-xp-pill">
                                        <i class="fa fa-diamond text-primary mr-1"></i><?php echo number_format($r->points); ?>
                                    </span>
                                </td>

                                <!-- 12. Stars -->
                                <td class="lp-td-stars text-center">
                                    <span class="lp-stars-pill text-warning font-weight-bold">
                                        <i class="fa fa-star text-warning mr-1"></i><?php echo number_format($r->stars); ?>
                                    </span>
                                </td>

                                <!-- 13. Badges -->
                                <td class="lp-td-badges text-center">
                                    <span class="lp-badge-count-pill font-weight-bold <?php echo ($r->badgescount > 0) ? 'text-purple' : 'text-muted'; ?>">
                                        <i class="fa fa-trophy mr-1"></i><?php echo number_format($r->badgescount); ?>
                                    </span>
                                </td>

                                <!-- 14. Department -->
                                <td class="lp-td-dept">
                                    <?php if (!empty($r->department)): ?>
                                        <span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
                                            <i class="fa fa-building-o text-muted mr-1"></i><?php echo s($r->department); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 15. Job Position -->
                                <td class="lp-td-pos">
                                    <?php if (!empty($r->jobposition)): ?>
                                        <span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
                                            <i class="fa fa-id-badge text-muted mr-1"></i><?php echo s($r->jobposition); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 16. Region -->
                                <td class="lp-td-region">
                                    <?php echo !empty($r->region) ? s($r->region) : '<span class="text-muted small">&mdash;</span>'; ?>
                                </td>

                                <!-- 17. City -->
                                <td class="lp-td-city">
                                    <?php if (!empty($r->city)): ?>
                                        <span class="text-dark small"><i class="fa fa-map-marker text-danger mr-1"></i><?php echo s($r->city); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 18. Branch -->
                                <td class="lp-td-branch">
                                    <?php echo !empty($r->branch) ? s($r->branch) : '<span class="text-muted small">&mdash;</span>'; ?>
                                </td>

                                <!-- 19. Line of Business -->
                                <td class="lp-td-lob">
                                    <?php echo !empty($r->lineofbusiness) ? s($r->lineofbusiness) : '<span class="text-muted small">&mdash;</span>'; ?>
                                </td>

                                <!-- 20. Employee Code -->
                                <td class="lp-td-empcode">
                                    <?php if (!empty($r->employeecode)): ?>
                                        <span class="badge badge-secondary font-weight-bold px-2 py-1">
                                            #<?php echo s($r->employeecode); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 21. Email -->
                                <td class="lp-td-email">
                                    <a href="mailto:<?php echo s($r->email); ?>" class="lp-email-link text-primary small">
                                        <i class="fa fa-envelope-o mr-1"></i><?php echo s($r->email); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Client-side Instant Search & Row Counting Script -->
<script>
(function() {
    var searchInput = document.getElementById('filter_search');
    var tableRows = document.querySelectorAll('.lp-mis-row');
    var visibleCounter = document.getElementById('misVisibleCount');

    if (searchInput && tableRows.length) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            var visibleCount = 0;

            tableRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (!query || searchData.indexOf(query) !== -1) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCounter) {
                visibleCounter.textContent = visibleCount.toLocaleString();
            }
        });
    }
})();
</script>

<?php
echo $OUTPUT->footer();
