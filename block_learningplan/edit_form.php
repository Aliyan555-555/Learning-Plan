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
 * Form for editing My Learning Path block instances.
 *
 * @package    block_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Configuration form for block_learningplan.
 */
class block_learningplan_edit_form extends block_edit_form {

    /**
     * Define the form elements for configuring the block instance.
     *
     * @param moodleform $mform
     */
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('config_title', 'block_learningplan'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('pluginname', 'block_learningplan'));

        $limits = [
            '2' => '2',
            '4' => '4',
            '6' => '6',
            '8' => '8',
            '10' => '10',
            '0' => get_string('all'),
        ];
        $mform->addElement('select', 'config_maxplans', get_string('config_maxplans', 'block_learningplan'), $limits);
        $mform->setDefault('config_maxplans', 6);

        $mform->addElement('advcheckbox', 'config_showpoints', get_string('config_showpoints', 'block_learningplan'));
        $mform->setDefault('config_showpoints', 1);

        $mform->addElement('advcheckbox', 'config_showstars', get_string('config_showstars', 'block_learningplan'));
        $mform->setDefault('config_showstars', 1);

        $mform->addElement('advcheckbox', 'config_shownextstep', get_string('config_shownextstep', 'block_learningplan'));
        $mform->setDefault('config_shownextstep', 1);
    }
}
