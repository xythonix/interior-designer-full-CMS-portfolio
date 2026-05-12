<?php
require_once '../config.php';
requireAdmin();

// Get unread messages count
$unreadStmt = db()->query("SELECT COUNT(*) as cnt FROM contact_messages WHERE is_read = 0");
$unreadCount = $unreadStmt->fetch()['cnt'];

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> — MyDesignAssistants</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SweetAlert2 custom theme to match admin design */
        .swal2-popup {
            font-family: 'Jost', sans-serif !important;
            border-radius: 2px !important;
            padding: 2rem !important;
        }
        .swal2-title {
            font-family: 'Cormorant Garamond', serif !important;
            font-size: 1.6rem !important;
            font-weight: 400 !important;
            color: #2C2C2C !important;
        }
        .swal2-html-container {
            font-size: 0.875rem !important;
            color: #7A8C7E !important;
        }
        .swal2-confirm {
            background: #C17B5C !important;
            border-radius: 0 !important;
            font-family: 'Jost', sans-serif !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            letter-spacing: 0.1em !important;
            text-transform: uppercase !important;
            padding: 0.625rem 1.5rem !important;
            box-shadow: none !important;
        }
        .swal2-cancel {
            background: transparent !important;
            border: 1px solid #D5C9B8 !important;
            border-radius: 0 !important;
            font-family: 'Jost', sans-serif !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            letter-spacing: 0.1em !important;
            text-transform: uppercase !important;
            color: #2C2C2C !important;
            padding: 0.625rem 1.5rem !important;
            box-shadow: none !important;
        }
        .swal2-cancel:hover { border-color: #2C2C2C !important; }
        .swal2-icon.swal2-warning { border-color: #C9A96E !important; color: #C9A96E !important; }
        .swal2-actions { gap: 0.75rem !important; }

        /* ── Admin base styles ── */
        :root {
            --cream: #F5F0E8;
            --cream-dark: #EDE6D6;
            --sand: #C9A96E;
            --sand-dark: #A07840;
            --charcoal: #2C2C2C;
            --sage: #7A8C7E;
            --sidebar-w: 260px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Jost', sans-serif;
            background: #F7F4EF;
            margin: 0;
            color: var(--charcoal);
        }
        /* Sidebar */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--charcoal);
            z-index: 100;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .sidebar-logo {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-logo-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
        }
        .sidebar-logo-sub {
            font-size: 0.58rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--sand);
        }
        .nav-section {
            padding: 1.5rem 1rem;
        }
        .nav-section-title {
            font-size: 0.6rem;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            padding: 0 0.5rem;
            margin-bottom: 0.75rem;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.7rem 0.875rem;
            border-radius: 6px;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 400;
            transition: all 0.2s;
            margin-bottom: 2px;
            position: relative;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.06);
            color: white;
        }
        .nav-item.active {
            background: rgba(201,169,110,0.15);
            color: var(--sand);
        }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 2px;
            background: var(--sand);
            border-radius: 2px;
        }
        .badge {
            margin-left: auto;
            background: #C17B5C;
            color: white;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }
        /* Main content */
        #main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
        }
        /* Top bar */
        .topbar {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #EDE6D6;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.5rem;
            font-weight: 400;
            color: var(--charcoal);
        }
        /* Cards */
        .admin-card {
            background: white;
            border: 1px solid #EDE6D6;
            padding: 1.5rem;
            border-radius: 2px;
        }
        /* Buttons */
        .btn-sand {
            padding: 0.625rem 1.5rem;
            background: var(--sand-dark);
            color: white;
            border: none;
            font-family: 'Jost', sans-serif;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-sand:hover { background: var(--charcoal); }
        .btn-ghost {
            padding: 0.625rem 1.5rem;
            background: transparent;
            color: var(--charcoal);
            border: 1px solid #D5C9B8;
            font-family: 'Jost', sans-serif;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-ghost:hover { border-color: var(--charcoal); }
        .btn-danger {
            padding: 0.5rem 1rem;
            background: transparent;
            color: #C17B5C;
            border: 1px solid #C17B5C;
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-danger:hover { background: #C17B5C; color: white; }
        /* Form inputs */
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #E5DDD0;
            font-family: 'Jost', sans-serif;
            font-size: 0.875rem;
            color: var(--charcoal);
            background: white;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--sand); }
        label.form-label {
            font-size: 0.7rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--sage);
            display: block;
            margin-bottom: 6px;
        }
        /* Table */
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--sage);
            border-bottom: 1px solid #EDE6D6;
            background: #FDFAF5;
        }
        .admin-table td {
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #F3EDE3;
            vertical-align: middle;
        }
        .admin-table tr:hover td { background: #FDFAF5; }
        /* Alerts */
        .alert-success { padding:0.875rem 1rem;background:#EFF7ED;border-left:3px solid #7A8C7E;color:#4A6B50;font-size:0.875rem;margin-bottom:1.5rem; }
        .alert-error { padding:0.875rem 1rem;background:#FEF3E8;border-left:3px solid #C17B5C;color:#C17B5C;font-size:0.875rem;margin-bottom:1.5rem; }
        /* Image preview */
        .img-preview {
            width: 80px; height: 60px;
            object-fit: cover;
            border: 1px solid #EDE6D6;
        }
        /* Star rating */
        .star-rating-input { display: flex; gap: 4px; flex-wrap: wrap; }
        .star-btn {
            padding: 5px 10px;
            border: 1px solid #E5DDD0;
            background: white;
            cursor: pointer;
            font-size: 0.78rem;
            font-family: 'Jost', sans-serif;
            transition: all 0.2s;
        }
        .star-btn:hover, .star-btn.selected { background: var(--sand); border-color: var(--sand); color: white; }
        /* Quill overrides */
        .ql-toolbar { border-color: #E5DDD0 !important; }
        .ql-container { border-color: #E5DDD0 !important; font-family: 'Jost', sans-serif !important; min-height: 200px; }
        .ql-editor { font-size: 0.9rem; line-height: 1.8; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-text">A. Moeed</div>
        <div class="sidebar-logo-sub">Admin Panel</div>
    </div>

    <nav class="nav-section flex-1">
        <div class="nav-section-title">Main</div>
        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="<?= SITE_URL ?>/admin/projects.php" class="nav-item <?= $currentPage === 'projects' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            Projects
        </a>
        <a href="<?= SITE_URL ?>/admin/testimonials.php" class="nav-item <?= $currentPage === 'testimonials' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Testimonials
        </a>
        <a href="<?= SITE_URL ?>/admin/messages.php" class="nav-item <?= $currentPage === 'messages' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Messages
            <?php if($unreadCount > 0): ?>
            <span class="badge"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= SITE_URL ?>/admin/send-email.php" class="nav-item <?= $currentPage === 'send-email' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            Send Email
        </a>
        <div class="nav-section-title" style="margin-top:1.5rem;">Settings</div>
        <a href="<?= SITE_URL ?>/admin/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            Site Settings
        </a>
        <a href="<?= SITE_URL ?>" target="_blank" class="nav-item" style="margin-top:auto;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            View Portfolio
        </a>
    </nav>

    <div style="padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.08);">
        <a href="<?= SITE_URL ?>/admin/logout.php" style="font-size:0.78rem;color:rgba(255,255,255,0.4);text-decoration:none;display:flex;align-items:center;gap:8px;transition:color 0.2s;" onmouseover="this.style.color='rgba(255,255,255,0.8)'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </a>
    </div>
</aside>

<!-- Main Content -->
<div id="main-content">
    <!-- Top Bar -->
    <div class="topbar">
        <h1 class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
        <div style="display:flex;align-items:center;gap:1rem;">
            <span style="font-size:0.8rem;color:var(--sage);">Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <div style="width:34px;height:34px;border-radius:50%;background:var(--sand);display:flex;align-items:center;justify-content:center;font-family:'Cormorant Garamond',serif;font-size:1rem;color:white;font-weight:600;">
                <?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?>
            </div>
        </div>
    </div>
    <div style="padding:2rem;">