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
 * JSON endpoint for local_learningplan AJAX operations.
 *
 * @package    local_learningplan
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_learningplan\api;
use local_learningplan\scoring;

require_login();
require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);

$action = required_param('action', PARAM_ALPHANUMEXT);

header('Content-Type: application/json; charset=utf-8');

try {
    if ($action === 'markdone') {
        require_capability('local/learningplan:view', $context);
        $stepid = required_param('stepid', PARAM_INT);
        $success = scoring::mark_self_reported($stepid, (int)$USER->id);
        echo json_encode(['success' => (bool)$success]);
        exit;
    }

    if ($action === 'get_gallery_icons' || $action === 'getgalleryicons') {
        require_capability('local/learningplan:manage', $context);
        $category = optional_param('category', '', PARAM_ALPHA);
        $search = optional_param('search', '', PARAM_TEXT);

        $icons = api::get_gallery_icons($category ?: null, $search ?: null);

        // Also compile preset bundled icons.
        $presets = [];
        for ($i = 1; $i <= 15; $i++) {
            $num = sprintf('%02d', $i);
            $pfilename = 'icon-' . $num . '.png';
            $purl = (new moodle_url('/local/learningplan/pix/icons/' . $pfilename))->out(false);
            $presets[] = [
                'id' => 0,
                'name' => 'Preset Badge #' . $i,
                'filename' => $pfilename,
                'code' => $pfilename,
                'url' => $purl,
                'is_gif' => false,
                'category' => 'preset',
            ];
        }

        echo json_encode([
            'success' => true,
            'icons'   => $icons,
            'presets' => $presets,
        ]);
        exit;
    }

    if ($action === 'quick_upload_icon' || $action === 'quickuploadicon') {
        require_capability('local/learningplan:manage', $context);

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No file uploaded or file upload error']);
            exit;
        }

        $filename = $_FILES['file']['name'];
        $tmpname = $_FILES['file']['tmp_name'];
        $content = file_get_contents($tmpname);
        $label = optional_param('name', '', PARAM_TEXT);
        $uploadcat = optional_param('category', 'steps', PARAM_ALPHA);

        $name = !empty($label) ? $label : pathinfo($filename, PATHINFO_FILENAME);
        $iconid = api::save_gallery_icon_content($name, $uploadcat, $filename, $content, (int)$USER->id);
        $icon = api::get_gallery_icon($iconid);

        echo json_encode([
            'success' => true,
            'icon'    => [
                'id' => $icon->id,
                'name' => $icon->name,
                'filename' => $icon->filename,
                'code' => 'gallery:' . $icon->id,
                'url' => $icon->url,
                'is_gif' => $icon->is_gif,
                'category' => $icon->category,
            ],
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
