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
 * Interactive Icon & Animated GIF Picker component.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learningplan;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;

/**
 * Renders interactive modal icon picker for plan covers and step nodes.
 */
class icon_picker {

    /**
     * Render the complete icon picker widget and its associated modal dialog.
     *
     * @param string $fieldname Form input field name (e.g. 'icon' or 'coverimage')
     * @param string $currentvalue Current stored icon code (e.g. 'icon-01.png', 'gallery:12', 'ocean')
     * @param string $label Display label
     * @return string HTML markup
     */
    public static function render(string $fieldname = 'icon', string $currentvalue = 'icon-01.png', string $label = 'Step Icon'): string {
        global $PAGE, $CFG;

        $info = api::resolve_icon_info($currentvalue);
        $galleryicons = api::get_gallery_icons();
        $sesskey = sesskey();
        $ajaxurl = (new moodle_url('/local/learningplan/ajax.php'))->out(false);
        $galleryurl = (new moodle_url('/local/learningplan/manage/icons.php'))->out(false);

        $widgetid = 'lp_picker_' . clean_param($fieldname, PARAM_ALPHANUMEXT);
        $inputid = 'id_' . clean_param($fieldname, PARAM_ALPHANUMEXT);
        $modalid = 'lp_modal_' . clean_param($fieldname, PARAM_ALPHANUMEXT);

        $html = '';

        // Hidden input storing active icon code.
        $html .= html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => $fieldname,
            'id'    => $inputid,
            'value' => s($currentvalue),
        ]);

        // Preview Widget Card.
        $html .= html_writer::start_div('lp-icon-picker-widget card border shadow-sm p-3 mb-3', ['id' => $widgetid]);
        $html .= html_writer::start_div('d-flex align-items-center justify-content-between flex-wrap gap-3');

        // Left: Thumbnail + Current selection details.
        $html .= html_writer::start_div('d-flex align-items-center');
        
        $currentscale = $info['scale'] ?? 100;
        $thumbstyle = ($currentscale != 100) ? 'transform: scale(' . ($currentscale / 100) . '); transform-origin: center center;' : '';

        $thumbhtml = '';
        if (!empty($info['url'])) {
            $thumbhtml = html_writer::empty_tag('img', [
                'src'   => $info['url'],
                'alt'   => s($info['name']),
                'class' => 'lp-picker-current-img ' . ($info['is_gif'] ? 'lp-icon-gif' : ''),
                'id'    => $widgetid . '_img',
                'style' => $thumbstyle,
            ]);
        } else if (!empty($info['emoji'])) {
            $thumbhtml = html_writer::tag('span', $info['emoji'], [
                'class' => 'lp-picker-current-emoji',
                'id'    => $widgetid . '_emoji',
                'style' => $thumbstyle,
            ]);
        } else {
            $purl = (new moodle_url('/local/learningplan/pix/icons/icon-01.png'))->out(false);
            $thumbhtml = html_writer::empty_tag('img', [
                'src'   => $purl,
                'alt'   => 'Default Icon',
                'class' => 'lp-picker-current-img',
                'id'    => $widgetid . '_img',
                'style' => $thumbstyle,
            ]);
        }

        $html .= html_writer::div($thumbhtml, 'lp-picker-thumb-wrap mr-3', ['id' => $widgetid . '_thumbwrap']);

        $html .= html_writer::start_div();
        $html .= html_writer::tag('h6', s($label), ['class' => 'font-weight-bold mb-1 text-dark']);
        $html .= html_writer::start_div('d-flex align-items-center gap-2');
        $html .= html_writer::tag('span', s($info['name']), ['class' => 'text-muted small mr-2', 'id' => $widgetid . '_name']);
        
        $badgeclass = $info['is_gif'] ? 'badge-warning text-dark font-weight-bold' : ($info['type'] === 'gallery' ? 'badge-primary' : 'badge-light border');
        $badgetext = $info['is_gif'] ? '✨ Animated GIF' : ($info['type'] === 'gallery' ? 'Gallery Custom' : '3D Preset');
        $html .= html_writer::tag('span', $badgetext, ['class' => 'badge ' . $badgeclass, 'id' => $widgetid . '_badge']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div(); // End details.

        $html .= html_writer::end_div(); // End Left.

        // Right: Action Buttons.
        $html .= html_writer::start_div('d-flex align-items-center gap-2');
        $html .= html_writer::tag('button', '<i class="fa fa-picture-o mr-1"></i> Choose from Gallery / Presets', [
            'type'           => 'button',
            'class'          => 'btn btn-primary font-weight-bold shadow-sm lp-open-picker-btn',
            'id'             => $widgetid . '_open_btn',
            'data-toggle'    => 'modal',
            'data-bs-toggle' => 'modal',
            'data-target'    => '#' . $modalid,
            'data-bs-target' => '#' . $modalid,
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End top row.

        // Scale / Sizing Control Bar.
        $html .= html_writer::start_div('lp-icon-scale-control mt-3 pt-3 border-top');
        $html .= html_writer::start_div('d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2');
        
        $html .= html_writer::start_div('d-flex align-items-center');
        $html .= html_writer::tag('label', '<i class="fa fa-arrows-alt text-primary mr-1"></i> <strong>Icon Scale / Size:</strong>', ['class' => 'small mb-0 mr-2 text-dark']);
        $html .= html_writer::tag('span', $currentscale . '%', ['class' => 'badge badge-primary font-weight-bold px-2 py-1', 'id' => $widgetid . '_scale_badge', 'style' => 'font-size: 0.85rem;']);
        $html .= html_writer::end_div();

        // Presets & Nudge controls.
        $html .= html_writer::start_div('d-flex align-items-center gap-1 flex-wrap');
        
        $html .= html_writer::start_div('btn-group btn-group-sm lp-scale-presets mr-2');
        $scales = [
            50  => '50%',
            75  => '75%',
            100 => '100% (Normal)',
            125 => '125%',
            150 => '150%',
            175 => '175%',
            200 => '200%',
        ];
        foreach ($scales as $sval => $slabel) {
            $isactive = ($currentscale === $sval);
            $btncls = $isactive ? 'btn-primary active font-weight-bold' : 'btn-outline-secondary';
            $html .= html_writer::tag('button', $slabel, [
                'type'       => 'button',
                'class'      => 'btn ' . $btncls . ' py-1 px-2 lp-scale-preset-btn',
                'data-scale' => $sval,
                'title'      => 'Set scale to ' . $sval . '%',
            ]);
        }
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('btn-group btn-group-sm');
        $html .= html_writer::tag('button', '<i class="fa fa-minus"></i>', [
            'type'  => 'button',
            'class' => 'btn btn-outline-secondary py-1 px-2',
            'id'    => $widgetid . '_zoom_out',
            'title' => 'Decrease scale by 10%',
        ]);
        $html .= html_writer::tag('button', 'Reset', [
            'type'  => 'button',
            'class' => 'btn btn-outline-secondary py-1 px-2',
            'id'    => $widgetid . '_zoom_reset',
            'title' => 'Reset to 100% standard size',
        ]);
        $html .= html_writer::tag('button', '<i class="fa fa-plus"></i>', [
            'type'  => 'button',
            'class' => 'btn btn-outline-secondary py-1 px-2',
            'id'    => $widgetid . '_zoom_in',
            'title' => 'Increase scale by 10%',
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End presets & nudge controls.
        $html .= html_writer::end_div(); // End scale header.

        // Range Slider.
        $html .= html_writer::start_div('d-flex align-items-center gap-3 mt-1');
        $html .= html_writer::tag('span', '50%', ['class' => 'text-muted small font-weight-bold']);
        $html .= html_writer::empty_tag('input', [
            'type'  => 'range',
            'class' => 'custom-range flex-grow-1 lp-scale-range-slider',
            'id'    => $widgetid . '_scale_slider',
            'min'   => '50',
            'max'   => '200',
            'step'  => '5',
            'value' => $currentscale,
        ]);
        $html .= html_writer::tag('span', '200%', ['class' => 'text-muted small font-weight-bold']);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End scale control bar.
        $html .= html_writer::end_div(); // End widget card.

        // Modal Dialog.
        $html .= html_writer::start_div('modal fade lp-icon-picker-modal', [
            'id'       => $modalid,
            'tabindex' => '-1',
            'role'     => 'dialog',
            'aria-labelledby' => $modalid . '_title',
            'aria-hidden' => 'true',
        ]);
        $html .= html_writer::start_div('modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable', ['role' => 'document']);
        $html .= html_writer::start_div('modal-content border-0 shadow-lg');

        // Modal Header.
        $html .= html_writer::start_div('modal-header bg-light py-3 d-flex align-items-center justify-content-between');
        $html .= html_writer::start_div('d-flex align-items-center');
        $html .= html_writer::tag('i', '', ['class' => 'fa fa-th-large text-primary mr-2', 'style' => 'font-size: 1.25rem;']);
        $html .= html_writer::tag('h5', 'Choose Icon or Animated GIF', ['class' => 'modal-title font-weight-bold text-dark', 'id' => $modalid . '_title']);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex align-items-center');
        $html .= html_writer::link($galleryurl, '<i class="fa fa-external-link mr-1"></i> Manage Gallery', [
            'class'  => 'btn btn-sm btn-outline-secondary mr-3',
            'target' => '_blank',
        ]);
        $html .= html_writer::tag('button', '<span aria-hidden="true">&times;</span>', [
            'type'         => 'button',
            'class'        => 'close',
            'data-dismiss' => 'modal',
            'aria-label'   => 'Close',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div(); // End modal-header.

        // Modal Navigation Tabs.
        $html .= html_writer::start_div('modal-body p-0');
        $html .= html_writer::start_div('lp-picker-tabs-header bg-white border-bottom px-4 pt-3');
        $html .= html_writer::start_tag('ul', ['class' => 'nav nav-pills', 'role' => 'tablist']);

        // Tab 1 Nav: Gallery.
        $html .= html_writer::start_tag('li', ['class' => 'nav-item']);
        $html .= html_writer::link('#' . $modalid . '_tab_gallery', '<i class="fa fa-folder-open mr-1"></i> Custom Gallery (' . count($galleryicons) . ')', [
            'class'       => 'nav-link active font-weight-bold',
            'data-toggle' => 'pill',
            'role'        => 'tab',
        ]);
        $html .= html_writer::end_tag('li');

        // Tab 2 Nav: 3D Presets.
        $html .= html_writer::start_tag('li', ['class' => 'nav-item ml-2']);
        $html .= html_writer::link('#' . $modalid . '_tab_presets', '<i class="fa fa-cube mr-1"></i> 3D Game Presets (15)', [
            'class'       => 'nav-link font-weight-bold',
            'data-toggle' => 'pill',
            'role'        => 'tab',
        ]);
        $html .= html_writer::end_tag('li');

        // Tab 3 Nav: Quick Upload.
        $html .= html_writer::start_tag('li', ['class' => 'nav-item ml-2']);
        $html .= html_writer::link('#' . $modalid . '_tab_upload', '<i class="fa fa-cloud-upload text-primary mr-1"></i> Upload New Image / GIF', [
            'class'       => 'nav-link font-weight-bold',
            'data-toggle' => 'pill',
            'role'        => 'tab',
        ]);
        $html .= html_writer::end_tag('li');

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // End tabs header.

        // Tab Content Container.
        $html .= html_writer::start_div('tab-content p-4', ['style' => 'max-height: 480px; min-height: 360px; overflow-y: auto;']);

        // -------------------------------------------------------------
        // TAB 1: Custom Gallery Icons & Animated GIFs.
        // -------------------------------------------------------------
        $html .= html_writer::start_div('tab-pane fade show active', [
            'id'   => $modalid . '_tab_gallery',
            'role' => 'tabpanel',
        ]);

        // Filter & Search Toolbar inside modal.
        $html .= html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2');
        $html .= html_writer::start_div('d-flex flex-wrap gap-1 lp-modal-cat-filters');
        $html .= html_writer::tag('button', 'All', ['type' => 'button', 'class' => 'btn btn-sm btn-primary active lp-modal-filter-btn mr-1', 'data-cat' => 'all']);
        $html .= html_writer::tag('button', '✨ GIFs', ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary lp-modal-filter-btn mr-1', 'data-cat' => 'animated']);
        $html .= html_writer::tag('button', '🎯 Steps', ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary lp-modal-filter-btn mr-1', 'data-cat' => 'steps']);
        $html .= html_writer::tag('button', '🗺️ Plans', ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary lp-modal-filter-btn mr-1', 'data-cat' => 'plans']);
        $html .= html_writer::tag('button', '🏆 Badges', ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary lp-modal-filter-btn', 'data-cat' => 'badges']);
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('lp-modal-search-box');
        $html .= html_writer::empty_tag('input', [
            'type'        => 'text',
            'class'       => 'form-control form-control-sm lp-modal-search-input',
            'placeholder' => 'Search gallery...',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        // Gallery Items Grid.
        $html .= html_writer::start_div('row row-cols-3 row-cols-sm-4 row-cols-md-5 g-3 lp-modal-gallery-grid', ['id' => $modalid . '_gallery_grid']);
        if (empty($galleryicons)) {
            $html .= html_writer::start_div('col-12 text-center py-5 text-muted');
            $html .= html_writer::tag('div', '🎨', ['style' => 'font-size: 2.5rem;']);
            $html .= html_writer::tag('h6', 'No gallery icons uploaded yet', ['class' => 'font-weight-bold mt-2']);
            $html .= html_writer::tag('p', 'Switch to the "Upload New" tab to upload your first custom image or animated GIF!', ['class' => 'small']);
            $html .= html_writer::end_div();
        } else {
            foreach ($galleryicons as $gicon) {
                $code = 'gallery:' . $gicon->id;
                $isactive = ($info['basecode'] === $code);
                $ext = strtoupper(pathinfo($gicon->filename, PATHINFO_EXTENSION));

                $html .= html_writer::start_div('col mb-3 lp-modal-item-col', [
                    'data-name'     => s(strtolower($gicon->name . ' ' . $gicon->filename)),
                    'data-category' => s($gicon->category),
                    'data-isgif'    => $gicon->is_gif ? '1' : '0',
                ]);
                $html .= html_writer::start_div('lp-modal-icon-tile card h-100 text-center p-2 cursor-pointer ' . ($isactive ? 'active' : ''), [
                    'data-code'  => $code,
                    'data-url'   => $gicon->url,
                    'data-name'  => s($gicon->name),
                    'data-isgif' => $gicon->is_gif ? '1' : '0',
                    'title'      => s($gicon->name),
                ]);

                $html .= html_writer::start_div('lp-modal-tile-preview mb-1 position-relative d-flex align-items-center justify-content-center');
                $html .= html_writer::empty_tag('img', [
                    'src'   => $gicon->url,
                    'alt'   => s($gicon->name),
                    'class' => 'lp-modal-tile-img ' . ($gicon->is_gif ? 'lp-icon-gif' : ''),
                    'loading' => 'lazy',
                ]);
                if ($gicon->is_gif) {
                    $html .= html_writer::tag('span', 'GIF', ['class' => 'badge badge-warning text-dark position-absolute', 'style' => 'top: 2px; left: 2px; font-size: 0.65rem;']);
                }
                $html .= html_writer::end_div();

                $html .= html_writer::tag('div', s($gicon->name), ['class' => 'small text-truncate font-weight-bold text-dark mb-0', 'style' => 'font-size: 0.75rem;']);
                $html .= html_writer::end_div(); // End tile.
                $html .= html_writer::end_div(); // End col.
            }
        }
        $html .= html_writer::end_div(); // End gallery grid.
        $html .= html_writer::end_div(); // End tab 1.

        // -------------------------------------------------------------
        // TAB 2: Bundled 3D Game Presets (15 icons).
        // -------------------------------------------------------------
        $html .= html_writer::start_div('tab-pane fade', [
            'id'   => $modalid . '_tab_presets',
            'role' => 'tabpanel',
        ]);

        $presetlabels = [
            1 => '🏆 Gold Cup',
            2 => '⭐ Golden Star',
            3 => '🛡️ Shield',
            4 => '🎖️ Honor Ribbon',
            5 => '💎 Crystal Gem',
            6 => '🚀 Rocket Launch',
            7 => '⚡ Lightning Bolt',
            8 => '🎯 Target',
            9 => '👑 Royal Crown',
            10 => '🥇 Champion Medal',
            11 => '🔥 Flame',
            12 => '🧭 Compass',
            13 => '🏅 Merit Badge',
            14 => '🌟 Super Nova',
            15 => '🪐 Planet Orbit',
        ];

        $html .= html_writer::start_div('row row-cols-3 row-cols-sm-4 row-cols-md-5 g-3');
        for ($i = 1; $i <= 15; $i++) {
            $num = sprintf('%02d', $i);
            $pfile = 'icon-' . $num . '.png';
            $purl = (new moodle_url('/local/learningplan/pix/icons/' . $pfile))->out(false);
            $isactive = ($info['basecode'] === $pfile);
            $plabel = $presetlabels[$i] ?? ('Preset #' . $i);

            $html .= html_writer::start_div('col mb-3');
            $html .= html_writer::start_div('lp-modal-icon-tile card h-100 text-center p-2 cursor-pointer ' . ($isactive ? 'active' : ''), [
                'data-code'  => $pfile,
                'data-url'   => $purl,
                'data-name'  => $plabel,
                'data-isgif' => '0',
                'title'      => $plabel,
            ]);

            $html .= html_writer::start_div('lp-modal-tile-preview mb-1 d-flex align-items-center justify-content-center');
            $html .= html_writer::empty_tag('img', [
                'src'   => $purl,
                'alt'   => $plabel,
                'class' => 'lp-modal-tile-img',
            ]);
            $html .= html_writer::end_div();

            $html .= html_writer::tag('div', $plabel, ['class' => 'small text-truncate font-weight-bold text-dark mb-0', 'style' => 'font-size: 0.75rem;']);
            $html .= html_writer::end_div(); // End tile.
            $html .= html_writer::end_div(); // End col.
        }
        $html .= html_writer::end_div(); // End row.
        $html .= html_writer::end_div(); // End tab 2.

        // -------------------------------------------------------------
        // TAB 3: Quick Upload New Image / GIF.
        // -------------------------------------------------------------
        $html .= html_writer::start_div('tab-pane fade', [
            'id'   => $modalid . '_tab_upload',
            'role' => 'tabpanel',
        ]);

        $html .= html_writer::start_div('lp-quick-upload-wrap p-3');
        $html .= html_writer::tag('h6', '<i class="fa fa-cloud-upload text-primary mr-1"></i> Quick Upload & Auto-Select', ['class' => 'font-weight-bold mb-1']);
        $html .= html_writer::tag('p', 'Upload any image or animated GIF (PNG, JPG, SVG, WebP, GIF) up to 8MB. It will be added to your gallery and instantly selected.', ['class' => 'text-muted small mb-3']);

        $html .= html_writer::start_div('row');
        $html .= html_writer::start_div('col-md-6 mb-3');
        $html .= html_writer::start_div('lp-modal-dropzone p-4 rounded text-center border-dashed d-flex flex-column align-items-center justify-content-center', ['id' => $modalid . '_dropzone']);
        $html .= html_writer::start_div('lp-modal-preview d-none mb-2', ['id' => $modalid . '_preview_wrap']);
        $html .= html_writer::empty_tag('img', ['src' => '', 'alt' => 'Preview', 'class' => 'lp-preview-thumb rounded shadow-sm', 'id' => $modalid . '_preview_img']);
        $html .= html_writer::end_div();
        $html .= html_writer::tag('i', '', ['class' => 'fa fa-file-image-o fa-3x text-primary opacity-75 mb-2', 'id' => $modalid . '_drop_icon']);
        $html .= html_writer::tag('div', 'Drag & drop image/GIF or browse', ['class' => 'font-weight-bold small mb-2', 'id' => $modalid . '_drop_text']);
        $html .= html_writer::start_tag('label', ['class' => 'btn btn-sm btn-outline-primary mb-0 cursor-pointer']);
        $html .= html_writer::tag('span', '<i class="fa fa-folder-open-o mr-1"></i> Choose File');
        $html .= html_writer::empty_tag('input', [
            'type'   => 'file',
            'class'  => 'd-none lp-modal-file-input',
            'id'     => $modalid . '_file_input',
            'accept' => '.gif,.png,.jpg,.jpeg,.svg,.webp',
        ]);
        $html .= html_writer::end_tag('label');
        $html .= html_writer::end_div(); // End dropzone.
        $html .= html_writer::end_div(); // End col.

        $html .= html_writer::start_div('col-md-6 d-flex flex-column justify-content-between');
        $html .= html_writer::start_div();
        $html .= html_writer::start_div('form-group mb-3');
        $html .= html_writer::tag('label', 'Icon Label / Name (Optional)', ['class' => 'font-weight-bold small mb-1']);
        $html .= html_writer::empty_tag('input', [
            'type'        => 'text',
            'class'       => 'form-control form-control-sm lp-modal-name-input',
            'id'          => $modalid . '_name_input',
            'placeholder' => 'e.g. Glowing Trophy GIF',
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('form-group mb-3');
        $html .= html_writer::tag('label', 'Category', ['class' => 'font-weight-bold small mb-1']);
        $html .= html_writer::start_tag('select', ['class' => 'custom-select custom-select-sm lp-modal-cat-input', 'id' => $modalid . '_cat_input']);
        $html .= html_writer::tag('option', '🎯 Step Journey Nodes', ['value' => 'steps', 'selected' => 'selected']);
        $html .= html_writer::tag('option', '🗺️ Plan Covers & Badges', ['value' => 'plans']);
        $html .= html_writer::tag('option', '🏆 Milestones & Rewards', ['value' => 'badges']);
        $html .= html_writer::tag('option', '✨ Animated GIFs', ['value' => 'animated']);
        $html .= html_writer::tag('option', '📁 General Assets', ['value' => 'general']);
        $html .= html_writer::end_tag('select');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::start_div('pt-2');
        $html .= html_writer::tag('button', '<i class="fa fa-cloud-upload mr-1"></i> Upload & Apply Now', [
            'type'  => 'button',
            'class' => 'btn btn-primary btn-block font-weight-bold shadow-sm lp-modal-upload-btn',
            'id'    => $modalid . '_upload_btn',
        ]);
        $html .= html_writer::start_div('lp-upload-status small text-center mt-2 d-none', ['id' => $modalid . '_upload_status']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End col right.
        $html .= html_writer::end_div(); // End row.
        $html .= html_writer::end_div(); // End quick upload wrap.
        $html .= html_writer::end_div(); // End tab 3.

        $html .= html_writer::end_div(); // End tab-content.
        $html .= html_writer::end_div(); // End modal-body.

        // Modal Footer.
        $html .= html_writer::start_div('modal-footer bg-light py-2');
        $html .= html_writer::tag('button', 'Done / Close', [
            'type'         => 'button',
            'class'        => 'btn btn-secondary',
            'data-dismiss' => 'modal',
        ]);
        $html .= html_writer::end_div();

        $html .= html_writer::end_div(); // End modal-content.
        $html .= html_writer::end_div(); // End modal-dialog.
        $html .= html_writer::end_div(); // End modal.

        // Injected JavaScript Controller.
        $initialbasecode_json = json_encode($info['basecode']);
        $initialurl_json = json_encode($info['url']);
        $initialname_json = json_encode($info['name']);
        $initialemoji_json = json_encode($info['emoji']);
        $initialisgif = $info['is_gif'] ? 'true' : 'false';

        $html .= html_writer::script("
        (function() {
            function initPicker() {
                var widget = document.getElementById('{$widgetid}');
                var modal = document.getElementById('{$modalid}');
                var hiddenInput = document.getElementById('{$inputid}');
                if (!widget || !modal || !hiddenInput) return;

                // Move modal to document.body so it is never trapped inside form stacking context.
                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }

                var currentBaseCode = {$initialbasecode_json};
                var currentScale = {$currentscale};
                var currentUrl = {$initialurl_json};
                var currentName = {$initialname_json};
                var currentIsGif = {$initialisgif};
                var currentEmoji = {$initialemoji_json};

                var scaleSlider = document.getElementById('{$widgetid}_scale_slider');
                var scaleBadge = document.getElementById('{$widgetid}_scale_badge');
                var thumbwrap = document.getElementById('{$widgetid}_thumbwrap');
                var nameEl = document.getElementById('{$widgetid}_name');
                var badgeEl = document.getElementById('{$widgetid}_badge');
                var openBtn = document.getElementById('{$widgetid}_open_btn');

                function showModal() {
                    // 1. Try jQuery Bootstrap modal if available.
                    if (window.jQuery && typeof jQuery(modal).modal === 'function') {
                        try {
                            jQuery(modal).modal('show');
                            return;
                        } catch(e) {}
                    }
                    // 2. Try Bootstrap 5 Modal if available.
                    if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                        try {
                            var bsModal = window.bootstrap.Modal.getOrCreateInstance(modal);
                            bsModal.show();
                            return;
                        } catch(e) {}
                    }
                    // 3. Fallback: Guaranteed pure JS display.
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    modal.removeAttribute('aria-hidden');
                    modal.setAttribute('aria-modal', 'true');
                    document.body.classList.add('modal-open');

                    var backdrop = document.getElementById('{$modalid}_backdrop');
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.id = '{$modalid}_backdrop';
                        backdrop.className = 'modal-backdrop fade show lp-modal-backdrop-custom';
                        document.body.appendChild(backdrop);
                        backdrop.addEventListener('click', hideModal);
                    } else {
                        backdrop.style.display = 'block';
                    }
                }

                function hideModal() {
                    if (window.jQuery && typeof jQuery(modal).modal === 'function') {
                        try { jQuery(modal).modal('hide'); } catch(e) {}
                    }
                    if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                        try {
                            var bsModal = window.bootstrap.Modal.getInstance(modal);
                            if (bsModal) bsModal.hide();
                        } catch(e) {}
                    }
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                    modal.setAttribute('aria-hidden', 'true');
                    modal.removeAttribute('aria-modal');
                    document.body.classList.remove('modal-open');

                    var backdrop = document.getElementById('{$modalid}_backdrop');
                    if (backdrop) {
                        backdrop.style.display = 'none';
                    }
                }

                // Connect Open Button.
                if (openBtn) {
                    openBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        showModal();
                    });
                }

                // Connect Close Buttons inside modal.
                modal.querySelectorAll('[data-dismiss=\"modal\"], [data-bs-dismiss=\"modal\"], .close').forEach(function(cbtn) {
                    cbtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        hideModal();
                    });
                });

                // Close on Escape key.
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && (modal.classList.contains('show') || modal.style.display === 'block')) {
                        hideModal();
                    }
                });

                // Tab switching inside modal.
                modal.querySelectorAll('.lp-picker-tabs-header .nav-link').forEach(function(tabLink) {
                    tabLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        var targetId = this.getAttribute('href');
                        if (!targetId || targetId === '#') {
                            targetId = this.getAttribute('data-target') || this.getAttribute('data-bs-target');
                        }
                        if (!targetId) return;

                        modal.querySelectorAll('.lp-picker-tabs-header .nav-link').forEach(function(l) {
                            l.classList.remove('active');
                        });
                        this.classList.add('active');

                        modal.querySelectorAll('.tab-content > .tab-pane').forEach(function(pane) {
                            pane.classList.remove('show', 'active');
                        });

                        var targetPane = modal.querySelector(targetId);
                        if (targetPane) {
                            targetPane.classList.add('show', 'active');
                        }
                    });
                });

                function setScale(scale, updateForm) {
                    scale = Math.max(50, Math.min(200, parseInt(scale, 10) || 100));
                    currentScale = scale;

                    if (scaleSlider) scaleSlider.value = scale;
                    if (scaleBadge) scaleBadge.textContent = scale + '%';

                    // Update active state on preset buttons.
                    widget.querySelectorAll('.lp-scale-preset-btn').forEach(function(btn) {
                        var bs = parseInt(btn.getAttribute('data-scale'), 10);
                        if (bs === scale) {
                            btn.classList.remove('btn-outline-secondary');
                            btn.classList.add('btn-primary', 'active', 'font-weight-bold');
                        } else {
                            btn.classList.remove('btn-primary', 'active', 'font-weight-bold');
                            btn.classList.add('btn-outline-secondary');
                        }
                    });

                    // Update live preview style.
                    var imgEl = document.getElementById('{$widgetid}_img');
                    var emojiEl = document.getElementById('{$widgetid}_emoji');
                    var target = imgEl || emojiEl;
                    if (target) {
                        target.style.transform = 'scale(' + (scale / 100) + ')';
                        target.style.transformOrigin = 'center center';
                    }

                    if (updateForm !== false) {
                        var finalCode = currentBaseCode;
                        if (scale !== 100) {
                            finalCode = currentBaseCode + '@' + scale;
                        }
                        document.querySelectorAll('input[name=\"{$fieldname}\"]').forEach(function(inp) {
                            inp.value = finalCode;
                            inp.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        if (hiddenInput) {
                            hiddenInput.value = finalCode;
                        }
                    }
                }

                // Connect range slider.
                if (scaleSlider) {
                    scaleSlider.addEventListener('input', function() {
                        setScale(this.value, true);
                    });
                    scaleSlider.addEventListener('change', function() {
                        setScale(this.value, true);
                    });
                }

                // Connect preset buttons.
                widget.querySelectorAll('.lp-scale-preset-btn').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var s = this.getAttribute('data-scale');
                        setScale(s, true);
                    });
                });

                // Connect zoom nudge buttons.
                var btnMinus = document.getElementById('{$widgetid}_zoom_out');
                var btnPlus = document.getElementById('{$widgetid}_zoom_in');
                var btnReset = document.getElementById('{$widgetid}_zoom_reset');

                if (btnMinus) {
                    btnMinus.addEventListener('click', function(e) {
                        e.preventDefault();
                        setScale(currentScale - 10, true);
                    });
                }
                if (btnPlus) {
                    btnPlus.addEventListener('click', function(e) {
                        e.preventDefault();
                        setScale(currentScale + 10, true);
                    });
                }
                if (btnReset) {
                    btnReset.addEventListener('click', function(e) {
                        e.preventDefault();
                        setScale(100, true);
                    });
                }

                // 1. Tile Selection inside modal
                modal.addEventListener('click', function(e) {
                    var tile = e.target.closest('.lp-modal-icon-tile');
                    if (!tile) return;

                    var code = tile.getAttribute('data-code');
                    var url = tile.getAttribute('data-url');
                    var name = tile.getAttribute('data-name');
                    var isgif = tile.getAttribute('data-isgif') === '1';

                    currentBaseCode = code;
                    currentUrl = url;
                    currentName = name;
                    currentIsGif = isgif;

                    // Update active class on all tiles in this modal.
                    modal.querySelectorAll('.lp-modal-icon-tile').forEach(function(t) {
                        t.classList.remove('active');
                    });
                    tile.classList.add('active');

                    // Update preview widget.
                    if (thumbwrap) {
                        var thumbstyle = (currentScale !== 100) ? 'transform: scale(' + (currentScale / 100) + '); transform-origin: center center;' : '';
                        thumbwrap.innerHTML = '<img src=\"' + url + '\" alt=\"' + name + '\" class=\"lp-picker-current-img ' + (isgif ? 'lp-icon-gif' : '') + '\" id=\"{$widgetid}_img\" style=\"' + thumbstyle + '\">';
                    }
                    if (nameEl) nameEl.textContent = name;
                    if (badgeEl) {
                        badgeEl.className = 'badge ' + (isgif ? 'badge-warning text-dark font-weight-bold' : (code.startsWith('gallery:') ? 'badge-primary' : 'badge-light border'));
                        badgeEl.textContent = isgif ? '✨ Animated GIF' : (code.startsWith('gallery:') ? 'Gallery Custom' : '3D Preset');
                    }

                    // Apply scale & update form.
                    setScale(currentScale, true);

                    // Dismiss modal on selection.
                    hideModal();
                });

                // 2. Client-side Category Filter inside modal
                var filterBtns = modal.querySelectorAll('.lp-modal-filter-btn');
                filterBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        filterBtns.forEach(function(b) { b.classList.remove('btn-primary', 'active'); b.classList.add('btn-outline-secondary'); });
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-primary', 'active');

                        var cat = this.getAttribute('data-cat');
                        var items = modal.querySelectorAll('#{$modalid}_gallery_grid .lp-modal-item-col');
                        items.forEach(function(item) {
                            var itemCat = item.getAttribute('data-category');
                            var isGif = item.getAttribute('data-isgif') === '1';
                            if (cat === 'all' || (cat === 'animated' && isGif) || (itemCat === cat)) {
                                item.style.display = '';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    });
                });

                // 3. Search Filter inside modal
                var searchInput = modal.querySelector('.lp-modal-search-input');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase().trim();
                        var items = modal.querySelectorAll('#{$modalid}_gallery_grid .lp-modal-item-col');
                        items.forEach(function(item) {
                            var name = (item.getAttribute('data-name') || '').toLowerCase();
                            if (!q || name.indexOf(q) !== -1) {
                                item.style.display = '';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    });
                }

                // 4. File input preview for quick upload
                var fileInput = document.getElementById('{$modalid}_file_input');
                var previewWrap = document.getElementById('{$modalid}_preview_wrap');
                var previewImg = document.getElementById('{$modalid}_preview_img');
                var dropIcon = document.getElementById('{$modalid}_drop_icon');
                var dropText = document.getElementById('{$modalid}_drop_text');
                var uploadBtn = document.getElementById('{$modalid}_upload_btn');
                var statusEl = document.getElementById('{$modalid}_upload_status');

                if (fileInput) {
                    fileInput.addEventListener('change', function(e) {
                        var f = e.target.files ? e.target.files[0] : null;
                        if (f) {
                            var r = new FileReader();
                            r.onload = function(re) {
                                if (previewImg) previewImg.src = re.target.result;
                                if (previewWrap) previewWrap.classList.remove('d-none');
                                if (dropIcon) dropIcon.classList.add('d-none');
                                if (dropText) dropText.textContent = f.name;
                            };
                            r.readAsDataURL(f);
                        }
                    });
                }

                // 5. Quick AJAX upload
                if (uploadBtn) {
                    uploadBtn.addEventListener('click', function() {
                        var f = fileInput && fileInput.files ? fileInput.files[0] : null;
                        if (!f) {
                            alert('Please choose an image or animated GIF file first.');
                            return;
                        }

                        var nameInput = document.getElementById('{$modalid}_name_input');
                        var catInput = document.getElementById('{$modalid}_cat_input');

                        var formData = new FormData();
                        formData.append('sesskey', '{$sesskey}');
                        formData.append('action', 'quick_upload_icon');
                        formData.append('file', f);
                        formData.append('name', nameInput ? nameInput.value : '');
                        formData.append('category', catInput ? catInput.value : 'steps');

                        uploadBtn.disabled = true;
                        uploadBtn.innerHTML = '<i class=\"fa fa-spinner fa-spin mr-1\"></i> Uploading...';
                        if (statusEl) {
                            statusEl.className = 'lp-upload-status small text-center mt-2 text-info';
                            statusEl.textContent = 'Uploading icon to gallery...';
                            statusEl.classList.remove('d-none');
                        }

                        fetch('{$ajaxurl}', {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            uploadBtn.disabled = false;
                            uploadBtn.innerHTML = '<i class=\"fa fa-cloud-upload mr-1\"></i> Upload & Apply Now';

                            if (data.success && data.icon) {
                                var icon = data.icon;
                                currentBaseCode = icon.code;
                                currentUrl = icon.url;
                                currentName = icon.name;
                                currentIsGif = icon.is_gif;

                                var thumbwrap = document.getElementById('{$widgetid}_thumbwrap');
                                var nameEl = document.getElementById('{$widgetid}_name');
                                var badgeEl = document.getElementById('{$widgetid}_badge');

                                if (thumbwrap) {
                                    var thumbstyle = (currentScale !== 100) ? 'transform: scale(' + (currentScale / 100) + '); transform-origin: center center;' : '';
                                    thumbwrap.innerHTML = '<img src=\"' + icon.url + '\" alt=\"' + icon.name + '\" class=\"lp-picker-current-img ' + (icon.is_gif ? 'lp-icon-gif' : '') + '\" id=\"{$widgetid}_img\" style=\"' + thumbstyle + '\">';
                                }
                                if (nameEl) nameEl.textContent = icon.name;
                                if (badgeEl) {
                                    badgeEl.className = 'badge ' + (icon.is_gif ? 'badge-warning text-dark font-weight-bold' : 'badge-primary');
                                    badgeEl.textContent = icon.is_gif ? '✨ Animated GIF' : 'Gallery Custom';
                                }

                                if (statusEl) {
                                    statusEl.className = 'lp-upload-status small text-center mt-2 text-success font-weight-bold';
                                    statusEl.textContent = 'Uploaded & applied successfully!';
                                }

                                // Apply scale and update hidden inputs.
                                setScale(currentScale, true);

                                setTimeout(function() {
                                    hideModal();
                                }, 600);
                            } else {
                                if (statusEl) {
                                    statusEl.className = 'lp-upload-status small text-center mt-2 text-danger font-weight-bold';
                                    statusEl.textContent = data.error || 'Upload failed';
                                }
                            }
                        })
                        .catch(function(err) {
                            uploadBtn.disabled = false;
                            uploadBtn.innerHTML = '<i class=\"fa fa-cloud-upload mr-1\"></i> Upload & Apply Now';
                            if (statusEl) {
                                statusEl.className = 'lp-upload-status small text-center mt-2 text-danger font-weight-bold';
                                statusEl.textContent = 'Upload error: ' + err.message;
                            }
                        });
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPicker);
            } else {
                initPicker();
            }
        })();
        ");

        return $html;
    }
}
