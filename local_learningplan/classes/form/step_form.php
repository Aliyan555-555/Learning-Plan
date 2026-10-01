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
 * Create/edit Step form.
 *
 * @package    local_learningplan
 */

namespace local_learningplan\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing a single step (level/node) within a chapter.
 */
class step_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('text', 'title', get_string('steptitle', 'local_learningplan'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'steptype', get_string('steptype', 'local_learningplan'), [
            'course' => get_string('steptype_course', 'local_learningplan'),
            'activity' => get_string('steptype_activity', 'local_learningplan'),
            'file' => get_string('steptype_file', 'local_learningplan'),
            'url' => get_string('steptype_url', 'local_learningplan'),
        ]);

        // Course picker (used by both 'course' and 'activity' types).
        // Only fetch courses where completion tracking is enabled.
        $courses = $DB->get_records_menu('course', ['visible' => 1, 'enablecompletion' => 1], 'fullname ASC', 'id, fullname');
        unset($courses[SITEID]);
        $mform->addElement('select', 'courseid', get_string('stepcourse', 'local_learningplan'),
            [0 => get_string('choosedots')] + $courses);
        $mform->hideIf('courseid', 'steptype', 'in', ['file', 'url']);

        // Step Journey Icon Selection (supports custom gallery icons, animated GIFs, and 3D presets).
        $currenticon = $this->_customdata['icon'] ?? 'icon-01.png';
        $mform->addElement('hidden', 'icon');
        $mform->setType('icon', PARAM_RAW);
        $mform->setDefault('icon', $currenticon);
        $mform->addElement('static', 'icon_picker_component', '', \local_learningplan\icon_picker::render('icon', $currenticon, get_string('stepicon', 'local_learningplan')));

        // Activity (course-module) id — resolved client-side via the course above in a later
        // iteration; for now entered directly by an admin who knows the target activity.
        $mform->addElement('text', 'cmid', get_string('stepactivity', 'local_learningplan'), ['size' => 10]);
        $mform->setType('cmid', PARAM_INT);
        $mform->hideIf('cmid', 'steptype', 'neq', 'activity');

        // Used for both 'url' (external link) and 'file' (link to an already-hosted
        // document) step types — both are learner self-reported, so no distinct
        // storage/handling is needed beyond the link itself.
        $mform->addElement('text', 'url', get_string('stepurl', 'local_learningplan'), ['size' => 60]);
        $mform->setType('url', PARAM_URL);
        $mform->hideIf('url', 'steptype', 'in', ['course', 'activity']);

        $mform->addElement('text', 'points', get_string('steppoints', 'local_learningplan'), ['size' => 5]);
        $mform->setType('points', PARAM_INT);
        $mform->setDefault('points', get_config('local_learningplan', 'defaultpoints') ?: 10);
        $mform->addRule('points', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'starthreshold1', get_string('starthreshold1', 'local_learningplan'), ['size' => 5]);
        $mform->setType('starthreshold1', PARAM_INT);
        $mform->setDefault('starthreshold1', get_config('local_learningplan', 'starthreshold1') ?: 70);
        $mform->hideIf('starthreshold1', 'steptype', 'in', ['file', 'url']);

        $mform->addElement('text', 'starthreshold2', get_string('starthreshold2', 'local_learningplan'), ['size' => 5]);
        $mform->setType('starthreshold2', PARAM_INT);
        $mform->setDefault('starthreshold2', get_config('local_learningplan', 'starthreshold2') ?: 90);
        $mform->hideIf('starthreshold2', 'steptype', 'in', ['file', 'url']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'planid');
        $mform->setType('planid', PARAM_INT);

        $mform->addElement('hidden', 'chapterid');
        $mform->setType('chapterid', PARAM_INT);

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

        if ($data['steptype'] === 'course' && empty($data['courseid'])) {
            $errors['courseid'] = get_string('required');
        }
        if ($data['steptype'] === 'activity' && (empty($data['courseid']) || empty($data['cmid']))) {
            $errors['cmid'] = get_string('required');
        }
        if (in_array($data['steptype'], ['url', 'file']) && empty($data['url'])) {
            $errors['url'] = get_string('required');
        }

        return $errors;
    }
}
