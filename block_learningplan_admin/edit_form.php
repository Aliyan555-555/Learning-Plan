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
 * Form for editing Learning Plans Admin block instances.
 *
 * @package    block_learningplan_admin
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Form class for instance configuration of block_learningplan_admin.
 */
class block_learningplan_admin_edit_form extends block_edit_form {

    /**
     * Define the form elements for configuring the block instance.
     *
     * @param moodleform $mform
     */
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('config_title', 'block_learningplan_admin'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_learningplan_admin'));

        $mform->addElement('advcheckbox', 'config_showstats', get_string('config_showstats', 'block_learningplan_admin'));
        $mform->setDefault('config_showstats', 1);

        $mform->addElement('advcheckbox', 'config_showcharts', get_string('config_showcharts', 'block_learningplan_admin'));
        $mform->setDefault('config_showcharts', 1);

        $mform->addElement('advcheckbox', 'config_shownavigation', get_string('config_shownavigation', 'block_learningplan_admin'));
        $mform->setDefault('config_shownavigation', 1);

        $mform->addElement('advcheckbox', 'config_showrecent', get_string('config_showrecent', 'block_learningplan_admin'));
        $mform->setDefault('config_showrecent', 1);

        $limits = [
            '3' => '3',
            '5' => '5',
            '10' => '10',
            '20' => '20',
            '0' => get_string('all'),
        ];
        $mform->addElement('select', 'config_recentlimit', get_string('config_recentlimit', 'block_learningplan_admin'), $limits);
        $mform->setDefault('config_recentlimit', 5);
        $mform->hideIf('config_recentlimit', 'config_showrecent', 'notchecked');
    }
}
