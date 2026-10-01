# Learning Plans Admin Block: Statistics & Graphs Guide

A guide to understanding the metrics, KPI cards, visual charts, and engagement graphs available in the **Learning Plans Admin Dashboard Block** (`block_learningplan_admin`).

---

## 📑 Overview of Dashboard Tabs

The Admin Block organizes analytics into three primary tabs for quick navigation:

| Tab | Icon | Purpose |
| :--- | :--- | :--- |
| **Visual Analytics** | 📈 | Interactive charts, 7-day velocity, retention health, progression funnel, and star mastery. |
| **KPI Overview** | 📊 | High-level summary metrics (Total Plans, Active Learners, Total XP, Stars, Completion Rate). |
| **Plan Management** | ⚡ | Quick action shortcuts, workflow navigation, and recently modified plans with one-click actions. |

---

## 📈 Tab 1: Visual Analytics & Graphs

The **Analytics** tab is divided into four focused sub-views:

### 1. Velocity (Daily Momentum & Status Distribution)
- **Progress Distribution Donut Chart**:
  - 🟢 **Completed (Green)**: Total steps finished and verified.
  - 🔵 **In Progress / Unlocked (Blue)**: Currently accessible steps that learners are actively working on.
  - ⚪ **Locked (Gray)**: Upcoming steps locked behind sequential prerequisites.
  - **Center Metric**: Overall completion percentage across all assigned steps.
- **7-Day Activity Velocity (Bar Chart)**:
  - Tracks day-by-day step completions over the past 7 days.
  - Displays total steps completed and total **Experience Points (XP)** awarded this week.
  - Helps administrators observe weekly momentum, peak study days, and trends.
- **Top Performing Plans Leaderboard**:
  - Highlights top learning plans ranked by learner completion rate and active enrollment.

---

### 2. Retention (Learner Health & Engagement)
Monitors how recently assigned learners have interacted with their learning plans:

| Health Tier | Criteria | Indicator | Recommended Action |
| :--- | :--- | :--- | :--- |
| **Active** | Active within the last **7 days** | 🟢 Green | On track and consistently progressing. |
| **Moderate** | Active between **8 to 14 days ago** | 🟡 Amber | Normal cadence; keep monitoring. |
| **At-Risk** | Inactive for **more than 14 days** | 🔴 Red | May need reminder notifications or instructor check-ins. |

- **Health Gauge**: Visual segmented bar showing the percentage split of your active vs at-risk cohort.

---

### 3. Progression Funnel (Pipeline Conversion)
Visualizes how learners flow through learning plans from enrollment to final completion:

```
[ 1. Enrolled ] ──────► [ 2. Started (1+ Steps) ] ──────► [ 3. Halfway (50%+) ] ──────► [ 4. Completed (100%) ]
```

- **Enrolled**: Total learner-plan pairings.
- **Started**: Learners who successfully completed at least one step.
- **Halfway**: Learners who reached or passed the 50% milestone of the plan.
- **Completed**: Learners who conquered 100% of all journey steps and earned the plan completion badge.
- **Conversion Drop-off**: Displays the retention percentage between each consecutive milestone.

---

### 4. Mastery (Gamification & Star Ratings)
Shows learner performance quality based on the 3-star scoring criteria:

- ⭐⭐⭐ **3 Stars (Mastery - 90%+ Score)**: Gold tier performance.
- ⭐⭐ **2 Stars (Proficient - 70% to 89%)**: Silver tier performance.
- ⭐ **1 Star (Passing - Below 70%)**: Bronze tier minimum completion.
- **Gamification Rewards**: Displays total **XP points** accumulated and **Milestone Badges** earned across the site.

---

## 📊 Tab 2: KPI Overview Metrics

Summary cards providing an at-a-glance health check:

| KPI Card | Icon | Metric Displayed | Explanation |
| :--- | :---: | :--- | :--- |
| **Total Plans** | 🗺️ | `Total Count` *(Active / Draft)* | Number of published plans available to learners vs draft/hidden plans under development. |
| **Assigned Learners** | 👥 | `Unique Learners` *(Total Steps)* | Total unique students assigned to at least one plan, alongside the total number of journey nodes. |
| **Points & Stars** | 🏆 | `Total XP` *(Total Stars)* | Cumulative gamification XP points awarded, with total star count earned. |
| **Completion Rate** | 🎯 | `Percentage %` | Global percentage of assigned steps completed across all users. |

---

## ⚡ Tab 3: Plan Management & Quick Shortcuts

- **Quick Action Buttons**:
  - ➕ **Create Plan**: Open the plan builder.
  - 🎨 **Icon Gallery**: Upload, scale, and manage custom icons and animated GIFs.
  - 🏆 **Badges & Milestones**: Configure rewards and badges.
  - 📋 **Reports & Tracking**: View detailed individual and cohort progress logs.
- **Recent Plans List**:
  - Quick view of recent plans with title, visibility indicator, and direct edit/step-builder shortcuts.

---

## ⚙️ Configuration Options (Block Settings)

Site administrators can configure block display preferences by clicking the gear icon ⚙️ on the block and selecting **Configure Learning Plans Admin block**:

1. **Show Visual Analytics (`showcharts`)**: Enable/disable Tab 1 (Charts & Funnels).
2. **Show KPI Overview (`showstats`)**: Enable/disable Tab 2 (Stat Cards).
3. **Show Quick Management (`shownavigation`)**: Enable/disable Tab 3 (Quick Actions).
4. **Recent Plans Limit (`recentlimit`)**: Control how many recently modified plans appear in the list (Default: `5`).

---
*Generated for Moodle Learning Plans Plugin (`local_learningplan` & `block_learningplan_admin`).*
