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
 * Main block class for Learning Plans Admin Block.
 *
 * @package    block_learningplan_admin
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Block displaying admin navigation hub, live statistics, and quick plan management.
 */
class block_learningplan_admin extends block_base {

    /**
     * Initialize block properties.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_learningplan_admin');
    }

    /**
     * Multiple instances allowed on the same page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return true;
    }

    /**
     * Instance configuration enabled.
     *
     * @return bool
     */
    public function instance_allow_config(): bool {
        return true;
    }

    /**
     * Global plugin settings check.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }

    /**
     * Formats/contexts where this block can be placed.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'all' => true,
            'site-index' => true,
            'course-view' => true,
            'my' => true,
            'admin' => true,
        ];
    }

    /**
     * Customization of block title based on instance settings.
     */
    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        } else {
            $this->title = get_string('pluginname', 'block_learningplan_admin');
        }
    }

    /**
     * Generate content for the block.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $USER, $DB, $OUTPUT, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Verify that local_learningplan is present.
        if (!class_exists('\local_learningplan\api') && file_exists($CFG->dirroot . '/local/learningplan/classes/api.php')) {
            require_once($CFG->dirroot . '/local/learningplan/classes/api.php');
        }

        $systemcontext = context_system::instance();
        $isadmin = is_siteadmin() ||
            has_capability('local/learningplan:manage', $systemcontext) ||
            has_capability('local/learningplan:viewreports', $systemcontext);

        $showstats = !isset($this->config->showstats) || !empty($this->config->showstats);
        $showcharts = !isset($this->config->showcharts) || !empty($this->config->showcharts);
        $shownav = !isset($this->config->shownavigation) || !empty($this->config->shownavigation);
        $showrecent = !isset($this->config->showrecent) || !empty($this->config->showrecent);
        $recentlimit = isset($this->config->recentlimit) ? (int)$this->config->recentlimit : 5;

        if ($isadmin) {
            $uniqueid = 'lpa_' . uniqid();
            $html = '';
            $html .= html_writer::start_div('block-learningplan-admin', ['id' => $uniqueid]);

            // Quick Segmented Tab Switcher.
            $html .= html_writer::start_div('lpa-tab-nav');
            if ($showcharts) {
                $html .= '<button type="button" class="lpa-tab-btn active" data-tab="analytics">📈 ' . get_string('tab_analytics', 'block_learningplan_admin') . '</button>';
            }
            if ($showstats) {
                $html .= '<button type="button" class="lpa-tab-btn ' . (!$showcharts ? 'active' : '') . '" data-tab="overview">📊 ' . get_string('tab_overview', 'block_learningplan_admin') . '</button>';
            }
            if ($shownav || $showrecent) {
                $html .= '<button type="button" class="lpa-tab-btn ' . (!$showcharts && !$showstats ? 'active' : '') . '" data-tab="manage">⚡ ' . get_string('tab_plans', 'block_learningplan_admin') . '</button>';
            }
            $html .= html_writer::end_div(); // .lpa-tab-nav

            // TAB PANEL 1: Visual Analytics & Charts.
            if ($showcharts) {
                $analytics = $this->get_cached_dashboard_data('analytics_v3', function() {
                    return $this->get_admin_analytics_data();
                });
                $html .= html_writer::start_div('lpa-tab-panel active', ['data-panel' => 'analytics']);
                $html .= $this->render_analytics_graphics($analytics);
                $html .= html_writer::end_div();
            }

            // TAB PANEL 2: KPI Metrics & Live Overview Stats.
            if ($showstats) {
                $stats = $this->get_cached_dashboard_data('stats_v2', function() {
                    return $this->get_admin_statistics();
                });

                $html .= html_writer::start_div('lpa-tab-panel ' . (!$showcharts ? 'active' : ''), ['data-panel' => 'overview']);
                $html .= html_writer::start_div('lpa-section lpa-stats-section');
                $html .= html_writer::start_div('lpa-stats-grid');

                // Total Plans Card.
                $html .= html_writer::start_div('lpa-stat-card lpa-stat-plans');
                $html .= html_writer::tag('div', '🗺️', ['class' => 'lpa-stat-icon']);
                $html .= html_writer::start_div('lpa-stat-body');
                $html .= html_writer::tag('div', (string)$stats->totalplans, ['class' => 'lpa-stat-value']);
                $html .= html_writer::tag('div', get_string('stat_totalplans', 'block_learningplan_admin'), ['class' => 'lpa-stat-label']);
                $html .= html_writer::tag('div', '<span class="lpa-stat-sub-pill lpa-sub-success">' . $stats->activeplans . ' ' . get_string('stat_activeplans', 'block_learningplan_admin') . '</span> <span class="lpa-stat-sub-pill lpa-sub-muted">' . $stats->draftplans . ' ' . get_string('stat_draftplans', 'block_learningplan_admin') . '</span>', ['class' => 'lpa-stat-sub']);
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();

                // Enrolled Learners Card.
                $html .= html_writer::start_div('lpa-stat-card lpa-stat-learners');
                $html .= html_writer::tag('div', '👥', ['class' => 'lpa-stat-icon']);
                $html .= html_writer::start_div('lpa-stat-body');
                $html .= html_writer::tag('div', (string)$stats->totallearners, ['class' => 'lpa-stat-value']);
                $html .= html_writer::tag('div', get_string('stat_assignedlearners', 'block_learningplan_admin'), ['class' => 'lpa-stat-label']);
                $html .= html_writer::tag('div', '<span class="lpa-stat-sub-text">🎯 ' . $stats->totalsteps . ' ' . get_string('stat_totalsteps', 'block_learningplan_admin') . '</span>', ['class' => 'lpa-stat-sub']);
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();

                // Total Points Awarded Card.
                $html .= html_writer::start_div('lpa-stat-card lpa-stat-points');
                $html .= html_writer::tag('div', '🏆', ['class' => 'lpa-stat-icon']);
                $html .= html_writer::start_div('lpa-stat-body');
                $html .= html_writer::tag('div', number_format($stats->totalpoints) . ' XP', ['class' => 'lpa-stat-value lpa-text-points']);
                $html .= html_writer::tag('div', get_string('stat_pointsawarded', 'block_learningplan_admin'), ['class' => 'lpa-stat-label']);
                $html .= html_writer::tag('div', '<span class="lpa-stars-badge">⭐ ' . number_format($stats->totalstars) . '</span>', ['class' => 'lpa-stat-sub']);
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();

                // Completion Rate Card.
                $html .= html_writer::start_div('lpa-stat-card lpa-stat-completion');
                $html .= html_writer::tag('div', '🎯', ['class' => 'lpa-stat-icon']);
                $html .= html_writer::start_div('lpa-stat-body');
                $html .= html_writer::tag('div', $stats->completionrate . '%', ['class' => 'lpa-stat-value lpa-text-completion']);
                $html .= html_writer::tag('div', get_string('stat_completionrate', 'block_learningplan_admin'), ['class' => 'lpa-stat-label']);
                $html .= html_writer::start_div('progress lpa-mini-progress');
                $html .= html_writer::tag('div', '', [
                    'class' => 'progress-bar bg-success',
                    'role' => 'progressbar',
                    'style' => 'width: ' . min(100, max(0, $stats->completionrate)) . '%;',
                    'aria-valuenow' => $stats->completionrate,
                    'aria-valuemin' => 0,
                    'aria-valuemax' => 100,
                ]);
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();

                $html .= html_writer::end_div(); // .lpa-stats-grid
                $html .= html_writer::end_div(); // .lpa-stats-section
                $html .= html_writer::end_div(); // .lpa-tab-panel
            }

            // TAB PANEL 3: Management Hub & Recent Plans.
            if ($shownav || $showrecent) {
                $html .= html_writer::start_div('lpa-tab-panel ' . (!$showcharts && !$showstats ? 'active' : ''), ['data-panel' => 'manage']);

                // Primary Action & Navigation.
                if ($shownav) {
                    $html .= html_writer::start_div('lpa-section lpa-navigation-section');
                    $createurl = new moodle_url('/local/learningplan/manage/edit_plan.php');
                    $html .= html_writer::link(
                        $createurl,
                        '➕ ' . get_string('createplan', 'block_learningplan_admin'),
                        ['class' => 'lpa-create-btn btn btn-primary']
                    );

                    $html .= html_writer::start_div('lpa-nav-grid');
                    $navitems = [
                        [
                            'url' => new moodle_url('/local/learningplan/manage/plans.php'),
                            'emoji' => '📋',
                            'label' => get_string('manageplans', 'block_learningplan_admin'),
                            'class' => 'lpa-nav-item',
                        ],
                        [
                            'url' => new moodle_url('/local/learningplan/dashboard.php'),
                            'emoji' => '📈',
                            'label' => 'Status Dashboard',
                            'class' => 'lpa-nav-item',
                        ],
                        [
                            'url' => new moodle_url('/local/learningplan/manage/report.php'),
                            'emoji' => '📊',
                            'label' => get_string('viewreports', 'block_learningplan_admin'),
                            'class' => 'lpa-nav-item',
                        ],
                        [
                            'url' => new moodle_url('/local/learningplan/manage/badges.php'),
                            'emoji' => '🏆',
                            'label' => get_string('managebadges', 'block_learningplan_admin'),
                            'class' => 'lpa-nav-item',
                        ],
                        [
                            'url' => new moodle_url('/local/learningplan/index.php'),
                            'emoji' => '🗺️',
                            'label' => get_string('previewmap', 'block_learningplan_admin'),
                            'class' => 'lpa-nav-item',
                        ],
                    ];

                    if (is_siteadmin()) {
                        $navitems[] = [
                            'url' => new moodle_url('/admin/settings.php', ['section' => 'local_learningplan_settings']),
                            'emoji' => '⚙️',
                            'label' => get_string('pluginsettings', 'block_learningplan_admin'),
                            'class' => 'lpa-nav-item',
                        ];
                    }

                    foreach ($navitems as $item) {
                        $html .= html_writer::link(
                            $item['url'],
                            '<span class="lpa-emoji">' . $item['emoji'] . '</span><span class="lpa-label">' . $item['label'] . '</span>',
                            ['class' => $item['class']]
                        );
                    }
                    $html .= html_writer::end_div(); // .lpa-nav-grid
                    $html .= html_writer::end_div(); // .lpa-navigation-section
                }

                // Recent Plans List.
                if ($showrecent) {
                    $limit = $recentlimit;
                    $recentplans = $this->get_cached_dashboard_data('recent_' . (int)$limit, function() use ($limit) {
                        return $this->get_recent_plans($limit);
                    });

                    $html .= html_writer::start_div('lpa-section lpa-recent-section');
                    $html .= html_writer::tag('h6', '⚡ ' . get_string('recentplans', 'block_learningplan_admin'), ['class' => 'lpa-section-title']);

                    if (empty($recentplans)) {
                        $html .= html_writer::tag('div', get_string('noplansfound', 'block_learningplan_admin'), ['class' => 'lpa-empty-text']);
                    } else {
                        $html .= html_writer::start_div('lpa-plans-list');
                        $themeemojis = ['ocean' => '🌊', 'sunset' => '🌅', 'forest' => '🌲', 'candy' => '🍭'];
                        foreach ($recentplans as $plan) {
                            $theme_icon = class_exists('\local_learningplan\api') ? \local_learningplan\api::render_plan_icon($plan) : ($themeemojis[$theme] ?? '🗺️');

                            $html .= html_writer::start_div('lpa-plan-row' . ($plan->visible ? '' : ' lpa-plan-draft'));
                            
                            $html .= html_writer::start_div('lpa-plan-info');
                            $html .= html_writer::start_div('lpa-plan-header-row');
                            $html .= html_writer::span($theme_icon, 'lpa-row-theme-icon');
                            $html .= html_writer::link(
                                new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $plan->id]),
                                format_string($plan->name),
                                ['class' => 'lpa-plan-name']
                            );
                            $html .= html_writer::end_div();
                            
                            $html .= html_writer::start_div('lpa-plan-meta');
                            $html .= html_writer::span('🎯 ' . $plan->stepcount . ' ' . get_string('steps', 'block_learningplan_admin'), 'lpa-pill');
                            $html .= html_writer::span('👥 ' . $plan->usercount . ' ' . get_string('learners', 'block_learningplan_admin'), 'lpa-pill');
                            if ($plan->visible) {
                                $html .= html_writer::span(get_string('visible', 'block_learningplan_admin'), 'lpa-pill lpa-pill-visible');
                            } else {
                                $html .= html_writer::span(get_string('hidden', 'block_learningplan_admin'), 'lpa-pill lpa-pill-hidden');
                            }
                            $html .= html_writer::end_div();
                            $html .= html_writer::end_div();

                            $html .= html_writer::start_div('lpa-plan-actions');
                            $html .= html_writer::link(
                                new moodle_url('/local/learningplan/index.php', ['id' => $plan->id]),
                                '🗺️',
                                ['class' => 'lpa-action-btn', 'title' => get_string('previewmap', 'block_learningplan_admin')]
                            );
                            $html .= html_writer::link(
                                new moodle_url('/local/learningplan/manage/edit_steps.php', ['planid' => $plan->id]),
                                '📑',
                                ['class' => 'lpa-action-btn', 'title' => get_string('editsteps', 'block_learningplan_admin')]
                            );
                            $html .= html_writer::link(
                                new moodle_url('/local/learningplan/manage/assign.php', ['planid' => $plan->id]),
                                '👥',
                                ['class' => 'lpa-action-btn', 'title' => get_string('assignlearners', 'block_learningplan_admin')]
                            );
                            $html .= html_writer::end_div();

                            $html .= html_writer::end_div(); // .lpa-plan-row
                        }
                        $html .= html_writer::end_div(); // .lpa-plans-list
                    }

                    $html .= html_writer::end_div(); // .lpa-recent-section
                }

                $html .= html_writer::end_div(); // .lpa-tab-panel
            }

            // Zero-dependency Client-Side Tab Switcher Script.
            $html .= '<script>
            (function(){
                var root = document.getElementById("' . $uniqueid . '");
                if (!root) return;
                var btns = root.querySelectorAll(".lpa-tab-btn");
                var panels = root.querySelectorAll(".lpa-tab-panel");
                btns.forEach(function(btn){
                    btn.addEventListener("click", function(){
                        var target = btn.getAttribute("data-tab");
                        btns.forEach(function(b){ b.classList.remove("active"); });
                        panels.forEach(function(p){ p.classList.remove("active"); });
                        btn.classList.add("active");
                        var activePanel = root.querySelector(\'.lpa-tab-panel[data-panel="\' + target + \'"]\');
                        if (activePanel) activePanel.classList.add("active");
                    });
                });
            })();
            </script>';

            $html .= html_writer::end_div(); // .block-learningplan-admin
            $this->content->text = $html;

        } else {
            // Learner Fallback Mode (when non-admin views dashboard).
            $this->content->text = $this->render_learner_view($USER->id);
        }

        return $this->content;
    }


    /**
     * Return a site-wide dashboard value, computing it via $builder only on a cache
     * miss. The whole‑site statistics and recent‑plans list are the same for every
     * admin, so this caps the work at one computation per 5‑minute TTL regardless of
     * how many admin pages are loaded.
     *
     * @param string $key cache key
     * @param callable $builder produces the value when the cache is cold
     * @return mixed
     */
    protected function get_cached_dashboard_data(string $key, callable $builder) {
        try {
            $cache = \cache::make('block_learningplan_admin', 'dashboard');
            $value = $cache->get($key);
            if ($value !== false) {
                return $value;
            }
            $value = $builder();
            $cache->set($key, $value);
            return $value;
        } catch (\Throwable $e) {
            // Never let a caching problem take the dashboard down.
            return $builder();
        }
    }

    /**
     * Compute summary statistics across all learning plans.
     *
     * @return stdClass
     */
    protected function get_admin_statistics(): stdClass {
        global $DB;

        $stats = new stdClass();
        $stats->totalplans = 0;
        $stats->activeplans = 0;
        $stats->draftplans = 0;
        $stats->totalsteps = 0;
        $stats->totallearners = 0;
        $stats->totalpoints = 0;
        $stats->totalstars = 0;
        $stats->completionrate = 0;

        try {
            $stats->totalplans = (int)$DB->count_records('local_learningplan_plan');
            $stats->activeplans = (int)$DB->count_records('local_learningplan_plan', ['visible' => 1]);
            $stats->draftplans = $stats->totalplans - $stats->activeplans;
            $stats->totalsteps = (int)$DB->count_records('local_learningplan_step');

            // Unique assigned learners across all plans.
            $assignments = $DB->get_records('local_learningplan_assignment');
            $userids = [];
            foreach ($assignments as $assignment) {
                if (class_exists('\local_learningplan\api')) {
                    $userids = array_merge($userids, \local_learningplan\api::expand_assignment_userids($assignment));
                } else {
                    if (!empty($assignment->userid)) {
                        $userids[] = $assignment->userid;
                    }
                }
            }
            $stats->totallearners = count(array_unique($userids));

            // Gamification totals.
            $pointsum = $DB->get_field_sql("SELECT COALESCE(SUM(pointsawarded), 0) FROM {local_learningplan_progress}");
            $stats->totalpoints = (int)$pointsum;

            $starsum = $DB->get_field_sql("SELECT COALESCE(SUM(starsearned), 0) FROM {local_learningplan_progress}");
            $stats->totalstars = (int)$starsum;

            // Overall completion percentage.
            $completedcount = (int)$DB->get_field_sql("SELECT COUNT(id) FROM {local_learningplan_progress} WHERE status = 'completed'");
            $totalprogressrows = (int)$DB->count_records('local_learningplan_progress');
            $stats->completionrate = $totalprogressrows > 0 ? (int)round(($completedcount / $totalprogressrows) * 100) : 0;

        } catch (\Exception $e) {
            // Graceful fallback if tables are not yet initialized.
        }

        return $stats;
    }

    /**
     * Compute visual analytics data including 7-day velocity, retention health, funnel, and gamification mastery.
     *
     * @return stdClass
     */
    protected function get_admin_analytics_data(): stdClass {
        global $DB;

        $data = new stdClass();
        $data->completed = 0;
        $data->inprogress = 0;
        $data->locked = 0;
        $data->totalsteps_tracked = 0;
        $data->completion_pct = 0;
        $data->dailyactivity = [];
        $data->maxdaily = 1;
        $data->totallast7days = 0;
        $data->totalpoints7days = 0;
        $data->topplans = [];
        
        // Retention health.
        $data->health_active = 0;
        $data->health_moderate = 0;
        $data->health_atrisk = 0;
        $data->health_total = 0;

        // Funnel stages.
        $data->funnel_enrolled = 0;
        $data->funnel_started = 0;
        $data->funnel_halfway = 0;
        $data->funnel_completed = 0;

        // Gamification Mastery.
        $data->stars_3 = 0;
        $data->stars_2 = 0;
        $data->stars_1 = 0;
        $data->stars_0 = 0;
        $data->totalbadges_awarded = 0;

        try {
            // 1. Status breakdown across all progress rows.
            $statusrows = $DB->get_records_sql("SELECT status, COUNT(id) AS cnt FROM {local_learningplan_progress} GROUP BY status");
            foreach ($statusrows as $row) {
                $count = (int)$row->cnt;
                if ($row->status === 'completed') {
                    $data->completed += $count;
                } else if ($row->status === 'unlocked' || $row->status === 'in_progress') {
                    $data->inprogress += $count;
                } else {
                    $data->locked += $count;
                }
            }
            $data->totalsteps_tracked = $data->completed + $data->inprogress + $data->locked;
            if ($data->totalsteps_tracked > 0) {
                $data->completion_pct = (int)round(($data->completed / $data->totalsteps_tracked) * 100);
            }

            // 2. 7-Day Activity Velocity & XP Momentum.
            $now = time();
            $maxval = 0;
            for ($i = 6; $i >= 0; $i--) {
                $daystart = strtotime("-$i days 00:00:00", $now);
                $dayend = strtotime("-$i days 23:59:59", $now);
                $daylabel = userdate($daystart, '%a');
                $daydate = userdate($daystart, '%b %d');

                $completions = (int)$DB->get_field_sql(
                    "SELECT COUNT(id) FROM {local_learningplan_progress} WHERE timecompleted >= :start AND timecompleted <= :end",
                    ['start' => $daystart, 'end' => $dayend]
                );

                $points = (int)$DB->get_field_sql(
                    "SELECT COALESCE(SUM(points), 0) FROM {local_learningplan_points_log} WHERE timecreated >= :start AND timecreated <= :end",
                    ['start' => $daystart, 'end' => $dayend]
                );

                if ($completions > $maxval) {
                    $maxval = $completions;
                }
                $data->totallast7days += $completions;
                $data->totalpoints7days += $points;

                $data->dailyactivity[] = (object)[
                    'label' => $daylabel,
                    'date' => $daydate,
                    'completions' => $completions,
                    'points' => $points,
                ];
            }
            $data->maxdaily = max(4, $maxval);

            // 3. Learner Retention & Activity Health (Active vs Moderate vs At-Risk).
            $assignments = $DB->get_records('local_learningplan_assignment');
            $userids = [];
            foreach ($assignments as $assignment) {
                if (class_exists('\local_learningplan\api')) {
                    $userids = array_merge($userids, \local_learningplan\api::expand_assignment_userids($assignment));
                } else if (!empty($assignment->userid)) {
                    $userids[] = $assignment->userid;
                }
            }
            $uniqueusers = array_unique($userids);
            $data->health_total = count($uniqueusers);

            if ($data->health_total > 0) {
                $sevendaysago = $now - (7 * 86400);
                $fourteendaysago = $now - (14 * 86400);

                $useractivity = $DB->get_records_sql(
                    "SELECT userid, MAX(COALESCE(timecompleted, timestarted)) AS lastactive
                       FROM {local_learningplan_progress}
                      WHERE timecompleted > 0 OR timestarted > 0
                   GROUP BY userid"
                );

                foreach ($uniqueusers as $uid) {
                    if (isset($useractivity[$uid]) && $useractivity[$uid]->lastactive >= $sevendaysago) {
                        $data->health_active++;
                    } else if (isset($useractivity[$uid]) && $useractivity[$uid]->lastactive >= $fourteendaysago) {
                        $data->health_moderate++;
                    } else {
                        $data->health_atrisk++;
                    }
                }
            }

            // 4. Progression Funnel Stages.
            $plans = $DB->get_records('local_learningplan_plan', ['visible' => 1]);
            $totalenrolledpairs = 0;
            $startedpairs = 0;
            $halfwaypairs = 0;
            $completedpairs = 0;

            foreach ($plans as $plan) {
                $pusers = class_exists('\local_learningplan\api') ? \local_learningplan\api::get_plan_userids($plan->id) : [];
                $stepcount = (int)$DB->count_records('local_learningplan_step', ['planid' => $plan->id]);
                if ($stepcount === 0 || empty($pusers)) {
                    continue;
                }

                $totalenrolledpairs += count($pusers);

                $progrows = $DB->get_records_sql(
                    "SELECT pr.userid, COUNT(pr.id) AS completedsteps
                       FROM {local_learningplan_progress} pr
                       JOIN {local_learningplan_step} s ON s.id = pr.stepid
                      WHERE s.planid = :planid AND pr.status = 'completed'
                   GROUP BY pr.userid",
                    ['planid' => $plan->id]
                );

                foreach ($pusers as $puid) {
                    $csteps = isset($progrows[$puid]) ? (int)$progrows[$puid]->completedsteps : 0;
                    if ($csteps > 0) {
                        $startedpairs++;
                    }
                    if ($csteps >= ceil($stepcount / 2)) {
                        $halfwaypairs++;
                    }
                    if ($csteps >= $stepcount) {
                        $completedpairs++;
                    }
                }
            }

            $data->funnel_enrolled = $totalenrolledpairs;
            $data->funnel_started = $startedpairs;
            $data->funnel_halfway = $halfwaypairs;
            $data->funnel_completed = $completedpairs;

            // 5. Gamification Stars Breakdown.
            $starrows = $DB->get_records_sql(
                "SELECT starsearned, COUNT(id) AS cnt 
                   FROM {local_learningplan_progress} 
                  WHERE status = 'completed' 
               GROUP BY starsearned"
            );
            foreach ($starrows as $sr) {
                $starval = (int)$sr->starsearned;
                $cnt = (int)$sr->cnt;
                if ($starval >= 3) {
                    $data->stars_3 += $cnt;
                } else if ($starval === 2) {
                    $data->stars_2 += $cnt;
                } else if ($starval === 1) {
                    $data->stars_1 += $cnt;
                } else {
                    $data->stars_0 += $cnt;
                }
            }

            // 6. Top performing plans with learner rates.
            $plans = $DB->get_records('local_learningplan_plan', ['visible' => 1], 'timemodified DESC', '*', 0, 8);
            foreach ($plans as $plan) {
                $stepcount = (int)$DB->count_records('local_learningplan_step', ['planid' => $plan->id]);
                $userids = class_exists('\local_learningplan\api') ? \local_learningplan\api::get_plan_userids($plan->id) : [];
                $usercount = count($userids);

                $totalexpected = $stepcount * max(1, $usercount);
                $completedsteps = 0;
                if ($stepcount > 0) {
                    $completedsteps = (int)$DB->get_field_sql(
                        "SELECT COUNT(pr.id) FROM {local_learningplan_progress} pr JOIN {local_learningplan_step} s ON s.id = pr.stepid WHERE s.planid = :planid AND pr.status = 'completed'",
                        ['planid' => $plan->id]
                    );
                }
                $rate = $totalexpected > 0 ? (int)round(($completedsteps / $totalexpected) * 100) : 0;

                $data->topplans[] = (object)[
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'usercount' => $usercount,
                    'stepcount' => $stepcount,
                    'rate' => min(100, $rate),
                    'completed' => $completedsteps,
                ];
            }

            usort($data->topplans, function($a, $b) {
                if ($b->rate !== $a->rate) {
                    return $b->rate <=> $a->rate;
                }
                return $b->usercount <=> $a->usercount;
            });
            $data->topplans = array_slice($data->topplans, 0, 3);

        } catch (\Exception $e) {
            // Graceful fallback on missing tables or queries.
        }

        return $data;
    }

    /**
     * Render rich SVG visual analytics graphics with actionable sub-views.
     *
     * @param stdClass $analytics
     * @return string HTML
     */
    protected function render_analytics_graphics(stdClass $analytics): string {
        $html = '';
        $html .= html_writer::start_div('lpa-analytics-container');

        // Sub-Navigation Pills within Analytics.
        $html .= html_writer::start_div('lpa-subtab-nav');
        $html .= '<button type="button" class="lpa-subtab-btn active" data-subtab="retention">' . get_string('subtab_retention', 'block_learningplan_admin') . '</button>';
        $html .= '<button type="button" class="lpa-subtab-btn" data-subtab="funnel">' . get_string('subtab_funnel', 'block_learningplan_admin') . '</button>';
        $html .= '<button type="button" class="lpa-subtab-btn" data-subtab="mastery">' . get_string('subtab_mastery', 'block_learningplan_admin') . '</button>';
        $html .= html_writer::end_div(); // .lpa-subtab-nav

        // SUB-PANEL 1: Progress Breakdown & Retention Health Matrix.
        $html .= html_writer::start_div('lpa-subtab-panel active', ['data-subpanel' => 'retention']);

        $total = $analytics->totalsteps_tracked;
        $r = 38;
        $c = 2 * M_PI * $r;

        $comp_pct = $total > 0 ? ($analytics->completed / $total) : 0;
        $prog_pct = $total > 0 ? ($analytics->inprogress / $total) : 0;
        $lock_pct = $total > 0 ? ($analytics->locked / $total) : 0;

        $comp_len = round($comp_pct * $c, 2);
        $prog_len = round($prog_pct * $c, 2);
        $lock_len = round($lock_pct * $c, 2);

        $comp_offset = 0;
        $prog_offset = -$comp_len;
        $lock_offset = -($comp_len + $prog_len);

        $pct_display = $analytics->completion_pct . '%';

        $html .= html_writer::start_div('lpa-chart-card lpa-chart-donut-card');
        $html .= html_writer::tag('div', '🎯 ' . get_string('progressdistribution', 'block_learningplan_admin'), ['class' => 'lpa-chart-card-title']);
        $html .= html_writer::start_div('lpa-donut-wrapper');
        $html .= html_writer::start_div('lpa-donut-ring-box');
        $svg = '<svg viewBox="0 0 100 100" class="lpa-donut-svg" aria-hidden="true">';
        $svg .= '<defs>';
        $svg .= '<linearGradient id="lpa-donut-comp-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#10b981"/><stop offset="100%" stop-color="#059669"/></linearGradient>';
        $svg .= '<linearGradient id="lpa-donut-prog-grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#0ea5e9"/><stop offset="100%" stop-color="#0284c7"/></linearGradient>';
        $svg .= '</defs>';
        $svg .= '<circle cx="50" cy="50" r="' . $r . '" class="lpa-donut-bg" fill="none" stroke-width="10" />';

        if ($total > 0) {
            if ($lock_len > 0) {
                $svg .= '<circle cx="50" cy="50" r="' . $r . '" class="lpa-donut-seg lpa-donut-locked" fill="none" stroke-width="10" stroke-dasharray="' . $lock_len . ' ' . ($c - $lock_len) . '" stroke-dashoffset="' . $lock_offset . '" transform="rotate(-90 50 50)" />';
            }
            if ($prog_len > 0) {
                $svg .= '<circle cx="50" cy="50" r="' . $r . '" class="lpa-donut-seg lpa-donut-prog" fill="none" stroke-width="10" stroke="url(#lpa-donut-prog-grad)" stroke-dasharray="' . $prog_len . ' ' . ($c - $prog_len) . '" stroke-dashoffset="' . $prog_offset . '" transform="rotate(-90 50 50)" />';
            }
            if ($comp_len > 0) {
                $svg .= '<circle cx="50" cy="50" r="' . $r . '" class="lpa-donut-seg lpa-donut-comp" fill="none" stroke-width="10" stroke="url(#lpa-donut-comp-grad)" stroke-dasharray="' . $comp_len . ' ' . ($c - $comp_len) . '" stroke-dashoffset="' . $comp_offset . '" transform="rotate(-90 50 50)" />';
            }
        }
        $svg .= '</svg>';
        $html .= $svg;

        $html .= '<div class="lpa-donut-center">';
        $html .= '<span class="lpa-donut-center-val">' . $pct_display . '</span>';
        $html .= '<span class="lpa-donut-center-lbl">' . get_string('overallcompletion', 'block_learningplan_admin') . '</span>';
        $html .= '</div>';
        $html .= html_writer::end_div(); // .lpa-donut-ring-box

        $html .= html_writer::start_div('lpa-donut-legend');
        $comp_str_pct = $total > 0 ? round($comp_pct * 100) : 0;
        $prog_str_pct = $total > 0 ? round($prog_pct * 100) : 0;
        $lock_str_pct = $total > 0 ? round($lock_pct * 100) : 0;

        $html .= '<div class="lpa-legend-item"><span class="lpa-legend-dot lpa-dot-success"></span><span class="lpa-legend-label">' . get_string('status_completed', 'block_learningplan_admin') . '</span><span class="lpa-legend-val">' . $analytics->completed . ' <span class="lpa-legend-pct">(' . $comp_str_pct . '%)</span></span></div>';
        $html .= '<div class="lpa-legend-item"><span class="lpa-legend-dot lpa-dot-info"></span><span class="lpa-legend-label">' . get_string('status_inprogress', 'block_learningplan_admin') . '</span><span class="lpa-legend-val">' . $analytics->inprogress . ' <span class="lpa-legend-pct">(' . $prog_str_pct . '%)</span></span></div>';
        $html .= '<div class="lpa-legend-item"><span class="lpa-legend-dot lpa-dot-muted"></span><span class="lpa-legend-label">' . get_string('status_locked', 'block_learningplan_admin') . '</span><span class="lpa-legend-val">' . $analytics->locked . ' <span class="lpa-legend-pct">(' . $lock_str_pct . '%)</span></span></div>';
        $html .= html_writer::end_div(); // .lpa-donut-legend
        $html .= html_writer::end_div(); // .lpa-donut-wrapper
        $html .= html_writer::end_div(); // .lpa-chart-card

        $html .= html_writer::start_div('lpa-chart-card');
        $html .= html_writer::start_div('lpa-chart-header-row');
        $html .= html_writer::tag('div', '🛡️ ' . get_string('learnerhealth', 'block_learningplan_admin'), ['class' => 'lpa-chart-card-title']);
        $html .= html_writer::tag('span', $analytics->health_total . ' ' . get_string('learners', 'block_learningplan_admin'), ['class' => 'lpa-chart-badge']);
        $html .= html_writer::end_div();

        $htotal = max(1, $analytics->health_total);
        $act_pct = round(($analytics->health_active / $htotal) * 100);
        $mod_pct = round(($analytics->health_moderate / $htotal) * 100);
        $risk_pct = max(0, 100 - $act_pct - $mod_pct);

        $html .= html_writer::start_div('lpa-health-bar-wrapper');
        $html .= '<div class="lpa-health-seg lpa-health-act" style="width:' . $act_pct . '%;" title="Active: ' . $analytics->health_active . ' (' . $act_pct . '%)"></div>';
        $html .= '<div class="lpa-health-seg lpa-health-mod" style="width:' . $mod_pct . '%;" title="Moderate: ' . $analytics->health_moderate . ' (' . $mod_pct . '%)"></div>';
        $html .= '<div class="lpa-health-seg lpa-health-risk" style="width:' . $risk_pct . '%;" title="At-Risk: ' . $analytics->health_atrisk . ' (' . $risk_pct . '%)"></div>';
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('lpa-health-list');
        $html .= '<div class="lpa-health-row"><div class="lpa-health-indicator lpa-dot-success"></div><span class="lpa-health-name">' . get_string('health_active', 'block_learningplan_admin') . '</span><span class="lpa-health-val">' . $analytics->health_active . ' <span class="lpa-legend-pct">(' . $act_pct . '%)</span></span></div>';
        $html .= '<div class="lpa-health-row"><div class="lpa-health-indicator lpa-dot-warning"></div><span class="lpa-health-name">' . get_string('health_moderate', 'block_learningplan_admin') . '</span><span class="lpa-health-val">' . $analytics->health_moderate . ' <span class="lpa-legend-pct">(' . $mod_pct . '%)</span></span></div>';
        $html .= '<div class="lpa-health-row"><div class="lpa-health-indicator lpa-dot-danger"></div><span class="lpa-health-name">' . get_string('health_atrisk', 'block_learningplan_admin') . '</span><span class="lpa-health-val lpa-text-danger">' . $analytics->health_atrisk . ' <span class="lpa-legend-pct">(' . $risk_pct . '%)</span></span></div>';
        $html .= html_writer::end_div();

        $reporturl = new moodle_url('/local/learningplan/manage/report.php');
        $html .= html_writer::link($reporturl, '📊 ' . get_string('viewreports', 'block_learningplan_admin') . ' ➔', ['class' => 'lpa-inline-link mt-2']);

        $html .= html_writer::end_div(); // .lpa-chart-card
        $html .= html_writer::end_div(); // .lpa-subtab-panel (retention)

        // SUB-PANEL 3: Progression Funnel Stages.
        $html .= html_writer::start_div('lpa-subtab-panel', ['data-subpanel' => 'funnel']);
        $html .= html_writer::start_div('lpa-chart-card');
        $html .= html_writer::tag('div', '🎯 ' . get_string('completionfunnel', 'block_learningplan_admin'), ['class' => 'lpa-chart-card-title']);

        $ftotal = max(1, $analytics->funnel_enrolled);
        $f_enrolled_pct = 100;
        $f_started_pct = round(($analytics->funnel_started / $ftotal) * 100);
        $f_halfway_pct = round(($analytics->funnel_halfway / $ftotal) * 100);
        $f_comp_pct = round(($analytics->funnel_completed / $ftotal) * 100);

        $funnelstages = [
            ['label' => '1. Enrolled / Assigned', 'count' => $analytics->funnel_enrolled, 'pct' => $f_enrolled_pct, 'class' => 'lpa-fnl-1'],
            ['label' => '2. Started (≥ 1 Step)', 'count' => $analytics->funnel_started, 'pct' => $f_started_pct, 'class' => 'lpa-fnl-2'],
            ['label' => '3. Halfway (≥ 50%)', 'count' => $analytics->funnel_halfway, 'pct' => $f_halfway_pct, 'class' => 'lpa-fnl-3'],
            ['label' => '4. 100% Completed', 'count' => $analytics->funnel_completed, 'pct' => $f_comp_pct, 'class' => 'lpa-fnl-4'],
        ];

        $html .= html_writer::start_div('lpa-funnel-list');
        foreach ($funnelstages as $stage) {
            $html .= html_writer::start_div('lpa-funnel-row');
            $html .= '<div class="lpa-funnel-header"><span class="lpa-funnel-lbl">' . $stage['label'] . '</span><span class="lpa-funnel-val">' . $stage['count'] . ' (' . $stage['pct'] . '%)</span></div>';
            $html .= '<div class="lpa-funnel-track"><div class="lpa-funnel-fill ' . $stage['class'] . '" style="width:' . max(4, $stage['pct']) . '%;"></div></div>';
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div(); // .lpa-funnel-list
        $html .= html_writer::end_div(); // .lpa-chart-card
        $html .= html_writer::end_div(); // .lpa-subtab-panel (funnel)

        // SUB-PANEL 4: Gamification & Stars Mastery.
        $html .= html_writer::start_div('lpa-subtab-panel', ['data-subpanel' => 'mastery']);
        $html .= html_writer::start_div('lpa-chart-card');
        $html .= html_writer::tag('div', '⭐ ' . get_string('starsmastery', 'block_learningplan_admin'), ['class' => 'lpa-chart-card-title']);

        $totalstarcompletions = max(1, $analytics->stars_3 + $analytics->stars_2 + $analytics->stars_1 + $analytics->stars_0);
        $s3_pct = round(($analytics->stars_3 / $totalstarcompletions) * 100);
        $s2_pct = round(($analytics->stars_2 / $totalstarcompletions) * 100);
        $s1_pct = round(($analytics->stars_1 / $totalstarcompletions) * 100);
        $s0_pct = max(0, 100 - $s3_pct - $s2_pct - $s1_pct);

        $starranks = [
            ['stars' => '⭐⭐⭐', 'label' => get_string('stars_3', 'block_learningplan_admin'), 'count' => $analytics->stars_3, 'pct' => $s3_pct, 'color' => '#eab308'],
            ['stars' => '⭐⭐', 'label' => get_string('stars_2', 'block_learningplan_admin'), 'count' => $analytics->stars_2, 'pct' => $s2_pct, 'color' => '#3b82f6'],
            ['stars' => '⭐', 'label' => get_string('stars_1', 'block_learningplan_admin'), 'count' => $analytics->stars_1, 'pct' => $s1_pct, 'color' => '#10b981'],
            ['stars' => '⚪', 'label' => get_string('stars_0', 'block_learningplan_admin'), 'count' => $analytics->stars_0, 'pct' => $s0_pct, 'color' => '#94a3b8'],
        ];

        $html .= html_writer::start_div('lpa-mastery-list');
        foreach ($starranks as $rk) {
            $html .= html_writer::start_div('lpa-mastery-row');
            $html .= '<div class="lpa-mastery-header"><span class="lpa-mastery-stars">' . $rk['stars'] . ' ' . $rk['label'] . '</span><span class="lpa-mastery-val">' . $rk['count'] . ' (' . $rk['pct'] . '%)</span></div>';
            $html .= '<div class="lpa-mastery-track"><div class="lpa-mastery-fill" style="width:' . max(3, $rk['pct']) . '%; background:' . $rk['color'] . ';"></div></div>';
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div(); // .lpa-mastery-list
        $html .= html_writer::end_div(); // .lpa-chart-card
        $html .= html_writer::end_div(); // .lpa-subtab-panel (mastery)

        // Subtab Switcher JS.
        $html .= '<script>
        (function(){
            var container = document.currentScript ? document.currentScript.parentElement : document.querySelector(".lpa-analytics-container");
            if (!container) return;
            var subbtns = container.querySelectorAll(".lpa-subtab-btn");
            var subpanels = container.querySelectorAll(".lpa-subtab-panel");
            subbtns.forEach(function(sb){
                sb.addEventListener("click", function(){
                    var target = sb.getAttribute("data-subtab");
                    subbtns.forEach(function(b){ b.classList.remove("active"); });
                    subpanels.forEach(function(p){ p.classList.remove("active"); });
                    sb.classList.add("active");
                    var act = container.querySelector(\'.lpa-subtab-panel[data-subpanel="\' + target + \'"]\');
                    if (act) act.classList.add("active");
                });
            });
        })();
        </script>';

        $html .= html_writer::end_div(); // .lpa-analytics-container
        return $html;
    }

    /**
     * Get recent learning plans with step and learner counts.
     *
     * @param int $limit
     * @return array
     */
    protected function get_recent_plans(int $limit = 5): array {
        global $DB;

        try {
            $limitnum = $limit > 0 ? $limit : 0;
            $plans = $DB->get_records('local_learningplan_plan', null, 'timemodified DESC, timecreated DESC', '*', 0, $limitnum);
            $result = [];

            foreach ($plans as $plan) {
                $planobj = new stdClass();
                $planobj->id = $plan->id;
                $planobj->name = $plan->name;
                $planobj->coverimage = !empty($plan->coverimage) ? $plan->coverimage : 'ocean';
                $planobj->visible = (int)$plan->visible;
                $planobj->stepcount = (int)$DB->count_records('local_learningplan_step', ['planid' => $plan->id]);

                $userids = [];
                if (class_exists('\local_learningplan\api')) {
                    $userids = \local_learningplan\api::get_plan_userids($plan->id);
                }
                $planobj->usercount = count($userids);

                $result[] = $planobj;
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Render learner view showing assigned plans and direct progress links.
     *
     * @param int $userid
     * @return string HTML
     */
    protected function render_learner_view(int $userid): string {
        global $OUTPUT;

        $html = html_writer::start_div('block-learningplan-admin block-learningplan-learner');

        $userplans = [];
        if (class_exists('\local_learningplan\api')) {
            $userplans = \local_learningplan\api::get_user_plans($userid);
        }

        if (empty($userplans)) {
            $html .= html_writer::tag('div', '🗺️ ' . get_string('nolearningplansassigned', 'block_learningplan_admin'), ['class' => 'lpa-empty-text']);
        } else {
            $html .= html_writer::tag('h6', '🎯 ' . get_string('mylearningplans', 'block_learningplan_admin'), ['class' => 'lpa-section-title']);
            $html .= html_writer::start_div('lpa-learner-plans-list');

            foreach ($userplans as $plan) {
                $totals = \local_learningplan\api::get_user_plan_totals($userid, $plan->id);
                $theme = !empty($plan->coverimage) ? $plan->coverimage : 'ocean';
                $theme_icon = class_exists('\local_learningplan\api') ? \local_learningplan\api::render_plan_icon($plan) : (['ocean' => '🌊', 'sunset' => '🌅', 'forest' => '🌲', 'candy' => '🍭'][$theme] ?? '🗺️');

                $html .= html_writer::start_div('lpa-learner-plan-card');
                $html .= html_writer::start_div('lpa-learner-card-header');
                $html .= html_writer::span($theme_icon, 'lpa-row-theme-icon');
                $html .= html_writer::tag('div', format_string($plan->name), ['class' => 'lpa-plan-name']);
                $html .= html_writer::span($totals->percent . '%', 'lpa-percent-pill');
                $html .= html_writer::end_div();
                
                $html .= html_writer::start_div('lpa-plan-meta mt-2');
                $html .= html_writer::span('🎯 ' . $totals->completedsteps . '/' . $totals->totalsteps . ' ' . get_string('steps', 'block_learningplan_admin'), 'lpa-pill');
                $html .= html_writer::span('🏆 ' . $totals->points . ' XP', 'lpa-pill');
                if ($totals->maxstars > 0) {
                    $html .= html_writer::span('⭐ ' . $totals->stars . '/' . $totals->maxstars, 'lpa-pill');
                }
                $html .= html_writer::end_div();

                $html .= html_writer::start_div('progress lpa-mini-progress mt-2');
                $html .= html_writer::tag('div', '', [
                    'class' => 'progress-bar',
                    'role' => 'progressbar',
                    'style' => 'width: ' . min(100, max(0, $totals->percent)) . '%;',
                    'aria-valuenow' => $totals->percent,
                    'aria-valuemin' => 0,
                    'aria-valuemax' => 100,
                ]);
                $html .= html_writer::end_div();

                $btnlabel = $totals->completedsteps > 0
                    ? get_string('continuejourney', 'block_learningplan_admin')
                    : get_string('startjourney', 'block_learningplan_admin');

                $html .= html_writer::link(
                    new moodle_url('/local/learningplan/index.php', ['id' => $plan->id]),
                    $btnlabel . ' ➔',
                    ['class' => 'lpa-learner-btn btn btn-primary mt-2']
                );

                $html .= html_writer::end_div(); // .lpa-learner-plan-card
            }

            $html .= html_writer::end_div(); // .lpa-learner-plans-list
        }

        $html .= html_writer::end_div();
        return $html;
    }
}
