<?php
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = db()->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: index.php');
    exit;
}

// Parse fields
$images   = json_decode($project['images'] ?? '[]', true) ?: [];
$features = $project['features'] ? array_filter(array_map('trim', explode("\n", $project['features']))) : [];
$software = $project['software_used'] ? array_map('trim', explode(',', $project['software_used'])) : [];
$thumb    = $project['thumbnail'] ?? ($images[0] ?? '');

// Prev / Next
$prevStmt = db()->prepare("SELECT id, title FROM projects WHERE id < ? ORDER BY id DESC LIMIT 1");
$prevStmt->execute([$id]);
$prev = $prevStmt->fetch();

$nextStmt = db()->prepare("SELECT id, title FROM projects WHERE id > ? ORDER BY id ASC LIMIT 1");
$nextStmt->execute([$id]);
$next = $nextStmt->fetch();

// Site settings
$siteName = getSetting('site_name', 'A. Moeed | MyDesignAssistants');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['title']) ?> — <?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="<?= htmlspecialchars(substr(strip_tags($project['description'] ?? ''), 0, 160)) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

    <script>
    tailwind.config = {
        theme: { extend: {
            colors: {
                'cream': '#F5F0E8', 'cream-dark': '#EDE6D6',
                'sand': '#C9A96E', 'sand-light': '#D4B896', 'sand-dark': '#A07840',
                'charcoal': '#2C2C2C', 'charcoal-light': '#4A4A4A',
                'sage': '#7A8C7E', 'warm-white': '#FDFAF5',
            },
            fontFamily: { 'display': ['Cormorant Garamond', 'serif'], 'body': ['Jost', 'sans-serif'] }
        }}
    }
    </script>

    <style>
        :root {
            --cream: #F5F0E8; --cream-dark: #EDE6D6;
            --sand: #C9A96E; --sand-light: #D4B896; --sand-dark: #A07840;
            --charcoal: #2C2C2C; --charcoal-light: #4A4A4A;
            --sage: #7A8C7E; --warm-white: #FDFAF5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; max-width: 100%;  }
        html { overflow-x: hidden; scroll-behavior: smooth; }
        body { font-family: 'Jost', sans-serif; background: var(--warm-white); color: var(--charcoal); overflow-x: hidden; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--cream); }
        ::-webkit-scrollbar-thumb { background: var(--sand); border-radius: 10px; }

        /* Custom cursor */
        /* .custom-cursor {
            position: fixed; width: 8px; height: 8px; border-radius: 50%;
            background: var(--sand-dark); pointer-events: none; z-index: 99999;
            top: 0; left: 0; will-change: transform; transform: translate(-50%,-50%);
        } */

        .custom-cursor {
            position: fixed;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--sand-dark);
            pointer-events: none;
            z-index: 99999;
            border: 1px solid var(--cream);
            top: 0; left: 0;
            will-change: transform;
            transform: translate(-50%, -50%);
        }
        .cursor-follower {
            position: fixed; width: 28px; height: 28px; border-radius: 50%;
            border: 1.5px solid var(--sand); pointer-events: none; z-index: 99998;
            top: 0; left: 0; will-change: transform; transform: translate(-50%,-50%); opacity: 0.7;
        }

        /* Navbar */
        #navbar {
            position: fixed; top: 0; width: 100%; z-index: 1000;
            transition: all 0.4s ease;
            padding: 1.5rem 0;
        }
        #navbar.scrolled {
            background: rgba(253, 250, 245, 0.97);
            backdrop-filter: blur(20px);
            padding: 0.8rem 0;
            box-shadow: 0 1px 30px rgba(44,44,44,0.08);
        }
        .nav-link {
            font-family: 'Jost', sans-serif;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--charcoal);
            position: relative;
            transition: color 0.3s;
            text-decoration: none;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -3px; left: 0;
            width: 0; height: 1px;
            background: var(--sand);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover, .nav-link.active { color: var(--sand-dark); }

        /* ── HERO ── */
        .project-hero {
            position: relative;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }
        .hero-bg {
            position: absolute; inset: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transform: scale(1.05);
            transition: transform 8s ease;
        }
        .hero-bg.loaded { transform: scale(1); }
        /* layered dark gradient — bottom heavy for text legibility */
        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(20,18,16,0.25) 0%,
                rgba(20,18,16,0.1) 35%,
                rgba(20,18,16,0.55) 65%,
                rgba(20,18,16,0.88) 100%
            );
        }
        .hero-content {
            position: relative; z-index: 2;
            width: 100%; padding: 0 1.5rem 5rem;
            max-width: 1280px; margin: 0 auto;
        }

        .hero-eyebrow {
            font-size: 0.68rem; font-weight: 500; letter-spacing: 0.4em;
            text-transform: uppercase; color: var(--sand-light);
            display: flex; align-items: center; gap: 12px; margin-bottom: 1.25rem;
        }
        .hero-eyebrow::before {
            content: ''; width: 36px; height: 1px; background: var(--sand-light);
        }
        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.8rem, 7vw, 6.5rem);
            font-weight: 300; line-height: 1.0;
            color: white; letter-spacing: -0.02em;
            margin-bottom: 1.5rem;
        }
        .hero-title em { font-style: italic; color: var(--sand-light); }
        .hero-meta {
            display: flex; flex-wrap: wrap; align-items: center;
            gap: 1.5rem; margin-bottom: 0;
        }
        .hero-meta-divider { width: 1px; height: 16px; background: rgba(255,255,255,0.25); }
        .hero-scroll-hint {
            position: absolute; bottom: 1.75rem; right: 2.5rem; z-index: 2;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            opacity: 0.5;
        }
        .hero-scroll-hint span {
            font-size: 0.58rem; letter-spacing: 0.3em; text-transform: uppercase; color: white;
        }

        /* ── BACK BUTTON ── */
        .back-btn {
            display: inline-flex; align-items: center; gap: 10px;
            font-size: 0.72rem; font-weight: 500; letter-spacing: 0.18em;
            text-transform: uppercase; color: rgba(255,255,255,0.7);
            text-decoration: none; transition: color 0.3s;
        }
        .back-btn:hover { color: var(--sand-light); }
        .back-btn svg { transition: transform 0.3s; }
        .back-btn:hover svg { transform: translateX(-4px); }

        /* ── CONTENT AREA ── */
        .section-eyebrow {
            font-size: 0.7rem; font-weight: 500; letter-spacing: 0.35em;
            text-transform: uppercase; color: var(--sand);
            display: flex; align-items: center; gap: 10px;
        }
        .section-eyebrow::before {
            content: ''; width: 28px; height: 1px; background: var(--sand);
        }

        /* ── GALLERY ── */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 12px;
        }
        .gallery-item {
            overflow: hidden;
            position: relative;
            background: var(--cream-dark);
        }
        .gallery-item img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.25,0.46,0.45,0.94);
            display: block;
        }
        .gallery-item:hover img { transform: scale(1.06); }

        /* Asymmetric spanning — beautiful editorial layout */
        .gallery-item:nth-child(1) { grid-column: span 8; height: 520px; }
        .gallery-item:nth-child(2) { grid-column: span 4; height: 520px; }
        .gallery-item:nth-child(3) { grid-column: span 4; height: 380px; }
        .gallery-item:nth-child(4) { grid-column: span 4; height: 380px; }
        .gallery-item:nth-child(5) { grid-column: span 4; height: 380px; }
        .gallery-item:nth-child(6) { grid-column: span 6; height: 420px; }
        .gallery-item:nth-child(7) { grid-column: span 6; height: 420px; }
        .gallery-item:nth-child(n+8) { grid-column: span 4; height: 360px; }

        @media (max-width: 1024px) {
            .gallery-item:nth-child(1) { grid-column: span 12; height: 420px; }
            .gallery-item:nth-child(2) { grid-column: span 12; height: 320px; }
            .gallery-item:nth-child(3),
            .gallery-item:nth-child(4),
            .gallery-item:nth-child(5) { grid-column: span 6; height: 280px; }
            .gallery-item:nth-child(6),
            .gallery-item:nth-child(7) { grid-column: span 12; height: 360px; }
            .gallery-item:nth-child(n+8) { grid-column: span 6; height: 280px; }
        }
        @media (max-width: 640px) {
            .gallery-item { grid-column: span 12 !important; height: 260px !important; }
        }

        /* Hover overlay on gallery */
        .gallery-item-overlay {
            position: absolute; inset: 0;
            background: rgba(44,44,44,0);
            display: flex; align-items: center; justify-content: center;
            transition: background 0.4s ease;
        }
        .gallery-item:hover .gallery-item-overlay { background: rgba(44,44,44,0.4); }
        .gallery-zoom-icon {
            opacity: 0; transform: scale(0.7);
            transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1);
            width: 52px; height: 52px; border: 1px solid white;
            display: flex; align-items: center; justify-content: center;
        }
        .gallery-item:hover .gallery-zoom-icon { opacity: 1; transform: scale(1); }

        /* ── LIGHTBOX ── */
        #lightbox {
            display: none; position: fixed; inset: 0;
            background: rgba(10,9,8,0.97);
            z-index: 9999; align-items: center; justify-content: center;
        }
        #lightbox.active { display: flex; }
        #lb-img {
            max-width: 92vw; max-height: 90vh;
            object-fit: contain;
            animation: lbIn 0.3s ease;
        }
        @keyframes lbIn {
            from { opacity: 0; transform: scale(0.94); }
            to   { opacity: 1; transform: scale(1); }
        }
        #lb-close {
            position: absolute; top: 1.5rem; right: 1.5rem;
            color: white; font-size: 1.4rem;
            width: 46px; height: 46px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid rgba(255,255,255,0.25);
            transition: all 0.3s; cursor: pointer !important;
        }
        #lb-close:hover { background: var(--sand); border-color: var(--sand); }
        #lb-prev, #lb-next {
            position: absolute; top: 50%; transform: translateY(-50%);
            color: white; width: 50px; height: 50px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s; cursor: pointer !important;
            background: rgba(44,44,44,0.5);
        }
        #lb-prev { left: 1.5rem; }
        #lb-next { right: 1.5rem; }
        #lb-prev:hover, #lb-next:hover { background: var(--sand); border-color: var(--sand); }
        #lb-counter {
            position: absolute; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
            font-size: 0.72rem; letter-spacing: 0.25em; color: rgba(255,255,255,0.45);
        }

        /* ── FEATURES ── */
        .feature-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 1rem 0; border-bottom: 1px solid rgba(201,169,110,0.12);
        }
        .feature-item:last-child { border-bottom: none; }
        .feature-diamond {
            width: 7px; height: 7px; background: var(--sand);
            transform: rotate(45deg); flex-shrink: 0; margin-top: 6px;
        }

        /* ── SOFTWARE TAGS ── */
        .sw-tag {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px;
            border: 1px solid rgba(201,169,110,0.3);
            background: rgba(201,169,110,0.06);
            font-size: 0.75rem; letter-spacing: 0.06em;
            color: var(--charcoal-light);
        }
        .sw-tag::before {
            content: ''; width: 4px; height: 4px;
            background: var(--sand); border-radius: 50%;
        }

        /* ── INFO STATS ── */
        .info-stat {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--cream-dark);
        }
        .info-stat:last-child { border-bottom: none; }
        .info-stat-label {
            font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase;
            color: var(--sage); margin-bottom: 6px;
        }
        .info-stat-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; color: var(--charcoal); font-weight: 400;
        }

        /* ── PREV/NEXT NAV ── */
        .project-nav-card {
            flex: 1; padding: 2rem; border: 1px solid var(--cream-dark);
            text-decoration: none; transition: all 0.4s ease;
            background: white; position: relative; overflow: hidden;
        }
        .project-nav-card::before {
            content: ''; position: absolute; inset: 0;
            background: var(--charcoal); transform: scaleX(0);
            transform-origin: left; transition: transform 0.4s ease;
        }
        .project-nav-card:hover::before { transform: scaleX(1); }
        .project-nav-card > * { position: relative; z-index: 1; }
        .project-nav-card:hover .nav-card-label { color: var(--sand-light); }
        .project-nav-card:hover .nav-card-title { color: white; }
        .nav-card-label {
            font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase;
            color: var(--sage); margin-bottom: 8px; transition: color 0.4s;
        }
        .nav-card-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.3rem; font-weight: 400;
            color: var(--charcoal); transition: color 0.4s; line-height: 1.3;
        }

        /* ── FOOTER ── */
        footer { background: var(--charcoal); color: rgba(255,255,255,0.7); padding: 3rem 0 2rem; }
        .footer-link {
            color: rgba(255,255,255,0.55); transition: color 0.3s;
            font-size: 0.85rem; text-decoration: none;
        }
        .footer-link:hover { color: var(--sand-light); }

        /* Buttons */
        .btn-primary {
            font-family: 'Jost', sans-serif; font-size: 0.75rem; font-weight: 500;
            letter-spacing: 0.2em; text-transform: uppercase;
            padding: 1rem 2.5rem; background: var(--charcoal); color: var(--cream);
            border: none; cursor: pointer; transition: all 0.4s ease;
            position: relative; overflow: hidden; display: inline-block; text-decoration: none;
        }
        .btn-primary::before {
            content: ''; position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%; background: var(--sand-dark);
            transition: left 0.4s ease; z-index: 0;
        }
        .btn-primary:hover::before { left: 0; }
        .btn-primary span { position: relative; z-index: 1; }

        .btn-outline {
            font-family: 'Jost', sans-serif; font-size: 0.75rem; font-weight: 500;
            letter-spacing: 0.2em; text-transform: uppercase;
            padding: 1rem 2.5rem; background: transparent; color: var(--charcoal);
            border: 1px solid var(--charcoal); cursor: pointer;
            transition: all 0.4s ease; display: inline-block; text-decoration: none;
        }
        .btn-outline:hover { background: var(--charcoal); color: var(--cream); }

        /* Mobile menu */
        #mobile-menu {
            position: fixed; inset: 0; background: var(--warm-white);
            z-index: 999; display: none; flex-direction: column;
            align-items: center; justify-content: center; gap: 2.5rem;
        }
        #mobile-menu.open { display: flex; }
        .mobile-nav-link {
            font-family: 'Cormorant Garamond', serif; font-size: 2.5rem;
            font-weight: 300; color: var(--charcoal); text-decoration: none; transition: color 0.3s;
        }
        .mobile-nav-link:hover { color: var(--sand-dark); }

        /* WhatsApp */
        .wa-wrapper {
            position: fixed; bottom: 2rem; right: 2rem; z-index: 9990;
            display: flex; flex-direction: column; align-items: flex-end; gap: 12px;
        }
        .wa-tooltip {
            background: white; color: var(--charcoal);
            font-family: 'Jost', sans-serif; font-size: 0.8rem; font-weight: 500;
            padding: 0.6rem 1rem; border-radius: 6px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12); white-space: nowrap;
            opacity: 0; transform: translateX(10px);
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); pointer-events: none; position: relative;
        }
        .wa-tooltip::after {
            content: ''; position: absolute; right: -6px; top: 50%; transform: translateY(-50%);
            border: 6px solid transparent; border-right: none; border-left-color: white;
        }
        .wa-wrapper:hover .wa-tooltip { opacity: 1; transform: translateX(0); }
        .wa-btn {
            position: relative; width: 58px; height: 58px; border-radius: 50%;
            background: #25D366; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 24px rgba(37,211,102,0.45); text-decoration: none;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s ease;
            animation: wa-float 3s ease-in-out infinite;
        }
        .wa-btn:hover { transform: scale(1.12); box-shadow: 0 10px 32px rgba(37,211,102,0.6); animation-play-state: paused; }
        .wa-btn svg { width: 30px; height: 30px; fill: white; }
        .wa-btn::before {
            content: ''; position: absolute; inset: -4px; border-radius: 50%;
            border: 2px solid rgba(37,211,102,0.4); animation: wa-pulse 2.5s ease-out infinite;
        }
        .wa-btn::after {
            content: ''; position: absolute; inset: -4px; border-radius: 50%;
            border: 2px solid rgba(37,211,102,0.2); animation: wa-pulse 2.5s ease-out infinite 0.6s;
        }
        @keyframes wa-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        @keyframes wa-pulse { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.7);opacity:0} }

        /* Loading screen */
        #loader {
            position: fixed; inset: 0;
            background: var(--warm-white);
            z-index: 99999;
            display: flex; align-items: center; justify-content: center; flex-direction: column;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        #loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem; font-weight: 300;
            color: var(--charcoal); letter-spacing: 0.15em;
        }
        .loader-bar {
            width: 200px; height: 1px;
            background: var(--cream-dark); margin-top: 1.5rem; overflow: hidden;
        }
        .loader-progress {
            height: 100%; background: var(--sand);
            animation: loadProgress 1.4s ease forwards;
        }
        @keyframes loadProgress { from { width: 0; } to { width: 100%; } }

        @media (max-width: 767px) {
            img, svg, video, canvas { max-width: 100%; }
            section, footer, nav { max-width: 100vw; overflow-x: hidden; }
        }
        /* ── Rich-text prose from Quill editor ─────────────────────────── */
        .project-prose {
            font-size: 1.05rem;
            line-height: 2;
            color: var(--charcoal-light);
            font-weight: 300;
            margin-bottom: 3rem;
        }
        .project-prose p {
            margin-bottom: 1.25em;
        }
        .project-prose p:last-child { margin-bottom: 0; }
        .project-prose strong, .project-prose b {
            font-weight: 600;
            color: var(--charcoal);
        }
        .project-prose em, .project-prose i {
            font-style: italic;
        }
        .project-prose u {
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .project-prose a {
            color: var(--sand-dark);
            text-decoration: underline;
            text-underline-offset: 3px;
            transition: color 0.3s;
        }
        .project-prose a:hover { color: var(--sand); }
        .project-prose h1, .project-prose h2, .project-prose h3 {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 400;
            color: var(--charcoal);
            margin: 1.5em 0 0.5em;
            line-height: 1.2;
        }
        .project-prose h1 { font-size: 2rem; }
        .project-prose h2 { font-size: 1.6rem; }
        .project-prose h3 { font-size: 1.3rem; }
        .project-prose ul, .project-prose ol {
            padding-left: 1.5rem;
            margin-bottom: 1.25em;
        }
        .project-prose ul { list-style: none; padding-left: 0; }
        .project-prose ul li {
            padding-left: 1.25rem;
            position: relative;
            margin-bottom: 0.4em;
        }
        .project-prose ul li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.65em;
            width: 6px; height: 6px;
            background: var(--sand);
            transform: rotate(45deg);
        }
        .project-prose ol { list-style: decimal; }
        .project-prose ol li { margin-bottom: 0.4em; padding-left: 0.25rem; }
        .project-prose blockquote {
            border-left: 2px solid var(--sand);
            margin: 1.5em 0;
            padding: 0.5em 0 0.5em 1.5em;
            color: var(--sage);
            font-style: italic;
        }
        .project-prose code {
            font-family: monospace;
            background: var(--cream-dark);
            padding: 2px 6px;
            font-size: 0.9em;
        }
        /* Strip Quill's default inline font/color styles that may clash */
        .project-prose [style*="color:"] { color: inherit !important; }
        .project-prose [style*="background-color:"] { background: none !important; }

    </style>
</head>
<body>

<!-- Loading Screen -->
<div id="loader">
    <p class="loader-text" style="font-weight:600;"><em style="color:var(--sand)">MyDesignAssistants</em></p>
    <div class="loader-bar"><div class="loader-progress"></div></div>
</div>

<!-- Custom Cursor -->
<div class="custom-cursor" id="cursor"></div>
<div class="cursor-follower" id="cursor-follower"></div>

<!-- Lightbox -->
<div id="lightbox">
    <div id="lb-close">&#x2715;</div>
    <div id="lb-prev">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M15 18l-6-6 6-6"/></svg>
    </div>
    <img id="lb-img" src="" alt="">
    <div id="lb-next">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M9 18l6-6-6-6"/></svg>
    </div>
    <div id="lb-counter"></div>
</div>

<!-- Mobile Menu -->
<div id="mobile-menu">
    <a href="index.php" class="mobile-nav-link" onclick="closeMobileMenu()">Home</a>
    <a href="index.php#about" class="mobile-nav-link" onclick="closeMobileMenu()">About</a>
    <a href="projects.php" class="mobile-nav-link" onclick="closeMobileMenu()">Projects</a>
    <a href="index.php#process" class="mobile-nav-link" onclick="closeMobileMenu()">Work with Me</a>
    <a href="index.php#testimonials" class="mobile-nav-link" onclick="closeMobileMenu()">Reviews</a>
    <a href="index.php#contact" class="mobile-nav-link" onclick="closeMobileMenu()">Contact</a>
    <div style="display:flex;gap:2rem;margin-top:1rem;">
        <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="mobile-nav-link" style="font-size:1rem;">Upwork</a>
        <a href="<?= getSetting('fiverr_url') ?>" target="_blank" class="mobile-nav-link" style="font-size:1rem;">Fiverr</a>
    </div>
</div>

<!-- NAVBAR -->
<nav id="navbar">
    <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
        <!-- Logo -->
        <a href="index.php" class="flex flex-col" style="text-decoration:none;">
            <span style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:600;color:var(--charcoal);letter-spacing:0.05em;line-height:1;">
                A. Moeed
            </span>
            <span style="font-size:0.58rem;letter-spacing:0.28em;text-transform:uppercase;color:var(--sand);font-weight:500;">
                MyDesignAssistants
            </span>
        </a>

        <!-- Desktop Nav -->
        <div class="hidden md:flex items-center gap-10">
            <a href="index.php#about" class="nav-link">About</a>
            <a href="projects.php" class="nav-link active">Portfolio</a>
            <a href="index.php#process" class="nav-link">Work with Me</a>
            <a href="index.php#testimonials" class="nav-link">Reviews</a>
            <a href="index.php#contact" class="nav-link">Contact</a>
        </div>

        <!-- CTA + Mobile toggle -->
        <div class="flex items-center gap-4">
            <a href="<?= getSetting('upwork_url') ?>" target="_blank"
               class="hidden md:inline-block"
               style="font-size:0.72rem;letter-spacing:0.18em;text-transform:uppercase;font-weight:500;padding:0.65rem 1.5rem;border:1px solid var(--sand);color:var(--sand-dark);text-decoration:none;transition:all 0.3s;"
               onmouseover="this.style.background='var(--sand-dark)';this.style.color='white';"
               onmouseout="this.style.background='transparent';this.style.color='var(--sand-dark)';">
                Hire Me
            </a>
            <!-- Hamburger -->
            <button onclick="toggleMobileMenu()" class="md:hidden flex flex-col gap-1.5 p-1" aria-label="Menu">
                <span style="display:block;width:24px;height:1.5px;background:var(--charcoal);"></span>
                <span style="display:block;width:18px;height:1.5px;background:var(--charcoal);"></span>
                <span style="display:block;width:24px;height:1.5px;background:var(--charcoal);"></span>
            </button>
        </div>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════
     HERO — full-viewport background image
═══════════════════════════════════════════════ -->
<section class="project-hero">

    <?php if ($thumb): ?>
    <div class="hero-bg" id="heroBg"
         style="background-image: url('<?= htmlspecialchars($thumb) ?>');">
    </div>
    <?php else: ?>
    <div class="hero-bg" style="background: linear-gradient(135deg, var(--charcoal) 0%, #1a1714 100%);"></div>
    <?php endif; ?>

    <div class="hero-overlay"></div>

    <!-- Back link top-left -->
    <div style="position:absolute;top:6rem;left:0;right:0;z-index:3;">
        <div class="max-w-7xl mx-auto px-6">
            <a href="index.php#portfolio" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                Back to Portfolio
            </a>
        </div>
    </div>

    <div class="hero-content max-w-7xl">
        <p class="hero-eyebrow" data-aos="fade-up" data-aos-duration="800">
            <?= htmlspecialchars($project['category'] ?? 'Interior Design') ?>
        </p>
        <h1 class="hero-title" data-aos="fade-up" data-aos-delay="80" data-aos-duration="900">
            <?php
            $words = explode(' ', $project['title']);
            $half  = ceil(count($words) / 2);
            $line1 = implode(' ', array_slice($words, 0, $half));
            $line2 = implode(' ', array_slice($words, $half));
            echo htmlspecialchars($line1);
            if ($line2) echo '<br><em>' . htmlspecialchars($line2) . '</em>';
            ?>
        </h1>
        <div class="hero-meta" data-aos="fade-up" data-aos-delay="180" data-aos-duration="800">
            <?php if ($project['client_name'] ?? ''): ?>
            <span style="font-size:0.78rem;color:rgba(255,255,255,0.6);">
                Client: <span style="color:rgba(255,255,255,0.9);"><?= htmlspecialchars($project['client_name']) ?></span>
            </span>
            <div class="hero-meta-divider"></div>
            <?php endif; ?>
            <?php if ($project['project_year'] ?? ''): ?>
            <span style="font-size:0.78rem;color:rgba(255,255,255,0.6);">
                Year: <span style="color:rgba(255,255,255,0.9);"><?= htmlspecialchars($project['project_year']) ?></span>
            </span>
            <div class="hero-meta-divider"></div>
            <?php endif; ?>
            <?php if (!empty($software)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach(array_slice($software, 0, 4) as $sw): ?>
                <span style="font-size:0.68rem;letter-spacing:0.1em;color:var(--sand-light);border:1px solid rgba(201,169,110,0.35);padding:3px 10px;">
                    <?= htmlspecialchars($sw) ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scroll hint -->
    <div class="hero-scroll-hint">
        <span>Scroll</span>
        <div style="width:1px;height:40px;background:linear-gradient(to bottom,white,transparent);animation:pulse 2s infinite;"></div>
    </div>
</section>

<style>
@keyframes pulse { 0%,100%{opacity:0.5} 50%{opacity:1} }
/* Navbar: white text over dark hero, switches to dark on scroll */
#navbar:not(.scrolled) .nav-link { color: rgba(255,255,255,0.85); }
#navbar:not(.scrolled) .nav-link:hover { color: var(--sand-light); }
#navbar:not(.scrolled) .nav-link::after { background: var(--sand-light); }
#navbar:not(.scrolled) .flex.flex-col span:first-child { color: white !important; }
#navbar:not(.scrolled) .flex.flex-col span:last-child { color: var(--sand-light) !important; }
#navbar:not(.scrolled) .md\:inline-block {
    border-color: rgba(255,255,255,0.45) !important;
    color: rgba(255,255,255,0.85) !important;
}
#navbar:not(.scrolled) .md\:inline-block:hover {
    background: var(--sand-dark) !important;
    border-color: var(--sand-dark) !important;
    color: white !important;
}
#navbar:not(.scrolled) button span[style] { background: white !important; }
</style>

<!-- ═══════════════════════════════════════════════
     DESCRIPTION + DETAILS
═══════════════════════════════════════════════ -->
<section style="padding:6rem 0; background:var(--warm-white);">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-16">

            <!-- Left: Description (2/3) -->
            <div class="lg:col-span-2" data-aos="fade-right" data-aos-duration="800">
                <p class="section-eyebrow mb-5">Project Overview</p>
                <div style="width:50px;height:2px;background:linear-gradient(90deg,var(--sand),transparent);margin-bottom:2.5rem;"></div>

                <?php if ($project['description']): ?>
                <div class="project-prose">
                    <?= $project['description'] ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($features)): ?>
                <div>
                    <p style="font-size:0.7rem;letter-spacing:0.3em;text-transform:uppercase;color:var(--sage);margin-bottom:1.25rem;">Key Features</p>
                    <?php foreach ($features as $feat): ?>
                    <div class="feature-item">
                        <div class="feature-diamond"></div>
                        <span style="font-size:0.92rem;color:var(--charcoal-light);line-height:1.7;"><?= htmlspecialchars($feat) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Info sidebar (1/3) -->
            <div data-aos="fade-left" data-aos-duration="800" data-aos-delay="100">
                <div style="background:var(--cream);padding:2.5rem;">
                    <p style="font-size:0.68rem;letter-spacing:0.3em;text-transform:uppercase;color:var(--sage);margin-bottom:1.5rem;">Project Details</p>

                    <?php if ($project['category'] ?? ''): ?>
                    <div class="info-stat">
                        <div class="info-stat-label">Category</div>
                        <div class="info-stat-value"><?= htmlspecialchars($project['category']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($project['client_name'] ?? ''): ?>
                    <div class="info-stat">
                        <div class="info-stat-label">Client</div>
                        <div class="info-stat-value"><?= htmlspecialchars($project['client_name']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($project['project_year'] ?? ''): ?>
                    <div class="info-stat">
                        <div class="info-stat-label">Year</div>
                        <div class="info-stat-value"><?= htmlspecialchars($project['project_year']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($project['location'] ?? ''): ?>
                    <div class="info-stat">
                        <div class="info-stat-label">Location</div>
                        <div class="info-stat-value"><?= htmlspecialchars($project['location']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($software)): ?>
                    <div class="info-stat">
                        <div class="info-stat-label">Software Used</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                            <?php foreach ($software as $sw): ?>
                            <span class="sw-tag"><?= htmlspecialchars($sw) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:2rem;">
                        <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="btn-primary" style="width:100%;text-align:center;display:block;">
                            <span>Hire Me for Similar</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════
     GALLERY — editorial asymmetric grid
═══════════════════════════════════════════════ -->
<?php if (!empty($images)): ?>
<section style="padding:0 0 6rem; background:var(--warm-white);">
    <div class="max-w-7xl mx-auto px-6">

        <div style="margin-bottom:3rem;" data-aos="fade-up">
            <p class="section-eyebrow mb-2">Project Gallery</p>
            <div style="width:40px;height:1px;background:var(--sand-light);margin-top:1rem;"></div>
        </div>

        <div class="gallery-grid" id="galleryGrid">
            <?php foreach ($images as $idx => $img): ?>
            <div class="gallery-item" data-aos="fade-up" data-aos-delay="<?= ($idx % 3) * 80 ?>"
                 onclick="openLightbox(<?= $idx ?>)" style="cursor:zoom-in;">
                <img src="<?= htmlspecialchars($img) ?>"
                     alt="<?= htmlspecialchars($project['title']) ?> — image <?= $idx + 1 ?>"
                     loading="lazy">
                <div class="gallery-item-overlay">
                    <div class="gallery-zoom-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
                            <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>
                            <path d="M11 8v6M8 11h6"/>
                        </svg>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     PREV / NEXT NAVIGATION
═══════════════════════════════════════════════ -->
<?php if ($prev || $next): ?>
<section style="background:var(--cream);padding:4rem 0;">
    <div class="max-w-7xl mx-auto px-6">
        <p style="font-size:0.68rem;letter-spacing:0.3em;text-transform:uppercase;color:var(--sage);text-align:center;margin-bottom:2.5rem;">More Projects</p>
        <div style="display:flex;gap:1px;">
            <?php if ($prev): ?>
            <a href="project-detail.php?id=<?= $prev['id'] ?>" class="project-nav-card">
                <div class="nav-card-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Previous Project
                </div>
                <div class="nav-card-title"><?= htmlspecialchars($prev['title']) ?></div>
            </a>
            <?php endif; ?>
            <?php if ($next): ?>
            <a href="project-detail.php?id=<?= $next['id'] ?>" class="project-nav-card" style="text-align:right;">
                <div class="nav-card-label">
                    Next Project
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:inline;vertical-align:middle;margin-left:4px;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </div>
                <div class="nav-card-title"><?= htmlspecialchars($next['title']) ?></div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════ -->
<footer>
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-10">
            <div>
                <div class="mb-4">
                    <span style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:600;color:white;letter-spacing:0.05em;">A. Moeed</span><br>
                    <span style="font-size:0.6rem;letter-spacing:0.28em;text-transform:uppercase;color:var(--sand-light);">MyDesignAssistants</span>
                </div>
                <p style="font-size:0.85rem;color:rgba(255,255,255,0.4);line-height:1.8;max-width:260px;">Transforming spaces into extraordinary experiences — one design at a time.</p>
            </div>
            <div>
                <h4 style="font-size:0.7rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand-light);margin-bottom:1.25rem;">Navigation</h4>
                <div class="flex flex-col gap-2">
                    <a href="index.php"              class="footer-link">Home</a>
                    <a href="index.php#about"        class="footer-link">About</a>
                    <a href="index.php#portfolio"    class="footer-link">Portfolio</a>
                    <a href="index.php#testimonials" class="footer-link">Reviews</a>
                    <a href="index.php#contact"      class="footer-link">Contact</a>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.7rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand-light);margin-bottom:1.25rem;">Find Me Online</h4>
                <div class="flex flex-col gap-2">
                    <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="footer-link">Upwork Profile</a>
                    <a href="<?= getSetting('fiverr_url') ?>"  target="_blank" class="footer-link">Fiverr Gigs</a>
                    <a href="mailto:<?= getSetting('email') ?>"               class="footer-link"><?= getSetting('email') ?></a>
                </div>
            </div>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,0.07);padding-top:1.75rem;text-align:center;">
            <p style="font-size:0.78rem;color:rgba(255,255,255,0.25);">© <?= date('Y') ?> A. Moeed · MyDesignAssistants. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- WhatsApp Fixed Button -->
<div class="wa-wrapper">
    <div class="wa-tooltip">Chat on WhatsApp</div>
    <a href="https://wa.me/923064879877?text=Hi%20A.%20Moeed!%20I%20saw%20your%20portfolio%20and%20would%20like%20to%20discuss%20a%20project."
       target="_blank" rel="noopener noreferrer" class="wa-btn" aria-label="Chat with A. Moeed on WhatsApp">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M16.004 0C7.164 0 0 7.163 0 16c0 2.822.737 5.469 2.027 7.77L0 32l8.463-2.007A15.93 15.93 0 0016.004 32C24.837 32 32 24.837 32 16S24.837 0 16.004 0zm0 29.23a13.19 13.19 0 01-6.72-1.832l-.482-.286-4.996 1.185 1.23-4.858-.315-.5A13.157 13.157 0 012.77 16c0-7.294 5.94-13.23 13.234-13.23S29.23 8.706 29.23 16 23.296 29.23 16.004 29.23zm7.254-9.918c-.397-.199-2.35-1.16-2.715-1.292-.364-.133-.63-.199-.895.199-.265.397-1.028 1.292-1.26 1.558-.232.265-.464.298-.86.1-.398-.2-1.678-.619-3.197-1.973-1.181-1.054-1.978-2.355-2.21-2.752-.232-.397-.025-.612.175-.81.18-.177.397-.464.596-.696.199-.232.265-.397.397-.662.133-.265.066-.497-.033-.696-.1-.2-.895-2.156-1.226-2.952-.322-.774-.65-.669-.895-.681l-.762-.013c-.265 0-.695.1-1.06.497-.364.397-1.392 1.358-1.392 3.314s1.425 3.844 1.624 4.11c.199.264 2.804 4.28 6.793 6.003.95.41 1.691.655 2.269.839.953.303 1.82.26 2.505.158.764-.114 2.35-.96 2.682-1.888.332-.927.332-1.723.232-1.888-.099-.166-.364-.265-.762-.464z"/>
        </svg>
    </a>
</div>

<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
// ── Loader ──
window.addEventListener('load', () => {
    setTimeout(() => {
        const loader = document.getElementById('loader');
        if (loader) loader.classList.add('hidden');
    }, 1400);
});

AOS.init({ once: true, offset: 60, duration: 700 });

// ── Navbar scroll behaviour ──
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    if (window.scrollY > 80) navbar.classList.add('scrolled');
    else navbar.classList.remove('scrolled');
}, { passive: true });

// ── Hero bg Ken Burns trigger ──
window.addEventListener('load', () => {
    const bg = document.getElementById('heroBg');
    if (bg) bg.classList.add('loaded');
});

// ── Custom cursor ──
(function() {
    const dot  = document.getElementById('cursor');
    const ring = document.getElementById('cursor-follower');
    if (!dot || !ring) return;
    let mx = window.innerWidth/2, my = window.innerHeight/2, rx = mx, ry = my;
    document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; }, { passive: true });
    (function loop() {
        dot.style.transform  = `translate(${mx-4}px,${my-4}px)`;
        rx += (mx-rx)*0.12; ry += (my-ry)*0.12;
        ring.style.transform = `translate(${rx-14}px,${ry-14}px)`;
        requestAnimationFrame(loop);
    })();
})();

// ── Mobile menu ──
function toggleMobileMenu() { document.getElementById('mobile-menu').classList.toggle('open'); }
function closeMobileMenu()  { document.getElementById('mobile-menu').classList.remove('open'); }

// ── Lightbox ──
const lbImages = <?= json_encode(array_values($images)) ?>;
let lbCurrent = 0;

function openLightbox(idx) {
    lbCurrent = idx;
    renderLb();
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function renderLb() {
    const img = document.getElementById('lb-img');
    img.style.opacity = '0';
    img.src = lbImages[lbCurrent];
    img.onload = () => { img.style.opacity = '1'; img.style.transition = 'opacity 0.3s'; };
    document.getElementById('lb-counter').textContent = (lbCurrent + 1) + ' / ' + lbImages.length;
    document.getElementById('lb-prev').style.display = lbImages.length > 1 ? 'flex' : 'none';
    document.getElementById('lb-next').style.display = lbImages.length > 1 ? 'flex' : 'none';
}
function closeLb() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.getElementById('lb-close').addEventListener('click', closeLb);
document.getElementById('lightbox').addEventListener('click', e => { if (e.target === e.currentTarget) closeLb(); });
document.getElementById('lb-prev').addEventListener('click', e => {
    e.stopPropagation();
    lbCurrent = (lbCurrent - 1 + lbImages.length) % lbImages.length;
    renderLb();
});
document.getElementById('lb-next').addEventListener('click', e => {
    e.stopPropagation();
    lbCurrent = (lbCurrent + 1) % lbImages.length;
    renderLb();
});
document.addEventListener('keydown', e => {
    if (document.getElementById('lightbox').style.display !== 'flex') return;
    if (e.key === 'Escape') closeLb();
    if (e.key === 'ArrowLeft') { lbCurrent = (lbCurrent-1+lbImages.length)%lbImages.length; renderLb(); }
    if (e.key === 'ArrowRight') { lbCurrent = (lbCurrent+1)%lbImages.length; renderLb(); }
});
</script>
</body>
</html>