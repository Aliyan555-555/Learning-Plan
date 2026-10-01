# Learning Plans Admin & Statistics Block (`block_learningplan_admin`)

A dedicated Moodle block plugin providing administrators and managers with an instant navigation hub, live progress statistics, and quick plan management directly on Moodle dashboards, frontpage, and course pages.

## Key Features

1. **Direct Navigation & Quick Actions**:
   - **Create New Plan** (`/local/learningplan/manage/edit_plan.php`)
   - **Manage All Plans** (`/local/learningplan/manage/plans.php`)
   - **Assign Learners** (`/local/learningplan/manage/assign.php`)
   - **Progress Reports & Export** (`/local/learningplan/manage/report.php`)
   - **Milestone Badges** (`/local/learningplan/manage/badges.php`)
   - **Plugin Settings** (`/admin/settings.php?section=local_learningplan_settings`)
   - **Preview Journey Map** (`/local/learningplan/index.php`)

2. **Live Analytics & Statistics**:
   - Total Learning Plans (Active vs Draft/Hidden)
   - Total Steps and Chapters
   - Total Enrolled/Assigned Learners
   - Gamification Points Awarded
   - Stars Earned
   - Average Completion Rate (%) with visual progress bar

3. **Recent Plans Management**:
   - Quick list of active/recent learning plans with step and learner counters.
   - Quick action shortcuts to edit steps, assign users, or preview map.

4. **Role-Aware Dashboard View**:
   - **Admins & Managers**: Displays full management hub, navigation, and statistics.
   - **Learners**: Displays their enrolled learning plans, current progress percentage, points/stars earned, and a direct "Continue Journey" link.

5. **Configurable Block Settings**:
   - Custom block title.
   - Toggle stats display on/off.
   - Toggle navigation links on/off.
   - Toggle recent plans on/off and configure maximum number of plans displayed.

## Installation

1. Ensure `local_learningplan` is installed in `server/moodle/local/learningplan`.
2. Place this plugin folder in `server/moodle/blocks/learningplan_admin`.
3. Log in as Site Administrator and visit `Site administration -> Notifications` to complete the installation.
4. Turn editing on on your Dashboard or Frontpage and click **Add a block -> Learning Plans Admin & Stats**.
