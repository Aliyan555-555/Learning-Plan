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
 * Map plans/chapters to core Moodle badges, auto-generate badges, and sync awards.
 *
 * @package    local_learningplan
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\badges;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:manage', $context);

// Creating, activating and issuing Moodle badges is governed by the core badge
// capabilities, checked here at the system context exactly as core's own site
// badge management does. Holding local/learningplan:manage alone is not enough.
$cancreatebadges = has_capability('moodle/badges:createbadge', $context);
$canconfigurebadges = has_capability('moodle/badges:configuredetails', $context);
$canawardbadges = has_capability('moodle/badges:awardbadge', $context);

$planid = optional_param('planid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

global $DB, $PAGE, $OUTPUT, $CFG;

$pageurl = new moodle_url('/local/learningplan/manage/badges.php', array_filter(['planid' => $planid]));
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('badgemapping', 'local_learningplan'));
$PAGE->set_heading(get_string('badgemapping', 'local_learningplan'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

/**
 * Render a server-side confirmation page for a badge management action and stop.
 *
 * @param string $message
 * @param moodle_url $continueurl already carries sesskey + confirm=1
 * @param int $headerplanid
 */
$lp_confirm_page = function(string $message, moodle_url $continueurl, int $headerplanid) use ($OUTPUT, $pageurl) {
    echo $OUTPUT->header();
    if ($headerplanid) {
        echo \local_learningplan\navigation::render_plan_context_header($headerplanid, 'badges');
    } else {
        echo \local_learningplan\navigation::render_global_header('badges');
    }
    echo $OUTPUT->confirm($message, $continueurl, $pageurl);
    echo $OUTPUT->footer();
    exit;
};

// Delete a plan/chapter -> badge mapping. Removing the local mapping row does not
// touch the badge itself, so local/learningplan:manage is sufficient.
if ($delete) {
    $mapping = $DB->get_record('local_learningplan_badge', ['id' => $delete], '*', MUST_EXIST);
    if ($planid && (int)$mapping->planid !== $planid) {
        throw new \moodle_exception('invalidrecordunknown', 'error');
    }
    if (!$confirm) {
        $lp_confirm_page(
            get_string('deletebadgemappingconfirm', 'local_learningplan'),
            new moodle_url($pageurl, ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]),
            (int)$mapping->planid
        );
    }
    require_sesskey();
    $DB->delete_records('local_learningplan_badge', ['id' => $delete]);
    redirect($pageurl, get_string('deleted'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Auto-generate a badge per chapter plus a plan-completion badge.
if ($action === 'autogen' && $planid) {
    require_capability('moodle/badges:createbadge', $context);
    require_capability('moodle/badges:configuredetails', $context);
    require_capability('moodle/badges:awardbadge', $context);
    api::get_plan($planid);
    if (!$confirm) {
        $lp_confirm_page(
            get_string('autogenbadgesconfirm', 'local_learningplan'),
            new moodle_url($pageurl, ['planid' => $planid, 'action' => 'autogen', 'confirm' => 1, 'sesskey' => sesskey()]),
            $planid
        );
    }
    require_sesskey();
    $created = badges::auto_generate_for_plan($planid);
    redirect($pageurl, get_string('badgesgenerated', 'local_learningplan', $created), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Retroactively award mapped badges to every eligible learner on the plan.
if ($action === 'sync' && $planid) {
    require_capability('moodle/badges:awardbadge', $context);
    api::get_plan($planid);
    if (!$confirm) {
        $lp_confirm_page(
            get_string('syncbadgesconfirm', 'local_learningplan'),
            new moodle_url($pageurl, ['planid' => $planid, 'action' => 'sync', 'confirm' => 1, 'sesskey' => sesskey()]),
            $planid
        );
    }
    require_sesskey();
    $awarded = badges::sync_plan_badges_for_all_users($planid);
    redirect($pageurl, get_string('badgessynced', 'local_learningplan', $awarded), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Quick create a custom site badge and link it to a milestone.
if ($action === 'quickcreate' && confirm_sesskey()) {
    require_capability('moodle/badges:createbadge', $context);
    require_capability('moodle/badges:configuredetails', $context);

    $badgename = required_param('badgename', PARAM_TEXT);
    $badgedesc = optional_param('badgedesc', '', PARAM_TEXT);
    $badgeicon = optional_param('badgeicon', 'icon-01.png', PARAM_FILE);
    $targetplanid = optional_param('quickplanid', $planid, PARAM_INT);
    $targetchapterid = optional_param('quickchapterid', 0, PARAM_INT) ?: null;

    if (trim($badgename) !== '') {
        $bid = badges::create_gamified_badge($badgename, $badgedesc, $badgeicon);

        if ($targetplanid && $DB->record_exists('local_learningplan_plan', ['id' => $targetplanid])) {
            if ($targetchapterid && !$DB->record_exists('local_learningplan_chapter',
                    ['id' => $targetchapterid, 'planid' => $targetplanid])) {
                $targetchapterid = null;
            }
            $criteria = $targetchapterid ? 'chapter_complete' : 'plan_complete';
            $existing = $DB->get_record('local_learningplan_badge', [
                'planid' => $targetplanid,
                'criteria' => $criteria,
                'chapterid' => $targetchapterid,
            ]);
            if ($existing) {
                $existing->badgeid = $bid;
                $DB->update_record('local_learningplan_badge', $existing);
            } else {
                $DB->insert_record('local_learningplan_badge', (object)[
                    'planid' => $targetplanid,
                    'chapterid' => $targetchapterid,
                    'badgeid' => $bid,
                    'criteria' => $criteria,
                ]);
            }
            if ($canawardbadges) {
                badges::sync_plan_badges_for_all_users($targetplanid);
            }
        }

        redirect($pageurl, get_string('badgecreatedmapped', 'local_learningplan'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Handle manual "link an existing badge" form submission.
if (($data = data_submitted()) && confirm_sesskey() && empty($action)) {
    require_capability('moodle/badges:configuredetails', $context);

    $linkplanid = (int)($data->planid ?? 0);
    $badgeid = (int)($data->badgeid ?? 0);
    $chapterid = ((int)($data->chapterid ?? 0)) ?: null;
    $criteria = $chapterid ? 'chapter_complete' : 'plan_complete';

    $badgerec = $badgeid ? $DB->get_record('badge', ['id' => $badgeid, 'type' => BADGE_TYPE_SITE]) : false;

    if ($linkplanid && $badgerec && $DB->record_exists('local_learningplan_plan', ['id' => $linkplanid])) {
        if ($chapterid && !$DB->record_exists('local_learningplan_chapter',
                ['id' => $chapterid, 'planid' => $linkplanid])) {
            $chapterid = null;
            $criteria = 'plan_complete';
        }

        // Activate the badge so it can be issued (capability re-checked inside).
        badges::ensure_badge_active($badgeid);

        $existing = $DB->get_record('local_learningplan_badge', [
            'planid' => $linkplanid,
            'criteria' => $criteria,
            'chapterid' => $chapterid,
        ]);
        if ($existing) {
            $existing->badgeid = $badgeid;
            $DB->update_record('local_learningplan_badge', $existing);
        } else {
            $DB->insert_record('local_learningplan_badge', (object)[
                'planid' => $linkplanid,
                'chapterid' => $chapterid,
                'badgeid' => $badgeid,
                'criteria' => $criteria,
            ]);
        }

        $awarded = 0;
        if ($canawardbadges) {
            $awarded = badges::sync_plan_badges_for_all_users($linkplanid);
        }
        $msg = get_string('badgemapped', 'local_learningplan');
        if ($awarded > 0) {
            $msg .= ' ' . get_string('badgesawardedcount', 'local_learningplan', $awarded);
        }

        redirect($pageurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

if ($planid) {
    echo navigation::render_plan_context_header($planid, 'badges');
} else {
    echo navigation::render_global_header('badges');
}

$availablebadges = badges::get_available_badges();
$plans = api::get_plans();

if (!$plans) {
    echo html_writer::tag('div', get_string('noplans', 'local_learningplan'), ['class' => 'alert alert-info']);
    echo $OUTPUT->footer();
    exit;
}

// Global Badges Summary Info & Quick Action Banner.
echo html_writer::start_div('alert alert-light border shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 mb-4');
echo html_writer::start_div('d-flex align-items-center');
echo html_writer::tag('div', '🏅', ['style' => 'font-size: 2.2rem; margin-right: 15px;']);
echo html_writer::start_div();
echo html_writer::tag('h5', 'Gamified Badges System', ['class' => 'font-weight-bold mb-1']);
echo html_writer::tag('p', 'Reward learners automatically with Moodle Badges when they complete milestones, individual chapters, or the entire learning pathway.', ['class' => 'mb-0 text-muted small']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('d-flex gap-2 mt-2 mt-md-0');
echo html_writer::link(
    new moodle_url('/badges/index.php', ['type' => BADGE_TYPE_SITE]),
    '<i class="fa fa-external-link mr-1"></i> Site Badges Core',
    ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']
);
echo html_writer::end_div();
echo html_writer::end_div();

foreach ($plans as $plan) {
    if ($planid && $plan->id != $planid) {
        continue;
    }

    $mappings = $DB->get_records('local_learningplan_badge', ['planid' => $plan->id]);
    $chapters = api::get_chapters_with_steps($plan->id);

    echo html_writer::start_div('card border shadow-sm mb-4');
    echo html_writer::start_div('card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-2 px-3 border-bottom');
    
    // Left: Title.
    echo html_writer::start_div('d-flex align-items-center mb-2 mb-md-0');
    echo html_writer::tag('h5', '<i class="fa fa-trophy text-warning mr-2"></i>' . format_string($plan->name), ['class' => 'h6 mb-0 font-weight-bold']);
    echo html_writer::tag('span', count($mappings) . ' Badges Linked', ['class' => 'badge badge-pill badge-info ml-2 font-weight-normal']);
    echo html_writer::end_div();

    // Right: Action buttons (Auto-Generate + Sync).
    echo html_writer::start_div('d-flex gap-2');
    if ($cancreatebadges && $canconfigurebadges && $canawardbadges) {
        echo html_writer::link(
            new moodle_url($pageurl, ['planid' => $plan->id, 'action' => 'autogen']),
            '<i class="fa fa-magic mr-1"></i> ' . get_string('autogeneratebadges', 'local_learningplan'),
            [
                'class' => 'btn btn-sm btn-primary py-1 px-2',
                'title' => get_string('autogeneratebadges_help', 'local_learningplan'),
            ]
        );
    }
    if (!empty($mappings) && $canawardbadges) {
        echo html_writer::link(
            new moodle_url($pageurl, ['planid' => $plan->id, 'action' => 'sync']),
            '<i class="fa fa-refresh mr-1"></i> ' . get_string('awardretroactively', 'local_learningplan'),
            [
                'class' => 'btn btn-sm btn-outline-success py-1 px-2',
                'title' => get_string('awardretroactively_help', 'local_learningplan'),
            ]
        );
    }
    echo html_writer::link(
        new moodle_url('/local/learningplan/index.php', ['id' => $plan->id]),
        '<i class="fa fa-eye mr-1"></i> View Map',
        ['class' => 'btn btn-sm btn-outline-secondary py-1 px-2']
    );
    echo html_writer::end_div();

    echo html_writer::end_div(); // card-header.

    echo html_writer::start_div('card-body p-3');

    // Mappings Table.
    if ($mappings) {
        $table = new html_table();
        $table->head = [
            'Badge',
            'Award Criteria / Milestone',
            'Status',
            'Learners Awarded',
            'Actions'
        ];
        $table->attributes['class'] = 'generaltable table-hover align-middle mb-3';

        foreach ($mappings as $mapping) {
            $badge = $DB->get_record('badge', ['id' => $mapping->badgeid]);
            $badgename = $badge ? format_string($badge->name) : ('Badge #' . $mapping->badgeid);
            $badgeimgurl = badges::get_badge_image_url($mapping->badgeid, 'small');

            $badgecell = html_writer::start_div('d-flex align-items-center');
            $badgecell .= html_writer::empty_tag('img', [
                'src' => $badgeimgurl,
                'alt' => $badgename,
                'style' => 'width: 42px; height: 42px; object-fit: contain; margin-right: 12px; border-radius: 8px;'
            ]);
            $badgecell .= html_writer::start_div();
            $badgecell .= html_writer::tag('div', html_writer::tag('strong', $badgename), ['class' => 'font-weight-bold']);
            if ($badge && !empty($badge->description)) {
                $badgecell .= html_writer::tag('div', s(core_text::substr($badge->description, 0, 75)) . '...', ['class' => 'text-muted small']);
            }
            $badgecell .= html_writer::end_div();
            $badgecell .= html_writer::end_div();

            if ($mapping->criteria === 'plan_complete') {
                $critlabel = '<span class="badge badge-warning text-dark px-2 py-1"><i class="fa fa-trophy mr-1"></i> 100% Plan Grand Champion</span>';
            } else {
                $chtitle = $DB->get_field('local_learningplan_chapter', 'title', ['id' => $mapping->chapterid]) ?: ('Chapter #' . $mapping->chapterid);
                $critlabel = '<span class="badge badge-info px-2 py-1"><i class="fa fa-bookmark mr-1"></i> Chapter: ' . format_string($chtitle) . '</span>';
            }

            if (!$badge) {
                $statuslabel = '<span class="badge badge-danger">' . get_string('error') . '</span>';
            } else if ($badge->status == BADGE_STATUS_ACTIVE || $badge->status == BADGE_STATUS_ACTIVE_LOCKED) {
                $statuslabel = '<span class="badge badge-success"><i class="fa fa-check-circle mr-1"></i> '
                    . get_string('active') . '</span>';
            } else {
                $statuslabel = '<span class="badge badge-secondary"><i class="fa fa-pause-circle mr-1"></i> '
                    . get_string('inactive') . '</span>';
            }

            $awards = $DB->count_records('badge_issued', ['badgeid' => $mapping->badgeid]);
            $awardscell = '<span class="badge badge-pill badge-light border px-2 py-1 font-weight-bold ' . ($awards > 0 ? 'text-success' : 'text-muted') . '"><i class="fa fa-users mr-1"></i> ' . get_string('badgesawardedcount', 'local_learningplan', $awards) . '</span>';

            $actioncell = html_writer::link(
                new moodle_url($pageurl, ['delete' => $mapping->id]),
                '<i class="fa fa-trash"></i> ' . get_string('delete'),
                ['class' => 'btn btn-sm btn-outline-danger py-0 px-2']
            );

            $table->data[] = [
                $badgecell,
                $critlabel,
                $statuslabel,
                $awardscell,
                $actioncell,
            ];
        }
        echo html_writer::table($table);
    } else {
        echo html_writer::start_div('alert alert-warning d-flex align-items-center mb-3');
        echo html_writer::tag('span', '⚠️', ['class' => 'mr-2', 'style' => 'font-size: 1.3rem;']);
        echo html_writer::tag('span', 'No badges have been linked to this learning plan yet. Click <strong>Auto-Generate Badges</strong> above to create ready-to-award badges instantly, or link an existing Moodle badge below.');
        echo html_writer::end_div();
    }

    // Forms Section (2-column layout with proper spacing and labels).
    $chapteroptions = [0 => '🏆 Whole Plan Completion (Grand Champion)'];
    foreach ($chapters as $chapter) {
        $chapteroptions[$chapter->id] = '🔖 Chapter: ' . format_string($chapter->title);
    }

    $iconchoices = [
        'icon-01.png' => '🏆 Gold Cup Trophy',
        'icon-02.png' => '⭐ Golden Star',
        'icon-03.png' => '🛡️ Knight Shield',
        'icon-04.png' => '🎖️ Honor Ribbon',
        'icon-05.png' => '💎 Crystal Gem',
        'icon-06.png' => '🚀 Rocket Launch',
        'icon-07.png' => '⚡ Lightning Bolt',
        'icon-08.png' => '🎯 Bullseye Target',
        'icon-09.png' => '👑 Royal Crown',
        'icon-10.png' => '🥇 Champion Medal',
        'icon-11.png' => '🔥 Flame Mastery',
        'icon-12.png' => '🧭 Explorer Compass',
        'icon-13.png' => '🏅 Merit Badge',
        'icon-14.png' => '🌟 Super Nova',
        'icon-15.png' => '🪐 Planetary Orbit',
    ];

    if (!$cancreatebadges && !$canconfigurebadges) {
        echo html_writer::div(
            get_string('nobadgecapability', 'local_learningplan'),
            'alert alert-info border small'
        );
        echo html_writer::end_div(); // card-body.
        echo html_writer::end_div(); // card.
        continue;
    }

    echo html_writer::start_div('row mt-4');

    // -------------------------------------------------------------
    // Col 1: Link an Existing Badge Form.
    // -------------------------------------------------------------
    if ($canconfigurebadges):
    echo html_writer::start_div('col-lg-5 mb-4');
    echo html_writer::start_div('card h-100 border shadow-sm');

    // Card Header.
    echo html_writer::start_div('card-header bg-white py-3 border-bottom d-flex align-items-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-link text-primary mr-2', 'style' => 'font-size: 1.15rem;']);
    echo html_writer::tag('h6', 'Link an Existing Badge', ['class' => 'mb-0 font-weight-bold text-dark']);
    echo html_writer::end_div();

    // Card Body.
    echo html_writer::start_div('card-body p-4 d-flex flex-column justify-content-between');
    echo html_writer::tag('p', 'Attach an existing Moodle site badge to a specific chapter milestone or 100% plan completion.', ['class' => 'text-muted small mb-4']);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl, 'class' => 'd-flex flex-column h-100']);
    echo html_writer::input_hidden_params(new moodle_url('', ['sesskey' => sesskey(), 'planid' => $plan->id]));

    // Field 1: Milestone Requirement.
    echo html_writer::start_div('form-group mb-3');
    echo html_writer::tag('label',
        '<i class="fa fa-flag-checkered text-primary mr-1"></i> Award Milestone Requirement <span class="text-danger">*</span>',
        ['for' => 'chapterid_' . $plan->id, 'class' => 'font-weight-bold small text-secondary text-uppercase mb-1']
    );
    echo html_writer::select($chapteroptions, 'chapterid', 0, false, [
        'id' => 'chapterid_' . $plan->id,
        'class' => 'custom-select form-control',
    ]);
    echo html_writer::tag('small', 'Learners unlock this badge when completing this milestone.', ['class' => 'form-text text-muted mt-1']);
    echo html_writer::end_div();

    // Field 2: Select Moodle Badge.
    echo html_writer::start_div('form-group mb-4');
    echo html_writer::tag('label',
        '<i class="fa fa-trophy text-warning mr-1"></i> Select Moodle Badge <span class="text-danger">*</span>',
        ['for' => 'badgeid_' . $plan->id, 'class' => 'font-weight-bold small text-secondary text-uppercase mb-1']
    );
    echo html_writer::select($availablebadges, 'badgeid', '', [get_string('selectbadge', 'local_learningplan')], [
        'id' => 'badgeid_' . $plan->id,
        'class' => 'custom-select form-control',
        'required' => 'required',
    ]);
    echo html_writer::tag('small', 'Draft badges will automatically be activated upon linking.', ['class' => 'form-text text-muted mt-1']);
    echo html_writer::end_div();

    // Submit Button.
    echo html_writer::start_div('mt-auto pt-3');
    echo html_writer::tag('button',
        '<i class="fa fa-link mr-1"></i> Link & Activate Badge',
        [
            'type' => 'submit',
            'class' => 'btn btn-primary btn-block py-2 font-weight-bold shadow-sm',
        ]
    );
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // card-body.
    echo html_writer::end_div(); // card.
    echo html_writer::end_div(); // col-lg-5.
    endif; // $canconfigurebadges

    // -------------------------------------------------------------
    // Col 2: Quick Create Custom Badge Form.
    // -------------------------------------------------------------
    if ($cancreatebadges && $canconfigurebadges):
    echo html_writer::start_div('col-lg-7 mb-4');
    echo html_writer::start_div('card h-100 border shadow-sm');

    // Card Header.
    echo html_writer::start_div('card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between');
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::tag('i', '', ['class' => 'fa fa-plus-circle text-success mr-2', 'style' => 'font-size: 1.15rem;']);
    echo html_writer::tag('h6', 'Quick Create Custom Badge', ['class' => 'mb-0 font-weight-bold text-dark']);
    echo html_writer::end_div();
    echo html_writer::tag('span', '<i class="fa fa-bolt mr-1"></i> Auto-Active', ['class' => 'badge badge-success font-weight-normal px-2 py-1']);
    echo html_writer::end_div();

    // Card Body.
    echo html_writer::start_div('card-body p-4');
    echo html_writer::tag('p', 'Create a new Moodle site badge with gamified icon artwork and link it directly to this pathway in 1 click.', ['class' => 'text-muted small mb-4']);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl]);
    echo html_writer::input_hidden_params(new moodle_url('', [
        'sesskey' => sesskey(),
        'action' => 'quickcreate',
        'quickplanid' => $plan->id,
        'planid' => $plan->id,
    ]));

    // Row 1: Name and Description.
    echo html_writer::start_div('row');

    echo html_writer::start_div('col-md-6 mb-3');
    echo html_writer::tag('label',
        '<i class="fa fa-tag text-success mr-1"></i> Badge Name <span class="text-danger">*</span>',
        ['for' => 'badgename_' . $plan->id, 'class' => 'font-weight-bold small text-secondary text-uppercase mb-1']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'badgename',
        'id' => 'badgename_' . $plan->id,
        'placeholder' => 'e.g. Frontend Architecture Master',
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::tag('small', 'Public badge title shown to learners.', ['class' => 'form-text text-muted mt-1']);
    echo html_writer::end_div();

    echo html_writer::start_div('col-md-6 mb-3');
    echo html_writer::tag('label',
        '<i class="fa fa-align-left text-muted mr-1"></i> Short Description',
        ['for' => 'badgedesc_' . $plan->id, 'class' => 'font-weight-bold small text-secondary text-uppercase mb-1']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'badgedesc',
        'id' => 'badgedesc_' . $plan->id,
        'placeholder' => 'e.g. Mastered all frontend concepts',
        'class' => 'form-control',
    ]);
    echo html_writer::tag('small', 'Brief summary of the achievement.', ['class' => 'form-text text-muted mt-1']);
    echo html_writer::end_div();

    echo html_writer::end_div(); // row.

    // Row 2: Milestone Requirement and Icon Selector with Live Preview.
    echo html_writer::start_div('row');

    echo html_writer::start_div('col-md-6 mb-3');
    echo html_writer::tag('label',
        '<i class="fa fa-flag text-info mr-1"></i> Milestone Requirement <span class="text-danger">*</span>',
        ['for' => 'quickchapterid_' . $plan->id, 'class' => 'font-weight-bold small text-secondary text-uppercase mb-1']
    );
    echo html_writer::select($chapteroptions, 'quickchapterid', 0, false, [
        'id' => 'quickchapterid_' . $plan->id,
        'class' => 'custom-select form-control',
    ]);
    echo html_writer::tag('small', 'Trigger event to award this badge.', ['class' => 'form-text text-muted mt-1']);
    echo html_writer::end_div();

    $defaulticonurl = (new moodle_url('/local/learningplan/pix/icons/icon-01.png'))->out(false);
    $iconsbaseurl = (new moodle_url('/local/learningplan/pix/icons/'))->out(false);

    echo html_writer::start_div('col-md-6 mb-3');
    echo html_writer::tag('label',
        '<i class="fa fa-image text-warning mr-1"></i> Badge Icon Artwork',
        ['for' => 'badgeicon_' . $plan->id, 'class' => 'font-weight-bold small text-secondary text-uppercase mb-1']
    );
    echo html_writer::start_div('d-flex align-items-center');
    echo html_writer::select($iconchoices, 'badgeicon', 'icon-01.png', false, [
        'id' => 'badgeicon_' . $plan->id,
        'class' => 'custom-select form-control mr-2',
        'onchange' => "document.getElementById('badgeicon_preview_" . $plan->id . "').src = '" . $iconsbaseurl . "' + this.value;",
    ]);
    echo html_writer::empty_tag('img', [
        'id' => 'badgeicon_preview_' . $plan->id,
        'src' => $defaulticonurl,
        'alt' => 'Badge Icon Preview',
        'class' => 'rounded border p-1 bg-light shadow-sm flex-shrink-0',
        'style' => 'width: 44px; height: 44px; object-fit: contain;',
    ]);
    echo html_writer::end_div();
    echo html_writer::tag('small', 'Choose an emblem to represent this badge.', ['class' => 'form-text text-muted mt-1']);
    echo html_writer::end_div();

    echo html_writer::end_div(); // row.

    // Submit Button.
    echo html_writer::start_div('pt-3');
    echo html_writer::tag('button',
        '<i class="fa fa-check-circle mr-1"></i> Create, Activate & Link Badge',
        [
            'type' => 'submit',
            'class' => 'btn btn-success btn-block py-2 font-weight-bold shadow-sm',
        ]
    );
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // card-body.
    echo html_writer::end_div(); // card.
    echo html_writer::end_div(); // col-lg-7.
    endif; // $cancreatebadges

    echo html_writer::end_div(); // row mt-4.

    echo html_writer::end_div(); // card-body.
    echo html_writer::end_div(); // card.
}

echo $OUTPUT->footer();
