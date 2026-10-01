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
 * Create/edit Learning Plan form.
 *
 * @package    local_learningplan
 */

namespace local_learningplan\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing a Learning Plan header record.
 */
class plan_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('planname', 'local_learningplan'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('plandescription', 'local_learningplan'));
        $mform->setType('description_editor', PARAM_RAW);

        $themes = [
            'ocean' => get_string('theme_ocean', 'local_learningplan'),
            'sunset' => get_string('theme_sunset', 'local_learningplan'),
            'forest' => get_string('theme_forest', 'local_learningplan'),
            'candy' => get_string('theme_candy', 'local_learningplan'),
            'island' => get_string('theme_island', 'local_learningplan'),
        ];
        $mform->addElement('select', 'coverimage', get_string('plancoverimage', 'local_learningplan'), $themes);
        $mform->setDefault('coverimage', 'ocean');

        $currenticon = $this->_customdata['icon'] ?? '';
        $mform->addElement('hidden', 'icon');
        $mform->setType('icon', PARAM_RAW);
        $mform->setDefault('icon', $currenticon);
        $mform->addElement('static', 'icon_picker_component', '', \local_learningplan\icon_picker::render('icon', $currenticon, 'Custom Plan Icon / GIF'));

        $mform->addElement('header', 'scheduleheader', get_string('settings', 'moodle'));

        $mform->addElement('date_selector', 'startdate', get_string('planstartdate', 'local_learningplan'), ['optional' => true]);
        $mform->addElement('date_selector', 'enddate', get_string('planenddate', 'local_learningplan'), ['optional' => true]);

        $mform->addElement('select', 'progressionmode', get_string('progressionmode', 'local_learningplan'), [
            'sequential' => get_string('sequential', 'local_learningplan'),
            'open' => get_string('open', 'local_learningplan'),
        ]);
        $mform->addHelpButton('progressionmode', 'progressionmode', 'local_learningplan');
        $mform->setDefault('progressionmode', get_config('local_learningplan', 'progressionmode') ?: 'sequential');

        $mform->addElement('advcheckbox', 'visible', get_string('planvisible', 'local_learningplan'));
        $mform->setDefault('visible', 1);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['startdate']) && !empty($data['enddate']) && $data['enddate'] < $data['startdate']) {
            $errors['enddate'] = get_string('enddateafterstart', 'local_learningplan');
        }

        return $errors;
    }
}
