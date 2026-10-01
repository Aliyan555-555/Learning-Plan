# My Learning Path Block (`block_learningplan`)

A dedicated student-facing Moodle block plugin that displays a learner's assigned gamified learning plans, progress percentages, points and stars earned, active next steps, and direct jump buttons into the interactive journey map.

## Features

- **Assigned Plans Overview**: Displays all active learning paths assigned to the logged-in student.
- **Visual Progress & Gamification Chips**: Live step counter (`4/6 steps`), Points earned (`⚡ 150 pts`), and Stars rating (`⭐ 12/15`).
- **Next Level Indicator**: Automatically detects and highlights the learner's next available active step.
- **Theme Accents**: Adapts to the learning plan's theme (Ocean, Sunset, Forest, Candy).
- **Direct 1-Click Action**: Prominent "Continue Journey" or "Start Journey" button leading directly to the level map.
- **Configurable Settings**: Customize block title, limit max plans displayed, and toggle chips or next step preview.

## Installation

1. Ensure `local_learningplan` is installed.
2. Place this plugin in `server/moodle/blocks/learningplan`.
3. Run Moodle upgrade or visit `Site administration -> Notifications`.
4. Add the block **My Learning Path** to the student Dashboard or My Courses page.
