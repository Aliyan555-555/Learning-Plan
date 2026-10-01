# Learning Plan Suite for Moodle

A comprehensive Learning Plan management and tracking suite for Moodle, designed to deliver competency frameworks, individual student learning pathways, automated milestone tracking, and administrative governance.

---

## 📦 Repository Structure

This repository contains three core Moodle plugins:

```text
Learning-Plan/
├── local_learningplan/         # Core local plugin (APIs, database tables, business logic, reports)
├── block_learningplan/          # Student-facing dashboard block (progress, milestones, competencies)
├── block_learningplan_admin/    # Admin & Manager governance block (quick management, overrides, templates)
├── .gitignore
└── README.md
```

### 1. `local_learningplan` (`local/learningplan`)
- **Type**: Moodle Local Plugin (`local_learningplan`)
- **Target Directory in Moodle**: `server/moodle/local/learningplan`
- **Features**:
  - Core database schema and data persistence
  - Learning plan assignment & lifecycle management
  - Competency and course tracking APIs
  - Web services & AJAX endpoints
  - Analytics and reporting views

### 2. `block_learningplan` (`blocks/learningplan`)
- **Type**: Moodle Block Plugin (`block_learningplan`)
- **Target Directory in Moodle**: `server/moodle/blocks/learningplan`
- **Features**:
  - Student dashboard widget
  - Real-time progress bar and percentage completion
  - Upcoming milestones and active learning plans summary
  - Direct links to assigned plans and course competencies

### 3. `block_learningplan_admin` (`blocks/learningplan_admin`)
- **Type**: Moodle Block Plugin (`block_learningplan_admin`)
- **Target Directory in Moodle**: `server/moodle/blocks/learningplan_admin`
- **Features**:
  - Administrator and manager dashboard block
  - Quick access to plan templates, cohorts, and user assignments
  - Review and approval queue for student plan requests
  - Summary metrics and fast-action management links

---

## 🚀 Installation & Deployment

To deploy these plugins into a Moodle instance:

1. **Clone or download this repository:**
   ```bash
   git clone https://github.com/Aliyan555-555/Learning-Plan.git
   ```

2. **Copy each folder to its corresponding Moodle directory:**
   - Copy `local_learningplan/` to `[moodle-root]/local/learningplan`
   - Copy `block_learningplan/` to `[moodle-root]/blocks/learningplan`
   - Copy `block_learningplan_admin/` to `[moodle-root]/blocks/learningplan_admin`

3. **Complete Database Upgrade:**
   - Log in to your Moodle site as an Administrator.
   - Navigate to **Site Administration > Notifications** to trigger the plugin upgrade process.
   - Alternatively, execute the Moodle CLI upgrade script:
     ```bash
     php admin/cli/upgrade.php
     ```

4. **Purge Caches:**
   - Run:
     ```bash
     php admin/cli/purge_caches.php
     ```

---

## 🛠️ Requirements

- **Moodle**: 4.1, 4.2, 4.3, 4.4+
- **PHP**: 8.1+
- **Database**: MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 13+

---

## 📄 License

These plugins are licensed under the GNU General Public License v3 or later (GPL-3.0-or-later).
