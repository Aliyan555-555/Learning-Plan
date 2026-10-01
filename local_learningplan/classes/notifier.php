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
 * Sends in-platform/email notifications through Moodle's Messaging API.
 *
 * @package    local_learningplan
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin wrapper around core message_send() for the plugin's notification types.
 */
class notifier {

    /**
     * Notify a learner that a plan has just been assigned to them.
     *
     * @param int $userid
     * @param \stdClass $plan
     */
    public static function plan_assigned(int $userid, \stdClass $plan): void {
        self::send('assigned', $userid,
            get_string('notif_assigned_subject', 'local_learningplan', format_string($plan->name)),
            new \moodle_url('/local/learningplan/index.php', ['id' => $plan->id])
        );
    }

    /**
     * Notify a learner that they finished a chapter.
     *
     * @param int $userid
     * @param \stdClass $plan
     * @param \stdClass $chapter
     */
    public static function chapter_completed(int $userid, \stdClass $plan, \stdClass $chapter): void {
        self::send('completed', $userid,
            get_string('notif_chapter_subject', 'local_learningplan', format_string($chapter->title)),
            new \moodle_url('/local/learningplan/index.php', ['id' => $plan->id])
        );
    }

    /**
     * Notify a learner that they finished the whole plan.
     *
     * @param int $userid
     * @param \stdClass $plan
     */
    public static function plan_completed(int $userid, \stdClass $plan): void {
        self::send('completed', $userid,
            get_string('notif_plan_subject', 'local_learningplan', format_string($plan->name)),
            new \moodle_url('/local/learningplan/index.php', ['id' => $plan->id])
        );
    }

    /**
     * Build and send the message.
     *
     * @param string $messagename 'assigned' or 'completed'
     * @param int $touserid
     * @param string $subject
     * @param \moodle_url $contexturl
     */
    private static function send(string $messagename, int $touserid, string $subject, \moodle_url $contexturl): void {
        $touser = \core_user::get_user($touserid);
        if (!$touser) {
            return;
        }

        $message = new \core\message\message();
        $message->component = 'local_learningplan';
        $message->name = $messagename;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $touser;
        $message->subject = $subject;
        $message->fullmessage = $subject;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = \html_writer::tag('p', $subject);
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $contexturl->out(false);
        $message->contexturlname = get_string('mylearningpath', 'local_learningplan');

        message_send($message);
    }
}
