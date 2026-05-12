<?php
$pageTitle = 'Site Settings';
include '_header.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Change Password ───────────────────────────────────────────────
    if (isset($_POST['change_password'])) {
        $oldPass  = $_POST['old_password']     ?? '';
        $newPass  = $_POST['new_password']     ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        // Fetch current admin (session username or first row)
        $adminRow = db()->query("SELECT * FROM admin_users LIMIT 1")->fetch();

        if (!$adminRow) {
            $error = 'Admin account not found.';
        } elseif (!password_verify($oldPass, $adminRow['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPass !== $confPass) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            db()->prepare("UPDATE admin_users SET password = ? WHERE id = ?")->execute([$hash, $adminRow['id']]);
            $success = 'Password changed successfully!';
        }
    } else {
        // ── Save general settings ─────────────────────────────────────
        $keys = ['site_name','hero_title','hero_subtitle','about_text','email','phone','upwork_url','fiverr_url','meta_description'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $stmt = db()->prepare("INSERT INTO settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=?");
                $stmt->execute([$key, $_POST[$key], $_POST[$key]]);
            }
        }
        $success = 'Settings saved successfully!';
    }
}

// Upload profile image
if (!empty($_FILES['profile_image']['name'])) {
    $up = handleUpload($_FILES['profile_image'], 'avatars');
    if (isset($up['path'])) {
        // Copy as profile.png equivalent
        $dest = UPLOAD_PATH . 'avatars/profile.png';
        copy(ltrim($up['path'],'/'), $dest); // simplified
        $success = 'Profile image uploaded! ' . ($success ?: '');
    }
}
?>

<?php if($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if($error):   ?><div class="alert-error"  style="background:#FDF0EC;border:1px solid #E8B4A0;color:#A0522D;padding:0.875rem 1.25rem;border-radius:2px;margin-bottom:1.25rem;font-size:0.875rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── Settings Form ─────────────────────────────────────────────────── -->
<form method="POST" enctype="multipart/form-data" id="settings-form">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="space-y-5">
            <div class="admin-card">
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:400;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid #EDE6D6;">Identity</h3>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Site / Portfolio Name</label>
                        <input type="text" name="site_name" class="form-input" value="<?= htmlspecialchars(getSetting('site_name')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars(getSetting('email')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars(getSetting('phone')) ?>">
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:400;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid #EDE6D6;">Platform Links</h3>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Upwork Profile URL</label>
                        <input type="url" name="upwork_url" class="form-input" value="<?= htmlspecialchars(getSetting('upwork_url')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Fiverr Profile URL</label>
                        <input type="url" name="fiverr_url" class="form-input" value="<?= htmlspecialchars(getSetting('fiverr_url')) ?>">
                    </div>
                </div>
            </div>
            <div class="admin-card">
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:400;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid #EDE6D6;">Hero Section</h3>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Hero Main Title</label>
                        <input type="text" name="hero_title" class="form-input" value="<?= htmlspecialchars(getSetting('hero_title')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Hero Subtitle</label>
                        <textarea name="hero_subtitle" class="form-textarea" rows="3"><?= htmlspecialchars(getSetting('hero_subtitle')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            

            <div class="admin-card">
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:400;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid #EDE6D6;">About Section</h3>
                <div>
                    <label class="form-label">About Text (HTML allowed)</label>
                    <textarea name="about_text" class="form-textarea" rows="8"><?= htmlspecialchars(getSetting('about_text')) ?></textarea>
                    <p style="font-size:0.72rem;color:var(--sage);margin-top:6px;">You can use HTML: &lt;strong&gt;, &lt;em&gt;, &lt;p&gt;, etc.</p>
                </div>
            </div>

            <div class="admin-card">
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:400;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid #EDE6D6;">SEO</h3>
                <div>
                    <label class="form-label">Meta Description</label>
                    <textarea name="meta_description" class="form-textarea" rows="3"><?= htmlspecialchars(getSetting('meta_description')) ?></textarea>
                    <p style="font-size:0.72rem;color:var(--sage);margin-top:6px;">Keep under 160 characters for best SEO results.</p>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:1.5rem;">
        <button type="submit" class="btn-sand" style="padding:0.875rem 3rem;">Save All Settings</button>
    </div>
</form>

<!-- ── Change Password Form (standalone — never nested) ──────────────── -->
<div style="margin-top:1.5rem;">
    <div class="admin-card">
        <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:400;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid #EDE6D6;">Change Password</h3>
        <?php
            $adminUser = db()->query("SELECT username, email FROM admin_users LIMIT 1")->fetch();
        ?>
        <?php if($adminUser): ?>
        <!-- <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;background:#FDFAF5;border:1px solid #EDE6D6;border-radius:2px;margin-bottom:1.25rem;">
            <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#C9A96E,#A07840);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:1rem;font-family:'Cormorant Garamond',serif;font-weight:600;"><?= strtoupper(substr($adminUser['username'],0,1)) ?></span>
            </div>
            <div>
                <div style="font-size:0.85rem;font-weight:600;color:#2C2C2C;"><?= htmlspecialchars($adminUser['username']) ?></div>
                <div style="font-size:0.75rem;color:#9A8C7E;"><?= htmlspecialchars($adminUser['email']) ?></div>
            </div>
        </div> -->
        <?php endif; ?>
        <form method="POST" id="pw-form">
            <input type="hidden" name="change_password" value="1">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Current Password</label>
                    <div style="position:relative;">
                        <input type="password" name="old_password" id="pw-old" class="form-input" autocomplete="current-password" style="padding-right:2.75rem;" required>
                        <button type="button" onclick="togglePw('pw-old','eye-old')" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9A8C7E;padding:0;line-height:1;">
                            <svg id="eye-old" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="form-label">New Password</label>
                    <div style="position:relative;">
                        <input type="password" name="new_password" id="pw-new" class="form-input" autocomplete="new-password" style="padding-right:2.75rem;" required minlength="8">
                        <button type="button" onclick="togglePw('pw-new','eye-new')" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9A8C7E;padding:0;line-height:1;">
                            <svg id="eye-new" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p style="font-size:0.72rem;color:var(--sage);margin-top:4px;">Minimum 8 characters.</p>
                </div>
                <div>
                    <label class="form-label">Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" name="confirm_password" id="pw-conf" class="form-input" autocomplete="new-password" style="padding-right:2.75rem;" required minlength="8">
                        <button type="button" onclick="togglePw('pw-conf','eye-conf')" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9A8C7E;padding:0;line-height:1;">
                            <svg id="eye-conf" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div id="pw-match-msg" style="font-size:0.72rem;margin-top:4px;min-height:1em;"></div>
                </div>
            </div>
            <div style="margin-top:1.25rem;">
                <button type="submit" class="btn-sand" style="padding:0.75rem 2rem;">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Eye toggle ────────────────────────────────────────────────────────
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    // Switch icon: open eye vs slashed eye
    icon.innerHTML = show
        ? `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`
        : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    icon.setAttribute('viewBox','0 0 24 24');
}

// ── Live confirm-password match check ─────────────────────────────────
(function() {
    const newPw  = document.getElementById('pw-new');
    const confPw = document.getElementById('pw-conf');
    const msg    = document.getElementById('pw-match-msg');
    if (!newPw || !confPw || !msg) return;

    function check() {
        if (!confPw.value) { msg.textContent = ''; return; }
        if (newPw.value === confPw.value) {
            msg.textContent = '✓ Passwords match';
            msg.style.color = '#5A7A5E';
        } else {
            msg.textContent = '✗ Passwords do not match';
            msg.style.color = '#C17B5C';
        }
    }
    newPw.addEventListener('input',  check);
    confPw.addEventListener('input', check);
})();
</script>

<?php include '_footer.php'; ?>