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
 * Universal navigation and UX helper for local_learningplan.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;

/**
 * Class navigation provides consistent, intuitive navigation headers,
 * plan switchers, contextual tabs, and next-step workflow actions.
 */
class navigation {

    /**
     * Render the top global admin navigation bar.
     *
     * @param string $activepage 'plans', 'create', 'report', 'badges', 'settings', 'map'
     * @param int $contextplanid Optional active plan ID for contextual links
     * @return string HTML output
     */
    public static function render_global_header(string $activepage = 'plans', int $contextplanid = 0): string {
        global $USER;

        $context = \context_system::instance();
        $canmanage = has_capability('local/learningplan:manage', $context);
        $canreport = has_capability('local/learningplan:viewreports', $context);

        $navitems = [];

        if ($canmanage) {
            $navitems[] = [
                'id' => 'plans',
                'url' => new moodle_url('/local/learningplan/manage/plans.php'),
                'icon' => 'fa fa-th-list',
                'label' => get_string('managelearningplans', 'local_learningplan'),
            ];
            $navitems[] = [
                'id' => 'create',
                'url' => new moodle_url('/local/learningplan/manage/edit_plan.php'),
                'icon' => 'fa fa-plus-circle',
                'label' => get_string('createplan', 'local_learningplan'),
            ];
        }

        if ($canreport) {
            $reportparams = $contextplanid ? ['planid' => $contextplanid] : [];
            $navitems[] = [
                'id' => 'dashboard',
                'url' => new moodle_url('/local/learningplan/dashboard.php', $reportparams),
                'icon' => 'fa fa-pie-chart',
                'label' => local_learningplan_str('lpdashboard', 'LP Dashboard'),
            ];
            $navitems[] = [
                'id' => 'report',
                'url' => new moodle_url('/local/learningplan/manage/report.php', $reportparams),
                'icon' => 'fa fa-line-chart',
                'label' => get_string('reports', 'local_learningplan'),
            ];
        }

        if ($canmanage) {
            $badgeparams = $contextplanid ? ['planid' => $contextplanid] : [];
            $navitems[] = [
                'id' => 'badges',
                'url' => new moodle_url('/local/learningplan/manage/badges.php', $badgeparams),
                'icon' => 'fa fa-trophy',
                'label' => get_string('badges', 'local_learningplan'),
            ];
            $navitems[] = [
                'id' => 'icons',
                'url' => new moodle_url('/local/learningplan/manage/icons.php'),
                'icon' => 'fa fa-picture-o',
                'label' => local_learningplan_str('icongallery', 'Icon Gallery'),
            ];
        }

        $leaderboardparams = $contextplanid ? ['planid' => $contextplanid] : [];
        $navitems[] = [
            'id' => 'leaderboard',
            'url' => new moodle_url('/local/learningplan/leaderboard.php', $leaderboardparams),
            'icon' => 'fa fa-list-ol',
            'label' => local_learningplan_str('leaderboard', 'Leaderboard'),
        ];

        $mapparams = $contextplanid ? ['id' => $contextplanid] : [];
        $navitems[] = [
            'id' => 'map',
            'url' => new moodle_url('/local/learningplan/index.php', $mapparams),
            'icon' => 'fa fa-map-signs',
            'label' => get_string('previewmap', 'local_learningplan'),
        ];

        if (is_siteadmin()) {
            $navitems[] = [
                'id' => 'settings',
                'url' => new moodle_url('/admin/settings.php', ['section' => 'local_learningplan_settings']),
                'icon' => 'fa fa-cog',
                'label' => get_string('settings', 'moodle'),
            ];
        }

        $html = html_writer::start_div('lp-global-nav-bar mb-3');
        $html .= html_writer::start_div('lp-global-nav-container');

        // Brand / Hub Title.
        $html .= html_writer::start_div('lp-global-nav-brand');
        $html .= html_writer::link(
            new moodle_url('/local/learningplan/manage/plans.php'),
            '<i class="fa fa-graduation-cap text-primary mr-1"></i> ' . get_string('pluginname', 'local_learningplan'),
            ['class' => 'lp-brand-link font-weight-bold text-dark']
        );
        $html .= html_writer::end_div();

        // Nav Tabs.
        $html .= html_writer::start_div('lp-global-nav-tabs');
        foreach ($navitems as $item) {
            $isactive = ($activepage === $item['id']);
            $classes = 'lp-nav-tab-btn' . ($isactive ? ' is-active' : '');
            $html .= html_writer::link(
                $item['url'],
                '<i class="' . $item['icon'] . ' mr-1"></i> ' . $item['label'],
                ['class' => $classes]
            );
        }
        $html .= html_writer::end_div(); // .lp-global-nav-tabs

        $html .= html_writer::end_div(); // .lp-global-nav-container
        $html .= html_writer::end_div(); // .lp-global-nav-bar

        return $html;
    }

    /**
     * Render the Plan Context Header (Tabs, Switcher, and Back Button).
     *
     * @param int $planid Current plan ID
     * @param string $activetab 'map', 'steps', 'assign', 'edit', 'report', 'badges'
     * @return string HTML output
     */
    public static function render_plan_context_header(int $planid, string $activetab = 'steps'): string {
        global $DB;

        try {
            $plan = api::get_plan($planid);
        } catch (\Exception $e) {
            return '';
        }

        $allplans = api::get_plans();

        $html = html_writer::start_div('lp-plan-context-header mb-4');

        // Top Row: Back button, Plan Title & Badges, Plan Switcher.
        $html .= html_writer::start_div('lp-context-top-row d-flex flex-wrap justify-content-between align-items-center mb-3');
        
        // Left: Back button + Title.
        $html .= html_writer::start_div('lp-context-left d-flex align-items-center flex-wrap');
        $html .= html_writer::link(
            new moodle_url('/local/learningplan/manage/plans.php'),
            '<i class="fa fa-arrow-left mr-1"></i> ' . get_string('allplans', 'local_learningplan'),
            ['class' => 'btn btn-sm btn-outline-secondary mr-3 lp-back-btn', 'title' => get_string('managelearningplans', 'local_learningplan')]
        );
        $html .= html_writer::start_div('lp-context-title-wrap');
        $html .= html_writer::tag('h3', format_string($plan->name), ['class' => 'lp-context-title mb-0 d-inline-block mr-2 font-weight-bold']);
        if ($plan->visible) {
            $html .= '<span class="badge badge-success align-middle"><i class="fa fa-eye mr-1"></i>' . get_string('visible', 'block_learningplan_admin') . '</span>';
        } else {
            $html .= '<span class="badge badge-secondary align-middle"><i class="fa fa-eye-slash mr-1"></i>' . get_string('hidden', 'block_learningplan_admin') . '</span>';
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div(); // .lp-context-left

        // Right: Quick Plan Switcher dropdown.
        if (count($allplans) > 1) {
            $html .= html_writer::start_div('lp-context-right mt-2 mt-md-0');
            $html .= html_writer::start_tag('form', ['method' => 'get', 'action' => '#', 'class' => 'form-inline d-flex align-items-center']);
            $html .= html_writer::tag('label', '<i class="fa fa-exchange mr-1 text-muted"></i> ' . get_string('switchplan', 'local_learningplan') . ': ', ['class' => 'mr-2 small font-weight-bold text-muted']);
            
            $options = [];
            foreach ($allplans as $p) {
                $options[$p->id] = format_string($p->name) . ($p->visible ? '' : ' (' . get_string('hidden', 'block_learningplan_admin') . ')');
            }

            // Determine target script based on active tab.
            $targetscript = '/local/learningplan/manage/edit_steps.php';
            $paramname = 'planid';
            if ($activetab === 'map') {
                $targetscript = '/local/learningplan/index.php';
                $paramname = 'id';
            } else if ($activetab === 'assign') {
                $targetscript = '/local/learningplan/manage/assign.php';
            } else if ($activetab === 'edit') {
                $targetscript = '/local/learningplan/manage/edit_plan.php';
                $paramname = 'id';
            } else if ($activetab === 'report') {
                $targetscript = '/local/learningplan/manage/report.php';
            } else if ($activetab === 'dashboard') {
                $targetscript = '/local/learningplan/dashboard.php';
            } else if ($activetab === 'badges') {
                $targetscript = '/local/learningplan/manage/badges.php';
            } else if ($activetab === 'leaderboard') {
                $targetscript = '/local/learningplan/leaderboard.php';
            }

            $selectattributes = [
                'class' => 'custom-select custom-select-sm lp-plan-switcher-select',
                'onchange' => 'window.location.href = "' . (new moodle_url($targetscript))->out(false) . '?' . $paramname . '=" + this.value;',
            ];
            $html .= html_writer::select($options, 'switchplanid', $planid, false, $selectattributes);
            $html .= html_writer::end_tag('form');
            $html .= html_writer::end_div(); // .lp-context-right
        }

        $html .= html_writer::end_div(); // .lp-context-top-row

        // Bottom Row: Contextual Sub-Nav Tabs (Workflow Pills).
        $plantabs = [
            [
                'id' => 'steps',
                'url' => new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $planid]),
                'icon' => 'fa fa-list-ol',
                'label' => get_string('steps', 'local_learningplan'),
            ],
            [
                'id' => 'assign',
                'url' => new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $planid]),
                'icon' => 'fa fa-user-plus',
                'label' => get_string('assign', 'local_learningplan'),
            ],
            [
                'id' => 'map',
                'url' => new moodle_url('/local/learningplan/index.php', ['id' => $planid]),
                'icon' => 'fa fa-map-signs',
                'label' => get_string('previewmap', 'local_learningplan'),
            ],
            [
                'id' => 'dashboard',
                'url' => new moodle_url('/local/learningplan/dashboard.php', ['planid' => $planid]),
                'icon' => 'fa fa-pie-chart',
                'label' => local_learningplan_str('lpdashboard', 'LP Dashboard'),
            ],
            [
                'id' => 'leaderboard',
                'url' => new moodle_url('/local/learningplan/leaderboard.php', ['planid' => $planid]),
                'icon' => 'fa fa-trophy',
                'label' => local_learningplan_str('leaderboard', 'Leaderboard'),
            ],
            [
                'id' => 'edit',
                'url' => new moodle_url('/local/learningplan/manage/edit_plan.php', ['id' => $planid]),
                'icon' => 'fa fa-pencil-square-o',
                'label' => get_string('editplan', 'local_learningplan'),
            ],
            [
                'id' => 'report',
                'url' => new moodle_url('/local/learningplan/manage/report.php', ['planid' => $planid]),
                'icon' => 'fa fa-bar-chart',
                'label' => get_string('reports', 'local_learningplan'),
            ],
            [
                'id' => 'badges',
                'url' => new moodle_url('/local/learningplan/manage/badges.php', ['planid' => $planid]),
                'icon' => 'fa fa-shield',
                'label' => get_string('badges', 'local_learningplan'),
            ],
        ];

        $html .= html_writer::start_div('lp-plan-subnav-pills d-flex flex-wrap');
        foreach ($plantabs as $tab) {
            $isactive = ($activetab === $tab['id']);
            $classes = 'lp-subnav-pill' . ($isactive ? ' is-active' : '');
            $html .= html_writer::link(
                $tab['url'],
                '<i class="' . $tab['icon'] . ' mr-1"></i> ' . $tab['label'],
                ['class' => $classes]
            );
        }
        $html .= html_writer::end_div(); // .lp-plan-subnav-pills

        $html .= html_writer::end_div(); // .lp-plan-context-header

        return $html;
    }

    /**
     * Render a Forward Workflow Guidance Footer Card.
     *
     * @param string $currentstage 'edit_plan', 'edit_steps', 'assign'
     * @param int $planid Active plan ID
     * @return string HTML output
     */
    public static function render_workflow_footer(string $currentstage, int $planid): string {
        $html = html_writer::start_div('lp-workflow-footer mt-4 p-3 bg-light rounded border d-flex flex-wrap justify-content-between align-items-center');

        if ($currentstage === 'edit_plan') {
            $html .= html_writer::start_div('lp-wf-message mb-2 mb-md-0');
            $html .= '<span class="font-weight-bold"><i class="fa fa-info-circle text-primary mr-1"></i> ' . get_string('readytoaddsteps', 'local_learningplan') . '</span>';
            $html .= '<p class="text-muted small mb-0">' . get_string('readytoaddsteps_desc', 'local_learningplan') . '</p>';
            $html .= html_writer::end_div();

            $html .= html_writer::start_div('lp-wf-actions');
            $html .= html_writer::link(
                new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $planid]),
                get_string('nextstep_builder', 'local_learningplan') . ' <i class="fa fa-arrow-right ml-1"></i>',
                ['class' => 'btn btn-primary']
            );
            $html .= html_writer::end_div();

        } else if ($currentstage === 'edit_steps') {
            $html .= html_writer::start_div('lp-wf-message mb-2 mb-md-0');
            $html .= '<span class="font-weight-bold"><i class="fa fa-check-circle text-success mr-1"></i> ' . get_string('stepconfigdone', 'local_learningplan') . '</span>';
            $html .= '<p class="text-muted small mb-0">' . get_string('stepconfigdone_desc', 'local_learningplan') . '</p>';
            $html .= html_writer::end_div();

            $html .= html_writer::start_div('lp-wf-actions d-flex gap-2');
            $html .= html_writer::link(
                new moodle_url('/local/learningplan/index.php', ['id' => $planid]),
                '<i class="fa fa-map-signs mr-1"></i> ' . get_string('previewmap', 'local_learningplan'),
                ['class' => 'btn btn-outline-primary mr-2']
            );
            $html .= html_writer::link(
                new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $planid]),
                get_string('nextstep_assign', 'local_learningplan') . ' <i class="fa fa-arrow-right ml-1"></i>',
                ['class' => 'btn btn-primary']
            );
            $html .= html_writer::end_div();

        } else if ($currentstage === 'assign') {
            $html .= html_writer::start_div('lp-wf-message mb-2 mb-md-0');
            $html .= '<span class="font-weight-bold"><i class="fa fa-rocket text-primary mr-1"></i> ' . get_string('assignmentdone', 'local_learningplan') . '</span>';
            $html .= '<p class="text-muted small mb-0">' . get_string('assignmentdone_desc', 'local_learningplan') . '</p>';
            $html .= html_writer::end_div();

            $html .= html_writer::start_div('lp-wf-actions d-flex gap-2');
            $html .= html_writer::link(
                new moodle_url('/local/learningplan/manage/report.php', ['planid' => $planid]),
                '<i class="fa fa-bar-chart mr-1"></i> ' . get_string('viewreports', 'block_learningplan_admin'),
                ['class' => 'btn btn-outline-info mr-2']
            );
            $html .= html_writer::link(
                new moodle_url('/local/learningplan/index.php', ['id' => $planid]),
                '<i class="fa fa-map-signs mr-1"></i> ' . get_string('previewmap', 'local_learningplan') . ' <i class="fa fa-arrow-right ml-1"></i>',
                ['class' => 'btn btn-success']
            );
            $html .= html_writer::end_div();
        }

        $html .= html_writer::end_div(); // .lp-workflow-footer
        return $html;
    }
}
