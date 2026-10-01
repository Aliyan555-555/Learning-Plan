# local_learningplan

Gamified Learning Plan plugin for Moodle. Admins build sequenced Learning Plans
(chapters of steps — courses, activities, files, or links) and assign them to
learners, groups, or cohorts. Learners follow their plan through a visual,
game-map-style path with points, star ratings, chapter rewards, and badges.

No AI features. All scoring is derived from Moodle's own completion and
gradebook data (see `classes/scoring.php`).

## Where things live

- **Admin**: Site administration → Plugins → Local plugins → Learning Plan
  (`manage/plans.php`, `edit_steps.php`, `assign.php`, `report.php`, `badges.php`)
- **Learner**: `/local/learningplan/index.php` ("My Learning Path"), also
  linked from the primary navigation menu.
- **Settings**: default points, default star thresholds, default progression
  mode — Site administration → Plugins → Local plugins → Learning Plan → Settings.

## Scoring model

- `course` step: complete = Moodle's `completion_info::is_course_complete()`.
  Stars = the learner's real course grade percentage (via the course's grade
  item) against the step's configured thresholds; if the course has no grade
  item, completion alone earns full stars.
- `activity` step: same idea, scoped to one course-module's completion +
  grade item, via `completion_info::get_data()`.
- `file` / `url` step: no native Moodle signal exists, so the learner
  self-reports via "Mark as done" — visibly tagged as self-reported on the map
  and in reports, and always worth its base points + 1 star.

Progress is synced in real time via an event observer
(`classes/observer.php`, listens to course/activity completion events) and a
15-minute scheduled task (`classes/task/sync_progress.php`) as a fallback.

## Known follow-ups (not in this build)

- No `classes/privacy/provider.php` yet — the plugin stores per-learner
  progress/points, so a proper Privacy API implementation should be added
  before a GDPR/data-request-heavy production rollout.
- The `activity` step type's course-module id is entered as a plain number
  in the admin form; a proper AJAX activity picker (scoped to the chosen
  course) would be a nicer follow-up.
- Drag-and-drop reordering in the admin chapter/step editor is up/down
  arrow links rather than true drag-and-drop — functional, but could be
  upgraded for a smoother admin experience.
