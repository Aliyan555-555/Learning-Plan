# Learning Plan: Marks, Scoring & Gamification Architecture

This document provides a comprehensive technical overview of how **Marks, Star Ratings, Points Calculation, and Step Unlocking** operate within the `local_learningplan` Moodle plugin.

---

## 1. Overview of Scoring Components

The gamification and evaluation system is composed of four distinct layers:

```
+-------------------------------------------------------------------------+
|                              1. STEP TYPES                              |
|   - Moodle Course Completion     - Moodle Activity Completion (Quiz/Assign)  |
|   - External URL Link            - Downloadable Resource/File           |
+-------------------------------------------------------------------------+
                                     |
                                     v
+-------------------------------------------------------------------------+
|                           2. SCORING ENGINE                             |
|   - Gradebook percentage evaluation: (finalgrade / grademax) * 100      |
|   - Star rating calculation: 1 Star, 2 Stars, 3 Stars                   |
|   - Points ledger transaction entry                                     |
+-------------------------------------------------------------------------+
                                     |
                                     v
+-------------------------------------------------------------------------+
|                      3. PROGRESSION & UNLOCKING                         |
|   - Status transition: 'locked' -> 'available' -> 'inprogress' -> 'completed'|
|   - Sequential vs. Open mode resolution                                 |
|   - Cascading unlock loop                                               |
+-------------------------------------------------------------------------+
                                     |
                                     v
+-------------------------------------------------------------------------+
|                        4. REWARDS & MILESTONES                          |
|   - Chapter completion badge & notification                             |
|   - Plan completion badge & notification                                |
|   - Dynamic live total aggregation                                      |
+-------------------------------------------------------------------------+
```

---

## 2. Step Evaluation Logic

Every step in a Learning Plan belongs to one of four types:

### A. Course Step (`steptype = 'course'`)
- **Signal Source:** Core Moodle Course Completion API (`\completion_info::is_course_complete($userid)`).
- **Grade Source:** Course total grade item (`\grade_item::fetch_course_item($courseid)`).
- **Percentage Formula:**
  $$\text{Percent} = \left( \frac{\text{finalgrade}}{\text{grademax}} \right) \times 100$$

### B. Activity Step (`steptype = 'activity'`)
- **Signal Source:** Core Moodle Activity Completion (`\completion_info::get_data($cm, false, $userid)`).
- **Valid States:** `COMPLETION_COMPLETE`, `COMPLETION_COMPLETE_PASS`, or `COMPLETION_COMPLETE_FAIL` (to prevent learners from being permanently locked out).
- **Grade Source:** Activity grade item (`\grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => $cm->modname, ...])`).
- **Percentage Formula:**
  $$\text{Percent} = \left( \frac{\text{finalgrade}}{\text{grademax}} \right) \times 100$$

### C. Resource & Link Steps (`steptype = 'file'` or `'url'`)
- **Signal Source:** Learner self-report via the interactive map popup ("Mark as done").
- **Verification Tag:** `verifiedby = 'self'` (transparently distinguishes self-reported items from native Moodle verified completions).
- **Grade Evaluation:** Not applicable (treated as 100% completion).

---

## 3. Star Rating Calculation (`1 – 3 Stars`)

Stars are awarded based on real gradebook performance or activity mastery:

| Condition | Stars Awarded | Description |
| :--- | :---: | :--- |
| **No Grade / Self-Reported** | **★★★ (3 Stars)** | Ungraded courses, files, or links automatically award maximum stars upon completion. |
| **Grade $\ge \text{Threshold 2}$** (Default: **90%**) | **★★★ (3 Stars)** | High mastery / distinction level. |
| **Grade $\ge \text{Threshold 1}$** (Default: **70%**) | **★★☆ (2 Stars)** | Standard proficiency / passing grade. |
| **Grade $< \text{Threshold 1}$** (Below **70%**) | **★☆☆ (1 Star)** | Completed the requirement but did not meet the higher grade thresholds. |

> **Configurability:**
> - Thresholds can be customized per-step in the Step Creation/Edit form.
> - Default thresholds (70% and 90%) can be adjusted globally in **Site Administration > Plugins > Local plugins > Learning Plan > Settings**.

---

## 4. Points & Transaction Ledger

Points incentivize learner engagement and provide a gamified score:

1. **Awarding Points:** Each step has a configured point value (e.g., `10 points`, `50 points`).
2. **Progress Table:** Upon completion, `local_learningplan_progress.pointsawarded` is updated.
3. **Audit Ledger:** An immutable transaction is recorded in `local_learningplan_points_log`:
   - `userid`: The learner receiving points.
   - `planid`: Associated learning plan ID.
   - `stepid`: Step that awarded the points.
   - `points`: Number of points earned.
   - `reason`: Step title as the transaction description.
   - `timecreated`: Unix timestamp.

---

## 5. Live Totals & Percentage Calculation

To guarantee data integrity and prevent out-of-sync cache errors, totals are calculated **live on demand** from the progress records:

$$\text{Overall Completion \%} = \text{round}\left( \frac{\text{Completed Steps}}{\text{Total Steps}} \times 100 \right)$$

$$\text{Total Points Earned} = \sum \text{pointsawarded}$$

$$\text{Total Stars Earned} = \sum \text{starsearned} \quad\text{out of}\quad \sum \text{maxstars}$$

---

## 6. Progression & Unlocking Engine

### Progression Modes
- **Sequential Mode (`progressionmode = 'sequential'`):**
  - The first step of Chapter 1 begins in `available` status; all subsequent steps start `locked`.
  - When Step $N$ is marked complete, Step $N+1$ automatically transitions to `available`.
  - If the last step of a chapter is completed, the first step of the next chapter is automatically unlocked.
- **Open Mode (`progressionmode = 'open'`):**
  - All steps across all chapters are in `available` status from the start, allowing learners to complete them in any order.

### Real-Time vs. Fallback Sync
1. **Real-time Event Observers:**
   - Hooked into `\core\event\course_completed` and `\core\event\course_module_completion_updated`.
   - Evaluates the specific user and course instantly in memory, providing zero-delay map updates.
2. **Cascading Loop Protection:**
   - `sync_user()` executes a loop (up to 20 iterations) to automatically resolve chained unlocks across multiple prerequisites.
3. **Scheduled Task Fallback:**
   - `\local_learningplan\task\sync_progress` runs every 15 minutes to reconcile offline/bulk enrolments or retroactive course completions.

---

## 7. Database Entity Relationship

```
+-----------------------------+
|  local_learningplan_plan    |
+-----------------------------+
| id                          |
| name, progressionmode, etc. |
+-----------------------------+
               | 1
               |
               | N
+-----------------------------+
| local_learningplan_chapter  |
+-----------------------------+
| id, planid, title, sortorder|
+-----------------------------+
               | 1
               |
               | N
+-----------------------------+          +----------------------------------+
|  local_learningplan_step    | 1      N |  local_learningplan_progress     |
+-----------------------------+----------+----------------------------------+
| id, chapterid, steptype     |          | id, stepid, userid               |
| points, maxstars, criteria  |          | status, starsearned, pointsawarded|
+-----------------------------+          +----------------------------------+
                                                           | 1
                                                           | N
                                         +----------------------------------+
                                         | local_learningplan_points_log    |
                                         +----------------------------------+
                                         | id, userid, planid, stepid, pts  |
                                         +----------------------------------+
```
