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
 * Upgrade steps for local_learningplan.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_learningplan upgrade steps between versions.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_learningplan_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082900) {

        // New bookkeeping table for auto-enrolments created from plan assignments.
        $table = new xmldb_table('local_learningplan_enrol');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('planid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('planid', XMLDB_KEY_FOREIGN, ['planid'], 'local_learningplan_plan', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('planid_courseid_userid', XMLDB_INDEX_UNIQUE, ['planid', 'courseid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Supporting indexes for the reconcile queries.
        $assignment = new xmldb_table('local_learningplan_assignment');
        foreach (['cohortid', 'groupid'] as $field) {
            $index = new xmldb_index($field, XMLDB_INDEX_NOTUNIQUE, [$field]);
            if (!$dbman->index_exists($assignment, $index)) {
                $dbman->add_index($assignment, $index);
            }
        }

        $step = new xmldb_table('local_learningplan_step');
        $stepindex = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($step, $stepindex)) {
            $dbman->add_index($step, $stepindex);
        }

        upgrade_plugin_savepoint(true, 2026082900, 'local', 'learningplan');
    }

    if ($oldversion < 2026091801) {
        $table = new xmldb_table('local_learningplan_plan');
        $field = new xmldb_field('icon', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'coverimage');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091801, 'local', 'learningplan');
    }

    if ($oldversion < 2026091802) {
        $table = new xmldb_table('local_learningplan_plan');
        $field = new xmldb_field('icon', XMLDB_TYPE_CHAR, '1024', null, null, null, null, 'coverimage');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091802, 'local', 'learningplan');
    }

    if ($oldversion < 2026092101) {
        $table = new xmldb_table('local_learningplan_icon');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mimetype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'image/png');
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('category', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'general');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
        $table->add_index('category', XMLDB_INDEX_NOTUNIQUE, ['category']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026092101, 'local', 'learningplan');
    }

    return true;
}
