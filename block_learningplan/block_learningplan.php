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
 * Main block class for My Learning Path (Student Block).
 *
 * @package    block_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Student-facing block displaying assigned learning paths, progress, points, and map links.
 */
class block_learningplan extends block_base {

    /**
     * Initialize block properties.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_learningplan');
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
        ];
    }

    /**
     * Customization of block title based on instance settings.
     */
    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        } else {
            $this->title = get_string('pluginname', 'block_learningplan');
        }
    }

    /**
     * Generate content for the block.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $USER, $CFG, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Verify local_learningplan availability.
        if (!class_exists('\local_learningplan\api') && file_exists($CFG->dirroot . '/local/learningplan/classes/api.php')) {
            require_once($CFG->dirroot . '/local/learningplan/classes/api.php');
        }

        // This block only displays already-computed progress. Completion catch-up
        // for the learner happens when they open their plan map and via the
        // sync_progress scheduled task — never here, so the block stays cheap on
        // every page it appears on.

        $systemcontext = context_system::instance();
        $canmanage = has_capability('local/learningplan:manage', $systemcontext);

        $userplans = [];
        if (class_exists('\local_learningplan\api')) {
            $userplans = \local_learningplan\api::get_user_plans((int)$USER->id);
            // If admin is previewing and has no personal assigned plans, show active plans.
            if (empty($userplans) && $canmanage) {
                $userplans = \local_learningplan\api::get_plans(true);
            }
        }

        $maxplans = isset($this->config->maxplans) ? (int)$this->config->maxplans : 6;
        $showpoints = !isset($this->config->showpoints) || !empty($this->config->showpoints);
        $showstars = !isset($this->config->showstars) || !empty($this->config->showstars);
        $shownextstep = !isset($this->config->shownextstep) || !empty($this->config->shownextstep);

        $viewmorelabel = 'View more';
        if (get_string_manager()->string_exists('viewmore', 'block_learningplan')) {
            $str = get_string('viewmore', 'block_learningplan');
            if (strpos($str, '[[') !== 0) {
                $viewmorelabel = $str;
            }
        }

        if (empty($userplans)) {
            $html = html_writer::start_div('block-student-learningplan');
            $html .= html_writer::start_div('lp-student-empty text-center p-4');
            $emptyicon = '🗺️';
            $html .= html_writer::tag('div', $emptyicon, ['class' => 'lp-empty-emoji mb-2']);
            $html .= html_writer::tag('h6', get_string('nolearningplansassigned', 'block_learningplan'), ['class' => 'fw-bold font-weight-bold mb-1']);
            $html .= html_writer::tag('p', get_string('nolearningplansassigned_desc', 'block_learningplan'), ['class' => 'text-muted small mb-0']);
            $html .= html_writer::start_div('lp-viewmore-container text-center d-flex justify-content-center w-100 mt-3');
            $html .= html_writer::link(
                new moodle_url('/local/learningplan/index.php'),
                $viewmorelabel . ' ➔',
                ['class' => 'lp-viewmore-btn btn btn-primary']
            );
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $this->content->text = $html;
            return $this->content;
        }

        if ($maxplans > 0 && count($userplans) > $maxplans) {
            $userplans = array_slice($userplans, 0, $maxplans);
        }

        $plancount = count($userplans);
        $sliderid = 'lp-slider-' . (!empty($this->instance->id) ? $this->instance->id : uniqid());

        $html = html_writer::start_div('block-student-learningplan', ['id' => $sliderid]);
        $html .= html_writer::start_div('lp-slider-wrapper');
        $html .= html_writer::start_div('lp-slider-track' . ($plancount === 1 ? ' single-item' : ''), [
            'data-slider-track' => '1',
            'tabindex' => '0',
            'role' => 'region',
            'aria-label' => get_string('pluginname', 'block_learningplan'),
        ]);

        $themeemojis = [
            'ocean'  => '🌊',
            'sunset' => '🌅',
            'forest' => '🌲',
            'candy'  => '🍭',
        ];

        $itemindex = 0;
        foreach ($userplans as $plan) {
            $totals = \local_learningplan\api::get_user_plan_totals((int)$USER->id, $plan->id);
            $theme = !empty($plan->coverimage) ? $plan->coverimage : 'ocean';
            $plan_icon = class_exists('\local_learningplan\api') ? \local_learningplan\api::render_plan_icon($plan) : ($themeemojis[$theme] ?? '🗺️');
            $iscompleted = ($totals->totalsteps > 0 && $totals->completedsteps >= $totals->totalsteps);

            // Find current active / next available step.
            $nextstepname = '';
            if ($shownextstep && !$iscompleted) {
                $chapters = \local_learningplan\api::get_chapters_with_steps($plan->id);
                $progressmap = \local_learningplan\api::get_user_progress((int)$USER->id, $plan->id);

                foreach ($chapters as $chapter) {
                    foreach ($chapter->steps as $step) {
                        $prow = $progressmap[$step->id] ?? null;
                        $status = $prow ? $prow->status : 'locked';
                        if ($status === 'available' || $status === 'inprogress') {
                            $nextstepname = format_string($step->title);
                            break 2;
                        }
                    }
                }
            }

            $mapurl = new moodle_url('/local/learningplan/index.php', ['id' => $plan->id]);

            $html .= html_writer::start_div('lp-slider-item', ['data-index' => $itemindex]);
            $html .= html_writer::start_div('lp-student-card' . ($iscompleted ? ' is-completed' : ''));

            // Top Header: Theme Icon + Plan Title + Status Badge.
            $html .= html_writer::start_div('lp-card-top');
            $html .= html_writer::start_div('lp-card-title-group');
            $html .= html_writer::span($plan_icon, 'lp-theme-pill-icon');
            $html .= html_writer::start_div('lp-card-title-wrap');
            $html .= html_writer::link(
                $mapurl,
                format_string($plan->name),
                ['class' => 'lp-student-plan-title', 'title' => get_string('openpath', 'block_learningplan')]
            );
            $html .= html_writer::end_div(); // .lp-card-title-wrap
            $html .= html_writer::end_div(); // .lp-card-title-group

            if ($iscompleted) {
                $html .= '<span class="lp-completed-pill">🏆 ' . get_string('completed', 'block_learningplan') . '</span>';
            } else {
                $html .= '<span class="lp-percent-badge">' . $totals->percent . '%</span>';
            }
            $html .= html_writer::end_div(); // .lp-card-top

            // Progress Bar.
            $html .= html_writer::start_div('progress lp-student-progress');
            $html .= html_writer::tag('div', '', [
                'class' => 'progress-bar ' . ($iscompleted ? 'bg-success' : 'bg-primary'),
                'role' => 'progressbar',
                'style' => 'width: ' . min(100, max(0, $totals->percent)) . '%;',
                'aria-valuenow' => $totals->percent,
                'aria-valuemin' => 0,
                'aria-valuemax' => 100,
            ]);
            $html .= html_writer::end_div();

            // Gamification Chips Row.
            $html .= html_writer::start_div('lp-card-chips-row');

            // Steps count chip.
            $html .= html_writer::start_div('lp-chip lp-chip-steps');
            $html .= '🎯 <span class="lp-chip-num">' . $totals->completedsteps . '/' . $totals->totalsteps . '</span> ' . get_string('steps', 'block_learningplan');
            $html .= html_writer::end_div();

            // Points chip.
            if ($showpoints) {
                $html .= html_writer::start_div('lp-chip lp-chip-points');
                $html .= '🏆 <span class="lp-chip-num">' . number_format($totals->points) . '</span> ' . get_string('points', 'block_learningplan');
                $html .= html_writer::end_div();
            }

            // Stars chip.
            if ($showstars && $totals->maxstars > 0) {
                $html .= html_writer::start_div('lp-chip lp-chip-stars');
                $html .= '⭐ <span class="lp-chip-num">' . $totals->stars . '</span>/' . $totals->maxstars;
                $html .= html_writer::end_div();
            }
            $html .= html_writer::end_div(); // .lp-card-chips-row

            // Next Step Preview (if applicable).
            if (!empty($nextstepname)) {
                $html .= html_writer::start_div('lp-next-step-box');
                $html .= '<span class="lp-next-pulse"></span>';
                $html .= html_writer::start_div('lp-next-step-content');
                $html .= '<span class="lp-next-label">' . get_string('nextstep', 'block_learningplan') . ':</span> ';
                $html .= html_writer::tag('span', $nextstepname, ['class' => 'lp-next-title']);
                $html .= html_writer::end_div();
                $html .= html_writer::end_div();
            } else if ($iscompleted) {
                $html .= html_writer::start_div('lp-completed-box');
                $html .= '🎉 ' . get_string('allcompleted_desc', 'block_learningplan');
                $html .= html_writer::end_div();
            }

            // Call to action button: Continue / Start Journey.
            $btnlabel = $totals->completedsteps > 0
                ? get_string('continuejourney', 'block_learningplan')
                : get_string('startjourney', 'block_learningplan');

            $html .= html_writer::link(
                $mapurl,
                $btnlabel . ' ➔',
                ['class' => 'lp-journey-btn btn btn-primary']
            );

            $html .= html_writer::end_div(); // .lp-student-card
            $html .= html_writer::end_div(); // .lp-slider-item
            $itemindex++;
        }

        $html .= html_writer::end_div(); // .lp-slider-track

        // Bottom Dotted Slider Pagination (if more than 1 plan).
        if ($plancount > 1) {
            $html .= html_writer::start_div('lp-slider-dots', ['data-slider-dots' => '1', 'role' => 'tablist']);
            for ($d = 0; $d < $plancount; $d++) {
                $html .= html_writer::tag('button', '', [
                    'class' => 'lp-slider-dot' . ($d === 0 ? ' active' : ''),
                    'type' => 'button',
                    'data-dot-index' => $d,
                    'aria-label' => 'Slide ' . ($d + 1),
                ]);
            }
            $html .= html_writer::end_div(); // .lp-slider-dots
        }

        $html .= html_writer::end_div(); // .lp-slider-wrapper

        // View More Button linking to /local/learningplan/index.php
        $viewallurl = new moodle_url('/local/learningplan/index.php');
        $html .= html_writer::start_div('lp-viewmore-container text-center d-flex justify-content-center w-100 mt-3');
        $html .= html_writer::link(
            $viewallurl,
            $viewmorelabel . ' ➔',
            ['class' => 'lp-viewmore-btn btn btn-primary']
        );
        $html .= html_writer::end_div();

        // Inline Smooth Drag & Touch Slider JS controller.
        if ($plancount > 1) {
            $html .= html_writer::tag('script', "
(function() {
    function initLpSlider() {
        var container = document.getElementById('{$sliderid}');
        if (!container) return;
        var track = container.querySelector('[data-slider-track]');
        var dots = container.querySelectorAll('.lp-slider-dot');
        var items = container.querySelectorAll('.lp-slider-item');
        if (!track || items.length === 0) return;

        var isDown = false;
        var startX = 0;
        var scrollLeft = 0;
        var hasDragged = false;

        // Mouse Drag to Slide
        track.addEventListener('mousedown', function(e) {
            isDown = true;
            hasDragged = false;
            track.classList.add('is-dragging');
            startX = e.pageX - track.offsetLeft;
            scrollLeft = track.scrollLeft;
        });

        track.addEventListener('mouseleave', function() {
            if (isDown) {
                isDown = false;
                track.classList.remove('is-dragging');
            }
        });

        track.addEventListener('mouseup', function() {
            if (isDown) {
                isDown = false;
                track.classList.remove('is-dragging');
            }
        });

        track.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - track.offsetLeft;
            var walk = (x - startX) * 1.5;
            if (Math.abs(walk) > 5) {
                hasDragged = true;
            }
            track.scrollLeft = scrollLeft - walk;
        });

        // Prevent unintentional clicks when dragging
        track.addEventListener('click', function(e) {
            if (hasDragged) {
                e.preventDefault();
                e.stopPropagation();
                hasDragged = false;
            }
        }, true);

        // Update active dot on scroll
        function updateActiveDot() {
            var trackScroll = track.scrollLeft;
            var trackWidth = track.clientWidth;
            var closestIndex = 0;
            var minDiff = Infinity;

            items.forEach(function(item, idx) {
                var itemLeft = item.offsetLeft - track.offsetLeft;
                var diff = Math.abs(itemLeft - trackScroll);
                if (diff < minDiff) {
                    minDiff = diff;
                    closestIndex = idx;
                }
            });

            dots.forEach(function(dot, idx) {
                if (idx === closestIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }

        track.addEventListener('scroll', updateActiveDot, { passive: true });

        // Click Dot to Slide
        dots.forEach(function(dot, idx) {
            dot.addEventListener('click', function() {
                if (items[idx]) {
                    var targetLeft = items[idx].offsetLeft - track.offsetLeft;
                    track.scrollTo({
                        left: targetLeft,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLpSlider);
    } else {
        initLpSlider();
    }
})();
");
        }

        $html .= html_writer::end_div(); // .block-student-learningplan

        $this->content->text = $html;
        return $this->content;
    }
}
