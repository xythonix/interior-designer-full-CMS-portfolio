<?php
// ── AJAX: paginated rows ─────────────────────────────────────────────
if (isset($_GET['ajax_page'])) {
    require_once __DIR__ . '/../config.php';
    $perPage = 12;
    $page    = max(1, intval($_GET['ajax_page']));
    $total   = (int) db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $offset  = ($page - 1) * $perPage;
    $rows    = db()->query("SELECT * FROM projects ORDER BY sort_order ASC, created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    ob_start();
    foreach ($rows as $p) {
        $imgs      = json_decode($p['images'] ?? '[]', true);
        $imgCount  = count($imgs);
        $imgWord   = $imgCount !== 1 ? 's' : '';
        $safTitle  = htmlspecialchars($p['title']);
        $safTitleA = htmlspecialchars($p['title'], ENT_QUOTES);
        $safCat    = htmlspecialchars($p['category'] ?? '');
        $safSw     = htmlspecialchars($p['software_used'] ?? '');
        $dateFmt   = date('d M Y', strtotime($p['created_at']));
        $star      = $p['is_featured'] ? '<span style="color:#C9A96E;font-size:1.1rem;">★</span>' : '<span style="color:#D5C9B8;font-size:1.1rem;">☆</span>';
        $thumb     = $p['thumbnail']
            ? '<img src="' . htmlspecialchars($p['thumbnail']) . '" class="img-preview">'
            : '<div style="width:80px;height:60px;background:var(--cream-dark);display:flex;align-items:center;justify-content:center;"><span style="font-size:0.65rem;color:var(--sage);">No img</span></div>';
        echo '<tr>'
           . '<td>' . $thumb . '</td>'
           . '<td><div style="font-weight:500;">' . $safTitle . '</div>'
           . '<div style="font-size:0.75rem;color:var(--sage);">' . $imgCount . ' gallery image' . $imgWord . '</div></td>'
           . '<td style="font-size:0.85rem;">' . $safCat . '</td>'
           . '<td style="font-size:0.8rem;color:var(--sage);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $safSw . '</td>'
           . '<td>' . $star . '</td>'
           . '<td style="font-size:0.8rem;color:var(--sage);">' . $dateFmt . '</td>'
           . '<td><div style="display:flex;gap:6px;">'
           . '<a href="projects.php?action=edit&edit=' . $p['id'] . '" class="btn-ghost" style="padding:0.4rem 0.875rem;font-size:0.75rem;">Edit</a>'
           . '<form method="POST" onsubmit="return false;" style="display:inline;" class="delete-form">'
           . '<input type="hidden" name="form_action" value="delete">'
           . '<input type="hidden" name="delete_id" value="' . $p['id'] . '">'
           . '<button type="button" class="btn-danger" onclick="confirmDelete(this.closest(\'form\'),\'project\',\'' . $safTitleA . '\')">Delete</button>'
           . '</form></div></td>'
           . '</tr>';
    }
    header('Content-Type: application/json');
    echo json_encode(['html' => ob_get_clean(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// deleteUploadFile()
// Stored paths are URL-style: "/portfolio/uploads/projects/img_xxx.jpg"
// UPLOAD_URL  = "/portfolio/uploads/"   <- URL prefix (from config.php)
// UPLOAD_PATH = "C:\xampp\htdocs\portfolio\uploads\" <- real disk path
// Strategy: strip the UPLOAD_URL prefix, then join the remainder with UPLOAD_PATH
// ─────────────────────────────────────────────────────────────────────────────
function deleteUploadFile(string $storedPath): void {
    if (empty(trim($storedPath))) return;

    // Normalise both sides to forward-slash, no leading slash
    $urlBase  = ltrim(rtrim(UPLOAD_URL, '/'), '/');   // "portfolio/uploads"
    $stored   = ltrim(str_replace('\\', '/', $storedPath), '/'); // "portfolio/uploads/projects/img.jpg"

    if (stripos($stored, $urlBase) === 0) {
        // Remove the URL base to get the relative part: "projects/img.jpg"
        $relative = ltrim(substr($stored, strlen($urlBase)), '/');
    } else {
        // Fallback: maybe only "projects/img.jpg" or just a filename was stored
        $relative = $stored;
    }

    // Build absolute disk path (works on Windows and Linux)
    $diskPath = rtrim(UPLOAD_PATH, '/\\')
              . DIRECTORY_SEPARATOR
              . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (is_file($diskPath)) {
        @unlink($diskPath);
    }
}

$pageTitle = 'Projects';
include '_header.php';

$action = $_GET['action'] ?? 'list';
$editId = intval($_GET['edit'] ?? 0);
$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['form_action'] ?? '';

    if ($act === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = $_POST['description'] ?? '';
        $software = trim($_POST['software_used'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $category = trim($_POST['category'] ?? 'Interior Design');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if (!$title) {
            $error = 'Title is required.';
        } else {
            $slug = slugify($title);

            // Handle thumbnail upload
            $thumbnail = $_POST['existing_thumbnail'] ?? ''; // set by JS hidden field
            if (!empty($_FILES['thumbnail']['name'])) {
                $up = handleUpload($_FILES['thumbnail'], 'projects');
                if (isset($up['path'])) $thumbnail = $up['path'];
                else $error = $up['error'];
            }

            // Handle multiple project images
            $existingImages = array_values(array_filter($_POST['existing_images_keep'] ?? []));
            $newImages = [];
            if (!empty($_FILES['project_images']['name'][0])) {
                foreach ($_FILES['project_images']['name'] as $k => $name) {
                    if ($name && $_FILES['project_images']['error'][$k] === 0) {
                        $fileArr = [
                            'name' => $name,
                            'type' => $_FILES['project_images']['type'][$k],
                            'tmp_name' => $_FILES['project_images']['tmp_name'][$k],
                            'error' => $_FILES['project_images']['error'][$k],
                            'size' => $_FILES['project_images']['size'][$k],
                        ];
                        $up = handleUpload($fileArr, 'projects');
                        if (isset($up['path'])) $newImages[] = $up['path'];
                    }
                }
            }
            $allImages = array_merge($existingImages, $newImages);

            if (!$error) {
                if ($id) {
                    // Fetch the current DB record BEFORE updating so we can
                    // delete any files that the user removed during this edit.
                    $oldStmt = db()->prepare("SELECT thumbnail, images FROM projects WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $oldRow = $oldStmt->fetch();

                    if ($oldRow) {
                        // ── Thumbnail: delete old file if it was replaced or cleared ──
                        $oldThumb = $oldRow['thumbnail'] ?? '';
                        if ($oldThumb !== '' && $oldThumb !== $thumbnail) {
                            deleteUploadFile($oldThumb);
                        }

                        // ── Gallery: delete every old image no longer in $allImages ──
                        $oldGallery = json_decode($oldRow['images'] ?? '[]', true) ?: [];
                        foreach ($oldGallery as $oldImg) {
                            if ($oldImg !== '' && !in_array($oldImg, $allImages, true)) {
                                deleteUploadFile($oldImg);
                            }
                        }
                    }

                    $stmt = db()->prepare("UPDATE projects SET title=?,slug=?,description=?,software_used=?,features=?,category=?,is_featured=?,thumbnail=?,images=?,updated_at=NOW() WHERE id=?");
                    $stmt->execute([$title,$slug,$description,$software,$features,$category,$isFeatured,$thumbnail,json_encode($allImages),$id]);
                } else {
                    $stmt = db()->prepare("INSERT INTO projects (title,slug,description,software_used,features,category,is_featured,thumbnail,images) VALUES(?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$title,$slug,$description,$software,$features,$category,$isFeatured,$thumbnail,json_encode($allImages)]);
                }
                $success = 'Project saved successfully!';
                $action = 'list';
            }
        }
    }

    if ($act === 'delete') {
        $id = intval($_POST['delete_id'] ?? 0);
        if ($id) {
            // Fetch files BEFORE deleting the row — once the row is gone the
            // paths are unrecoverable.
            $delStmt = db()->prepare("SELECT thumbnail, images FROM projects WHERE id = ?");
            $delStmt->execute([$id]);
            $delRow = $delStmt->fetch();

            if ($delRow) {
                deleteUploadFile($delRow['thumbnail'] ?? '');
                $delGallery = json_decode($delRow['images'] ?? '[]', true) ?: [];
                foreach ($delGallery as $delImg) {
                    deleteUploadFile($delImg);
                }
            }

            db()->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
            $success = 'Project deleted.';
        }
    }
}

// Fetch for edit
$editProject = null;
if ($action === 'edit' && $editId) {
    $editProject = db()->prepare("SELECT * FROM projects WHERE id=?")->execute([$editId]) ? db()->prepare("SELECT * FROM projects WHERE id=?"): null;
    $stmt = db()->prepare("SELECT * FROM projects WHERE id=?");
    $stmt->execute([$editId]);
    $editProject = $stmt->fetch();
    if (!$editProject) { $action = 'list'; }
}

// Fetch all projects
$projects = db()->query("SELECT * FROM projects ORDER BY sort_order ASC, created_at DESC")->fetchAll();
?>

<?php if($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<!-- PROJECT FORM -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:400;"><?= $action === 'edit' ? 'Edit Project' : 'Add New Project' ?></h2>
    <a href="<?= SITE_URL ?>/admin/projects.php" class="btn-ghost">← Back to List</a>
</div>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="form_action" value="save">
    <input type="hidden" name="id" value="<?= $editProject['id'] ?? 0 ?>">
    <!-- existing_thumbnail and existing_images are now rendered inside their cards -->

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main fields -->
        <div class="lg:col-span-2 space-y-5">
            <div class="admin-card">
                <div style="margin-bottom:1.25rem;">
                    <label class="form-label">Project Title *</label>
                    <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($editProject['title'] ?? '') ?>" placeholder="e.g. Modern Living Room Redesign">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-input" value="<?= htmlspecialchars($editProject['category'] ?? 'Interior Design') ?>" placeholder="Interior Design">
                    </div>
                    <div>
                        <label class="form-label">Software Used</label>
                        <input type="text" name="software_used" class="form-input" value="<?= htmlspecialchars($editProject['software_used'] ?? '') ?>" placeholder="SketchUp, 3ds Max, Lumion">
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <label class="form-label" style="margin-bottom:0.75rem;">Description</label>
                <div id="editor" style="height:280px;"><?= $editProject['description'] ?? '' ?></div>
                <input type="hidden" name="description" id="description-input">
            </div>

            <div class="admin-card">
                <label class="form-label">Key Features (one per line)</label>
                <textarea name="features" class="form-textarea" rows="5" placeholder="Photorealistic 3D renders&#10;Custom furniture layout&#10;Lighting simulation&#10;Material selection"><?= htmlspecialchars($editProject['features'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Sidebar: Images & Settings -->
        <div class="space-y-5">
            <div class="admin-card">
                <label class="form-label">Thumbnail Image</label>

                <!-- Preview grid -->
                <div id="thumb-preview-grid" style="display:grid;grid-template-columns:1fr;gap:6px;margin-bottom:0.75rem;">
                    <?php if(!empty($editProject['thumbnail'])): ?>
                    <div class="preview-item" style="position:relative;">
                        <img src="<?= htmlspecialchars($editProject['thumbnail']) ?>"
                             style="width:100%;height:120px;object-fit:cover;border:1px solid #EDE6D6;display:block;">
                        <button type="button" onclick="removeExistingThumb(this)"
                                style="position:absolute;top:4px;right:4px;width:22px;height:22px;background:#e53e3e;border:none;border-radius:50%;color:white;font-size:13px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;">&#x2715;</button>
                        <input type="hidden" name="existing_thumbnail" id="existing-thumb-val" value="<?= htmlspecialchars($editProject['thumbnail'] ?? '') ?>">
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="existing_thumbnail" id="existing-thumb-val" value="">
                    <?php endif; ?>
                </div>

                <!-- File input — hidden, triggered by button -->
                <input type="file" name="thumbnail" id="thumb-file-input" accept="image/*"
                       style="display:none;" onchange="handleThumbSelect(this)">
                <button type="button" onclick="document.getElementById('thumb-file-input').click()"
                        class="btn-ghost" style="width:100%;text-align:center;font-size:0.75rem;padding:0.5rem;">
                    + Choose Thumbnail
                </button>
            </div>

            <div class="admin-card">
                <label class="form-label">Project Images (Gallery)</label>

                <!-- Existing saved images grid (PHP-rendered) -->
                <div id="existing-gallery-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:0.5rem;">
                    <?php
                    $existingGallery = json_decode($editProject['images'] ?? '[]', true) ?: [];
                    foreach($existingGallery as $gImg): ?>
                    <div class="preview-item" style="position:relative;">
                        <img src="<?= htmlspecialchars($gImg) ?>"
                             style="width:100%;height:70px;object-fit:cover;border:1px solid #EDE6D6;display:block;">
                        <button type="button" onclick="removeExistingGalleryImg(this, '<?= htmlspecialchars($gImg, ENT_QUOTES) ?>')"
                                style="position:absolute;top:3px;right:3px;width:20px;height:20px;background:#e53e3e;border:none;border-radius:50%;color:white;font-size:12px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;">&#x2715;</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tracks which existing images survive (not removed) -->
                <div id="existing-images-inputs">
                    <?php foreach($existingGallery as $gImg): ?>
                    <input type="hidden" name="existing_images_keep[]" value="<?= htmlspecialchars($gImg) ?>">
                    <?php endforeach; ?>
                </div>

                <!-- NEW images selected by user — preview grid -->
                <div id="new-gallery-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:0.5rem;"></div>

                <!-- Hidden file input — accumulates files via JS DataTransfer -->
                <input type="file" name="project_images[]" id="gallery-file-input"
                       multiple accept="image/*" style="display:none;"
                       onchange="handleGallerySelect(this)">
                <button type="button" onclick="document.getElementById('gallery-file-input').click()"
                        class="btn-ghost" style="width:100%;text-align:center;font-size:0.75rem;padding:0.5rem;">
                    + Add Gallery Images
                </button>
                <p style="font-size:0.72rem;color:var(--sage);margin-top:6px;">Select multiple — existing images are kept unless removed.</p>
            </div>

            <div class="admin-card">
                <label class="form-label">Settings</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.875rem;text-transform:none;letter-spacing:0;color:var(--charcoal);">
                    <input type="checkbox" name="is_featured" <?= ($editProject['is_featured'] ?? 0) ? 'checked' : '' ?>>
                    Featured Project
                </label>
            </div>

            <button type="submit" class="btn-sand w-full" style="width:100%;text-align:center;display:block;">
                <?= $action === 'edit' ? 'Update Project' : 'Publish Project' ?>
            </button>
        </div>
    </div>
</form>

<script>
// Initialize Quill
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ 'header': [1,2,3,false] }],
            ['bold','italic','underline'],
            [{ 'list': 'ordered' },{ 'list': 'bullet' }],
            ['link'],
            ['clean']
        ]
    }
});

// Set existing content
<?php if(!empty($editProject['description'])): ?>
quill.root.innerHTML = <?= json_encode($editProject['description']) ?>;
<?php endif; ?>

// On submit, copy HTML to hidden input
document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('description-input').value = quill.root.innerHTML;
});
</script>

<script>
/* ═══════════════════════════════════════════════════════
   THUMBNAIL — single image, replaces on new pick
═══════════════════════════════════════════════════════ */
function handleThumbSelect(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const grid = document.getElementById('thumb-preview-grid');

    // Clear any previous preview (keep only the hidden input for existing)
    grid.innerHTML = '';

    const reader = new FileReader();
    reader.onload = function(e) {
        const wrap = document.createElement('div');
        wrap.className = 'preview-item';
        wrap.style.cssText = 'position:relative;';
        wrap.innerHTML = `
            <img src="${e.target.result}"
                 style="width:100%;height:120px;object-fit:cover;border:1px solid #EDE6D6;display:block;">
            <button type="button" onclick="removeNewThumb(this)"
                    style="position:absolute;top:4px;right:4px;width:22px;height:22px;background:#e53e3e;border:none;border-radius:50%;color:white;font-size:13px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;">&#x2715;</button>
            <input type="hidden" name="existing_thumbnail" id="existing-thumb-val" value="">
        `;
        grid.appendChild(wrap);
    };
    reader.readAsDataURL(file);
}

function removeExistingThumb(btn) {
    // Remove preview, clear hidden value
    btn.closest('.preview-item').remove();
    // Add blank hidden input so PHP gets empty existing_thumbnail
    const grid = document.getElementById('thumb-preview-grid');
    const hi = document.createElement('input');
    hi.type = 'hidden'; hi.name = 'existing_thumbnail'; hi.id = 'existing-thumb-val'; hi.value = '';
    grid.appendChild(hi);
    // Clear file input so no new file is submitted
    document.getElementById('thumb-file-input').value = '';
}

function removeNewThumb(btn) {
    btn.closest('.preview-item').remove();
    // Re-add blank hidden input
    const grid = document.getElementById('thumb-preview-grid');
    const hi = document.createElement('input');
    hi.type = 'hidden'; hi.name = 'existing_thumbnail'; hi.id = 'existing-thumb-val'; hi.value = '';
    grid.appendChild(hi);
    // Clear the file input — user must re-pick
    const fi = document.getElementById('thumb-file-input');
    fi.value = '';
    // Reset via DataTransfer so the file is not submitted
    try { fi.files = new DataTransfer().files; } catch(e) {}
}

/* ═══════════════════════════════════════════════════════
   GALLERY — accumulates files; existing preserved until X
═══════════════════════════════════════════════════════ */

// We keep a DataTransfer to accumulate new files across multiple picks
var galleryDT = new DataTransfer();

function handleGallerySelect(input) {
    const newGrid = document.getElementById('new-gallery-grid');

    Array.from(input.files).forEach(function(file) {
        // Avoid duplicate filenames
        const existing = Array.from(galleryDT.files).some(f => f.name === file.name && f.size === file.size);
        if (existing) return;

        galleryDT.items.add(file);

        const reader = new FileReader();
        reader.onload = function(e) {
            const idx = galleryDT.files.length - 1; // approximate index
            const wrap = document.createElement('div');
            wrap.className = 'preview-item';
            wrap.dataset.filename = file.name;
            wrap.dataset.filesize = file.size;
            wrap.style.cssText = 'position:relative;';
            wrap.innerHTML = `
                <img src="${e.target.result}"
                     style="width:100%;height:70px;object-fit:cover;border:1px solid #EDE6D6;display:block;">
                <button type="button" onclick="removeNewGalleryImg(this)"
                        style="position:absolute;top:3px;right:3px;width:20px;height:20px;background:#e53e3e;border:none;border-radius:50%;color:white;font-size:12px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;">&#x2715;</button>
            `;
            newGrid.appendChild(wrap);
        };
        reader.readAsDataURL(file);
    });

    // Sync DataTransfer back to the actual file input
    syncGalleryInput();
}

function removeNewGalleryImg(btn) {
    const wrap = btn.closest('.preview-item');
    const fname = wrap.dataset.filename;
    const fsize = parseInt(wrap.dataset.filesize);

    // Rebuild DataTransfer without this file
    const newDT = new DataTransfer();
    Array.from(galleryDT.files).forEach(function(f) {
        if (!(f.name === fname && f.size === fsize)) {
            newDT.items.add(f);
        }
    });
    galleryDT = newDT;

    wrap.remove();
    syncGalleryInput();
}

function syncGalleryInput() {
    const input = document.getElementById('gallery-file-input');
    try {
        input.files = galleryDT.files;
    } catch(e) {
        // Fallback: DataTransfer assign not supported — files still accumulate via galleryDT
    }
}

function removeExistingGalleryImg(btn, imgPath) {
    // Remove the hidden input that keeps this image
    const container = document.getElementById('existing-images-inputs');
    Array.from(container.querySelectorAll('input[type=hidden]')).forEach(function(inp) {
        if (inp.value === imgPath) inp.remove();
    });
    // Remove preview
    btn.closest('.preview-item').remove();
}
</script>

<?php else: ?>
<!-- PROJECTS LIST -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <p style="color:var(--sage);font-size:0.85rem;" id="proj-count-label"><?= count($projects) ?> project<?= count($projects) !== 1 ? 's' : '' ?> total</p>
    <a href="<?= SITE_URL ?>/admin/projects.php?action=new" class="btn-sand">+ Add New Project</a>
</div>

<div class="admin-card" style="position:relative;">
    <?php if(empty($projects)): ?>
    <div style="text-align:center;padding:3rem;color:#B5A898;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;margin-bottom:0.5rem;">No projects yet</p>
        <p style="font-size:0.875rem;margin-bottom:1.5rem;">Add your first design project to showcase your work.</p>
        <a href="<?= SITE_URL ?>/admin/projects.php?action=new" class="btn-sand">+ Create First Project</a>
    </div>
    <?php else: ?>
    <!-- Spinner overlay -->
    <div id="pg-spinner" style="display:none;position:absolute;inset:0;background:rgba(253,250,245,0.75);z-index:10;align-items:center;justify-content:center;">
        <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
            <div style="width:32px;height:32px;border:2.5px solid #EDE6D6;border-top-color:#C9A96E;border-radius:50%;animation:spin360 0.7s linear infinite;"></div>
            <span style="font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);">Loading…</span>
        </div>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Thumbnail</th>
                <th>Title</th>
                <th>Category</th>
                <th>Software</th>
                <th>Featured</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="proj-tbody"></tbody>
    </table>
    <div id="pg-wrap" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 0 0;border-top:1px solid #EDE6D6;margin-top:0.5rem;flex-wrap:wrap;gap:0.5rem;"></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
@keyframes spin360 { to { transform:rotate(360deg); } }
.pg-btn {
    padding:0.4rem 0.75rem;font-size:0.78rem;border:1px solid #E5DDD0;background:white;
    color:var(--charcoal);cursor:pointer;font-family:'Jost',sans-serif;transition:background 0.15s,border-color 0.15s;
}
.pg-btn:hover:not(:disabled) { border-color:#C9A96E;background:rgba(201,169,110,0.07); }
.pg-btn.active { background:var(--charcoal);color:white;border-color:var(--charcoal); }
.pg-btn:disabled { opacity:0.38;cursor:default; }
</style>

<script>
var PROJ_TOTAL = <?= count($projects) ?>;
var PROJ_PER   = 12;
var PROJ_PAGE  = 1;
var PROJ_PAGES = Math.ceil(PROJ_TOTAL / PROJ_PER);

function loadProjPage(page) {
    PROJ_PAGE = page;
    const spinner = document.getElementById('pg-spinner');
    const tbody   = document.getElementById('proj-tbody');
    if (spinner) spinner.style.display = 'flex';

    fetch('projects.php?ajax_page=' + page)
        .then(r => r.json())
        .then(data => {
            if (tbody) tbody.innerHTML = data.html;
            PROJ_TOTAL = data.total;
            PROJ_PAGES = Math.ceil(data.total / PROJ_PER);
            renderPgControls();
            const lbl = document.getElementById('proj-count-label');
            if (lbl) lbl.textContent = data.total + ' project' + (data.total !== 1 ? 's' : '') + ' total';
            if (spinner) spinner.style.display = 'none';
        })
        .catch(() => { if (spinner) spinner.style.display = 'none'; });
}

function renderPgControls() {
    const wrap = document.getElementById('pg-wrap');
    if (!wrap || PROJ_PAGES <= 1) { if(wrap) wrap.innerHTML = ''; return; }
    const start = (PROJ_PAGE - 1) * PROJ_PER + 1;
    const end   = Math.min(PROJ_PAGE * PROJ_PER, PROJ_TOTAL);
    let html = '<span style="font-size:0.78rem;color:var(--sage);">Showing ' + start + '–' + end + ' of ' + PROJ_TOTAL + '</span>';
    html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
    html += `<button class="pg-btn" onclick="loadProjPage(${PROJ_PAGE-1})" ${PROJ_PAGE===1?'disabled':''}>← Prev</button>`;
    for (let i = 1; i <= PROJ_PAGES; i++) {
        if (PROJ_PAGES > 7 && Math.abs(i - PROJ_PAGE) > 2 && i !== 1 && i !== PROJ_PAGES) {
            if (i === PROJ_PAGE - 3 || i === PROJ_PAGE + 3) html += '<span style="padding:0.4rem 0.3rem;font-size:0.78rem;color:#B5A898;">…</span>';
            continue;
        }
        html += `<button class="pg-btn ${i===PROJ_PAGE?'active':''}" onclick="loadProjPage(${i})">${i}</button>`;
    }
    html += `<button class="pg-btn" onclick="loadProjPage(${PROJ_PAGE+1})" ${PROJ_PAGE===PROJ_PAGES?'disabled':''}>Next →</button>`;
    html += '</div>';
    wrap.innerHTML = html;
}

function confirmDelete(form, type, name) {
    Swal.fire({
        title: 'Delete ' + type.charAt(0).toUpperCase() + type.slice(1) + '?',
        html: '<span style="color:#4A4A4A;">Are you sure you want to delete <strong>"' + name + '"</strong>? This cannot be undone.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) form.submit();
    });
}

if (document.getElementById('proj-tbody')) loadProjPage(1);
</script>
<?php include '_footer.php'; ?>