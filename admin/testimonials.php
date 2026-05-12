<?php
// ── AJAX: paginated rows ─────────────────────────────────────────────
if (isset($_GET['ajax_page'])) {
    require_once __DIR__ . '/../config.php';
    $perPage = 12;
    $page    = max(1, intval($_GET['ajax_page']));
    $total   = (int) db()->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
    $offset  = ($page - 1) * $perPage;
    $rows    = db()->query("SELECT * FROM testimonials ORDER BY sort_order ASC, created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    ob_start();
    foreach ($rows as $t) {
        $safName    = htmlspecialchars($t['client_name']);
        $safNameA   = htmlspecialchars($t['client_name'], ENT_QUOTES);
        $safCountry = htmlspecialchars($t['country'] ?? '');
        $rating     = floatval($t['rating']);
        $stars      = str_repeat('★', (int)floor($rating));
        $ratingFmt  = number_format($rating, 1);
        $platform   = strtolower($t['platform'] ?? 'upwork');
        $platformUC = ucfirst($platform);
        $review     = htmlspecialchars(substr($t['review_text'], 0, 80));
        $visible    = $t['is_featured'] ? '<span style="color:var(--sage);">✓</span>' : '<span style="color:#D5C9B8;">✗</span>';
        $thumb      = $t['client_image']
            ? '<img src="' . htmlspecialchars($t['client_image']) . '" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid #EDE6D6;">'
            : '<div style="width:44px;height:44px;border-radius:50%;background:var(--cream-dark);display:flex;align-items:center;justify-content:center;font-family:\'Cormorant Garamond\',serif;font-size:1.2rem;color:var(--sand-dark);">' . strtoupper(substr($t['client_name'], 0, 1)) . '</div>';
        echo '<tr>'
           . '<td>' . $thumb . '</td>'
           . '<td><div style="font-weight:500;">' . $safName . '</div>'
           . '<div style="font-size:0.75rem;color:var(--sage);">' . $safCountry . '</div></td>'
           . '<td><div style="display:flex;align-items:center;gap:4px;">'
           . '<span style="color:#C9A96E;">' . $stars . '</span>'
           . '<span style="font-size:0.8rem;font-weight:600;color:var(--sand-dark);">' . $ratingFmt . '</span>'
           . '</div></td>'
           . '<td><span class="platform-badge badge-' . $platform . '">' . $platformUC . '</span></td>'
           . '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.82rem;color:var(--sage);">&ldquo;' . $review . '&hellip;&rdquo;</td>'
           . '<td>' . $visible . '</td>'
           . '<td><div style="display:flex;gap:6px;">'
           . '<a href="testimonials.php?action=edit&edit=' . $t['id'] . '" class="btn-ghost" style="padding:0.4rem 0.875rem;font-size:0.75rem;">Edit</a>'
           . '<form method="POST" onsubmit="return false;" class="delete-form">'
           . '<input type="hidden" name="form_action" value="delete">'
           . '<input type="hidden" name="delete_id" value="' . $t['id'] . '">'
           . '<button type="button" class="btn-danger" onclick="confirmDelete(this.closest(\'form\'),\'testimonial\',\'' . $safNameA . '\')">Del</button>'
           . '</form></div></td>'
           . '</tr>';
    }
    header('Content-Type: application/json');
    echo json_encode(['html' => ob_get_clean(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    exit;
}

$pageTitle = 'Testimonials';
include '_header.php';

$action = $_GET['action'] ?? 'list';
$editId = intval($_GET['edit'] ?? 0);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['form_action'] ?? '';

    if ($act === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['client_name'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $rating = floatval($_POST['rating'] ?? 5.0);
        $review = trim($_POST['review_text'] ?? '');
        $platform = $_POST['platform'] ?? 'upwork';
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if (!$name || !$review) {
            $error = 'Name and review text are required.';
        } else {
            $clientImage = $_POST['existing_image'] ?? '';
            if (!empty($_FILES['client_image']['name'])) {
                $up = handleUpload($_FILES['client_image'], 'testimonials');
                if (isset($up['path'])) $clientImage = $up['path'];
                else $error = $up['error'];
            }

            if (!$error) {
                if ($id) {
                    $stmt = db()->prepare("UPDATE testimonials SET client_name=?,country=?,rating=?,review_text=?,platform=?,is_featured=?,client_image=? WHERE id=?");
                    $stmt->execute([$name,$country,$rating,$review,$platform,$isFeatured,$clientImage,$id]);
                } else {
                    $stmt = db()->prepare("INSERT INTO testimonials (client_name,country,rating,review_text,platform,is_featured,client_image) VALUES(?,?,?,?,?,?,?)");
                    $stmt->execute([$name,$country,$rating,$review,$platform,$isFeatured,$clientImage]);
                }
                $success = 'Testimonial saved!';
                $action = 'list';
            }
        }
    }

    if ($act === 'delete') {
        $id = intval($_POST['delete_id'] ?? 0);
        if ($id) {
            db()->prepare("DELETE FROM testimonials WHERE id=?")->execute([$id]);
            $success = 'Testimonial deleted.';
        }
    }
}

$editTesti = null;
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare("SELECT * FROM testimonials WHERE id=?");
    $stmt->execute([$editId]);
    $editTesti = $stmt->fetch();
    if (!$editTesti) $action = 'list';
}

$testimonials = db()->query("SELECT * FROM testimonials ORDER BY sort_order ASC, created_at DESC")->fetchAll();

// All possible ratings
$ratings = [1.0,1.5,2.0,2.5,3.0,3.5,4.0,4.5,5.0];
?>

<?php if($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:400;"><?= $action === 'edit' ? 'Edit Testimonial' : 'Add Testimonial' ?></h2>
    <a href="<?= SITE_URL ?>/admin/testimonials.php" class="btn-ghost">← Back</a>
</div>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="form_action" value="save">
    <input type="hidden" name="id" value="<?= $editTesti['id'] ?? 0 ?>">
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editTesti['client_image'] ?? '') ?>">
    <input type="hidden" name="rating" id="rating-input" value="<?= $editTesti['rating'] ?? 5.0 ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">
            <div class="admin-card">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label">Client Name *</label>
                        <input type="text" name="client_name" class="form-input" required value="<?= htmlspecialchars($editTesti['client_name'] ?? '') ?>" placeholder="Sarah Mitchell">
                    </div>
                    <div>
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-input" value="<?= htmlspecialchars($editTesti['country'] ?? '') ?>" placeholder="United States">
                    </div>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label class="form-label">Star Rating</label>
                    <div class="star-rating-input" id="star-buttons">
                        <?php foreach($ratings as $r): ?>
                        <button type="button" class="star-btn <?= (floatval($editTesti['rating'] ?? 5.0) == $r) ? 'selected' : '' ?>"
                                onclick="selectRating(<?= $r ?>)" data-val="<?= $r ?>">
                            <?php
                            // Show stars
                            $full = floor($r);
                            $half = ($r - $full) >= 0.4;
                            for($i=0;$i<$full;$i++) echo '★';
                            if($half) echo '½';
                            echo ' '.$r;
                            ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:0.72rem;color:var(--sage);margin-top:6px;">Currently selected: <strong id="rating-display"><?= $editTesti['rating'] ?? 5.0 ?></strong>/5</p>
                </div>

                <div>
                    <label class="form-label">Platform</label>
                    <select name="platform" class="form-select" style="max-width:200px;">
                        <?php foreach(['upwork','fiverr','google','direct'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($editTesti['platform']??'upwork')===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="admin-card">
                <label class="form-label">Client Review *</label>
                <textarea name="review_text" class="form-textarea" rows="6" required placeholder="Write the client's review here..."><?= htmlspecialchars($editTesti['review_text'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="space-y-5">
            <!-- <div class="admin-card">
                <label class="form-label">Client Photo</label>
                <?php if(!empty($editTesti['client_image'])): ?>
                <img src="<?= htmlspecialchars($editTesti['client_image']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #EDE6D6;display:block;margin-bottom:1rem;">
                <?php endif; ?>
                <input type="file" name="client_image" accept="image/*" class="form-input" style="padding:0.5rem;">
                <p style="font-size:0.72rem;color:var(--sage);margin-top:6px;">Square photo recommended. JPG or PNG.</p>
            </div> -->

            <div class="admin-card">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.875rem;color:var(--charcoal);">
                    <input type="checkbox" name="is_featured" <?= ($editTesti['is_featured'] ?? 1) ? 'checked' : '' ?>>
                    Show on Portfolio
                </label>
            </div>

            <button type="submit" class="btn-sand" style="width:100%;display:block;text-align:center;">
                <?= $action === 'edit' ? 'Update Testimonial' : 'Save Testimonial' ?>
            </button>
        </div>
    </div>
</form>

<script>
function selectRating(val) {
    document.getElementById('rating-input').value = val;
    document.getElementById('rating-display').textContent = val;
    document.querySelectorAll('.star-btn').forEach(btn => {
        btn.classList.toggle('selected', parseFloat(btn.dataset.val) === val);
    });
}
</script>

<?php else: ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <p style="color:var(--sage);font-size:0.85rem;"><?= count($testimonials) ?> testimonial<?= count($testimonials)!==1?'s':'' ?></p>
    <a href="testimonials.php?action=new" class="btn-sand">+ Add Testimonial</a>
</div>

<div class="admin-card" style="position:relative;">
    <?php if(empty($testimonials)): ?>
    <div style="text-align:center;padding:3rem;color:#B5A898;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;margin-bottom:0.5rem;">No testimonials yet</p>
        <a href="/admin/testimonials.php?action=new" class="btn-sand">+ Add First Testimonial</a>
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
                <th>Photo</th>
                <th>Client</th>
                <th>Rating</th>
                <th>Platform</th>
                <th>Review</th>
                <th>Visible</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="testi-tbody"></tbody>
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
.platform-badge { display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:0.65rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase; }
.badge-upwork { background:#E8F4E8;color:#14A800; }
.badge-fiverr { background:#E8F4E8;color:#1DBF73; }
.badge-google { background:#E8EEF8;color:#4285F4; }
.badge-direct { background:#F5F0E8;color:#A07840; }
</style>

<script>
var TESTI_TOTAL = <?= count($testimonials) ?>;
var TESTI_PER   = 12;
var TESTI_PAGE  = 1;
var TESTI_PAGES = Math.ceil(TESTI_TOTAL / TESTI_PER);

function loadTestiPage(page) {
    TESTI_PAGE = page;
    const spinner = document.getElementById('pg-spinner');
    const tbody   = document.getElementById('testi-tbody');
    if (spinner) spinner.style.display = 'flex';

    fetch('testimonials.php?ajax_page=' + page)
        .then(r => r.json())
        .then(data => {
            if (tbody) tbody.innerHTML = data.html;
            TESTI_TOTAL = data.total;
            TESTI_PAGES = Math.ceil(data.total / TESTI_PER);
            renderPgControls();
            if (spinner) spinner.style.display = 'none';
        })
        .catch(() => { if (spinner) spinner.style.display = 'none'; });
}

function renderPgControls() {
    const wrap = document.getElementById('pg-wrap');
    if (!wrap || TESTI_PAGES <= 1) { if(wrap) wrap.innerHTML = ''; return; }
    const start = (TESTI_PAGE - 1) * TESTI_PER + 1;
    const end   = Math.min(TESTI_PAGE * TESTI_PER, TESTI_TOTAL);
    let html = '<span style="font-size:0.78rem;color:var(--sage);">Showing ' + start + '–' + end + ' of ' + TESTI_TOTAL + '</span>';
    html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
    html += `<button class="pg-btn" onclick="loadTestiPage(${TESTI_PAGE-1})" ${TESTI_PAGE===1?'disabled':''}>← Prev</button>`;
    for (let i = 1; i <= TESTI_PAGES; i++) {
        if (TESTI_PAGES > 7 && Math.abs(i - TESTI_PAGE) > 2 && i !== 1 && i !== TESTI_PAGES) {
            if (i === TESTI_PAGE - 3 || i === TESTI_PAGE + 3) html += '<span style="padding:0.4rem 0.3rem;font-size:0.78rem;color:#B5A898;">…</span>';
            continue;
        }
        html += `<button class="pg-btn ${i===TESTI_PAGE?'active':''}" onclick="loadTestiPage(${i})">${i}</button>`;
    }
    html += `<button class="pg-btn" onclick="loadTestiPage(${TESTI_PAGE+1})" ${TESTI_PAGE===TESTI_PAGES?'disabled':''}>Next →</button>`;
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

if (document.getElementById('testi-tbody')) loadTestiPage(1);
</script>
<?php include '_footer.php'; ?>