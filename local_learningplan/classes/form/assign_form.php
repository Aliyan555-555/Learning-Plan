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
 * Assign a Learning Plan to learners/cohort/group.
 *
 * @package    local_learningplan
 */

namespace local_learningplan\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for assigning a plan to individual learners, a cohort, or a course group.
 */
class assign_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $planid = $this->_customdata['planid'];

        $mform->addElement('select', 'assigntype', get_string('assignto', 'local_learningplan'), [
            'user' => get_string('assignbyuser', 'local_learningplan'),
            'cohort' => get_string('assignbycohort', 'local_learningplan'),
            'group' => get_string('assignbygroup', 'local_learningplan'),
        ]);

        // Multi-user AJAX autocomplete, backed by Moodle's core user search web service.
        $mform->addElement('autocomplete', 'userids', get_string('selectusers', 'local_learningplan'), [], [
            'multiple' => true,
            'ajax' => 'core_user/form_user_selector',
        ]);
        $mform->hideIf('userids', 'assigntype', 'neq', 'user');

        $cohorts = $DB->get_records_menu('cohort', ['visible' => 1], 'name ASC', 'id, name');
        $mform->addElement('select', 'cohortid', get_string('selectcohort', 'local_learningplan'),
            [0 => get_string('choosedots')] + $cohorts);
        $mform->hideIf('cohortid', 'assigntype', 'neq', 'cohort');

        $groups = $DB->get_records_menu('groups', [], 'name ASC', 'id, name');
        $mform->addElement('select', 'groupid', get_string('selectgroup', 'local_learningplan'),
            [0 => get_string('choosedots')] + $groups);
        $mform->hideIf('groupid', 'assigntype', 'neq', 'group');

        $mform->addElement('hidden', 'planid', $planid);
        $mform->setType('planid', PARAM_INT);

        $this->add_action_buttons(true, get_string('assign', 'local_learningplan'));
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

        if ($data['assigntype'] === 'user' && empty($data['userids'])) {
            $errors['userids'] = get_string('required');
        }
        if ($data['assigntype'] === 'cohort' && empty($data['cohortid'])) {
            $errors['cohortid'] = get_string('required');
        }
        if ($data['assigntype'] === 'group' && empty($data['groupid'])) {
            $errors['groupid'] = get_string('required');
        }

        return $errors;
    }
}
