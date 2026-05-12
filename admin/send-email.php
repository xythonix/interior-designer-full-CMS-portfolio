<?php
// ── Output buffering — catches any stray whitespace/notices ──────────
ob_start();

// ── Bootstrap PHPMailer ──────────────────────────────────────────────
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';

require_once __DIR__ . '/../config.php';

$FROM_EMAIL = 'xythonfreelancer@gmail.com';
$FROM_NAME  = 'A. Moeed - MyDesignAssistants';

// ── Attachments upload directory ─────────────────────────────────────
define('ATTACH_DIR', __DIR__ . '/uploads/email-attachments/');
if (!is_dir(ATTACH_DIR)) mkdir(ATTACH_DIR, 0755, true);

// ── Ensure DB table exists (with attachments column) ─────────────────
db()->exec("
    CREATE TABLE IF NOT EXISTS `email_logs` (
        `id`          INT(11)               NOT NULL AUTO_INCREMENT,
        `from_email`  VARCHAR(255)          NOT NULL,
        `to_emails`   TEXT                  NOT NULL,
        `subject`     VARCHAR(500)          NOT NULL,
        `body`        LONGTEXT              NOT NULL,
        `attachments` LONGTEXT              DEFAULT NULL COMMENT 'JSON array of stored filenames',
        `status`      ENUM('sent','failed') DEFAULT 'sent',
        `fail_reason` TEXT                  DEFAULT NULL,
        `sent_at`     TIMESTAMP             NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Add attachments column if upgrading from old schema
try {
    db()->exec("ALTER TABLE `email_logs` ADD COLUMN IF NOT EXISTS `attachments` LONGTEXT DEFAULT NULL COMMENT 'JSON array of stored filenames' AFTER `body`");
} catch (\Exception $e) { /* column already exists */ }

// ── AJAX: Delete email log ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email'])) {
    ob_clean();
    header('Content-Type: application/json');
    $delId = intval($_POST['delete_id'] ?? 0);
    if (!$delId) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }
    // Fetch stored attachments and delete from disk
    $s = db()->prepare("SELECT attachments FROM email_logs WHERE id = ?");
    $s->execute([$delId]);
    $row = $s->fetch();
    if ($row && $row['attachments']) {
        $files = json_decode($row['attachments'], true) ?: [];
        foreach ($files as $fname) {
            $path = ATTACH_DIR . basename($fname);
            if (file_exists($path)) unlink($path);
        }
    }
    db()->prepare("DELETE FROM email_logs WHERE id = ?")->execute([$delId]);
    echo json_encode(['success' => true]);
    exit;
}

// ── AJAX send handler — before any HTML output ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {

    ob_clean();
    header('Content-Type: application/json');

    $toRaw   = trim($_POST['to']      ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body']    ?? '');

    $toList = array_filter(array_map('trim', explode(',', $toRaw)));

    if (empty($toList) || !$subject || !$body) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Validate emails
    $invalid = [];
    foreach ($toList as $e) {
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) $invalid[] = $e;
    }
    if ($invalid) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address(es): ' . implode(', ', $invalid)]);
        exit;
    }

    // ── Handle uploaded attachments ───────────────────────────────────
    $savedFiles     = []; // stored disk filenames (for DB)
    $attachmentMeta = []; // [{name, path, size}] for PHPMailer
    $allowedTypes   = [
        'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip','application/x-zip-compressed',
        'application/x-rar-compressed','application/x-rar','application/vnd.rar','application/octet-stream',
        'text/plain','text/csv',
    ];

    // ── Normalize $_FILES into a flat list ──────────────────────────
    // JS uses fd.append('attachments[]', file) which PHP reliably
    // parses into $_FILES['attachments']['name'][0..n].
    // We also fall back to handle any other shape just in case.
    $uploads = [];

    // Primary path: standard array shape from 'attachments[]'
    if (!empty($_FILES['attachments']['name'])) {
        $bucket = $_FILES['attachments'];
        if (is_array($bucket['name'])) {
            foreach ($bucket['name'] as $i => $name) {
                if ($name === '' || $name === null) continue;
                $uploads[] = [
                    'name'     => $name,
                    'tmp_name' => $bucket['tmp_name'][$i],
                    'error'    => $bucket['error'][$i],
                    'size'     => $bucket['size'][$i],
                ];
            }
        } else {
            // Single file landed without array brackets
            if ($bucket['name'] !== '') {
                $uploads[] = [
                    'name'     => $bucket['name'],
                    'tmp_name' => $bucket['tmp_name'],
                    'error'    => $bucket['error'],
                    'size'     => $bucket['size'],
                ];
            }
        }
    }

    // Fallback path: indexed keys like attachments[0], attachments[1]
    // (older FormData.append style — covers all edge cases)
    if (empty($uploads)) {
        foreach ($_FILES as $key => $fileData) {
            // Match attachments, attachments[0], attachments[1], etc.
            if (!preg_match('/^attachments(\[\d*\])?$/', $key)) continue;
            if (is_array($fileData['name'])) {
                foreach ($fileData['name'] as $i => $name) {
                    if ($name === '' || $name === null) continue;
                    $uploads[] = [
                        'name'     => $name,
                        'tmp_name' => $fileData['tmp_name'][$i],
                        'error'    => $fileData['error'][$i],
                        'size'     => $fileData['size'][$i],
                    ];
                }
            } else {
                if ($fileData['name'] !== '') {
                    $uploads[] = [
                        'name'     => $fileData['name'],
                        'tmp_name' => $fileData['tmp_name'],
                        'error'    => $fileData['error'],
                        'size'     => $fileData['size'],
                    ];
                }
            }
        }
    }

    foreach ($uploads as $upload) {
        if ($upload['error'] !== UPLOAD_ERR_OK) continue;
        if ($upload['size'] > 10 * 1024 * 1024) continue;
        if (!is_uploaded_file($upload['tmp_name'])) continue;
        $mime = mime_content_type($upload['tmp_name']);
        if (!in_array($mime, $allowedTypes)) continue;
        // Remove dot from uniqid prefix to avoid filename parsing issues
        $safeOrig = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($upload['name']));
        $stored   = 'att_' . str_replace('.', '', uniqid('', true)) . '_' . $safeOrig;
        $dest     = ATTACH_DIR . $stored;
        if (move_uploaded_file($upload['tmp_name'], $dest)) {
            $savedFiles[]     = $stored;
            $attachmentMeta[] = ['name' => $upload['name'], 'path' => $dest];
        }
    }

    // Strip ALL <img> tags from Quill body.
    // The editor has no image-insert button, so any <img> in the body is either:
    //   (a) a base64 data URI from a paste, or
    //   (b) a Quill internal blot/cursor artifact
    // Both appear as phantom attachments in Gmail (e.g. "underline text.PNG").
    $body = preg_replace('/<img[^>]*>/i', '', $body);
    // Also strip any empty <span> Quill cursor markers
    $body = preg_replace('/<span\b[^>]*class="[^"]*ql-cursor[^"]*"[^>]*>.*?<\/span>/is', '', $body);

    // Build branded HTML email
    $emailHtml = buildEmailTemplate($subject, $body, $FROM_NAME);

    // Send via PHPMailer
    $mail       = new PHPMailer(true);
    $failReason = null;
    $status     = 'sent';

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $FROM_EMAIL;
        $mail->Password   = 'mqleyjoailihvsrf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($FROM_EMAIL, $FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $emailHtml;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        foreach ($toList as $email) {
            $mail->addAddress($email);
        }

        // Attach files
        foreach ($attachmentMeta as $att) {
            $mail->addAttachment($att['path'], $att['name']);
        }

        $mail->send();
    } catch (Exception $e) {
        $status     = 'failed';
        $failReason = $mail->ErrorInfo;
    }

    // If failed, clean up uploaded files
    if ($status === 'failed') {
        foreach ($savedFiles as $f) {
            $p = ATTACH_DIR . $f;
            if (file_exists($p)) unlink($p);
        }
        $savedFiles = [];
    }

    // Log to DB
    db()->prepare("
        INSERT INTO email_logs (from_email, to_emails, subject, body, attachments, status, fail_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $FROM_EMAIL,
        implode(', ', $toList),
        $subject,
        $body,
        $savedFiles ? json_encode($savedFiles) : null,
        $status,
        $failReason
    ]);

    ob_clean();
    if ($status === 'sent') {
        echo json_encode(['success' => true, 'message' => 'Email sent successfully to ' . count($toList) . ' recipient(s).']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send. ' . $failReason]);
    }
    exit;
}


// ── AJAX: paginated email history rows ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_page'])) {
    ob_clean();
    header('Content-Type: application/json');
    $perPage = 12;
    $page    = max(1, intval($_GET['ajax_page']));
    $total   = (int) db()->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
    $offset  = ($page - 1) * $perPage;
    $rows    = db()->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    ob_start();
    foreach ($rows as $log) {
        $logAtts  = !empty($log['attachments']) ? (json_decode($log['attachments'], true) ?: []) : [];
        $attCount = count($logAtts);
        $attCell  = $attCount
            ? '<div style="display:flex;align-items:center;gap:5px;font-size:0.78rem;color:var(--sage);"><span>&#9675;</span><span>' . $attCount . ' file' . ($attCount > 1 ? 's' : '') . '</span></div>'
            : '<span style="font-size:0.72rem;color:#D5C9B8;">&#8212;</span>';
        $badge    = $log['status'] === 'sent'
            ? '<span class="badge-sent">Sent</span>'
            : '<span class="badge-failed">Failed</span>';
        $dateFmt  = date('d M Y, g:i A', strtotime($log['sent_at']));
        $recipients = array_map('trim', explode(',', $log['to_emails']));
        $shown = array_slice($recipients, 0, 2);
        $recipHtml = '';
        foreach ($shown as $r) {
            $recipHtml .= '<div style="font-size:0.78rem;color:var(--sage);">' . htmlspecialchars($r) . '</div>';
        }
        if (count($recipients) > 2) {
            $recipHtml .= '<div style="font-size:0.72rem;color:#B5A898;">+' . (count($recipients) - 2) . ' more</div>';
        }
        $safSubj = htmlspecialchars($log['subject']);
        $logId   = (int) $log['id'];
        echo '<tr id="log-row-' . $logId . '">'
           . '<td style="font-weight:500;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $safSubj . '</td>'
           . '<td>' . $recipHtml . '</td>'
           . '<td>' . $attCell . '</td>'
           . '<td style="font-size:0.8rem;color:var(--sage);white-space:nowrap;">' . $dateFmt . '</td>'
           . '<td>' . $badge . '</td>'
           . '<td><div style="display:flex;gap:6px;align-items:center;">'
           . '<a href="?view=' . $logId . '" style="font-size:0.78rem;color:var(--sand-dark);text-decoration:none;padding:0.35rem 0.75rem;border:1px solid var(--sand);transition:background 0.15s;" onmouseover="this.style.background=\'rgba(201,169,110,0.12)\'" onmouseout="this.style.background=\'transparent\'">View</a>'
           . '<button class="btn-delete" style="font-size:0.78rem;padding:0.35rem 0.75rem;" onclick="deleteEmail(' . $logId . ', false)">Delete</button>'
           . '</div></td>'
           . '</tr>';
    }
    echo json_encode(['html' => ob_get_clean(), 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    exit;
}

// ── Not an AJAX request — render the page ────────────────────────────
$pageTitle = 'Send Email';
include '_header.php';

// ── View single history entry ─────────────────────────────────────────
$viewId  = intval($_GET['view'] ?? 0);
$viewLog = null;
if ($viewId) {
    $s = db()->prepare("SELECT * FROM email_logs WHERE id = ?");
    $s->execute([$viewId]);
    $viewLog = $s->fetch();
}

// ── Pre-fill TO from query string (e.g. reply from messages.php) ─────
$prefillTo = trim($_GET['to'] ?? '');

// ── Show history tab ─────────────────────────────────────────────────
$showHistory = isset($_GET['history']) || $viewId;

// ── History data ─────────────────────────────────────────────────────
$logs = [];
if ($showHistory && !$viewLog) {
    $logs = db()->query("SELECT * FROM email_logs ORDER BY sent_at DESC")->fetchAll();
}

// ── Recent sidebar ───────────────────────────────────────────────────
$recentLogs = db()->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 5")->fetchAll();

// ── Helper: human-readable filesize ──────────────────────────────────
function humanSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ── Helper: file icon by extension ───────────────────────────────────
function fileIcon(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'jpg'=>'○','jpeg'=>'○','png'=>'○','gif'=>'○','webp'=>'○','svg'=>'○',
        'pdf'=>'○','doc'=>'○','docx'=>'○',
        'xls'=>'○','xlsx'=>'○','csv'=>'○',
        'ppt'=>'○','pptx'=>'○',
        'zip'=>'○','rar'=>'○',
        'txt'=>'○',
    ];
    return $map[$ext] ?? '○';
}

// ── Build branded HTML email ─────────────────────────────────────────
function buildEmailTemplate(string $subject, string $body, string $senderName): string {
    $safeSubject = htmlspecialchars($subject);
    $safeSender  = htmlspecialchars($senderName);
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$safeSubject}</title>
</head>
<body style="margin:0;padding:0;background:#F5F0E8;font-family:Georgia,serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F0E8;padding:40px 20px;">
<tr><td align="center">
  <table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;background:#fff;border:1px solid #EDE6D6;">
    <tr><td style="height:3px;background:linear-gradient(90deg,#C9A96E,#A07840);font-size:0;"></td></tr>
    <tr>
      <td style="padding:40px 48px 32px;text-align:center;border-bottom:1px solid #EDE6D6;background:#FDFAF5;">
        <div style="font-family:Georgia,serif;font-size:28px;font-weight:700;color:#2C2C2C;letter-spacing:2px;">A. Moeed</div>
        <div style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:4px;text-transform:uppercase;color:#C9A96E;margin-top:4px;">MyDesignAssistants</div>
        <div style="width:40px;height:1px;background:#C9A96E;margin:16px auto 0;"></div>
      </td>
    </tr>
    <tr>
      <td style="padding:0;background:#3B2A1A;">
        <div style="font-family:Arial,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#C9A96E;padding:14px 48px;">{$safeSubject}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:28px 48px 36px;font-family:Georgia,serif;font-size:15px;color:#4A4A4A;line-height:1.9;">
        {$body}
      </td>
    </tr>
    <tr><td style="padding:0 48px;"><div style="height:1px;background:#EDE6D6;"></div></td></tr>
    <tr>
      <td style="padding:28px 48px;text-align:center;">
        <a href="https://mydesignassistants.com" style="display:inline-block;padding:12px 36px;background:#2C2C2C;color:#ffffff;font-family:Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;text-decoration:none;">HIRE ME &rarr;</a>
      </td>
    </tr>
    <tr>
      <td style="padding:20px 48px 32px;text-align:center;background:#FDFAF5;border-top:1px solid #EDE6D6;">
        <div style="font-family:Arial,sans-serif;font-size:11px;color:#B5A898;line-height:1.7;">
          This email was sent by <strong style="color:#7A8C7E;">{$safeSender}</strong><br>
          <a href="mailto:moeed@mydesignassistants.com" style="color:#C9A96E;text-decoration:none;">moeed@mydesignassistants.com</a>
          &nbsp;&middot;&nbsp;
          <a href="https://mydesignassistants.com" style="color:#C9A96E;text-decoration:none;">mydesignassistants.com</a>
        </div>
        <div style="font-family:Arial,sans-serif;font-size:10px;color:#D5C9B8;margin-top:12px;letter-spacing:1px;">&copy; 2026 MyDesignAssistants. All rights reserved.</div>
      </td>
    </tr>
    <tr><td style="height:3px;background:linear-gradient(90deg,#A07840,#C9A96E);font-size:0;"></td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
HTML;
}
?>

<style>
/* ── Tag input ─────────────────────────────────────────────── */
.tag-input-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0.55rem 0.875rem;
    border: 1px solid #E5DDD0;
    background: white;
    cursor: text;
    min-height: 50px;
    align-items: center;
    transition: border-color 0.2s;
}
.tag-input-wrapper:focus-within { border-color: #C9A96E; }
.tag-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(201,169,110,0.12);
    border: 1px solid rgba(201,169,110,0.4);
    color: #7A5A30;
    padding: 3px 10px 3px 12px;
    font-size: 0.78rem;
    font-family: 'Jost', sans-serif;
    border-radius: 2px;
    animation: tagIn 0.15s ease;
}
@keyframes tagIn { from{opacity:0;transform:scale(0.88);} to{opacity:1;transform:scale(1);} }
.tag-remove {
    background: none;
    border: none;
    cursor: pointer;
    color: #A07840;
    font-size: 1.05rem;
    line-height: 1;
    padding: 0 2px;
    transition: color 0.15s;
    display: flex;
    align-items: center;
}
.tag-remove:hover { color: #C17B5C; }
#tag-real-input {
    flex: 1;
    min-width: 180px;
    border: none;
    outline: none;
    font-family: 'Jost', sans-serif;
    font-size: 0.875rem;
    color: #2C2C2C;
    background: transparent;
    padding: 2px 0;
}
/* ── Quill body ─────────────────────────────────────────────── */
#email-body-editor .ql-container { min-height: 270px; }
/* ── Status badges ──────────────────────────────────────────── */
.badge-sent   { display:inline-block;padding:2px 10px;background:#EFF7ED;color:#4A6B50;font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;font-weight:600; }
.badge-failed { display:inline-block;padding:2px 10px;background:#FEF3E8;color:#C17B5C;font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;font-weight:600; }
/* ── Preview overlay ────────────────────────────────────────── */
.preview-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(30,25,20,0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    backdrop-filter: blur(3px);
}
.preview-overlay.open { display: flex; }
.preview-modal {
    background: #F5F0E8;
    width: 100%;
    max-width: 680px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 32px 90px rgba(0,0,0,0.25);
    border: 1px solid #EDE6D6;
}
.preview-topbar {
    background: white;
    border-bottom: 1px solid #EDE6D6;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}
kbd {
    background: #EDE6D6;
    padding: 1px 5px;
    border-radius: 2px;
    font-size: 0.68rem;
    font-family: monospace;
}
/* ── Attachment list ─────────────────────────────────────────── */
.attach-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 0;
}
.attach-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #FDFAF5;
    border: 1px solid #EDE6D6;
    font-size: 0.8rem;
    color: #4A4A4A;
    animation: tagIn 0.15s ease;
}
.attach-item .attach-icon { font-size: 1.1rem; flex-shrink: 0; }
.attach-item .attach-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.attach-item .attach-size { color: #B5A898; font-size: 0.72rem; flex-shrink: 0; }
.attach-item .attach-remove {
    background: none;
    border: none;
    cursor: pointer;
    color: #B5A898;
    font-size: 1.1rem;
    line-height: 1;
    padding: 2px 4px;
    transition: color 0.15s;
    flex-shrink: 0;
}
.attach-item .attach-remove:hover { color: #C17B5C; }
/* ── Drop zone ──────────────────────────────────────────────── */
.drop-zone {
    border: 1.5px dashed #E5DDD0;
    padding: 1rem 1.25rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    background: #FDFAF5;
}
.drop-zone:hover, .drop-zone.dragging { border-color: #C9A96E; background: rgba(201,169,110,0.05); }
.drop-zone input[type=file] { display: none; }
/* ── Delete btn ─────────────────────────────────────────────── */
.btn-delete {
    background: none;
    border: 1px solid #F2C4B3;
    color: #C17B5C;
    font-family: 'Jost', sans-serif;
    font-size: 0.72rem;
    padding: 0.3rem 0.65rem;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-delete:hover { background: #C17B5C; color: white; border-color: #C17B5C; }
</style>

<!-- ════════════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════════ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:400;margin:0;">
            <?php
            if ($viewLog)         echo 'Email Details';
            elseif ($showHistory) echo 'Email History';
            else                  echo 'Send Email';
            ?>
        </h2>
        <?php if (!$showHistory): ?>
        <p style="font-size:0.78rem;color:var(--sage);margin:4px 0 0;">Compose and send branded emails directly from the dashboard.</p>
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:0.625rem;">
        <?php if ($viewLog): ?>
            <a href="?history" class="btn-ghost">← Back to History</a>
            <a href="send-email.php" class="btn-sand">+ Compose Email</a>
        <?php elseif ($showHistory): ?>
            <a href="send-email.php" class="btn-sand" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                Compose Email
            </a>
        <?php else: ?>
            <a href="?history" class="btn-ghost" style="display:inline-flex;align-items:center;gap:6px;">
                <!-- <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg> -->
                Emails History
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     SINGLE LOG VIEW
════════════════════════════════════════════════════════ -->
<?php if ($viewLog): ?>
<?php
// Decode attachments for this log
$logAttachments = [];
if (!empty($viewLog['attachments'])) {
    $logAttachments = json_decode($viewLog['attachments'], true) ?: [];
}
?>
<div class="admin-card" style="max-width:100%;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #EDE6D6;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:0.65rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--sage);margin-bottom:6px;">Subject</div>
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;"><?= htmlspecialchars($viewLog['subject']) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <?= $viewLog['status'] === 'sent' ? '<span class="badge-sent">Sent</span>' : '<span class="badge-failed">Failed</span>' ?>
            <button class="btn-delete" onclick="deleteEmail(<?= $viewLog['id'] ?>, true)">
                <!-- <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg> -->
                Delete
            </button>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
        <div>
            <div style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-bottom:4px;">From</div>
            <div style="font-size:0.875rem;"><?= htmlspecialchars($viewLog['from_email']) ?></div>
        </div>
        <div>
            <div style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-bottom:4px;">Sent At</div>
            <div style="font-size:0.875rem;"><?= date('d M Y, g:i A', strtotime($viewLog['sent_at'])) ?></div>
        </div>
        <div style="grid-column:1/-1;">
            <div style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-bottom:8px;">Recipients</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach (explode(', ', $viewLog['to_emails']) as $e): ?>
                <span style="background:rgba(201,169,110,0.12);border:1px solid rgba(201,169,110,0.35);color:#7A5A30;padding:4px 12px;font-size:0.78rem;"><?= htmlspecialchars(trim($e)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($viewLog['fail_reason']): ?>
    <div style="padding:0.875rem 1rem;background:#FEF3E8;border-left:3px solid #C17B5C;color:#C17B5C;font-size:0.85rem;margin-bottom:1.25rem;">
        <strong>Failure Reason:</strong> <?= htmlspecialchars($viewLog['fail_reason']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($logAttachments)): ?>
    <div style="margin-bottom:1.25rem;">
        <div style="font-size:0.65rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--sage);margin-bottom:0.75rem;">Attachments (<?= count($logAttachments) ?>)</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($logAttachments as $fname):
                $origName = preg_replace('/^att_[a-f0-9]+\.[a-f0-9]+_/', '', $fname);
                $filePath = ATTACH_DIR . $fname;
                $size     = file_exists($filePath) ? humanSize(filesize($filePath)) : 'file missing';
                $icon     = fileIcon($origName);
            ?>
            <div style="display:inline-flex;align-items:center;gap:8px;padding:7px 14px;background:#FDFAF5;border:1px solid #EDE6D6;font-size:0.8rem;color:#4A4A4A;">
                <span><?= $icon ?></span>
                <span><?= htmlspecialchars($origName) ?></span>
                <span style="color:#B5A898;font-size:0.72rem;"><?= $size ?></span>
                <?php
                // Build public URL — adjust base path to match your server layout
                $publicPath = 'uploads/email-attachments/' . rawurlencode($fname);
                ?>
                <a href="<?= htmlspecialchars($publicPath) ?>" download="<?= htmlspecialchars($origName) ?>" style="color:var(--sand-dark);font-size:0.72rem;text-decoration:none;padding:2px 6px;border:1px solid var(--sand);transition:background 0.15s;" onmouseover="this.style.background='rgba(201,169,110,0.12)'" onmouseout="this.style.background='transparent'">↓ Download</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="background:#FDFAF5;border-left:3px solid #EDE6D6;padding:1.75rem 2rem;">
        <div style="font-size:0.65rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--sage);margin-bottom:1rem;">Email Body</div>
        <div style="font-size:0.9rem;line-height:1.85;color:#4A4A4A;"><?= $viewLog['body'] ?></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     HISTORY LIST
════════════════════════════════════════════════════════ -->
<?php elseif ($showHistory): ?>
<?php $histTotal = (int) db()->query("SELECT COUNT(*) FROM email_logs")->fetchColumn(); ?>
<div class="admin-card" style="position:relative;">
    <?php if ($histTotal === 0): ?>
    <div style="text-align:center;padding:4rem;color:#B5A898;">
        <svg width="44" height="44" fill="none" stroke="#D5C9B8" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 1.25rem;display:block;"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        <p style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;margin-bottom:0.5rem;">No emails sent yet</p>
        <p style="font-size:0.875rem;">Sent emails and their delivery status will appear here.</p>
        <a href="send-email.php" class="btn-sand" style="display:inline-block;margin-top:1.25rem;">Compose First Email</a>
    </div>
    <?php else: ?>
    <!-- Spinner overlay -->
    <div id="hist-spinner" style="display:none;position:absolute;inset:0;background:rgba(253,250,245,0.75);z-index:10;align-items:center;justify-content:center;">
        <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
            <div style="width:32px;height:32px;border:2.5px solid #EDE6D6;border-top-color:#C9A96E;border-radius:50%;animation:spin360 0.7s linear infinite;"></div>
            <span style="font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);">Loading…</span>
        </div>
    </div>
    <table class="admin-table" id="history-table">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Recipients</th>
                <th>Attachments</th>
                <th>Date &amp; Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="hist-tbody"></tbody>
    </table>
    <div id="hist-pg-wrap" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 0 0;border-top:1px solid #EDE6D6;margin-top:0.5rem;flex-wrap:wrap;gap:0.5rem;"></div>
    <?php endif; ?>
</div>

<style>
@keyframes spin360 { to { transform:rotate(360deg); } }
.pg-btn { padding:0.4rem 0.75rem;font-size:0.78rem;border:1px solid #E5DDD0;background:white;color:var(--charcoal);cursor:pointer;font-family:'Jost',sans-serif;transition:background 0.15s,border-color 0.15s; }
.pg-btn:hover:not(:disabled) { border-color:#C9A96E;background:rgba(201,169,110,0.07); }
.pg-btn.active { background:var(--charcoal);color:white;border-color:var(--charcoal); }
.pg-btn:disabled { opacity:0.38;cursor:default; }
</style>

<script>
var HIST_TOTAL = <?= $histTotal ?>;
var HIST_PER   = 12;
var HIST_PAGE  = 1;
var HIST_PAGES = Math.ceil(HIST_TOTAL / HIST_PER);

function loadHistPage(page) {
    HIST_PAGE = page;
    const spinner = document.getElementById('hist-spinner');
    const tbody   = document.getElementById('hist-tbody');
    if (spinner) spinner.style.display = 'flex';
    fetch('send-email.php?history&ajax_page=' + page)
        .then(r => r.json())
        .then(data => {
            if (tbody) tbody.innerHTML = data.html;
            HIST_TOTAL = data.total;
            HIST_PAGES = Math.ceil(data.total / HIST_PER);
            renderHistPg();
            if (spinner) spinner.style.display = 'none';
        })
        .catch(() => { if (spinner) spinner.style.display = 'none'; });
}

function renderHistPg() {
    const wrap = document.getElementById('hist-pg-wrap');
    if (!wrap || HIST_PAGES <= 1) { if(wrap) wrap.innerHTML = ''; return; }
    const start = (HIST_PAGE - 1) * HIST_PER + 1;
    const end   = Math.min(HIST_PAGE * HIST_PER, HIST_TOTAL);
    let html = '<span style="font-size:0.78rem;color:var(--sage);">Showing ' + start + '–' + end + ' of ' + HIST_TOTAL + '</span>';
    html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
    html += `<button class="pg-btn" onclick="loadHistPage(${HIST_PAGE-1})" ${HIST_PAGE===1?'disabled':''}>← Prev</button>`;
    for (let i = 1; i <= HIST_PAGES; i++) {
        if (HIST_PAGES > 7 && Math.abs(i - HIST_PAGE) > 2 && i !== 1 && i !== HIST_PAGES) {
            if (i === HIST_PAGE - 3 || i === HIST_PAGE + 3) html += '<span style="padding:0.4rem 0.3rem;font-size:0.78rem;color:#B5A898;">…</span>';
            continue;
        }
        html += `<button class="pg-btn ${i===HIST_PAGE?'active':''}" onclick="loadHistPage(${i})">${i}</button>`;
    }
    html += `<button class="pg-btn" onclick="loadHistPage(${HIST_PAGE+1})" ${HIST_PAGE===HIST_PAGES?'disabled':''}>Next →</button>`;
    html += '</div>';
    wrap.innerHTML = html;
}

if (document.getElementById('hist-tbody')) loadHistPage(1);
</script>

<!-- ════════════════════════════════════════════════════════
     COMPOSE FORM
════════════════════════════════════════════════════════ -->
<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 310px;gap:1.5rem;align-items:start;">

    <!-- LEFT: Compose -->
    <div class="admin-card">

        <!-- FROM -->
        <div style="margin-bottom:1.25rem;">
            <label class="form-label">From</label>
            <div style="display:flex;align-items:center;gap:12px;padding:0.75rem 1rem;border:1px solid #E5DDD0;background:#FDFAF5;">
                <div style="width:34px;height:34px;border-radius:50%;background:var(--sand);display:flex;align-items:center;justify-content:center;font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:white;font-weight:600;flex-shrink:0;">M</div>
                <div>
                    <div style="font-size:0.875rem;font-weight:500;"><?= htmlspecialchars($FROM_NAME) ?></div>
                    <div style="font-size:0.75rem;color:var(--sage);"><?= htmlspecialchars($FROM_EMAIL) ?></div>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D5C9B8" stroke-width="1.5" style="margin-left:auto;flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </div>
        </div>

        <!-- TO — tag input -->
        <div style="margin-bottom:1.25rem;">
            <label class="form-label">To <span style="color:#C17B5C;">*</span></label>
            <div class="tag-input-wrapper" id="tag-wrapper" onclick="document.getElementById('tag-real-input').focus()">
                <input type="text" id="tag-real-input" placeholder="Type email and press Enter or comma…" autocomplete="off" spellcheck="false">
            </div>
            <input type="hidden" id="to-hidden" name="to">
            <p style="font-size:0.72rem;color:#B5A898;margin-top:5px;">Press <kbd>Enter</kbd> or <kbd>,</kbd> to add · <kbd>Backspace</kbd> to remove last</p>
        </div>

        <!-- SUBJECT -->
        <div style="margin-bottom:1.25rem;">
            <label class="form-label">Subject <span style="color:#C17B5C;">*</span></label>
            <input type="text" id="subject-input" class="form-input" placeholder="Enter email subject…" maxlength="500">
        </div>

        <!-- BODY — Quill -->
        <div style="margin-bottom:1.5rem;">
            <label class="form-label">Message <span style="color:#C17B5C;">*</span></label>
            <div id="email-body-editor"></div>
        </div>

        <!-- ACTIONS -->
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;border-top:1px solid #EDE6D6;padding-top:1.25rem;align-items:center;">
            <button type="button" id="btn-send" class="btn-sand" style="display:inline-flex;align-items:center;gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                Send Email
            </button>
            <button type="button" id="btn-preview" class="btn-ghost" style="display:inline-flex;align-items:center;gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Preview
            </button>
            <button type="button" id="btn-clear" style="background:none;border:none;font-family:'Jost',sans-serif;font-size:0.78rem;color:#B5A898;cursor:pointer;margin-left:auto;display:inline-flex;align-items:center;gap:5px;transition:color 0.2s;" onmouseover="this.style.color='#C17B5C'" onmouseout="this.style.color='#B5A898'">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                Clear Form
            </button>
        </div>
    </div>

    <!-- RIGHT: Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1rem;">

        <!-- Attachments -->
        <div class="admin-card">
            <h4 style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:400;margin:0 0 1rem;color:#2C2C2C;display:flex;align-items:center;gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                Attachments
            </h4>

            <!-- Drop zone -->
            <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()" 
                 ondragover="event.preventDefault();this.classList.add('dragging')" 
                 ondragleave="this.classList.remove('dragging')"
                 ondrop="handleDrop(event)">
                <input type="file" id="file-input" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt,.csv">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.3" style="margin:0 auto 8px;display:block;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div style="font-size:0.78rem;color:var(--sage);line-height:1.5;">Click to browse or drag &amp; drop files<br><span style="font-size:0.7rem;color:#B5A898;">Images, PDF, Word, Excel, ZIP · Max 10 MB each</span></div>
            </div>

            <!-- Attached files list -->
            <div id="attach-list" class="attach-list" style="margin-top:10px;"></div>
        </div>

        <!-- Tips -->
        <div class="admin-card">
            <h4 style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:400;margin:0 0 1rem;color:#2C2C2C;">Compose Tips</h4>
            <?php foreach ([
                ['', 'Add multiple recipients — press Enter or comma after each email'],
                ['', 'Use the rich text editor for bold, lists, links, and more'],
                ['', 'Attach images, PDFs, Word docs, ZIPs &amp; more (10 MB each)'],
                ['', 'Click Preview to see the branded email before sending'],
                ['', 'Every email is logged in History with delivery status'],
            ] as [$icon, $tip]): ?>
            <div style="display:flex;gap:10px;align-items:flex-start;font-size:0.82rem;color:var(--sage);line-height:1.55;margin-bottom:10px;">
                <span style="font-size:1rem;margin-top:1px;flex-shrink:0;"><?= $icon ?></span>
                <span><?= $tip ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════
     EMAIL PREVIEW MODAL
════════════════════════════════════════════════════════ -->
<div class="preview-overlay" id="preview-overlay">
    <div class="preview-modal">
        <div class="preview-topbar">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:#2C2C2C;">Email Preview</div>
                <div style="font-size:0.72rem;color:var(--sage);">Exact layout your recipients will see</div>
            </div>
            <button onclick="closePreview()" style="background:none;border:none;cursor:pointer;font-size:1.6rem;line-height:1;color:#B5A898;padding:4px 8px;transition:color 0.2s;" onmouseover="this.style.color='#2C2C2C'" onmouseout="this.style.color='#B5A898'">×</button>
        </div>

        <!-- Meta strip -->
        <div id="preview-meta" style="background:#fff;border-bottom:1px solid #EDE6D6;padding:0.875rem 1.5rem;display:none;">
            <table style="width:100%;font-size:0.8rem;color:#4A4A4A;border-collapse:collapse;">
                <tr>
                    <td style="padding:3px 0;color:var(--sage);width:60px;font-size:0.7rem;letter-spacing:0.1em;text-transform:uppercase;">From</td>
                    <td id="pm-from" style="padding:3px 0;"></td>
                </tr>
                <tr>
                    <td style="padding:3px 0;color:var(--sage);font-size:0.7rem;letter-spacing:0.1em;text-transform:uppercase;">To</td>
                    <td id="pm-to" style="padding:3px 0;"></td>
                </tr>
                <tr>
                    <td style="padding:3px 0;color:var(--sage);font-size:0.7rem;letter-spacing:0.1em;text-transform:uppercase;">Subject</td>
                    <td id="pm-subject" style="padding:3px 0;font-weight:500;"></td>
                </tr>
                <tr id="pm-att-row" style="display:none;">
                    <td style="padding:3px 0;color:var(--sage);font-size:0.7rem;letter-spacing:0.1em;text-transform:uppercase;">Attach.</td>
                    <td id="pm-attachments" style="padding:3px 0;"></td>
                </tr>
            </table>
        </div>

        <div id="preview-content" style="padding:1.5rem;"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════════ -->
<script>
// ── Quill init ───────────────────────────────────────────────────────
const quill = typeof Quill !== 'undefined' ? new Quill('#email-body-editor', {
    theme: 'snow',
    placeholder: 'Write your email message here…',
    modules: {
        toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ color: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean']
        ],
        // Disable clipboard image pasting — images pasted into body get
        // embedded as base64 data URIs which Gmail shows as fake attachments.
        // Use the Attachments panel above to attach image files instead.
        clipboard: {
            matchVisual: false,
        },
    },
    // Block image drops/pastes at the editor level
}) : null;

// Intercept paste in the Quill editor — strip any base64 image data URIs
if (quill) {
    quill.root.addEventListener('paste', e => {
        const items = (e.clipboardData || window.clipboardData)?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                e.preventDefault();
                Swal.fire({
                    icon: 'info',
                    title: 'Use Attachments Panel',
                    text: 'To attach images, please use the Attachments section on the right side.',
                    confirmButtonText: 'Got it',
                    timer: 3000,
                });
                return;
            }
        }
    }, true);
}

// ── Tag / multi-email input ──────────────────────────────────────────
const tags     = [];
const input    = document.getElementById('tag-real-input');
const wrapper  = document.getElementById('tag-wrapper');
const hiddenTo = document.getElementById('to-hidden');

function addTag(val) {
    val = val.trim().replace(/,+$/, '').trim();
    if (!val) return;
    if (tags.includes(val)) { input.value = ''; return; }
    tags.push(val);
    renderTags();
    input.value = '';
}

function removeTag(idx) {
    tags.splice(idx, 1);
    renderTags();
}

function renderTags() {
    document.querySelectorAll('.tag-item').forEach(t => t.remove());
    tags.forEach((tag, idx) => {
        const span = document.createElement('span');
        span.className = 'tag-item';
        span.innerHTML = `${escHtml(tag)}<button class="tag-remove" type="button" onclick="removeTag(${idx})" title="Remove">×</button>`;
        wrapper.insertBefore(span, input);
    });
    if (hiddenTo) hiddenTo.value = tags.join(',');
}

// ── Pre-fill TO field from URL ?to= param ────────────────────────────
<?php if (!empty($prefillTo) && filter_var($prefillTo, FILTER_VALIDATE_EMAIL)): ?>
addTag(<?= json_encode($prefillTo) ?>);
<?php endif; ?>

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

if (input) {
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag(input.value);
        } else if (e.key === 'Backspace' && input.value === '' && tags.length) {
            removeTag(tags.length - 1);
        }
    });
    input.addEventListener('blur', () => { if (input.value.trim()) addTag(input.value); });
    input.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        pasted.split(/[\s,;]+/).forEach(v => { if (v) addTag(v); });
    });
}

// ── Attachment handling ──────────────────────────────────────────────
let attachedFiles = []; // array of File objects

const fileInput  = document.getElementById('file-input');
const attachList = document.getElementById('attach-list');

const FILE_ICONS = {
    jpg:'○',jpeg:'○',png:'○',gif:'○',webp:'○',svg:'○',
    pdf:'○',doc:'○',docx:'○',
    xls:'○',xlsx:'○',csv:'○',
    ppt:'○',pptx:'○',
    zip:'○',rar:'○',
    txt:'○',
};

function getFileIcon(name) {
    const ext = name.split('.').pop().toLowerCase();
    return FILE_ICONS[ext] || '○';
}

function humanSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function renderAttachments() {
    if (!attachList) return;
    attachList.innerHTML = '';
    attachedFiles.forEach((file, idx) => {
        const div = document.createElement('div');
        div.className = 'attach-item';
        div.innerHTML = `
            <span class="attach-icon">${getFileIcon(file.name)}</span>
            <span class="attach-name" title="${escHtml(file.name)}">${escHtml(file.name)}</span>
            <span class="attach-size">${humanSize(file.size)}</span>
            <button class="attach-remove" type="button" onclick="removeAttachment(${idx})" title="Remove attachment">×</button>
        `;
        attachList.appendChild(div);
    });
}

function addFiles(files) {
    const MAX = 10 * 1024 * 1024;
    [...files].forEach(f => {
        if (f.size > MAX) {
            Swal.fire({ icon:'warning', title:'File Too Large', text:`"${f.name}" exceeds the 10 MB limit and was skipped.`, confirmButtonText:'OK' });
            return;
        }
        // Avoid duplicates by name+size
        const dup = attachedFiles.find(x => x.name === f.name && x.size === f.size);
        if (!dup) attachedFiles.push(f);
    });
    renderAttachments();
}

function removeAttachment(idx) {
    attachedFiles.splice(idx, 1);
    renderAttachments();
}

if (fileInput) {
    fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
        fileInput.value = ''; // allow re-selecting same file
    });
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('dragging');
    if (e.dataTransfer?.files) addFiles(e.dataTransfer.files);
}

// ── Preview ──────────────────────────────────────────────────────────
function closePreview() {
    document.getElementById('preview-overlay').classList.remove('open');
}

document.getElementById('btn-preview')?.addEventListener('click', () => {
    const subject  = (document.getElementById('subject-input')?.value.trim()) || '(No Subject)';
    const bodyHtml = quill ? quill.root.innerHTML : '';
    const toVal    = (document.getElementById('to-hidden')?.value || '').trim();
    const FROM     = '<?= addslashes($FROM_NAME) ?> &lt;<?= addslashes($FROM_EMAIL) ?>&gt;';

    // Populate meta strip
    const metaDiv = document.getElementById('preview-meta');
    if (metaDiv) {
        document.getElementById('pm-from').innerHTML    = FROM;
        document.getElementById('pm-to').textContent    = toVal || '(no recipients yet)';
        document.getElementById('pm-subject').textContent = subject;

        const attRow = document.getElementById('pm-att-row');
        const attCell = document.getElementById('pm-attachments');
        if (attachedFiles.length > 0) {
            attCell.innerHTML = attachedFiles.map(f =>
                `<span style="display:inline-flex;align-items:center;gap:4px;margin-right:8px;font-size:0.75rem;">${getFileIcon(f.name)} ${escHtml(f.name)} <span style="color:#B5A898;">(${humanSize(f.size)})</span></span>`
            ).join('');
            attRow.style.display = '';
        } else {
            attRow.style.display = 'none';
        }
        metaDiv.style.display = 'block';
    }

    const preview = `
    <div style="background:#F5F0E8;padding:20px;font-family:Georgia,serif;">
      <table style="max-width:620px;width:100%;background:#fff;border:1px solid #EDE6D6;margin:auto;">
        <tr><td style="height:3px;background:linear-gradient(90deg,#C9A96E,#A07840);font-size:0;"></td></tr>
        <tr>
          <td style="padding:32px 40px 24px;text-align:center;border-bottom:1px solid #EDE6D6;background:#FDFAF5;">
            <div style="font-size:26px;font-weight:700;color:#2C2C2C;letter-spacing:2px;">A. Moeed</div>
            <div style="font-size:10px;letter-spacing:4px;text-transform:uppercase;color:#C9A96E;margin-top:3px;">MyDesignAssistants</div>
            <div style="width:36px;height:1px;background:#C9A96E;margin:12px auto 0;"></div>
          </td>
        </tr>
        <tr>
          <td style="padding:0;background:#3B2A1A;">
            <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#C9A96E;padding:14px 40px;">${escHtml(subject)}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 40px 32px;font-size:15px;color:#4A4A4A;line-height:1.85;">${bodyHtml}</td>
        </tr>
        <tr><td style="padding:0 40px;"><div style="height:1px;background:#EDE6D6;"></div></td></tr>
        <tr>
          <td style="padding:24px 40px;text-align:center;">
            <a href="https://upwork.com" style="display:inline-block;padding:11px 32px;background:#2C2C2C;color:#fff;font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;text-decoration:none;">Hire me &rarr;</a>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 40px 28px;text-align:center;background:#FDFAF5;border-top:1px solid #EDE6D6;">
            <div style="font-size:11px;color:#B5A898;line-height:1.7;">
              This email was sent by <strong style="color:#7A8C7E;">A. Moeed | MyDesignAssistants</strong><br>
              <span style="color:#C9A96E;">moeed@mydesignassistants.com</span>
              &nbsp;·&nbsp;
              <span style="color:#C9A96E;">mydesignassistants.com</span>
            </div>
            <div style="font-size:10px;color:#D5C9B8;margin-top:10px;letter-spacing:1px;">&copy; 2026 MyDesignAssistants. All rights reserved.</div>
          </td>
        </tr>
        <tr><td style="height:3px;background:linear-gradient(90deg,#A07840,#C9A96E);font-size:0;"></td></tr>
      </table>
    </div>`;

    document.getElementById('preview-content').innerHTML = preview;
    document.getElementById('preview-overlay').classList.add('open');
});

document.getElementById('preview-overlay')?.addEventListener('click', e => {
    if (e.target === document.getElementById('preview-overlay')) closePreview();
});

// ── Send ─────────────────────────────────────────────────────────────
document.getElementById('btn-send')?.addEventListener('click', () => {
    const toVal   = document.getElementById('to-hidden')?.value.trim() ?? '';
    const subjVal = document.getElementById('subject-input')?.value.trim() ?? '';
    const bodyVal = quill ? quill.root.innerHTML : '';
    const bodyTxt = quill ? quill.getText().trim() : '';

    if (!toVal) {
        return Swal.fire({ icon:'warning', title:'No Recipients', text:'Please add at least one recipient email address.', confirmButtonText:'Got it' });
    }
    if (!subjVal) {
        return Swal.fire({ icon:'warning', title:'Missing Subject', text:'Please enter a subject for your email.', confirmButtonText:'Got it' });
    }
    if (!bodyTxt) {
        return Swal.fire({ icon:'warning', title:'Empty Message', text:'Please write a message before sending.', confirmButtonText:'Got it' });
    }

    const count    = toVal.split(',').filter(Boolean).length;
    const attCount = attachedFiles.length;
    const attNote  = attCount > 0 ? `<br><span style="font-size:0.8rem;color:#7A8C7E;">○ ${attCount} attachment${attCount>1?'s':''} included</span>` : '';

    Swal.fire({
        title: 'Send Email?',
        html: `<span style="color:#4A4A4A;">You are about to send <strong>"${escHtml(subjVal)}"</strong> to <strong>${count}</strong> recipient${count > 1 ? 's' : ''}.</span>${attNote}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Send',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
    }).then(res => {
        if (!res.isConfirmed) return;

        // ── Progress bar state ────────────────────────────────────────
        let _progressInterval = null;
        let _currentProgress  = 0;

        function _startProgress() {
            _currentProgress = 0;
            _setProgress(0, 'Preparing your email…');

            // Stages: [targetPct, durationMs, label]
            const stages = [
                [15,  400,  'Preparing your email…'],
                [35,  600,  'Uploading attachments…'],
                [55,  700,  'Connecting to mail server…'],
                [75,  800,  'Authenticating…'],
                [90,  900,  'Delivering message…'],
            ];

            let stageIndex = 0;
            function runStage() {
                if (stageIndex >= stages.length) return;
                const [target, duration, label] = stages[stageIndex++];
                _animateTo(target, duration, label, runStage);
            }
            runStage();
        }

        function _animateTo(target, duration, label, onDone) {
            const start     = _currentProgress;
            const startTime = performance.now();
            cancelAnimationFrame(_progressInterval);

            function step(now) {
                const elapsed = now - startTime;
                const t       = Math.min(elapsed / duration, 1);
                // ease-out curve
                const eased   = 1 - Math.pow(1 - t, 2);
                _currentProgress = start + (target - start) * eased;
                _setProgress(_currentProgress, label);
                if (t < 1) {
                    _progressInterval = requestAnimationFrame(step);
                } else {
                    _currentProgress = target;
                    if (onDone) onDone();
                }
            }
            _progressInterval = requestAnimationFrame(step);
        }

        function _finishProgress(onDone) {
            _animateTo(100, 350, 'Done!', onDone);
        }

        function _setProgress(pct, label) {
            const bar  = document.getElementById('swal-progress-bar');
            const pctEl = document.getElementById('swal-progress-pct');
            const lblEl = document.getElementById('swal-progress-label');
            if (bar)   bar.style.width = pct.toFixed(1) + '%';
            if (pctEl) pctEl.textContent = Math.round(pct) + '%';
            if (lblEl) lblEl.textContent = label;
        }

        Swal.fire({
            title: 'Sending…',
            html: `
                <span id="swal-progress-label" style="display:block;font-size:0.8rem;color:#7A8C7E;margin-bottom:12px;min-height:1.2em;">Preparing your email…</span>
                <div style="background:#F0EBE0;border-radius:4px;overflow:hidden;height:10px;width:100%;position:relative;">
                    <div id="swal-progress-bar" style="
                        height:100%;width:0%;
                        background:linear-gradient(90deg,#A07840,#C9A96E);
                        border-radius:4px;
                        transition:none;
                        position:relative;
                        overflow:hidden;
                    ">
                        <div style="
                            position:absolute;inset:0;
                            background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,0.35) 50%,transparent 100%);
                            animation:swal-shimmer 1.2s infinite;
                        "></div>
                    </div>
                </div>
                <div id="swal-progress-pct" style="margin-top:8px;font-size:0.75rem;color:#B5A898;font-family:'Jost',sans-serif;letter-spacing:1px;">0%</div>
                <style>
                    @keyframes swal-shimmer {
                        0%   { transform: translateX(-100%); }
                        100% { transform: translateX(200%); }
                    }
                </style>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => _startProgress(),
        });

        const fd = new FormData();
        fd.append('send_email', '1');
        fd.append('to',      toVal);
        fd.append('subject', subjVal);
        fd.append('body',    bodyVal);
        attachedFiles.forEach(f => fd.append('attachments[]', f, f.name));

        fetch('send-email.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                cancelAnimationFrame(_progressInterval);
                _finishProgress(() => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Sent!',
                        html: `<span style="color:#4A6B50;">${data.message}</span>`,
                        confirmButtonText: 'Done',
                    }).then(() => {
                        tags.length = 0;
                        renderTags();
                        if (document.getElementById('subject-input')) document.getElementById('subject-input').value = '';
                        if (quill) quill.setContents([]);
                        attachedFiles = [];
                        renderAttachments();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Send Failed',
                        html: `<span style="color:#C17B5C;">${data.message}</span>`,
                        confirmButtonText: 'OK',
                    });
                }
                }); // end _finishProgress
            })
            .catch(() => {
                cancelAnimationFrame(_progressInterval);
                Swal.fire({ icon:'error', title:'Network Error', text:'Could not reach the server. Please try again.' });
            });
    });
});

// ── Clear ────────────────────────────────────────────────────────────
document.getElementById('btn-clear')?.addEventListener('click', () => {
    Swal.fire({
        title: 'Clear Form?',
        text: 'This will erase all entered content and remove attachments.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Clear',
        cancelButtonText: 'Keep',
        reverseButtons: true,
    }).then(r => {
        if (r.isConfirmed) {
            tags.length = 0;
            renderTags();
            if (document.getElementById('subject-input')) document.getElementById('subject-input').value = '';
            if (quill) quill.setContents([]);
            attachedFiles = [];
            renderAttachments();
        }
    });
});

// ── Delete email (history) ───────────────────────────────────────────
function deleteEmail(id, isDetailView) {
    Swal.fire({
        title: 'Delete this email?',
        html: '<span style="color:#4A4A4A;font-size:0.9rem;">This will permanently remove the email log and any attached files from storage.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#C17B5C',
        reverseButtons: true,
    }).then(res => {
        if (!res.isConfirmed) return;

        const fd = new FormData();
        fd.append('delete_email', '1');
        fd.append('delete_id', id);

        fetch('send-email.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (isDetailView) {
                        // Redirect back to history
                        window.location.href = '?history';
                    } else {
                        // Remove row from table
                        const row = document.getElementById('log-row-' + id);
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                // Refresh current page via AJAX pagination
                                HIST_TOTAL = Math.max(0, HIST_TOTAL - 1);
                                HIST_PAGES = Math.max(1, Math.ceil(HIST_TOTAL / HIST_PER));
                                if (HIST_PAGE > HIST_PAGES) HIST_PAGE = HIST_PAGES;
                                if (HIST_TOTAL === 0) { location.reload(); return; }
                                loadHistPage(HIST_PAGE);
                            }, 300);
                        }
                        Swal.fire({ icon:'success', title:'Deleted', text:'Email log and attachments removed.', timer:1800, showConfirmButton:false });
                    }
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: data.message || 'Could not delete.' });
                }
            })
            .catch(() => {
                Swal.fire({ icon:'error', title:'Network Error', text:'Could not reach the server.' });
            });
    });
}
</script>

<?php include '_footer.php'; ?>