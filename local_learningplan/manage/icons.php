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
 * Reusable Icon & Animated GIF Gallery Manager.
 *
 * @package    local_learningplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_learningplan\api;
use local_learningplan\navigation;

require_login();
$context = context_system::instance();
require_capability('local/learningplan:manage', $context);

global $DB, $USER, $PAGE, $OUTPUT;

$category = optional_param('category', 'all', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$iconid = optional_param('id', 0, PARAM_INT);

$pageurl = new moodle_url('/local/learningplan/manage/icons.php', array_filter([
    'category' => $category !== 'all' ? $category : '',
    'search'   => $search,
]));

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(local_learningplan_str('icongallery', 'Icon & GIF Gallery'));
$PAGE->set_heading(local_learningplan_str('icongallery', 'Icon & GIF Gallery'));
navigation_node::override_active_url(new moodle_url('/local/learningplan/manage/plans.php'));

$PAGE->requires->css(new moodle_url('/local/learningplan/styles.css'));

// Handle Actions.
if ($action === 'upload' && data_submitted() && confirm_sesskey()) {
    $name = optional_param('name', '', PARAM_TEXT);
    $uploadcategory = optional_param('category', 'general', PARAM_ALPHA);

    if (!empty($_FILES['iconfiles']['name'][0])) {
        $count = count($_FILES['iconfiles']['name']);
        $uploadedcount = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['iconfiles']['error'][$i] === UPLOAD_ERR_OK) {
                $filename = $_FILES['iconfiles']['name'][$i];
                $tmpname = $_FILES['iconfiles']['tmp_name'][$i];
                $content = file_get_contents($tmpname);
                $itemname = ($count === 1 && !empty($name)) ? $name : pathinfo($filename, PATHINFO_FILENAME);
                try {
                    api::save_gallery_icon_content($itemname, $uploadcategory, $filename, $content, (int)$USER->id);
                    $uploadedcount++;
                } catch (\Exception $e) {
                    // Continue with next file.
                }
            }
        }
        if ($uploadedcount > 0) {
            redirect($pageurl, local_learningplan_str('iconsuploaded_success', "Successfully uploaded {$uploadedcount} icon(s) to the gallery!"), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($pageurl, local_learningplan_str('uploaderror', 'Failed to upload icon. Please ensure file is PNG, JPG, SVG, WebP, or animated GIF.'), null, \core\output\notification::NOTIFY_ERROR);
        }
    } else if (!empty($_FILES['iconfile']['name']) && $_FILES['iconfile']['error'] === UPLOAD_ERR_OK) {
        $filename = $_FILES['iconfile']['name'];
        $tmpname = $_FILES['iconfile']['tmp_name'];
        $content = file_get_contents($tmpname);
        $itemname = !empty($name) ? $name : pathinfo($filename, PATHINFO_FILENAME);
        try {
            api::save_gallery_icon_content($itemname, $uploadcategory, $filename, $content, (int)$USER->id);
            redirect($pageurl, local_learningplan_str('iconuploaded_success', 'Icon uploaded successfully and ready for use!'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (\Exception $e) {
            redirect($pageurl, local_learningplan_str('uploaderror', 'Invalid file type: ' . $e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

if ($action === 'delete' && $iconid > 0 && confirm_sesskey()) {
    $icon = api::get_gallery_icon($iconid);
    if ($icon) {
        api::delete_gallery_icon($iconid);
        redirect($pageurl, local_learningplan_str('icondeleted_success', 'Icon removed from gallery.'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

if ($action === 'update' && $iconid > 0 && data_submitted() && confirm_sesskey()) {
    $newname = optional_param('name', '', PARAM_TEXT);
    $newcat = optional_param('category', 'general', PARAM_ALPHA);
    $icon = $DB->get_record('local_learningplan_icon', ['id' => $iconid]);
    if ($icon) {
        $icon->name = !empty($newname) ? $newname : $icon->name;
        $icon->category = !empty($newcat) ? $newcat : $icon->category;
        $DB->update_record('local_learningplan_icon', $icon);
        redirect($pageurl, local_learningplan_str('iconsaved_success', 'Icon details updated.'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// Global Header Navigation.
echo navigation::render_global_header('icons');

// Fetch gallery items.
$icons = api::get_gallery_icons($category !== 'all' ? $category : null, $search ?: null);

// Calculate overall statistics.
$allicons = api::get_gallery_icons();
$totalcount = count($allicons);
$gifcount = 0;
$totalusage = 0;
foreach ($allicons as $ic) {
    if ($ic->is_gif) {
        $gifcount++;
    }
    $totalusage += $ic->usage_count;
}

?>

<div class="lp-gallery-container my-4">
    <!-- Top Hero Banner & Quick Stats -->
    <div class="lp-gallery-hero p-4 rounded mb-4 shadow-sm">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex align-items-center mb-2">
                    <span class="lp-gallery-hero-icon mr-3">🎨</span>
                    <div>
                        <h3 class="font-weight-bold text-white mb-1"><?php echo local_learningplan_str('icongallery', 'Icon & GIF Gallery'); ?></h3>
                        <p class="text-white-50 mb-0"><?php echo local_learningplan_str('icongallery_desc', 'Upload custom icons and animated GIFs once, and easily reuse them across Learning Plans, Chapter milestones, and Step nodes.'); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mt-3 mt-lg-0 text-lg-right">
                <div class="d-inline-flex bg-dark-translucent p-2 rounded shadow-sm border border-light-translucent">
                    <div class="px-3 text-center border-right border-secondary">
                        <div class="h4 font-weight-bold text-white mb-0"><?php echo $totalcount; ?></div>
                        <small class="text-white-50"><?php echo local_learningplan_str('totalicons', 'Icons'); ?></small>
                    </div>
                    <div class="px-3 text-center border-right border-secondary">
                        <div class="h4 font-weight-bold text-warning mb-0">✨ <?php echo $gifcount; ?></div>
                        <small class="text-white-50"><?php echo local_learningplan_str('animatedgifs', 'GIFs'); ?></small>
                    </div>
                    <div class="px-3 text-center">
                        <div class="h4 font-weight-bold text-success mb-0">🔗 <?php echo $totalusage; ?></div>
                        <small class="text-white-50"><?php echo local_learningplan_str('activeuses', 'In Use'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Card & Drag-and-Drop Area -->
    <div class="card shadow-sm border-0 mb-4 lp-upload-card">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-cloud-upload text-primary mr-2" style="font-size: 1.25rem;"></i>
                <span><?php echo local_learningplan_str('uploadnewicon', 'Upload New Icon or Animated GIF'); ?></span>
            </div>
            <span class="badge badge-light border text-muted">Supports GIF, PNG, SVG, JPG, WebP</span>
        </div>
        <div class="card-body p-4">
            <form action="<?php echo $pageurl->out(false); ?>" method="post" enctype="multipart/form-data" id="lp-gallery-upload-form">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="upload">

                <div class="row">
                    <div class="col-lg-6 mb-3 mb-lg-0">
                        <div class="lp-dropzone p-4 rounded text-center border-dashed d-flex flex-column align-items-center justify-content-center" id="lp-dropzone">
                            <div class="lp-dropzone-preview d-none mb-2" id="lp-dropzone-preview">
                                <img src="" alt="Preview" class="lp-preview-thumb shadow-sm rounded" id="lp-preview-img">
                            </div>
                            <div class="lp-dropzone-icon mb-2" id="lp-dropzone-icon">
                                <i class="fa fa-file-image-o fa-3x text-primary opacity-75"></i>
                            </div>
                            <h6 class="font-weight-bold mb-1" id="lp-dropzone-text"><?php echo local_learningplan_str('dragdropfiles', 'Drag & drop image/GIF here or browse'); ?></h6>
                            <p class="text-muted small mb-3">Upload crisp animated GIFs, vector SVGs, or 3D icons (up to 8MB)</p>
                            <label class="btn btn-sm btn-outline-primary mb-0 cursor-pointer">
                                <i class="fa fa-folder-open-o mr-1"></i> <?php echo local_learningplan_str('browsefiles', 'Browse Files'); ?>
                                <input type="file" name="iconfiles[]" id="id_iconfiles" class="d-none" accept=".gif,.png,.jpg,.jpeg,.svg,.webp" multiple>
                            </label>
                        </div>
                    </div>

                    <div class="col-lg-6 d-flex flex-column justify-content-between">
                        <div>
                            <div class="form-group mb-3">
                                <label for="id_icon_name" class="font-weight-bold text-dark small mb-1"><?php echo local_learningplan_str('iconname_label', 'Icon Label / Name (Optional)'); ?></label>
                                <input type="text" name="name" id="id_icon_name" class="form-control" placeholder="e.g. Glowing Golden Trophy, Rocket Launch GIF">
                                <small class="text-muted">Descriptive name to easily identify and search for this icon.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label for="id_icon_cat" class="font-weight-bold text-dark small mb-1"><?php echo local_learningplan_str('category', 'Category'); ?></label>
                                <select name="category" id="id_icon_cat" class="custom-select">
                                    <option value="general">📁 General Assets</option>
                                    <option value="steps" selected>🎯 Step Journey Nodes</option>
                                    <option value="plans">🗺️ Plan Covers & Badges</option>
                                    <option value="badges">🏆 Milestones & Rewards</option>
                                    <option value="animated">✨ Animated GIFs</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm py-2">
                                <i class="fa fa-upload mr-1"></i> <?php echo local_learningplan_str('uploadandadd', 'Upload & Save to Gallery'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filter Toolbar & Search -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3 px-4">
            <div class="row align-items-center">
                <!-- Category Pills -->
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-muted small mr-2 font-weight-bold"><i class="fa fa-filter mr-1"></i> Filter:</span>
                        <?php
                        $cats = [
                            'all'      => ['label' => 'All Assets', 'icon' => 'fa-th-large'],
                            'animated' => ['label' => 'Animated GIFs', 'icon' => 'fa-bolt text-warning'],
                            'steps'    => ['label' => 'Steps', 'icon' => 'fa-dot-circle-o'],
                            'plans'    => ['label' => 'Plans', 'icon' => 'fa-map-o'],
                            'badges'   => ['label' => 'Badges', 'icon' => 'fa-trophy'],
                            'general'  => ['label' => 'General', 'icon' => 'fa-folder-o'],
                        ];
                        foreach ($cats as $ckey => $cinfo) {
                            $isactive = ($category === $ckey);
                            $caturl = new moodle_url('/local/learningplan/manage/icons.php', ['category' => $ckey, 'search' => $search]);
                            $btnclass = $isactive ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary';
                            echo html_writer::link(
                                $caturl,
                                '<i class="fa ' . $cinfo['icon'] . ' mr-1"></i> ' . $cinfo['label'],
                                ['class' => 'btn btn-sm ' . $btnclass . ' mr-1 mb-1 rounded-pill']
                            );
                        }
                        ?>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="col-lg-4">
                    <form action="<?php echo $pageurl->out(false); ?>" method="get" class="d-flex">
                        <?php if ($category !== 'all'): ?>
                            <input type="hidden" name="category" value="<?php echo s($category); ?>">
                        <?php endif; ?>
                        <div class="input-group">
                            <input type="text" name="search" id="id_gallery_search" class="form-control form-control-sm" placeholder="Search icons..." value="<?php echo s($search); ?>">
                            <div class="input-group-append">
                                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fa fa-search"></i></button>
                                <?php if (!empty($search)): ?>
                                    <a href="<?php echo (new moodle_url('/local/learningplan/manage/icons.php', ['category' => $category]))->out(false); ?>" class="btn btn-sm btn-outline-secondary" title="Clear search"><i class="fa fa-times"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Gallery Grid -->
    <?php if (empty($icons)): ?>
        <div class="card shadow-sm border-0 text-center p-5 my-4">
            <div class="py-4">
                <div class="lp-empty-icon-art mb-3">🖼️</div>
                <h4 class="font-weight-bold text-dark mb-2"><?php echo local_learningplan_str('noiconsfound', 'No icons in this view'); ?></h4>
                <p class="text-muted mx-auto" style="max-width: 500px;">
                    <?php if (!empty($search) || $category !== 'all'): ?>
                        No gallery assets match your current search or category filter. Try clearing filters or uploading a new icon.
                    <?php else: ?>
                        Your custom icon gallery is empty. Upload your favorite animated GIFs, PNG badges, or SVG icons above to start building visually stunning learning journeys!
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || $category !== 'all'): ?>
                    <a href="<?php echo (new moodle_url('/local/learningplan/manage/icons.php'))->out(false); ?>" class="btn btn-outline-primary">
                        <i class="fa fa-refresh mr-1"></i> View All Icons
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4" id="lp-gallery-grid">
            <?php foreach ($icons as $icon): ?>
                <?php
                $ext = strtoupper(pathinfo($icon->filename, PATHINFO_EXTENSION));
                $badgecolor = $icon->is_gif ? 'badge-warning text-dark font-weight-bold' : ($ext === 'SVG' ? 'badge-info' : 'badge-secondary');
                $sizekb = round($icon->filesize / 1024, 1);
                $code = 'gallery:' . $icon->id;
                ?>
                <div class="col mb-4 lp-gallery-item" data-name="<?php echo s(strtolower($icon->name . ' ' . $icon->filename)); ?>" data-category="<?php echo s($icon->category); ?>">
                    <div class="card h-100 shadow-sm border lp-gallery-card">
                        <!-- Preview Box with Checkered BG -->
                        <div class="lp-gallery-preview-box position-relative d-flex align-items-center justify-content-center p-4">
                            <img src="<?php echo $icon->url; ?>" alt="<?php echo s($icon->name); ?>" class="lp-gallery-thumb img-fluid" loading="lazy">
                            
                            <!-- Format Badge -->
                            <span class="badge <?php echo $badgecolor; ?> position-absolute lp-format-badge" style="top: 8px; left: 8px;">
                                <?php echo $icon->is_gif ? '✨ GIF' : $ext; ?>
                            </span>

                            <!-- Usage Pill -->
                            <?php if ($icon->usage_count > 0): ?>
                                <span class="badge badge-success position-absolute" style="top: 8px; right: 8px;" title="<?php echo $icon->usage_steps; ?> steps, <?php echo $icon->usage_plans; ?> plans">
                                    <i class="fa fa-link mr-1"></i> <?php echo $icon->usage_count; ?> uses
                                </span>
                            <?php else: ?>
                                <span class="badge badge-light border text-muted position-absolute" style="top: 8px; right: 8px;">
                                    Unused
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Card Details -->
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="font-weight-bold text-dark text-truncate mb-1" title="<?php echo s($icon->name); ?>">
                                    <?php echo s($icon->name); ?>
                                </h6>
                                <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                    <span class="badge badge-light border text-capitalize"><?php echo s($icon->category); ?></span>
                                    <span><?php echo $sizekb; ?> KB</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary lp-copy-code-btn py-1 px-2" data-code="<?php echo $code; ?>" title="Copy ID code (<?php echo $code; ?>)">
                                    <i class="fa fa-code mr-1"></i> <code>#<?php echo $icon->id; ?></code>
                                </button>

                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 lp-edit-icon-btn" 
                                        data-id="<?php echo $icon->id; ?>" 
                                        data-name="<?php echo s($icon->name); ?>" 
                                        data-category="<?php echo s($icon->category); ?>" 
                                        title="Rename / Edit category">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    <a href="<?php echo (new moodle_url($pageurl, ['action' => 'delete', 'id' => $icon->id, 'sesskey' => sesskey()]))->out(false); ?>" 
                                       class="btn btn-sm btn-outline-danger py-1 px-2 lp-delete-icon-btn"
                                       data-name="<?php echo s($icon->name); ?>"
                                       data-uses="<?php echo $icon->usage_count; ?>"
                                       title="Delete icon">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Icon Modal -->
<div class="modal fade" id="lpEditIconModal" tabindex="-1" role="dialog" aria-labelledby="lpEditIconModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="<?php echo $pageurl->out(false); ?>" method="post">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="lp_edit_icon_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold" id="lpEditIconModalLabel"><i class="fa fa-pencil text-primary mr-2"></i> Edit Icon Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label for="lp_edit_icon_name" class="font-weight-bold small">Icon Label</label>
                        <input type="text" name="name" id="lp_edit_icon_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="lp_edit_icon_cat" class="font-weight-bold small">Category</label>
                        <select name="category" id="lp_edit_icon_cat" class="custom-select">
                            <option value="general">📁 General Assets</option>
                            <option value="steps">🎯 Step Journey Nodes</option>
                            <option value="plans">🗺️ Plan Covers & Badges</option>
                            <option value="badges">🏆 Milestones & Rewards</option>
                            <option value="animated">✨ Animated GIFs</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Drag & drop image preview
    var fileInput = document.getElementById("id_iconfiles");
    var previewWrap = document.getElementById("lp-dropzone-preview");
    var previewImg = document.getElementById("lp-preview-img");
    var dropIcon = document.getElementById("lp-dropzone-icon");
    var dropText = document.getElementById("lp-dropzone-text");

    if (fileInput) {
        fileInput.addEventListener("change", function(e) {
            var files = e.target.files;
            if (files && files[0]) {
                if (files.length === 1) {
                    var reader = new FileReader();
                    reader.onload = function(re) {
                        previewImg.src = re.target.result;
                        previewWrap.classList.remove("d-none");
                        dropIcon.classList.add("d-none");
                        dropText.textContent = files[0].name;
                    };
                    reader.readAsDataURL(files[0]);
                } else {
                    previewWrap.classList.add("d-none");
                    dropIcon.classList.remove("d-none");
                    dropText.textContent = files.length + " files selected for upload";
                }
            }
        });
    }

    // 2. Copy Code Toast
    var copyBtns = document.querySelectorAll(".lp-copy-code-btn");
    copyBtns.forEach(function(btn) {
        btn.addEventListener("click", function() {
            var code = this.getAttribute("data-code");
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function() {
                    btn.classList.remove("btn-outline-secondary");
                    btn.classList.add("btn-success");
                    btn.innerHTML = '<i class="fa fa-check mr-1"></i> Copied!';
                    setTimeout(function() {
                        btn.classList.remove("btn-success");
                        btn.classList.add("btn-outline-secondary");
                        btn.innerHTML = '<i class="fa fa-code mr-1"></i> <code>' + code + '</code>';
                    }, 2000);
                });
            }
        });
    });

    // 3. Edit Icon Modal
    var editBtns = document.querySelectorAll(".lp-edit-icon-btn");
    editBtns.forEach(function(btn) {
        btn.addEventListener("click", function() {
            var id = this.getAttribute("data-id");
            var name = this.getAttribute("data-name");
            var cat = this.getAttribute("data-category");

            document.getElementById("lp_edit_icon_id").value = id;
            document.getElementById("lp_edit_icon_name").value = name;
            document.getElementById("lp_edit_icon_cat").value = cat;

            if (window.jQuery && jQuery("#lpEditIconModal").modal) {
                jQuery("#lpEditIconModal").modal("show");
            }
        });
    });

    // 4. Delete Icon Confirmation
    var delBtns = document.querySelectorAll(".lp-delete-icon-btn");
    delBtns.forEach(function(btn) {
        btn.addEventListener("click", function(e) {
            var name = this.getAttribute("data-name");
            var uses = parseInt(this.getAttribute("data-uses") || "0", 10);
            var msg = "Are you sure you want to delete '" + name + "' from the icon gallery?";
            if (uses > 0) {
                msg += "\n\nWARNING: This icon is currently active in " + uses + " step(s) or plan(s). Deleting it will cause them to revert to the default icon.";
            }
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
echo $OUTPUT->footer();
