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
 * English language strings.
 *
 * @package    local_learningplan
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Learning Plan';
$string['mylearningpath'] = 'My Learning Path';
$string['managelearningplans'] = 'Manage Learning Plans';

// Capabilities.
$string['learningplan:manage'] = 'Manage learning plans';
$string['learningplan:assign'] = 'Assign learning plans';
$string['learningplan:viewreports'] = 'View learning plan reports';
$string['learningplan:view'] = 'View own learning plans';

// Plans.
$string['plans'] = 'Learning Plans';
$string['plan'] = 'Learning Plan';
$string['createplan'] = 'Create Learning Plan';
$string['editplan'] = 'Edit Learning Plan';
$string['deleteplan'] = 'Delete Learning Plan';
$string['planname'] = 'Plan name';
$string['plandescription'] = 'Description';
$string['plancoverimage'] = 'Cover image / Theme';
$string['planstartdate'] = 'Start date';
$string['planenddate'] = 'End date';
$string['planvisible'] = 'Visible to learners';
$string['theme_ocean'] = 'Ocean (blue)';
$string['theme_sunset'] = 'Sunset (orange/pink)';
$string['theme_forest'] = 'Forest (green)';
$string['theme_candy'] = 'Candy (purple/pink)';
$string['theme_island'] = 'Island (tropical map)';
$string['progressionmode'] = 'Progression mode';
$string['progressionmode_help'] = 'Sequential: each step unlocks only after the previous one is completed. Open: all steps in the current chapter are available immediately.';
$string['sequential'] = 'Sequential (locked steps)';
$string['open'] = 'Open (all steps available)';
$string['noplans'] = 'No learning plans have been created yet.';
$string['deleteplanconfirm'] = 'Are you sure you want to delete the learning plan "{$a}"? This will remove all chapters, steps, assignments and progress data for every learner.';
$string['plandeleted'] = 'Learning plan deleted successfully.';
$string['plansaved'] = 'Learning plan saved.';
$string['enddateafterstart'] = 'End date should be after the start date.';
$string['admincontrols'] = 'Admin Controls';
$string['adminpreview'] = 'Admin Preview Mode';
$string['adminpreview_desc'] = 'You are viewing this plan in preview mode as an administrator.';
$string['createnewplan'] = 'Create Learning Plan';
$string['managesteps'] = 'Manage Steps';
$string['assignedlearners'] = 'Assigned Learners';
$string['previewmap'] = 'Preview Map';
$string['allplans_admin'] = 'Learning Plans Management';
$string['allplans_admin_desc'] = 'Create, customize, assign learners, and monitor gamified learning paths across your platform.';
$string['createfirstplan'] = 'Create your first learning plan to build a gamified adventure for your learners.';
$string['allplans'] = 'All Plans';
$string['switchplan'] = 'Switch Plan';
$string['nextstep_builder'] = 'Build Steps & Chapters';
$string['nextstep_assign'] = 'Assign Learners';
$string['readytoaddsteps'] = 'Plan Details Configured';
$string['readytoaddsteps_desc'] = 'Next step is adding chapters and interactive learning steps.';
$string['stepconfigdone'] = 'Steps & Chapters Ready';
$string['stepconfigdone_desc'] = 'Your path is structured. Next, assign this learning plan to learners or preview the journey map.';
$string['assignmentdone'] = 'Learners Assigned';
$string['assignmentdone_desc'] = 'Learners are now enrolled. You can preview the live journey map or monitor analytics reports.';
$string['plancreated_nextsteps'] = 'Learning plan created! Now add chapters and steps to build your learning path.';
$string['actions'] = 'Actions';



// Chapters & steps.
$string['chapters'] = 'Chapters';
$string['chapter'] = 'Chapter';
$string['addchapter'] = 'Add chapter';
$string['editchapter'] = 'Edit chapter';
$string['chaptertitle'] = 'Chapter title';
$string['steps'] = 'Steps';
$string['step'] = 'Step';
$string['addstep'] = 'Add step';
$string['editstep'] = 'Edit step';
$string['deletestep'] = 'Delete step';
$string['steptitle'] = 'Step title';
$string['steptype'] = 'Step type';
$string['steptype_course'] = 'Moodle course';
$string['steptype_activity'] = 'Course activity';
$string['steptype_file'] = 'Uploaded file';
$string['steptype_url'] = 'External link';
$string['stepcourse'] = 'Course';
$string['stepactivity'] = 'Activity';
$string['stepurl'] = 'URL';
$string['stepfile'] = 'File';
$string['stepicon'] = 'Step Icon';
$string['stepicon_desc'] = 'Choose the journey icon displayed on the gamified map for this step.';
$string['icon_num'] = 'Icon {$a}';
$string['steppoints'] = 'Points';
$string['stepmaxstars'] = 'Maximum stars';
$string['starthreshold1'] = '2-star grade threshold (%)';
$string['starthreshold2'] = '3-star grade threshold (%)';
$string['nosteps'] = 'This chapter has no steps yet.';
$string['nochapters'] = 'This plan has no chapters yet. Add a chapter to start adding steps.';
$string['stepsaved'] = 'Step saved.';
$string['stepdeleted'] = 'Step deleted.';
$string['deletestepconfirm'] = 'Delete the step "{$a}"? Any learner progress, stars and points recorded against it will be removed.';
$string['chaptersaved'] = 'Chapter saved.';
$string['chapterdeleted'] = 'Chapter deleted.';
$string['deletechapterconfirm'] = 'Delete the chapter "{$a}" and all of its steps? Any learner progress recorded against those steps will be removed.';
$string['selfreported'] = 'Self-reported';

// Assignment.
$string['assign'] = 'Assign Learning Plan';
$string['assignto'] = 'Assign to';
$string['assignbyuser'] = 'By individual learner';
$string['assignbycohort'] = 'By cohort';
$string['assignbygroup'] = 'By course group';
$string['selectusers'] = 'Select learners';
$string['selectcohort'] = 'Select cohort';
$string['selectgroup'] = 'Select group';
$string['assignsuccess'] = 'Learning plan assigned successfully.';
$string['assignqueued'] = 'Learning plan assigned. Learners are being enrolled into the plan\'s courses in the background; this may take a few minutes for large cohorts.';
$string['removeassignment'] = 'Remove assignment';
$string['assignmentremoved'] = 'Assignment removed.';
$string['removeassignmentconfirm'] = 'Remove this assignment ({$a})? Progress and points already earned by affected learners are kept, and they are not unenrolled from any course.';
$string['assignedusers'] = 'Assigned learners';
$string['noassignments'] = 'This plan has not been assigned to anyone yet.';

// Learner map.
$string['nolearningplans'] = 'You do not have any learning plans assigned yet.';
$string['locked'] = 'Locked';
$string['lockedhint'] = 'Complete the previous step to unlock this one.';
$string['prereq_gap_title'] = 'Prerequisite Steps Required';
$string['prereq_gap_msg'] = 'You have already completed this course/activity! However, to progress on this sequential learning journey and earn your rewards, you must first complete earlier step(s): Step {$a->stepnum} ("{$a->steptitle}").';
$string['prereq_gap_autonotice'] = 'Once you finish the preceding step(s), this step will unlock and complete automatically without having to redo it.';
$string['prereq_gap_banner_title'] = 'Sequential Journey: Prior Step Completion Required';
$string['prereq_gap_banner_desc'] = 'You have already completed one or more downstream courses (such as "{$a->completedstep}"), but this learning plan follows a sequential path. Please finish earlier steps (such as Step {$a->prereqnum}: "{$a->prereqtitle}") first. Once finished, your downstream progress and rewards will unlock automatically!';
$string['prereq_pending_badge'] = 'Prerequisite Pending';
$string['prereq_pending_node_hint'] = 'Course completed! Finish Step {$a} first to unlock and claim rewards.';
$string['gotoprereq'] = 'Go to Step {$a}';
$string['available'] = 'Start';
$string['inprogress'] = 'In progress';
$string['completed'] = 'Completed';
$string['markasdone'] = 'Mark as done';
$string['points'] = 'Points';
$string['stars'] = 'Stars';
$string['progress'] = 'Progress';
$string['finishplan'] = 'Finish';
$string['plancomplete'] = 'Plan complete!';
$string['chaptercomplete'] = 'Chapter complete!';
$string['openstep'] = 'Open';
$string['starsearned'] = '{$a->earned} / {$a->max} stars';
$string['totalpoints'] = '{$a} points';
$string['allplans'] = 'All Learning Plans';
$string['yourplans'] = 'Your Learning Adventures';
$string['yourplans_desc'] = 'Select a learning path below to explore your gamified journey and unlock new milestones.';
$string['exploremap'] = 'Explore Map';
$string['backtoallplans'] = 'All Learning Plans';
$string['chapters_count'] = '{$a} Chapters';
$string['steps_completed_ratio'] = '{$a->completed} of {$a->total} steps completed';

// Reports.
$string['reports'] = 'Reports';
$string['report'] = 'Progress Report';
$string['filterbyplan'] = 'Filter by plan';
$string['filterbystatus'] = 'Filter by status';
$string['learner'] = 'Learner';
$string['completionpercent'] = 'Completion %';
$string['export'] = 'Export to CSV';
$string['status'] = 'Status';
$string['norecordsfound'] = 'No matching records found.';
$string['recordcount'] = '{$a} record(s)';

// Badges.
$string['badges'] = 'Badges';
$string['badgemapping'] = 'Badge mapping';
$string['selectbadge'] = 'Select a Moodle badge';
$string['badgecriteria'] = 'Award on';
$string['badgemapped'] = 'Badge linked successfully.';
$string['nobadgesavailable'] = 'No site badges are available yet. Create one under Site administration > Badges first.';
$string['autogeneratebadges'] = 'Auto-generate badges';
$string['autogeneratebadges_help'] = 'Create and map a completion badge for every chapter plus a plan-completion badge, and award them to everyone already eligible.';
$string['awardretroactively'] = 'Award retroactively';
$string['awardretroactively_help'] = 'Check every assigned learner and issue any mapped badge they have already earned.';
$string['autogenbadgesconfirm'] = 'Auto-generate and map a badge for every chapter and for plan completion? New site badges will be created and awarded to every learner who already qualifies.';
$string['syncbadgesconfirm'] = 'Check every learner assigned to this plan and award any mapped badge they have already earned?';
$string['deletebadgemappingconfirm'] = 'Remove this badge mapping? The Moodle badge itself and any badges already issued are not affected.';
$string['badgesgenerated'] = '{$a} badge(s) generated and mapped. Eligible learners have been awarded their badges.';
$string['badgessynced'] = 'Retroactive award complete: {$a} new badge(s) issued to eligible learners.';
$string['badgecreatedmapped'] = 'New badge created and linked successfully.';
$string['badgesawardedcount'] = '{$a} awarded';
$string['nobadgecapability'] = 'You can view the badge mappings for this plan, but creating, linking or activating Moodle badges requires the corresponding site badge permissions (moodle/badges:createbadge, moodle/badges:configuredetails, moodle/badges:awardbadge).';

// Notifications.
$string['messageprovider:assigned'] = 'Learning plan assigned';
$string['messageprovider:completed'] = 'Learning plan / chapter completed';
$string['notif_assigned_subject'] = 'A new Learning Plan has been assigned to you: {$a}';
$string['notif_chapter_subject'] = 'Chapter complete: {$a}';
$string['notif_plan_subject'] = 'Learning Plan complete: {$a}';

// Settings.
$string['settings_defaultpoints'] = 'Default points per step';
$string['settings_defaultpoints_desc'] = 'Used to pre-fill the points field when a new step is created.';
$string['settings_starthreshold1'] = 'Default 2-star grade threshold (%)';
$string['settings_starthreshold1_desc'] = 'A course/activity step earns 2 stars when the learner\'s grade is at or above this percentage.';
$string['settings_starthreshold2'] = 'Default 3-star grade threshold (%)';
$string['settings_starthreshold2_desc'] = 'A course/activity step earns 3 stars when the learner\'s grade is at or above this percentage.';
$string['settings_progressionmode'] = 'Default progression mode';
$string['settings_progressionmode_desc'] = 'Used as the default for new plans.';
$string['settings_enrolheading'] = 'Course auto-enrolment';
$string['settings_enrolheading_desc'] = 'When a learning plan is assigned to a learner, cohort, or group, learners are automatically enrolled into every Moodle course referenced by the plan\'s steps. Enrolment is add-only: unassigning a plan or leaving a cohort never removes an enrolment.';
$string['settings_autoenrol'] = 'Enable course auto-enrolment';
$string['settings_autoenrol_desc'] = 'If disabled, assigning a plan still seeds learner progress but does not enrol anyone into courses.';
$string['settings_enrolrole'] = 'Enrolment role';
$string['settings_enrolrole_desc'] = 'Role assigned to learners when they are enrolled via a learning plan. Uses the manual enrolment method on each course.';
$string['settings_enrolrole_none'] = 'No role (enrol only)';
$string['settings_enrolbatchsize'] = 'Enrolment batch size';
$string['settings_enrolbatchsize_desc'] = 'Number of learners processed per batch by the background enrolment tasks. Lower this if cron runs are hitting memory or time limits.';

// Tasks.
$string['task_sync_progress'] = 'Sync learning plan progress with Moodle completion data';
$string['task_reconcile_enrolments'] = 'Reconcile learning plan assignments with course enrolments';

// Privacy.
$string['privacy:metadata'] = 'The Learning Plan plugin stores learners\' progress, points, and star ratings against learning plans assigned to them.';
$string['privacy:metadata:progress'] = 'Stores progress, star ratings, and completion status for each step.';
$string['privacy:metadata:progress:userid'] = 'The ID of the learner whose progress is recorded.';
$string['privacy:metadata:progress:stepid'] = 'The ID of the step in the learning plan.';
$string['privacy:metadata:progress:status'] = 'The current progress status (locked, available, inprogress, completed).';
$string['privacy:metadata:progress:starsearned'] = 'The number of stars earned on this step.';
$string['privacy:metadata:progress:pointsawarded'] = 'The number of gamification points awarded for completing the step.';
$string['privacy:metadata:progress:timestarted'] = 'The time when the step was first started.';
$string['privacy:metadata:progress:timecompleted'] = 'The time when the step was completed.';
$string['privacy:metadata:points_log'] = 'An audit log of points awarded for completed steps.';
$string['privacy:metadata:points_log:userid'] = 'The ID of the user receiving points.';
$string['privacy:metadata:points_log:planid'] = 'The ID of the associated learning plan.';
$string['privacy:metadata:points_log:stepid'] = 'The ID of the step that awarded the points.';
$string['privacy:metadata:points_log:points'] = 'The amount of points awarded.';
$string['privacy:metadata:points_log:reason'] = 'The title or reason for awarding the points.';
$string['privacy:metadata:points_log:timecreated'] = 'The timestamp when the points were logged.';
$string['privacy:metadata:assignment'] = 'Stores which learning plans are assigned to which users, cohorts, or groups.';
$string['privacy:metadata:assignment:userid'] = 'The ID of the user directly assigned to the plan.';
$string['privacy:metadata:assignment:planid'] = 'The ID of the assigned learning plan.';
$string['privacy:metadata:assignment:assignedby'] = 'The ID of the administrator or teacher who created the assignment.';
$string['privacy:metadata:assignment:timeassigned'] = 'The timestamp when the assignment was made.';
$string['privacy:metadata:enrol'] = 'An audit record of course enrolments created automatically from a learning plan assignment.';
$string['privacy:metadata:enrol:userid'] = 'The ID of the enrolled learner.';
$string['privacy:metadata:enrol:planid'] = 'The ID of the learning plan that triggered the enrolment.';
$string['privacy:metadata:enrol:courseid'] = 'The ID of the course the learner was enrolled into.';
$string['privacy:metadata:enrol:timecreated'] = 'The timestamp when the enrolment record was created.';

// Leaderboard.
$string['leaderboard'] = 'Leaderboard';
$string['planleaderboard'] = 'Plan Leaderboard';
$string['yourrank'] = 'Your Rank';
$string['yourranking'] = 'Your Standing';
$string['rank'] = 'Rank';
$string['score'] = 'Score';
$string['totalxp'] = 'Total XP';
$string['completedsteps'] = 'Completed Steps';
$string['completedchapters'] = 'Completed Chapters';
$string['currentchapter'] = 'Current Chapter';
$string['allchapterscompleted'] = 'All Chapters Completed!';
$string['podium_1st'] = '1st Place';
$string['podium_2nd'] = '2nd Place';
$string['podium_3rd'] = '3rd Place';
$string['halloffame'] = 'Hall of Fame';
$string['classstandings'] = 'Class Standings';
$string['you'] = 'YOU';
$string['toprank_desc'] = 'You are ranked #{$a->rank} out of {$a->total} learners on this learning plan!';
$string['continuejourney'] = 'Continue Journey';
$string['searchlearners'] = 'Search learners...';
$string['noleaderboardentries'] = 'No learners have scored points on this plan yet.';
$string['chapterscompletedcount'] = '{$a->completed} of {$a->total} Chapters';
$string['stepscompletedcount'] = '{$a->completed} of {$a->total} Steps';

// Events.
$string['event:plan_created'] = 'Learning plan created';
$string['event:plan_updated'] = 'Learning plan updated';
$string['event:plan_deleted'] = 'Learning plan deleted';
$string['event:plan_viewed'] = 'Learning plan viewed';
$string['event:chapter_created'] = 'Learning plan chapter created';
$string['event:chapter_updated'] = 'Learning plan chapter updated';
$string['event:chapter_deleted'] = 'Learning plan chapter deleted';
$string['event:step_created'] = 'Learning plan step created';
$string['event:step_updated'] = 'Learning plan step updated';
$string['event:step_deleted'] = 'Learning plan step deleted';
$string['event:plan_assigned'] = 'Learning plan assigned';
$string['event:plan_unassigned'] = 'Learning plan unassigned';
$string['event:step_completed'] = 'Learning plan step completed';
$string['event:points_awarded'] = 'Learning plan points awarded';
// Icon Gallery & Asset Manager.
$string['icongallery'] = 'Icon & GIF Gallery';
$string['icongallery_desc'] = 'Upload custom icons and animated GIFs once, and easily reuse them across Learning Plans, Chapter milestones, and Step nodes.';
$string['totalicons'] = 'Total Icons';
$string['animatedgifs'] = 'Animated GIFs';
$string['activeuses'] = 'Active In Use';
$string['uploadnewicon'] = 'Upload New Icon or Animated GIF';
$string['dragdropfiles'] = 'Drag & drop image/GIF here or browse';
$string['browsefiles'] = 'Browse Files';
$string['iconname_label'] = 'Icon Label / Name';
$string['uploadandadd'] = 'Upload & Save to Gallery';
$string['noiconsfound'] = 'No icons found';
$string['iconsuploaded_success'] = 'Successfully uploaded {$a} icon(s) to the gallery!';
$string['iconuploaded_success'] = 'Icon uploaded successfully and ready for use!';
$string['iconsaved_success'] = 'Icon details updated successfully.';
$string['icondeleted_success'] = 'Icon removed from gallery.';
$string['event:badge_awarded'] = 'Learning plan badge awarded';
$string['event:user_enrolled'] = 'User auto-enrolled via learning plan';

// Status & Analytics Dashboard Strings.
$string['lpdashboard'] = 'LP Dashboard';
$string['statusdashboard'] = 'Status Dashboard';
$string['analyticsdashboard'] = 'Learning Plan Status Dashboard';
$string['totalinactiveenrolment'] = 'Total Inactive Enrolment';
$string['totalactiveenrolment'] = 'Total Active Enrolment';
$string['avgcoursescompleted'] = 'Average Courses Completed Per Participant';
$string['totalenrolledcourses'] = 'Total Number of Enrolled Courses';
$string['totalactivelogins'] = 'Total Active Logins';
$string['totalinactivelogins'] = 'Total Inactive Logins';
$string['overallcompletionpct'] = 'Overall Courses Completion Percentage';
$string['percoursecompletion'] = 'Per Course Completion Status';
$string['subfunctioncompletion'] = 'Sub-function Completion Status';
$string['learninghoursmonth'] = 'Learning Hours Per Month';
$string['pathlearninghoursmonth'] = 'Learning Path Based Learning Hours Per Month';
$string['reminderstatus'] = 'Reminder Status';
$string['loginstatus'] = 'Login Status';
$string['learnerstatus'] = 'Learner Status - 2025';
$string['viewstatusdashboard'] = 'View Status Dashboard';
$string['last21days'] = 'last 21 days';
$string['switchtovisualdashboard'] = 'Switch to Visual Dashboard';
