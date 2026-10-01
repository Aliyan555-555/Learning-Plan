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
 * Library functions, mainly navigation hooks.
 *
 * @package    local_learningplan
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds "My Learning Path" to the primary user navigation, and an admin
 * shortcut to "Manage Learning Plans" for users who can manage them.
 *
 * @param global_navigation $navigation
 */
function local_learningplan_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    if (has_capability('local/learningplan:view', $context)) {
        $node = $navigation->add(
            get_string('mylearningpath', 'local_learningplan'),
            new moodle_url('/local/learningplan/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_learningplan_mypath',
            new pix_icon('i/badge', '')
        );
        $node->showinflatnavigation = true;
    }

    if (has_capability('local/learningplan:viewreports', $context) || has_capability('local/learningplan:manage', $context)) {
        $dashnode = $navigation->add(
            local_learningplan_str('lpdashboard', 'LP Dashboard'),
            new moodle_url('/local/learningplan/dashboard.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_learningplan_dashboard',
            new pix_icon('i/report', '')
        );
        $dashnode->showinflatnavigation = true;
    }
}

/**
 * Serves plugin files (cover images, step icons) via pluginfile.php.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_learningplan_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if (!has_capability('local/learningplan:view', $context)) {
        return false;
    }

    $allowedareas = ['coverimage', 'stepicon', 'gallery_icon', 'gallery_icons'];
    if (!in_array($filearea, $allowedareas)) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_learningplan', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Safe string resolver that provides fallbacks to prevent raw bracket output like [[identifier]].
 *
 * @param string $identifier
 * @param string $fallback
 * @param mixed $a
 * @return string
 */
function local_learningplan_str(string $identifier, string $fallback = '', $a = null): string {
    $res = get_string($identifier, 'local_learningplan', $a);
    if (strpos($res, '[[' . $identifier . ']]') !== false && !empty($fallback)) {
        if ($a !== null) {
            if (is_object($a) || is_array($a)) {
                foreach ((array)$a as $k => $v) {
                    $fallback = str_replace('{$a->' . $k . '}', (string)$v, $fallback);
                }
            } else {
                $fallback = str_replace('{$a}', (string)$a, $fallback);
            }
        }
        return $fallback;
    }
    return $res;
}

