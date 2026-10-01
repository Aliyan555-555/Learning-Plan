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
 * Modern Executive Learning Plan Status Dashboard & Multi-Chart Analytics Hub.
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

$planid = optional_param('planid', 0, PARAM_INT);
$days = optional_param('days', 21, PARAM_INT);
$export = optional_param('export', 0, PARAM_BOOL);
$exportmis = optional_param('exportmis', 0, PARAM_BOOL);

// MIS Excel Report (.xlsx) Export Handler.
if ($exportmis) {
    require_sesskey();
    api::export_mis_report($planid, $days);
    exit;
}

// Fetch complete live status analytics data from database.
$dashboarddata = api::get_status_dashboard_data($planid, $days);

// CSV Export Handler.
if ($export) {
    require_sesskey();
    $filename = 'learning_plan_live_dashboard_' . userdate(time(), '%Y%m%d_%H%M%S') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    fputcsv($out, ['Learning Plan Live Status & Analytics Dashboard Export']);
    fputcsv($out, ['Site', format_string($SITE->fullname)]);
    fputcsv($out, ['Mode', 'Live Database Production Metrics']);
    fputcsv($out, ['Generated', userdate(time())]);
    fputcsv($out, []);

    fputcsv($out, ['--- KPI SUMMARY METRICS (LIVE) ---']);
    fputcsv($out, ['Metric', 'Value', 'Details']);
    fputcsv($out, ['Total Inactive Enrolment', $dashboarddata->kpi->total_inactive_enrolment, $dashboarddata->kpi->inactive_enrolment_ratio . ' (' . $dashboarddata->kpi->inactive_enrolment_pct . '%)']);
    fputcsv($out, ['Total Active Enrolment', $dashboarddata->kpi->total_active_enrolment, $dashboarddata->kpi->active_enrolment_ratio . ' (' . $dashboarddata->kpi->active_enrolment_pct . '%)']);
    fputcsv($out, ['Average Courses Completed Per Participant', $dashboarddata->kpi->avg_courses_completed, '']);
    fputcsv($out, ['Overall Courses Completion Percentage', $dashboarddata->kpi->overall_completion_pct . '%', '']);
    fputcsv($out, []);

    fputcsv($out, ['--- PER LEARNING PLAN BREAKDOWN (LIVE) ---']);
    fputcsv($out, ['Plan Name', 'Completed %', 'In Progress %', 'Completed Learners', 'In Progress Learners', 'Total Assigned', 'Steps/Courses', 'XP Awarded', 'Stars']);
    foreach ($dashboarddata->plan_charts as $pc) {
        fputcsv($out, [
            $pc->name,
            $pc->completed_pct . '%',
            $pc->inprogress_pct . '%',
            $pc->completed_count,
            $pc->inprogress_count,
            $pc->total_learners,
            $pc->total_steps,
            $pc->xp_awarded ?? 0,
            $pc->stars_awarded ?? 0,
        ]);
    }
    fclose($out);
    exit;
}

$pageurl = new moodle_url('/local/learningplan/dashboard.php', [
    'planid' => $planid,
    'days' => $days,
]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(local_learningplan_str('analyticsdashboard', 'Learning Plan Status Dashboard'));
$PAGE->set_heading(local_learningplan_str('analyticsdashboard', 'Learning Plan Status Dashboard'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

echo $OUTPUT->header();

// Universal Learning Plan Navigation Bar.
if ($planid) {
    echo navigation::render_plan_context_header($planid, 'dashboard');
} else {
    echo navigation::render_global_header('dashboard');
}

$dashboardjson = json_encode($dashboarddata);
$sitename = format_string($SITE->fullname);
$totalusers = $DB->count_records('user', ['deleted' => 0]);
?>

<!-- ======================================================================== -->
<!-- MODERN EXECUTIVE STATUS DASHBOARD (LIVE DATA)                             -->
<!-- ======================================================================== -->
<div class="lp-dashboard-wrap" id="lpDashboardApp">

    <!-- Sleek Executive Hero Banner (Learning Plan Theme Aligned) -->
    <header class="lp-dashboard-hero">
        <div class="lp-hero-main">
            <div class="lp-hero-badge-wrap">
                <div class="lp-hero-icon-glow">
                    <i class="fa fa-tachometer"></i>
                </div>
                <div class="lp-hero-headings">
                    <div class="lp-hero-pretitle">
                        <span class="lp-pulse-dot"></span>
                        <span class="lp-hero-mode-text">Real-time Live Production Data</span>
                        <span class="lp-hero-divider">&bull;</span>
                        <span class="lp-hero-sitename"><?php echo $sitename; ?> (<?php echo number_format($totalusers); ?> Users)</span>
                    </div>
                    <h1 class="lp-hero-title">Learning Plan Status &amp; Analytics Hub</h1>
                    <p class="lp-hero-desc">Executive live performance intelligence, real-time learner engagement, and course completion analytics.</p>
                </div>
            </div>
        </div>

        <div class="lp-hero-controls">
            <!-- Live Status Pill -->
            <div class="lp-hero-live-badge">
                <span class="lp-pulse-dot"></span>
                <span>Live DB Connected (<?php echo count($dashboarddata->plan_charts); ?> Plans)</span>
                <span class="lp-hero-divider">&bull;</span>
                <span class="text-light opacity-75"><i class="fa fa-clock-o mr-1"></i><?php echo userdate(time(), '%I:%M %p'); ?></span>
            </div>

            <!-- Action Buttons -->
            <div class="lp-hero-actions">
                <a href="<?php echo new moodle_url($pageurl, ['exportmis' => 1, 'sesskey' => sesskey()]); ?>" class="lp-btn-hero-action lp-btn-mis-action" title="Download MIS Report (.xlsx)">
                    <i class="fa fa-file-excel-o mr-1 text-success"></i><span><?php echo local_learningplan_str('downloadmisreport', 'Download MIS Report'); ?></span>
                </a>
                <a href="<?php echo new moodle_url($pageurl, ['export' => 1, 'sesskey' => sesskey()]); ?>" class="lp-btn-hero-action" title="Export CSV Summary">
                    <i class="fa fa-download mr-1"></i><span>CSV</span>
                </a>
                <button type="button" class="lp-btn-hero-action" onclick="window.print()" title="Print Dashboard or Save PDF">
                    <i class="fa fa-print mr-1"></i><span>PDF</span>
                </button>
                <button type="button" class="lp-btn-hero-action" onclick="window.location.reload()" title="Refresh live database data">
                    <i class="fa fa-refresh mr-1"></i><span>Refresh</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ==================================================================== -->
    <!-- TOP KPI SUMMARY TILES                                                -->
    <!-- ==================================================================== -->
    <div class="lp-modern-kpi-grid">

        <!-- KPI 1: Total Inactive Enrolment -->
        <div class="lp-modern-kpi-tile lp-kpi-border-coral">
            <div class="lp-kpi-top-row">
                <div class="lp-kpi-avatar lp-avatar-coral">
                    <i class="fa fa-user-times"></i>
                </div>
                <div class="lp-kpi-badge-tag bg-soft-coral text-coral">
                    <?php echo $dashboarddata->kpi->inactive_enrolment_pct; ?>% Inactive
                </div>
            </div>
            <div class="lp-kpi-main-metric">
                <span class="lp-kpi-title">Total Inactive Enrolment</span>
                <span class="lp-kpi-sub">Enrolled learners with no progress in last <?php echo $days; ?> days</span>
                <div class="lp-kpi-big-num text-coral">
                    <?php echo $dashboarddata->kpi->inactive_enrolment_ratio; ?>
                </div>
            </div>
            <div class="lp-kpi-progress-bar">
                <div class="lp-kpi-progress-fill bg-coral" style="width: <?php echo min(100, $dashboarddata->kpi->inactive_enrolment_pct); ?>%;"></div>
            </div>
        </div>

        <!-- KPI 2: Total Active Enrolment -->
        <div class="lp-modern-kpi-tile lp-kpi-border-blue">
            <div class="lp-kpi-top-row">
                <div class="lp-kpi-avatar lp-avatar-blue">
                    <i class="fa fa-users"></i>
                </div>
                <div class="lp-kpi-badge-tag bg-soft-blue text-blue">
                    <?php echo $dashboarddata->kpi->active_enrolment_pct; ?>% Active
                </div>
            </div>
            <div class="lp-kpi-main-metric">
                <span class="lp-kpi-title">Total Active Enrolment</span>
                <span class="lp-kpi-sub">Learners actively progressing in last <?php echo $days; ?> days</span>
                <div class="lp-kpi-big-num text-blue">
                    <?php echo $dashboarddata->kpi->active_enrolment_ratio; ?>
                </div>
            </div>
            <div class="lp-kpi-progress-bar">
                <div class="lp-kpi-progress-fill bg-blue" style="width: <?php echo min(100, $dashboarddata->kpi->active_enrolment_pct); ?>%;"></div>
            </div>
        </div>

        <!-- KPI 3: Average Courses Completed Per Participant -->
        <div class="lp-modern-kpi-tile lp-kpi-border-gold">
            <div class="lp-kpi-top-row">
                <div class="lp-kpi-avatar lp-avatar-gold">
                    <i class="fa fa-graduation-cap"></i>
                </div>
                <div class="lp-kpi-badge-tag bg-soft-gold text-gold">
                    <i class="fa fa-star mr-1"></i>Velocity
                </div>
            </div>
            <div class="lp-kpi-main-metric">
                <span class="lp-kpi-title">Average Courses Completed</span>
                <span class="lp-kpi-sub">Mean completed steps/courses per participant</span>
                <div class="lp-kpi-big-num text-gold">
                    <?php echo $dashboarddata->kpi->avg_courses_completed; ?>
                </div>
            </div>
            <div class="lp-kpi-meta-footer">
                <span class="text-success small font-weight-bold"><i class="fa fa-arrow-up mr-1"></i>Healthy progression rate</span>
            </div>
        </div>

        <!-- KPI 4: Overall Courses Completion Percentage -->
        <div class="lp-modern-kpi-tile lp-kpi-border-purple">
            <div class="lp-kpi-top-row">
                <div class="lp-kpi-avatar lp-avatar-purple">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div class="lp-kpi-badge-tag bg-soft-purple text-purple">
                    Target: 60%+
                </div>
            </div>
            <div class="lp-kpi-main-metric">
                <span class="lp-kpi-title">Overall Completion Percentage</span>
                <span class="lp-kpi-sub">Global completion rate across all journeys</span>
                <div class="lp-kpi-big-num text-purple">
                    <?php echo $dashboarddata->kpi->overall_completion_pct; ?>%
                </div>
            </div>
            <div class="lp-kpi-progress-bar">
                <div class="lp-kpi-progress-fill bg-purple" style="width: <?php echo min(100, $dashboarddata->kpi->overall_completion_pct); ?>%;"></div>
            </div>
        </div>

    </div>

    <!-- ==================================================================== -->
    <!-- SECTION 2: MULTI-CHART STATUS TILES (EACH PLAN HAS SEPARATE DONUT)   -->
    <!-- ==================================================================== -->
    <div class="lp-section-heading-row mt-4 mb-3">
        <div class="lp-section-heading-content">
            <h3 class="lp-modern-section-title">
                <span class="lp-section-icon"><i class="fa fa-pie-chart"></i></span>
                <span>Learning Plans Completion &amp; Engagement Charts</span>
            </h3>
            <p class="lp-modern-section-sub">
                Each learning plan features a dedicated donut chart with live completion ratios, learner counts, and progress tracking.
            </p>
        </div>
    </div>

    <div class="lp-multi-donut-grid">

        <!-- DYNAMIC SEPARATE DONUT CHART FOR EACH LEARNING PLAN -->
        <?php foreach ($dashboarddata->plan_charts as $plan): ?>
            <?php 
                if ($planid > 0 && $planid != $plan->id) {
                    continue;
                }
            ?>
            <div class="lp-modern-donut-card" id="plan-chart-card-<?php echo $plan->id; ?>">
                <div class="lp-donut-header">
                    <div class="lp-donut-header-title-wrap">
                        <span class="lp-donut-dot" style="background: <?php echo $plan->color_completed; ?>;"></span>
                        <h4 class="lp-donut-plan-name" title="<?php echo s($plan->name); ?>">
                            <?php echo s($plan->name); ?>
                        </h4>
                    </div>
                    <div class="lp-donut-header-actions">
                        <span class="badge badge-pill badge-light border text-muted small px-2 py-1 mr-1"><?php echo $plan->total_steps; ?> Steps</span>
                        <a href="<?php echo new moodle_url($pageurl, ['exportmis' => 1, 'planid' => $plan->id, 'sesskey' => sesskey()]); ?>" 
                           class="lp-card-download-btn" 
                           title="Download full report for <?php echo s($plan->name); ?> (.xlsx)">
                            <i class="fa fa-download"></i>
                            <span class="lp-card-download-text">Report</span>
                        </a>
                    </div>
                </div>

                <!-- Donut Chart Canvas Container -->
                <div class="lp-donut-visual-container">
                    <!-- Top-Right Legend Callout -->
                    <div class="lp-callout-pill lp-callout-top-right">
                        <span class="lp-callout-lbl">Completed</span>
                        <span class="lp-callout-val" style="color: <?php echo $plan->color_completed; ?>;">
                            <?php echo number_format($plan->completed_pct, 1); ?>%
                        </span>
                    </div>

                    <div class="lp-donut-canvas-holder">
                        <canvas class="lp-donut-canvas" 
                                data-type="plan" 
                                data-completed="<?php echo $plan->completed_pct; ?>" 
                                data-inprogress="<?php echo $plan->inprogress_pct; ?>"
                                data-color-completed="<?php echo $plan->color_completed; ?>"
                                data-color-inprogress="<?php echo $plan->color_inprogress; ?>"
                                width="200" height="200"></canvas>
                        <div class="lp-donut-inner-content">
                            <span class="lp-inner-pct"><?php echo round($plan->completed_pct); ?>%</span>
                            <span class="lp-inner-lbl">Done</span>
                        </div>
                    </div>

                    <!-- Bottom-Left Legend Callout -->
                    <div class="lp-callout-pill lp-callout-bottom-left">
                        <span class="lp-callout-lbl">In Progress</span>
                        <span class="lp-callout-val" style="color: <?php echo $plan->color_inprogress; ?>;">
                            <?php echo number_format($plan->inprogress_pct, 1); ?>%
                        </span>
                    </div>
                </div>

                <!-- UNDER-CHART: ADVANCED COMPLETION & DETAILED METRICS -->
                <div class="lp-under-chart-stats">
                    <!-- Progress meter -->
                    <div class="lp-under-chart-progress mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1 font-weight-bold">
                            <span>Completion Velocity</span>
                            <span class="text-dark"><?php echo number_format($plan->completed_pct, 1); ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px; border-radius: 99px; background: #f1f5f9;">
                            <div class="progress-bar" style="width: <?php echo min(100, $plan->completed_pct); ?>%; background: <?php echo $plan->color_completed; ?>;"></div>
                            <div class="progress-bar" style="width: <?php echo min(100 - $plan->completed_pct, $plan->inprogress_pct); ?>%; background: <?php echo $plan->color_inprogress; ?>;"></div>
                        </div>
                    </div>

                    <!-- 4-Stat Box Grid -->
                    <div class="lp-under-chart-metrics-grid">
                        <div class="lp-uc-metric-box">
                            <span class="lp-uc-label">Enrolled</span>
                            <span class="lp-uc-value"><i class="fa fa-users text-muted mr-1"></i><?php echo number_format($plan->total_learners); ?></span>
                        </div>
                        <div class="lp-uc-metric-box">
                            <span class="lp-uc-label">Completed</span>
                            <span class="lp-uc-value text-success"><i class="fa fa-check-circle mr-1"></i><?php echo number_format($plan->completed_count); ?></span>
                        </div>
                        <div class="lp-uc-metric-box">
                            <span class="lp-uc-label">In Progress</span>
                            <span class="lp-uc-value text-warning"><i class="fa fa-hourglass-half mr-1"></i><?php echo number_format($plan->inprogress_count); ?></span>
                        </div>
                        <div class="lp-uc-metric-box">
                            <span class="lp-uc-label">Avg Pace</span>
                            <span class="lp-uc-value text-primary"><i class="fa fa-clock-o mr-1"></i>~<?php echo $plan->avg_completion_days; ?>d</span>
                        </div>
                    </div>

                    <div class="lp-under-chart-actions mt-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if (!empty($plan->xp_awarded)): ?>
                                <span class="badge badge-pill badge-light border text-muted px-2 py-1">
                                    <i class="fa fa-bolt text-warning mr-1"></i><?php echo number_format($plan->xp_awarded); ?> XP
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($plan->stars_awarded)): ?>
                                <span class="badge badge-pill badge-light border text-muted px-2 py-1">
                                    <i class="fa fa-star text-warning mr-1"></i><?php echo number_format($plan->stars_awarded); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo new moodle_url('/local/learningplan/index.php', ['id' => $plan->id]); ?>" class="lp-btn-action-map ml-auto">
                            <span>Preview Journey</span> <i class="fa fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Executive Footer -->
    <footer class="lp-modern-exec-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="lp-footer-brand">
                <i class="fa fa-graduation-cap text-primary mr-1"></i>
                <span>&copy; <?php echo date('Y'); ?> <?php echo $sitename; ?> &bull; Learning Plan Executive Intelligence Hub</span>
            </div>
            <div class="lp-footer-links">
                <a href="<?php echo new moodle_url('/local/learningplan/manage/plans.php'); ?>" class="text-muted small mr-3">All Plans</a>
                <a href="<?php echo new moodle_url('/local/learningplan/manage/report.php'); ?>" class="text-muted small mr-3">Learner Reports</a>
                <a href="<?php echo new moodle_url('/local/learningplan/leaderboard.php'); ?>" class="text-muted small mr-3">Leaderboard</a>
                <a href="<?php echo new moodle_url('/local/learningplan/index.php'); ?>" class="text-muted small">Journey Maps</a>
            </div>
        </div>
    </footer>

</div>

<!-- ======================================================================== -->
<!-- HIGH-DEFINITION CANVAS CHART ENGINE WITH RETINA / RESPONSIVE SCALING      -->
<!-- ======================================================================== -->
<script>
(function() {
    'use strict';

    var DASHBOARD_DATA = <?php echo $dashboardjson; ?>;

    /**
     * Helper to setup crisp high-DPI canvas drawing
     */
    function getCrispContext(canvas, cssWidth, cssHeight) {
        var dpr = window.devicePixelRatio || 1;
        canvas.style.width = cssWidth + 'px';
        canvas.style.height = cssHeight + 'px';
        canvas.width = Math.floor(cssWidth * dpr);
        canvas.height = Math.floor(cssHeight * dpr);
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        return { ctx: ctx, width: cssWidth, height: cssHeight, dpr: dpr };
    }

    /**
     * Draw modern anti-aliased Donut Charts on HTML5 canvas with smooth arcs and shadows.
     */
    function renderDonutChart(canvas, segments) {
        if (!canvas) return;
        var setup = getCrispContext(canvas, 200, 200);
        var ctx = setup.ctx;
        var width = setup.width;
        var height = setup.height;

        ctx.clearRect(0, 0, width, height);

        var centerX = width / 2;
        var centerY = height / 2;
        var radius = Math.min(centerX, centerY) - 16;
        var lineWidth = 20;

        var total = 0;
        segments.forEach(function(s) { total += s.value; });
        if (total <= 0) total = 100;

        var startAngle = -0.5 * Math.PI; // Start at 12 o'clock

        // Background track circle
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
        ctx.strokeStyle = '#f1f5f9';
        ctx.lineWidth = lineWidth;
        ctx.stroke();

        segments.forEach(function(s) {
            if (s.value <= 0) return;
            var sliceAngle = (s.value / total) * 2 * Math.PI;
            var endAngle = startAngle + sliceAngle;

            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.strokeStyle = s.color;
            ctx.lineWidth = lineWidth;
            ctx.lineCap = 'round';
            ctx.stroke();

            startAngle = endAngle;
        });

        // Subtle inner ring
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius - lineWidth / 2 - 1, 0, 2 * Math.PI);
        ctx.strokeStyle = 'rgba(226, 232, 240, 0.6)';
        ctx.lineWidth = 1;
        ctx.stroke();
    }

    /**
     * Initialize all Donut charts across the grid.
     */
    function initAllDonuts() {
        var canvases = document.querySelectorAll('.lp-donut-canvas');
        canvases.forEach(function(canvas) {
            var type = canvas.getAttribute('data-type');
            var segments = [];

            if (type === 'plan') {
                var comp = parseFloat(canvas.getAttribute('data-completed')) || 0;
                var inprog = parseFloat(canvas.getAttribute('data-inprogress')) || 0;
                var colComp = canvas.getAttribute('data-color-completed') || '#0284c7';
                var colInprog = canvas.getAttribute('data-color-inprogress') || '#38bdf8';

                segments = [
                    { value: comp, color: colComp },
                    { value: inprog, color: colInprog }
                ];
            }

            renderDonutChart(canvas, segments);
        });
    }

    function init() {
        initAllDonuts();

        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                initAllDonuts();
            }, 100);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<?php
echo $OUTPUT->footer();

