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
 * Links plan/chapter completion to Moodle's core Badges feature.
 *
 * @package    local_learningplan
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/badges/classes/badge.php');
require_once($CFG->libdir . '/badgeslib.php');

/**
 * Issues and manages core Moodle badges mapped to learning plans and chapters.
 */
class badges {

    /**
     * Issue the badge mapped to this plan/chapter completion criteria, if
     * one has been configured by the admin, and the learner does not
     * already hold it.
     *
     * @param int $planid
     * @param int|null $chapterid null for a plan-level badge
     * @param string $criteria 'plan_complete' or 'chapter_complete'
     * @param int $userid
     * @return bool True if a badge was newly awarded
     */
    public static function award_if_mapped(int $planid, ?int $chapterid, string $criteria, int $userid): bool {
        global $DB;

        $conditions = ['planid' => $planid, 'criteria' => $criteria];
        if ($criteria === 'chapter_complete') {
            $conditions['chapterid'] = $chapterid;
        }

        $mapping = $DB->get_record('local_learningplan_badge', $conditions);
        if (!$mapping) {
            return false;
        }

        if (!$DB->record_exists('badge', ['id' => $mapping->badgeid])) {
            return false;
        }

        if ($DB->record_exists('badge_issued', ['badgeid' => $mapping->badgeid, 'userid' => $userid])) {
            return false;
        }

        $badge = new \core_badges\badge($mapping->badgeid);

        // A mapped-but-inactive badge is never issued. Activation is an explicit
        // administrative decision made (with the moodle/badges:configuredetails
        // capability) on the badge management screen, never a silent side effect of
        // a learner completing a step.
        if (!$badge->is_active()) {
            debugging(
                "local_learningplan: badge {$mapping->badgeid} is mapped to plan {$planid} but is not active - skipping award",
                DEBUG_DEVELOPER
            );
            return false;
        }

        try {
            // Attempt standard issue with image baking and recipient notifications.
            $badge->issue($userid, false);
        } catch (\Throwable $e) {
            // Fallback: If mailer or baking encounters a local environment issue,
            // issue without baking so the learner's badge record is never lost.
            try {
                if (!$DB->record_exists('badge_issued', ['badgeid' => $mapping->badgeid, 'userid' => $userid])) {
                    $badge->issue($userid, true);
                }
            } catch (\Throwable $e2) {
                debugging('local_learningplan badge issue failed: ' . $e2->getMessage(), DEBUG_DEVELOPER);
                return false;
            }
        }

        \local_learningplan\event\badge_awarded::create([
            'objectid' => $mapping->badgeid,
            'context' => \context_system::instance(),
            'relateduserid' => $userid,
            'other' => [
                'planid' => $planid,
                'chapterid' => $chapterid,
                'badgeid' => $mapping->badgeid,
            ],
        ])->trigger();

        return true;
    }

    /**
     * Check completion for a user across a specific plan (or all assigned plans)
     * and award any badges for chapters or plans that are complete.
     *
     * @param int $userid
     * @param int|null $planid Specific plan ID or null for all user's plans
     * @return int Number of badges newly awarded
     */
    public static function sync_user_badges(int $userid, ?int $planid = null): int {
        global $DB;

        if ($planid) {
            $planids = [$planid];
        } else {
            $planids = $DB->get_fieldset_sql(
                "SELECT DISTINCT planid FROM {local_learningplan_assignment} WHERE userid = :uid
                 UNION
                 SELECT DISTINCT s.planid
                   FROM {local_learningplan_progress} p
                   JOIN {local_learningplan_step} s ON s.id = p.stepid
                  WHERE p.userid = :uid2",
                ['uid' => $userid, 'uid2' => $userid]
            );
        }

        if (empty($planids)) {
            return 0;
        }

        $awarded = 0;

        foreach ($planids as $pid) {
            $plan = $DB->get_record('local_learningplan_plan', ['id' => $pid]);
            if (!$plan) {
                continue;
            }

            // 1. Check all chapters in this plan.
            $chapters = $DB->get_records('local_learningplan_chapter', ['planid' => $pid]);
            foreach ($chapters as $chapter) {
                $chapterstepids = $DB->get_fieldset_select('local_learningplan_step', 'id', 'chapterid = ?', [$chapter->id]);
                if (empty($chapterstepids)) {
                    continue;
                }

                $completedcount = 0;
                foreach ($chapterstepids as $sid) {
                    $p = $DB->get_record('local_learningplan_progress', ['stepid' => $sid, 'userid' => $userid]);
                    if ($p && $p->status === 'completed') {
                        $completedcount++;
                    }
                }

                if ($completedcount === count($chapterstepids)) {
                    if (self::award_if_mapped($plan->id, $chapter->id, 'chapter_complete', $userid)) {
                        $awarded++;
                    }
                }
            }

            // 2. Check the entire plan.
            $planstepids = $DB->get_fieldset_select('local_learningplan_step', 'id', 'planid = ?', [$pid]);
            if (empty($planstepids)) {
                continue;
            }

            $plancompleted = 0;
            foreach ($planstepids as $sid) {
                $p = $DB->get_record('local_learningplan_progress', ['stepid' => $sid, 'userid' => $userid]);
                if ($p && $p->status === 'completed') {
                    $plancompleted++;
                }
            }

            if ($plancompleted === count($planstepids)) {
                if (self::award_if_mapped($plan->id, null, 'plan_complete', $userid)) {
                    $awarded++;
                }
            }
        }

        return $awarded;
    }

    /**
     * Retroactively sync and award badges for all learners assigned to a plan.
     *
     * @param int $planid
     * @return int Number of badges awarded across all users
     */
    public static function sync_plan_badges_for_all_users(int $planid): int {
        $userids = api::get_plan_userids($planid);
        $totalawarded = 0;
        foreach ($userids as $uid) {
            $totalawarded += self::sync_user_badges((int)$uid, $planid);
        }
        return $totalawarded;
    }

    /**
     * Get the site-level badges an admin may link to a learning plan, with a
     * status indicator. Only BADGE_TYPE_SITE badges are returned — learning plans
     * live in the system context, so course-scoped badges are never eligible.
     *
     * @return array id => "Badge Name [Status]"
     */
    public static function get_available_badges(): array {
        global $DB;
        $records = $DB->get_records('badge', ['type' => BADGE_TYPE_SITE], 'name ASC', 'id, name, status, type');
        $options = [];
        foreach ($records as $b) {
            $statuslabel = '';
            if ($b->status == BADGE_STATUS_INACTIVE) {
                $statuslabel = ' (' . get_string('inactive', 'badges') . ')';
            } else if ($b->status == BADGE_STATUS_ACTIVE_LOCKED) {
                $statuslabel = ' (' . get_string('locked', 'badges') . ')';
            }
            $options[$b->id] = format_string($b->name) . $statuslabel;
        }
        return $options;
    }

    /**
     * Programmatically create a new Moodle badge with an icon and activate it.
     *
     * @param string $name
     * @param string $description
     * @param string|null $iconfile Filename in pix/icons/ (e.g. 'icon-01.png') or full path
     * @param int $type BADGE_TYPE_SITE or BADGE_TYPE_COURSE
     * @param int|null $courseid
     * @return int Created Badge ID
     */
    public static function create_gamified_badge(
        string $name,
        string $description,
        ?string $iconfile = null,
        int $type = BADGE_TYPE_SITE,
        ?int $courseid = null
    ): int {
        global $CFG, $DB, $USER;

        $admin = get_admin();
        $usercreated = !empty($USER->id) ? (int)$USER->id : (int)($admin->id ?? get_admin()->id);

        $badgedata = (object)[
            'name' => $name,
            'description' => $description,
            'timecreated' => time(),
            'timemodified' => time(),
            'usercreated' => $usercreated,
            'usermodified' => $usercreated,
            'issuername' => get_string('pluginname', 'local_learningplan'),
            'issuerurl' => $CFG->wwwroot,
            'issuercontact' => !empty($admin->email) ? $admin->email : 'admin@localhost',
            'type' => $type,
            'courseid' => $courseid,
            'messagesubject' => get_string('badge', 'badges') . ': ' . $name,
            'message' => 'Congratulations! You have completed the requirements and earned the ' . $name . ' badge.',
            'attachment' => 1,
            'notification' => 1,
            'status' => BADGE_STATUS_ACTIVE,
            'version' => OPEN_BADGES_V2,
            'language' => 'en',
            'imagecaption' => $name,
        ];

        $badgeid = $DB->insert_record('badge', $badgedata);
        $badge = new \core_badges\badge($badgeid);

        // Resolve icon file.
        $sourcefile = null;
        if ($iconfile && file_exists($iconfile)) {
            $sourcefile = $iconfile;
        } else if ($iconfile && file_exists($CFG->dirroot . '/local/learningplan/pix/icons/' . $iconfile)) {
            $sourcefile = $CFG->dirroot . '/local/learningplan/pix/icons/' . $iconfile;
        } else {
            $sourcefile = $CFG->dirroot . '/local/learningplan/pix/icons/icon-01.png';
        }

        if ($sourcefile && file_exists($sourcefile)) {
            $tempdir = make_temp_directory('badges');
            $tempimage = $tempdir . '/lp_badge_' . $badgeid . '_' . time() . '.png';
            if (copy($sourcefile, $tempimage)) {
                badges_process_badge_image($badge, $tempimage);
            }
        }

        return $badgeid;
    }

    /**
     * Automatically generate gamified badges for an entire learning plan:
     * - One completion badge for each chapter
     * - One grand champion completion badge for the plan
     * Automatically maps all badges and awards them retroactively to any eligible learners.
     *
     * @param int $planid
     * @return int Number of badges created/mapped
     */
    public static function auto_generate_for_plan(int $planid): int {
        global $DB;

        $plan = $DB->get_record('local_learningplan_plan', ['id' => $planid], '*', MUST_EXIST);
        $chapters = $DB->get_records('local_learningplan_chapter', ['planid' => $planid], 'sortorder ASC');

        $icons = [
            'icon-01.png', 'icon-02.png', 'icon-03.png', 'icon-04.png',
            'icon-05.png', 'icon-06.png', 'icon-07.png', 'icon-08.png',
            'icon-09.png', 'icon-10.png', 'icon-11.png', 'icon-12.png',
        ];

        $created = 0;
        $iconindex = 0;

        // 1. Chapter Badges.
        foreach ($chapters as $cindex => $chapter) {
            $existing = $DB->get_record('local_learningplan_badge', [
                'planid' => $planid,
                'chapterid' => $chapter->id,
                'criteria' => 'chapter_complete',
            ]);

            if (!$existing) {
                $icon = $icons[$iconindex % count($icons)];
                $iconindex++;

                $badgename = format_string($plan->name) . ' - ' . format_string($chapter->title);
                $badgedesc = 'Awarded for completing all steps in Chapter ' . ($cindex + 1) . ' (' . format_string($chapter->title) . ') of "' . format_string($plan->name) . '".';

                $badgeid = self::create_gamified_badge($badgename, $badgedesc, $icon);

                $DB->insert_record('local_learningplan_badge', (object)[
                    'planid' => $planid,
                    'chapterid' => $chapter->id,
                    'badgeid' => $badgeid,
                    'criteria' => 'chapter_complete',
                ]);
                $created++;
            }
        }

        // 2. Plan Completion Badge.
        $existingplanbadge = $DB->get_record('local_learningplan_badge', [
            'planid' => $planid,
            'criteria' => 'plan_complete',
        ]);

        if (!$existingplanbadge) {
            $planbadgename = format_string($plan->name) . ' - Grand Champion';
            $planbadgedesc = 'Awarded for achieving 100% completion across all chapters and steps in "' . format_string($plan->name) . '".';

            $planbadgeid = self::create_gamified_badge($planbadgename, $planbadgedesc, 'icon-10.png');

            $DB->insert_record('local_learningplan_badge', (object)[
                'planid' => $planid,
                'chapterid' => null,
                'badgeid' => $planbadgeid,
                'criteria' => 'plan_complete',
            ]);
            $created++;
        }

        // Retroactively award to any users who already completed these chapters or the plan.
        self::sync_plan_badges_for_all_users($planid);

        return $created;
    }

    /**
     * Get all mapped badges for a specific plan along with the user's earned status.
     *
     * @param int $userid
     * @param int $planid
     * @return array List of badge descriptor objects
     */
    public static function get_user_plan_badges(int $userid, int $planid): array {
        global $DB;

        $mappings = $DB->get_records('local_learningplan_badge', ['planid' => $planid]);
        if (empty($mappings)) {
            return [];
        }

        $results = [];
        foreach ($mappings as $m) {
            $b = $DB->get_record('badge', ['id' => $m->badgeid]);
            if (!$b) {
                continue;
            }

            $issued = $DB->get_record('badge_issued', ['badgeid' => $b->id, 'userid' => $userid]);
            $chaptertitle = '';
            if ($m->criteria === 'chapter_complete' && $m->chapterid) {
                $chaptertitle = $DB->get_field('local_learningplan_chapter', 'title', ['id' => $m->chapterid]) ?: '';
            }

            $badgeobj = new \core_badges\badge($b->id);
            $imageurl = self::get_badge_image_url($b->id, 'small');

            $results[] = (object)[
                'id' => (int)$b->id,
                'mappingid' => (int)$m->id,
                'name' => format_string($b->name),
                'description' => format_text($b->description, FORMAT_PLAIN),
                'criteria' => $m->criteria,
                'chapterid' => $m->chapterid ? (int)$m->chapterid : null,
                'chaptertitle' => format_string($chaptertitle),
                'imageurl' => $imageurl,
                'isearned' => !empty($issued),
                'dateissued' => $issued ? (int)$issued->dateissued : null,
            ];
        }

        // Sort: Plan completion badge last, chapter badges in chapter order.
        usort($results, function($a, $b) {
            if ($a->criteria === 'plan_complete') {
                return 1;
            }
            if ($b->criteria === 'plan_complete') {
                return -1;
            }
            return ($a->chapterid ?? 0) <=> ($b->chapterid ?? 0);
        });

        return $results;
    }

    /**
     * Activate a badge so Moodle will allow it to be issued, but only after the
     * current user is confirmed to hold moodle/badges:configuredetails on that
     * badge's context. Callers must already be inside a sesskey-checked request.
     *
     * @param int $badgeid
     * @return bool true if the badge is active afterwards
     */
    public static function ensure_badge_active(int $badgeid): bool {
        if (!$badgeid || !class_exists('\core_badges\badge')) {
            return false;
        }

        $badge = new \core_badges\badge($badgeid);
        if ($badge->is_active()) {
            return true;
        }

        require_capability('moodle/badges:configuredetails', $badge->get_context());
        $badge->set_status(BADGE_STATUS_ACTIVE);

        return $badge->is_active();
    }

    /**
     * Get the web URL of a badge image.
     *
     * @param int $badgeid
     * @param string $size 'small' (f2) or 'large' (f1)
     * @return string
     */
    public static function get_badge_image_url(int $badgeid, string $size = 'small'): string {
        global $CFG;

        if (!class_exists('\core_badges\badge')) {
            return (new \moodle_url('/local/learningplan/pix/icons/icon-01.png'))->out(false);
        }

        try {
            $badge = new \core_badges\badge($badgeid);
            $context = $badge->get_context();
            $fsize = ($size === 'small') ? 'f2' : 'f1';
            $imageurl = \moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', $fsize, false);
            return $imageurl->out(false);
        } catch (\Throwable $e) {
            return (new \moodle_url('/local/learningplan/pix/icons/icon-01.png'))->out(false);
        }
    }
}
