<?php
// ── AJAX: paginated rows ─────────────────────────────────────────────
if (isset($_GET['ajax_page'])) {
    require_once __DIR__ . '/../config.php';
    $perPage = 12;
    $page    = max(1, intval($_GET['ajax_page']));
    $filter  = $_GET['filter'] ?? 'all';
    $where   = '';
    if ($filter === 'unread')    $where = 'WHERE is_read = 0';
    if ($filter === 'important') $where = 'WHERE is_important = 1';
    $total  = (int) db()->query("SELECT COUNT(*) FROM contact_messages $where")->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $msgs   = db()->query("SELECT * FROM contact_messages $where ORDER BY is_important DESC, created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    ob_start();
    foreach ($msgs as $msg) {
        $isUnread  = !$msg['is_read'];
        $rowBg     = $isUnread ? 'background:#FFFCF7;' : '';
        $starColor = $msg['is_important'] ? '#C9A96E' : '#D5C9B8';
        $starIcon  = $msg['is_important'] ? '★' : '☆';
        $nameWt    = $isUnread ? '600' : '400';
        $subjWt    = $isUnread ? '500' : '400';
        $dateFmt   = date('d M, g:i A', strtotime($msg['created_at']));
        $status    = $isUnread
            ? '<span style="display:inline-block;padding:2px 8px;background:#EFF7ED;color:#4A6B50;font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;">New</span>'
            : '<span style="font-size:0.75rem;color:#D5C9B8;">Read</span>';
        $safeName  = htmlspecialchars($msg['name']);
        $safeEmail = htmlspecialchars($msg['email']);
        $safeSubjDisp = htmlspecialchars($msg['subject']);
        $safeSubjAttr = htmlspecialchars($msg['subject'], ENT_QUOTES);
        echo "<tr style=\"{$rowBg}\">
  <td>
    <form method=\"POST\" style=\"display:inline;\">
      <input type=\"hidden\" name=\"action\" value=\"toggle_important\">
      <input type=\"hidden\" name=\"id\" value=\"{$msg['id']}\">
      <button type=\"submit\" style=\"background:none;border:none;cursor:pointer;font-size:1rem;color:{$starColor};padding:0;\">{$starIcon}</button>
    </form>
  </td>
  <td>
    <div style=\"font-weight:{$nameWt};\">{$safeName}</div>
    <div style=\"font-size:0.75rem;color:var(--sage);\">{$safeEmail}</div>
  </td>
  <td style=\"font-weight:{$subjWt};max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;\">{$safeSubjDisp}</td>
  <td style=\"font-size:0.8rem;color:var(--sage);white-space:nowrap;\">{$dateFmt}</td>
  <td>{$status}</td>
  <td>
    <div style=\"display:flex;gap:6px;align-items:center;\">
      <a href=\"messages.php?view={$msg['id']}\" style=\"font-size:0.78rem;color:var(--sand-dark);text-decoration:none;padding:0.35rem 0.75rem;border:1px solid var(--sand-light);\">View</a>
      <form method=\"POST\" onsubmit=\"return false;\" style=\"display:inline;\" class=\"delete-form\">
        <input type=\"hidden\" name=\"action\" value=\"delete\">
        <input type=\"hidden\" name=\"id\" value=\"{$msg['id']}\">
        <button type=\"button\" style=\"background:none;border:none;cursor:pointer;font-size:0.78rem;color:#C17B5C;font-family:'Jost',sans-serif;\" onclick=\"confirmDelete(this.closest('form'),'message','{$safeSubjAttr}')\" >Delete</button>
      </form>
    </div>
  </td>
</tr>";
    }
    header('Content-Type: application/json');
    echo json_encode(['html' => ob_get_clean(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    exit;
}

$pageTitle = 'Messages';
include '_header.php';

$success = $error = '';

// Handle actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    if ($act === 'mark_read' && $id) {
        db()->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
    }
    if ($act === 'toggle_important' && $id) {
        db()->prepare("UPDATE contact_messages SET is_important = NOT is_important WHERE id=?")->execute([$id]);
    }
    if ($act === 'delete' && $id) {
        db()->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
        $success = 'Message deleted.';
    }
    if ($act === 'mark_all_read') {
        db()->query("UPDATE contact_messages SET is_read=1");
        $success = 'All messages marked as read.';
    }
}

// View single message
$viewId = intval($_GET['view'] ?? 0);
$viewMsg = null;
if ($viewId) {
    $stmt = db()->prepare("SELECT * FROM contact_messages WHERE id=?");
    $stmt->execute([$viewId]);
    $viewMsg = $stmt->fetch();
    if ($viewMsg && !$viewMsg['is_read']) {
        db()->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$viewId]);
        $viewMsg['is_read'] = 1;
    }
}

// Filters
$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'unread') $where = 'WHERE is_read = 0';
if ($filter === 'important') $where = 'WHERE is_important = 1';

$messages = db()->query("SELECT * FROM contact_messages $where ORDER BY is_important DESC, created_at DESC")->fetchAll();

$unread = db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
$important = db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_important=1")->fetchColumn();
$total = db()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
?>

<?php if($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if($viewMsg): ?>
<!-- SINGLE MESSAGE VIEW -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:400;">Message Details</h2>
    <a href="<?= SITE_URL ?>/admin/messages.php" class="btn-ghost">← Back to Inbox</a>
</div>

<div class="admin-card" style="width:100%;">
    <!-- Message header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #EDE6D6;">
        <div>
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.5rem;">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--sand);display:flex;align-items:center;justify-content:center;font-family:'Cormorant Garamond',serif;font-size:1.3rem;color:white;font-weight:600;flex-shrink:0;">
                    <?= strtoupper(substr($viewMsg['name'],0,1)) ?>
                </div>
                <div>
                    <div style="font-weight:600;font-size:1rem;"><?= htmlspecialchars($viewMsg['name']) ?></div>
                    <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>" style="font-size:0.85rem;color:var(--sand-dark);text-decoration:none;"><?= htmlspecialchars($viewMsg['email']) ?></a>
                </div>
            </div>
            <div style="font-size:0.78rem;color:var(--sage);">
                Received: <?= date('d M Y, g:i A', strtotime($viewMsg['created_at'])) ?>
                <?php if($viewMsg['ip_address']): ?> 
                    <!-- · IP: <?= htmlspecialchars($viewMsg['ip_address']) ?> -->
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
            <!-- Toggle Important -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle_important">
                <input type="hidden" name="id" value="<?= $viewMsg['id'] ?>">
                <button type="submit" style="padding:0.5rem 1rem;border:1px solid <?= $viewMsg['is_important'] ? '#C9A96E' : '#E5DDD0' ?>;background:<?= $viewMsg['is_important'] ? 'rgba(201,169,110,0.1)' : 'white' ?>;cursor:pointer;font-family:'Jost',sans-serif;font-size:0.78rem;color:<?= $viewMsg['is_important'] ? '#A07840' : 'var(--charcoal)' ?>;">
                    <?= $viewMsg['is_important'] ? '★ Important' : '☆ Mark Important' ?>
                </button>
            </form>

            <!-- Reply via Send Email -->
            <a href="<?= SITE_URL ?>/admin/send-email.php?to=<?= urlencode($viewMsg['email']) ?>" class="btn-sand" style="display:inline-flex;align-items:center;gap:8px;padding:0.5rem 1rem">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Reply via Email
            </a>

            <!-- Delete -->
            <form method="POST" onsubmit="return false;" style="display:inline;" class="delete-form">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $viewMsg['id'] ?>">
                <button type="button" class="btn-danger" onclick="confirmDelete(this.closest('form'), 'message', '<?= htmlspecialchars($viewMsg['subject'], ENT_QUOTES) ?>')">Delete</button>
            </form>
        </div>
    </div>

    <!-- Subject -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.65rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--sage);margin-bottom:6px;">Subject</div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-weight:400;"><?= htmlspecialchars($viewMsg['subject']) ?></div>
    </div>

    <!-- Message body -->
    <div style="background:#FDFAF5;padding:2rem;border-left:3px solid #EDE6D6;">
        <div style="font-size:0.65rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--sage);margin-bottom:1rem;">Message</div>
        <p style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);white-space:pre-wrap;"><?= htmlspecialchars($viewMsg['message']) ?></p>
    </div>

    <!-- Quick reply box (opens Gmail) -->
    <!-- <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid #EDE6D6;">
        <p style="font-size:0.78rem;color:var(--sage);margin-bottom:1rem;">Quick Reply — compose message below, then click send to open Gmail:</p>
        <textarea id="quick-reply" rows="4" class="form-textarea" placeholder="Type your reply here..." style="margin-bottom:0.75rem;"></textarea>
        <button onclick="openGmailWithReply()" class="btn-sand" style="display:inline-flex;align-items:center;gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            Open Gmail & Send
        </button>
    </div> -->
</div>

<script>
function openGmailWithReply() {
    const reply = document.getElementById('quick-reply').value;
    const email = <?= json_encode($viewMsg['email']) ?>;
    const subject = <?= json_encode('Re: ' . $viewMsg['subject']) ?>;
    const original = "\n\n---\nOriginal message from <?= htmlspecialchars($viewMsg['name']) ?>:\n<?= htmlspecialchars(substr($viewMsg['message'],0,300)) ?>...";
    const body = encodeURIComponent(reply + original);
    const url = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(email)}&su=${encodeURIComponent(subject)}&body=${body}`;
    window.open(url, '_blank');
}
</script>

<?php else: ?>
<!-- MESSAGES LIST -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <!-- Filters -->
    <div style="display:flex;gap:0.5rem;">
        <a href="<?= SITE_URL ?>/admin/messages.php" style="padding:0.5rem 1rem;font-size:0.78rem;border:1px solid <?= $filter==='all'?'var(--charcoal)':'#E5DDD0' ?>;background:<?= $filter==='all'?'var(--charcoal)':'white' ?>;color:<?= $filter==='all'?'white':'var(--charcoal)' ?>;text-decoration:none;">
            All (<?= $total ?>)
        </a>
        <a href="<?= SITE_URL ?>/admin/messages.php?filter=unread" style="padding:0.5rem 1rem;font-size:0.78rem;border:1px solid <?= $filter==='unread'?'var(--charcoal)':'#E5DDD0' ?>;background:<?= $filter==='unread'?'var(--charcoal)':'white' ?>;color:<?= $filter==='unread'?'white':'var(--charcoal)' ?>;text-decoration:none;">
            Unread (<?= $unread ?>)
        </a>
        <a href="<?= SITE_URL ?>/admin/messages.php?filter=important" style="padding:0.5rem 1rem;font-size:0.78rem;border:1px solid <?= $filter==='important'?'var(--charcoal)':'#E5DDD0' ?>;background:<?= $filter==='important'?'var(--charcoal)':'white' ?>;color:<?= $filter==='important'?'white':'var(--charcoal)' ?>;text-decoration:none;">
            ★ Important (<?= $important ?>)
        </a>
    </div>

    <?php if($unread > 0): ?>
    <form method="POST">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn-ghost" style="font-size:0.78rem;">Mark All Read</button>
    </form>
    <?php endif; ?>
</div>

<div class="admin-card" style="position:relative;">
    <?php if($total == 0): ?>
    <div style="text-align:center;padding:3rem;color:#B5A898;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;margin-bottom:0.5rem;">No messages</p>
        <p style="font-size:0.875rem;">Messages from your portfolio contact form will appear here.</p>
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
                <th style="width:30px;">Priority</th>
                <th>From</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="msg-tbody"></tbody>
    </table>
    <!-- Pagination -->
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
var MSG_FILTER  = '<?= $filter ?>';
var MSG_TOTAL   = <?= $total ?>;
var MSG_PER     = 12;
var MSG_PAGE    = 1;
var MSG_PAGES   = Math.ceil(MSG_TOTAL / MSG_PER);

function loadMsgPage(page) {
    MSG_PAGE = page;
    const spinner = document.getElementById('pg-spinner');
    const tbody   = document.getElementById('msg-tbody');
    if (spinner) spinner.style.display = 'flex';

    fetch('messages.php?ajax_page=' + page + '&filter=' + MSG_FILTER)
        .then(r => r.json())
        .then(data => {
            if (tbody) tbody.innerHTML = data.html;
            MSG_TOTAL = data.total;
            MSG_PAGES = Math.ceil(data.total / MSG_PER);
            renderPgControls();
            if (spinner) spinner.style.display = 'none';
        })
        .catch(() => { if (spinner) spinner.style.display = 'none'; });
}

function renderPgControls() {
    const wrap = document.getElementById('pg-wrap');
    if (!wrap || MSG_PAGES <= 1) { if(wrap) wrap.innerHTML = ''; return; }
    const start = (MSG_PAGE - 1) * MSG_PER + 1;
    const end   = Math.min(MSG_PAGE * MSG_PER, MSG_TOTAL);
    let html = '<span style="font-size:0.78rem;color:var(--sage);">Showing ' + start + '–' + end + ' of ' + MSG_TOTAL + '</span>';
    html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
    html += `<button class="pg-btn" onclick="loadMsgPage(${MSG_PAGE-1})" ${MSG_PAGE===1?'disabled':''}>← Prev</button>`;
    for (let i = 1; i <= MSG_PAGES; i++) {
        if (MSG_PAGES > 7 && Math.abs(i - MSG_PAGE) > 2 && i !== 1 && i !== MSG_PAGES) {
            if (i === MSG_PAGE - 3 || i === MSG_PAGE + 3) html += '<span style="padding:0.4rem 0.3rem;font-size:0.78rem;color:#B5A898;">…</span>';
            continue;
        }
        html += `<button class="pg-btn ${i===MSG_PAGE?'active':''}" onclick="loadMsgPage(${i})">${i}</button>`;
    }
    html += `<button class="pg-btn" onclick="loadMsgPage(${MSG_PAGE+1})" ${MSG_PAGE===MSG_PAGES?'disabled':''}>Next →</button>`;
    html += '</div>';
    wrap.innerHTML = html;
}

function confirmDelete(form, type, name) {
    Swal.fire({
        title: 'Delete Message?',
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

// Initial load
if (document.getElementById('msg-tbody')) loadMsgPage(1);
</script>
<?php include '_footer.php'; ?>