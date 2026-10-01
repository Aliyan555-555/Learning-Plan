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
 * Admin settings and navigation.
 *
 * @package    local_learningplan
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Top-level category so the plugin gets its own folder-like grouping
    // under Site administration > Plugins > Local plugins.
    $ADMIN->add('localplugins', new admin_category(
        'local_learningplan_cat',
        get_string('pluginname', 'local_learningplan')
    ));

    // Management pages (custom UI, not admin_setting based).
    $ADMIN->add('local_learningplan_cat', new admin_externalpage(
        'local_learningplan_plans',
        get_string('managelearningplans', 'local_learningplan'),
        new moodle_url('/local/learningplan/manage/plans.php'),
        'local/learningplan:manage'
    ));

    $ADMIN->add('local_learningplan_cat', new admin_externalpage(
        'local_learningplan_report',
        get_string('reports', 'local_learningplan'),
        new moodle_url('/local/learningplan/manage/report.php'),
        'local/learningplan:viewreports'
    ));

    $ADMIN->add('local_learningplan_cat', new admin_externalpage(
        'local_learningplan_badges',
        get_string('badges', 'local_learningplan'),
        new moodle_url('/local/learningplan/manage/badges.php'),
        'local/learningplan:manage'
    ));

    // Configuration settings page.
    $settings = new admin_settingpage(
        'local_learningplan_settings',
        get_string('settings', 'moodle')
    );
    $ADMIN->add('local_learningplan_cat', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'local_learningplan/defaultpoints',
            get_string('settings_defaultpoints', 'local_learningplan'),
            get_string('settings_defaultpoints_desc', 'local_learningplan'),
            10,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_learningplan/starthreshold1',
            get_string('settings_starthreshold1', 'local_learningplan'),
            get_string('settings_starthreshold1_desc', 'local_learningplan'),
            70,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_learningplan/starthreshold2',
            get_string('settings_starthreshold2', 'local_learningplan'),
            get_string('settings_starthreshold2_desc', 'local_learningplan'),
            90,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configselect(
            'local_learningplan/progressionmode',
            get_string('settings_progressionmode', 'local_learningplan'),
            get_string('settings_progressionmode_desc', 'local_learningplan'),
            'sequential',
            [
                'sequential' => get_string('sequential', 'local_learningplan'),
                'open' => get_string('open', 'local_learningplan'),
            ]
        ));

        $settings->add(new admin_setting_heading(
            'local_learningplan/enrolheading',
            get_string('settings_enrolheading', 'local_learningplan'),
            get_string('settings_enrolheading_desc', 'local_learningplan')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_learningplan/autoenrol',
            get_string('settings_autoenrol', 'local_learningplan'),
            get_string('settings_autoenrol_desc', 'local_learningplan'),
            1
        ));

        $enrolroles = [0 => get_string('settings_enrolrole_none', 'local_learningplan')];
        foreach (role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL) as $role) {
            $enrolroles[$role->id] = $role->localname;
        }
        $studentroles = get_archetype_roles('student');
        $defaultrole = $studentroles ? (int)reset($studentroles)->id : 0;

        $settings->add(new admin_setting_configselect(
            'local_learningplan/enrolrole',
            get_string('settings_enrolrole', 'local_learningplan'),
            get_string('settings_enrolrole_desc', 'local_learningplan'),
            $defaultrole,
            $enrolroles
        ));

        $settings->add(new admin_setting_configtext(
            'local_learningplan/enrolbatchsize',
            get_string('settings_enrolbatchsize', 'local_learningplan'),
            get_string('settings_enrolbatchsize_desc', 'local_learningplan'),
            1000,
            PARAM_INT
        ));
    }

    // Prevent Moodle from auto-adding a generic settings link, we already
    // registered our own pages above.
    $settings = null;
}
