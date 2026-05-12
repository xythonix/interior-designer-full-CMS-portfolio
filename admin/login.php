<?php
require_once '../config.php';
startSession();

if (isAdmin()) {
    header('Location: '. SITE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = db()->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit;
        }
        $error = 'Invalid credentials. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — MyDesignAssistants</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Jost', sans-serif;
            background: #F5F0E8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            padding: 3.5rem;
            width: 100%;
            max-width: 440px;
            position: relative;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: -8px; right: -8px;
            width: 100%; height: 100%;
            border: 1px solid #C9A96E;
            z-index: 0;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #E5DDD0;
            font-family: 'Jost', sans-serif;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.3s;
            background: white;
        }
        .form-input:focus { border-color: #C9A96E; }
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: #2C2C2C;
            color: white;
            border: none;
            font-family: 'Jost', sans-serif;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover { background: #A07840; }
        label {
            font-size: 0.68rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #7A8C7E;
            display: block;
            margin-bottom: 8px;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .form-input {
            padding-right: 2.8rem;
        }
        .eye-toggle {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            color: #B5A898;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }
        .eye-toggle:hover { color: #C9A96E; }
    </style>
</head>
<body>
    <div class="login-card relative z-10">
        <div class="text-center mb-10">
            <div style="font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:600;color:#2C2C2C;letter-spacing:0.05em;">A. Moeed</div>
            <div style="font-size:0.6rem;letter-spacing:0.3em;text-transform:uppercase;color:#C9A96E;margin-top:2px;">Admin Panel</div>
            <div style="width:40px;height:1px;background:#C9A96E;margin:1.5rem auto 0;"></div>
        </div>

        <?php if($error): ?>
        <div style="padding:0.875rem 1rem;background:#FEF3E8;border-left:3px solid #C17B5C;color:#C17B5C;font-size:0.85rem;margin-bottom:1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label>Username</label>
                <input type="text" name="username" class="form-input" placeholder="Enter username" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div>
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" class="form-input" placeholder="Enter password" required autocomplete="current-password">
                    <button type="button" class="eye-toggle" id="eyeToggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-login">Sign In to Dashboard</button>
        </form>

        <p style="text-align:center;margin-top:2rem;font-size:0.75rem;color:#B5A898;">
            <a href="<?= SITE_URL ?>" style="color:#C9A96E;text-decoration:none;">← Back to Portfolio</a>
        </p>
    </div>
<script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeOffIcon = document.getElementById('eyeOffIcon');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        eyeIcon.style.display = isHidden ? 'none' : '';
        eyeOffIcon.style.display = isHidden ? '' : 'none';
    }
</script>
</body>
</html>