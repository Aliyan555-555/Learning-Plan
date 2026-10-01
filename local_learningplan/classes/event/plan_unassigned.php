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
 * Event triggered when a learning plan assignment is removed.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Plan unassigned event.
 */
class plan_unassigned extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_learningplan_assignment';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:plan_unassigned', 'local_learningplan');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $planid = $this->other['planid'] ?? '';
        return "The user with id '{$this->userid}' removed assignment id '{$this->objectid}' for learning plan '{$planid}'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $planid = $this->other['planid'] ?? '';
        return new \moodle_url('/local/learningplan/manage/assign.php', ['planid' => $planid]);
    }
}
