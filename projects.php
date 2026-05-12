<?php
require_once 'config.php';

// Only fetch categories (lightweight) and site settings on page load.
// Projects are loaded via AJAX on demand — no DB project query here.

// Fetch distinct categories for filter tabs
$catStmt    = db()->query("SELECT DISTINCT category FROM projects WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categories = $catStmt->fetchAll(\PDO::FETCH_COLUMN);

// Total project count for the hero stat (single cheap COUNT)
$totalProjects = (int) db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Site settings
$siteName = getSetting('site_name', 'A. Moeed | MyDesignAssistants');
$metaDesc = getSetting('meta_description', 'A. Moeed - Professional Interior Designer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Projects — <?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="Browse all interior design projects by A. Moeed — <?= htmlspecialchars($metaDesc) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://mydesignassistants.com/projects.php">

    <!-- Open Graph -->
    <meta property="og:title" content="All Projects — <?= htmlspecialchars($siteName) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://mydesignassistants.com/projects.php">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'cream': '#F5F0E8',
                        'cream-dark': '#EDE6D6',
                        'sand': '#C9A96E',
                        'sand-light': '#D4B896',
                        'sand-dark': '#A07840',
                        'charcoal': '#2C2C2C',
                        'charcoal-light': '#4A4A4A',
                        'sage': '#7A8C7E',
                        'sage-light': '#9EB5A4',
                        'terracotta': '#C17B5C',
                        'warm-white': '#FDFAF5',
                    },
                    fontFamily: {
                        'display': ['Cormorant Garamond', 'serif'],
                        'body': ['Jost', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --cream: #F5F0E8;
            --cream-dark: #EDE6D6;
            --sand: #C9A96E;
            --sand-light: #D4B896;
            --sand-dark: #A07840;
            --charcoal: #2C2C2C;
            --charcoal-light: #4A4A4A;
            --sage: #424242;
            --terracotta: #C17B5C;
            --warm-white: #FDFAF5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; max-width: 100%; }

        html { overflow-x: hidden; }

        body {
            font-family: 'Jost', sans-serif;
            background: var(--warm-white);
            color: var(--charcoal);
            overflow-x: hidden;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--cream); }
        ::-webkit-scrollbar-thumb { background: var(--sand); border-radius: 10px; }

        /* Custom cursor */
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
            position: fixed;
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 1.5px solid var(--sand);
            pointer-events: none;
            z-index: 99998;
            top: 0; left: 0;
            will-change: transform;
            transform: translate(-50%, -50%);
            opacity: 0.7;
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

        /* ── PAGE HERO ── */
        .page-hero {
            position: relative;
            padding: 10rem 0 6rem;
            background: var(--warm-white);
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            right: -5vw; top: 50%;
            transform: translateY(-50%);
            width: 50vw; height: 50vw;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,169,110,0.07) 0%, rgba(201,169,110,0.02) 50%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .page-hero-content { position: relative; z-index: 2; }

        .hero-eyebrow {
            font-family: 'Jost', sans-serif;
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--sand);
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        .hero-eyebrow::before {
            content: '';
            width: 40px; height: 1px;
            background: var(--sand);
        }

        /* Section eyebrow */
        .section-eyebrow {
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--sand);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-eyebrow::before {
            content: '';
            width: 28px; height: 1px;
            background: var(--sand);
            flex-shrink: 0;
        }

        /* Section title */
        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 4vw, 3.8rem);
            font-weight: 300;
            line-height: 1.08;
            color: var(--charcoal);
            letter-spacing: -0.01em;
        }
        .section-title em { font-style: italic; color: var(--sand-dark); }

        /* ── FILTER TABS ── */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .filter-btn {
            font-family: 'Jost', sans-serif;
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            padding: 0.6rem 1.4rem;
            border: 1px solid rgba(44,44,44,0.18);
            background: transparent;
            color: var(--charcoal-light);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--charcoal);
            color: var(--cream);
            border-color: var(--charcoal);
        }
        .filter-btn.active {
            background: var(--sand-dark);
            border-color: var(--sand-dark);
            color: white;
        }

        /* ── PORTFOLIO GRID ── */
        .portfolio-item {
            background: var(--cream);
            cursor: pointer;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .portfolio-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 24px 60px rgba(44,44,44,0.12);
        }
        .portfolio-img-wrap {
            overflow: hidden;
            height: 260px;
            background: var(--cream-dark);
            flex-shrink: 0;
            position: relative;
        }
        .portfolio-img-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.25,0.46,0.45,0.94);
            display: block;
        }
        .portfolio-item:hover .portfolio-img-wrap img { transform: scale(1.06); }

        /* Hover overlay */
        .portfolio-img-wrap::after {
            content: '';
            position: absolute; inset: 0;
            background: rgba(44,44,44,0);
            transition: background 0.4s ease;
        }
        .portfolio-item:hover .portfolio-img-wrap::after {
            background: rgba(44,44,44,0.18);
        }

        .portfolio-info {
            padding: 1.25rem 1.4rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-top: 1px solid rgba(201,169,110,0.12);
            background: white;
            flex: 1;
        }
        .portfolio-info-category {
            font-size: 0.62rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--sand-dark);
            font-weight: 500;
        }
        .portfolio-info-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--charcoal);
            line-height: 1.25;
        }
        .portfolio-info-software {
            font-size: 0.72rem;
            color: var(--sage);
            letter-spacing: 0.04em;
            margin-top: 2px;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 6rem 2rem;
        }

        /* ── PAGINATION ── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-top: 4rem;
            flex-wrap: wrap;
        }
        .page-btn {
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            width: 42px; height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(44,44,44,0.15);
            background: white;
            color: var(--charcoal-light);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .page-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--charcoal);
            transform: scaleY(0);
            transform-origin: bottom;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        .page-btn:hover::before { transform: scaleY(1); }
        .page-btn:hover { color: var(--cream); border-color: var(--charcoal); }
        .page-btn span { position: relative; z-index: 1; }
        .page-btn.current {
            background: var(--sand-dark);
            border-color: var(--sand-dark);
            color: white;
        }
        .page-btn.current::before { display: none; }
        .page-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }
        .page-ellipsis {
            font-size: 0.8rem;
            color: var(--sage);
            padding: 0 6px;
            letter-spacing: 0.1em;
        }

        /* Page info */
        .page-info {
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            color: var(--sage);
            text-align: center;
            margin-top: 1.25rem;
        }

        /* Buttons */
        .btn-primary {
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 1rem 2.5rem;
            background: var(--charcoal);
            color: var(--cream);
            border: none;
            cursor: pointer;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            display: inline-block;
            text-decoration: none;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: var(--sand-dark);
            transition: left 0.4s ease;
            z-index: 0;
        }
        .btn-primary:hover::before { left: 0; }
        .btn-primary span { position: relative; z-index: 1; }

        .btn-outline {
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 1rem 2.5rem;
            background: transparent;
            color: var(--charcoal);
            border: 1px solid var(--charcoal);
            cursor: pointer;
            transition: all 0.4s ease;
            display: inline-block;
            text-decoration: none;
        }
        .btn-outline:hover { background: var(--charcoal); color: var(--cream); }

        /* Decorative line */
        .deco-line {
            width: 1px;
            height: 80px;
            background: linear-gradient(to bottom, transparent, var(--sand), transparent);
            margin: 0 auto;
        }

        /* Footer */
        footer {
            background: var(--charcoal);
            color: rgba(255,255,255,0.7);
        }
        .footer-link {
            color: rgba(255,255,255,0.6);
            transition: color 0.3s;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .footer-link:hover { color: var(--sand-light); }

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
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); pointer-events: none; position: relative;
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
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
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

        /* ── AJAX grid spinner ── */
        #grid-spinner {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6rem 0;
            gap: 1.5rem;
        }
        #grid-spinner.visible { display: flex; }
        .spinner-ring {
            width: 48px; height: 48px;
            border: 2px solid var(--cream-dark);
            border-top-color: var(--sand);
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
        }
        .spinner-label {
            font-size: 0.72rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--sage);
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Grid fade-in on AJAX load */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Mobile overflow safety */
        @media (max-width: 767px) {
            img, svg, video, canvas { max-width: 100%; }
            section, footer, nav { max-width: 100vw; overflow-x: hidden; }
            .filter-bar { gap: 6px; }
            .filter-btn { font-size: 0.65rem; padding: 0.5rem 1rem; }
        }
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

<!-- ══════════════════════════════════════════════════════
     PAGE HERO
══════════════════════════════════════════════════════ -->
<section class="page-hero">
    <div class="max-w-7xl mx-auto px-6 page-hero-content">
        <!-- Breadcrumb -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:2.5rem;" data-aos="fade-up">
            <a href="index.php" style="font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);text-decoration:none;transition:color 0.3s;"
               onmouseover="this.style.color='var(--sand-dark)'" onmouseout="this.style.color='var(--sage)'">Home</a>
            <span style="color:var(--sand);font-size:0.7rem;">&#47;</span>
            <span style="font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sand-dark);">Projects</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-end">
            <div data-aos="fade-up" data-aos-delay="80">
                <p class="hero-eyebrow mb-5">Portfolio</p>
                <h1 class="section-title" style="font-size:clamp(2.8rem,5.5vw,5.5rem);font-weight:300;">
                    All <em>Projects</em>
                </h1>
                <div style="width:60px;height:2px;background:linear-gradient(90deg,var(--sand),transparent);margin-top:1.75rem;"></div>
            </div>
            <div data-aos="fade-up" data-aos-delay="180">
                <p style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);max-width:480px;">
                    Every project is a distinct dialogue between space, light, and purpose — browse the full collection of residential, commercial, and 3D design work.
                </p>
                <div style="display:flex;align-items:center;gap:16px;margin-top:1.75rem;">
                    <span style="font-family:'Cormorant Garamond',serif;font-size:2.2rem;font-weight:600;color:var(--sand-dark);line-height:1;"><?= $totalProjects ?></span>
                    <span style="font-size:0.72rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--sage);">Total Projects</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     FILTER BAR + PROJECTS GRID
══════════════════════════════════════════════════════ -->
<section id="projects-section" style="padding:0 0 7rem;background:white;">
    <div class="max-w-7xl mx-auto px-6">

        <?php if (!empty($categories)): ?>
        <!-- Filter tabs — AJAX, no page reload -->
        <div style="padding:2.5rem 0 3rem;border-bottom:1px solid var(--cream-dark);margin-bottom:3.5rem;" data-aos="fade-up">
            <div class="filter-bar" id="filter-bar">
                <button class="filter-btn active"
                        data-category=""
                        onclick="filterClick(this)">
                    All
                </button>
                <?php foreach ($categories as $cat): ?>
                <button class="filter-btn"
                        data-category="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"
                        onclick="filterClick(this)">
                    <?= htmlspecialchars($cat) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- AJAX content area -->
        <div id="grid-spinner" aria-live="polite" aria-label="Loading projects">
            <div class="spinner-ring"></div>
            <p class="spinner-label">Loading projects</p>
        </div>

        <div id="projects-container"></div>
        <div id="pagination-container"></div>

    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     CTA STRIP
══════════════════════════════════════════════════════ -->
<section style="background:var(--charcoal);padding:5rem 0;">
    <div class="max-w-7xl mx-auto px-6 text-center" data-aos="fade-up">
        <p style="font-size:0.72rem;letter-spacing:0.35em;text-transform:uppercase;color:var(--sand-light);margin-bottom:1.5rem;display:flex;align-items:center;justify-content:center;gap:12px;">
            <span style="width:28px;height:1px;background:var(--sand-light);display:inline-block;"></span>
            Let's Collaborate
            <span style="width:28px;height:1px;background:var(--sand-light);display:inline-block;"></span>
        </p>
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:300;color:white;margin-bottom:2rem;line-height:1.1;">
            Have a <em style="font-style:italic;color:var(--sand-light);">Project</em> in Mind?
        </h2>
        <p style="font-size:0.9rem;color:rgba(255,255,255,0.5);max-width:420px;margin:0 auto 2.5rem;line-height:1.8;">
            Let's turn your vision into a breathtaking interior — reach out and start the conversation.
        </p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
            <a href="index.php#contact" class="btn-primary"><span>Get In Touch</span></a>
            <a href="<?= getSetting('upwork_url') ?>" target="_blank"
               style="font-family:'Jost',sans-serif;font-size:0.75rem;font-weight:500;letter-spacing:0.2em;text-transform:uppercase;padding:1rem 2.5rem;background:transparent;color:var(--sand-light);border:1px solid rgba(201,169,110,0.4);text-decoration:none;transition:all 0.4s;"
               onmouseover="this.style.background='rgba(201,169,110,0.1)'"
               onmouseout="this.style.background='transparent'">
                Hire on Upwork
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════════ -->
<footer style="padding:4rem 0 2rem;">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
            <div>
                <div class="mb-4">
                    <span style="font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:600;color:white;letter-spacing:0.05em;">A. Moeed</span><br>
                    <span style="font-size:0.6rem;letter-spacing:0.28em;text-transform:uppercase;color:var(--sand-light);">MyDesignAssistants</span>
                </div>
                <p style="font-size:0.85rem;color:rgba(255,255,255,0.45);line-height:1.8;max-width:260px;">Transforming spaces into extraordinary experiences — one design at a time.</p>
            </div>
            <div>
                <h4 style="font-size:0.7rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand-light);margin-bottom:1.25rem;">Navigation</h4>
                <div class="flex flex-col gap-2">
                    <a href="index.php" class="footer-link">Home</a>
                    <a href="index.php#about" class="footer-link">About</a>
                    <a href="projects.php" class="footer-link" style="color:var(--sand-light);">All Projects</a>
                    <a href="index.php#testimonials" class="footer-link">Reviews</a>
                    <a href="index.php#contact" class="footer-link">Contact</a>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.7rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand-light);margin-bottom:1.25rem;">Find Me Online</h4>
                <div class="flex flex-col gap-2">
                    <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="footer-link">Upwork Profile</a>
                    <a href="<?= getSetting('fiverr_url') ?>"  target="_blank" class="footer-link">Fiverr Gigs</a>
                    <a href="mailto:<?= getSetting('email') ?>" class="footer-link"><?= getSetting('email') ?></a>
                </div>
            </div>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,0.07);padding-top:1.75rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <p style="font-size:0.78rem;color:rgba(255,255,255,0.25);">© <?= date('Y') ?> A. Moeed · MyDesignAssistants. All rights reserved.</p>
            <a href="projects.php" style="font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:rgba(255,255,255,0.25);text-decoration:none;transition:color 0.3s;"
               onmouseover="this.style.color='var(--sand-light)'" onmouseout="this.style.color='rgba(255,255,255,0.25)'">
                View All Projects ↑
            </a>
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

<script>
// ── Loader ──
window.addEventListener('load', () => {
    setTimeout(() => {
        const loader = document.getElementById('loader');
        if (loader) loader.classList.add('hidden');
    }, 1400);
});

// ── AOS ──
AOS.init({ once: true, offset: 50, duration: 700 });

// ── Navbar scroll ──
window.addEventListener('scroll', () => {
    const nav = document.getElementById('navbar');
    if (window.scrollY > 60) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
}, { passive: true });

// ── Custom cursor ──
(function () {
    const dot  = document.getElementById('cursor');
    const ring = document.getElementById('cursor-follower');
    if (!dot || !ring) return;
    let mx = window.innerWidth / 2, my = window.innerHeight / 2, rx = mx, ry = my;
    document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; }, { passive: true });
    (function loop() {
        dot.style.transform  = `translate(${mx - 4}px,${my - 4}px)`;
        rx += (mx - rx) * 0.12; ry += (my - ry) * 0.12;
        ring.style.transform = `translate(${rx - 14}px,${ry - 14}px)`;
        requestAnimationFrame(loop);
    })();
})();

// ── Mobile menu ──
function toggleMobileMenu() { document.getElementById('mobile-menu').classList.toggle('open'); }
function closeMobileMenu()  { document.getElementById('mobile-menu').classList.remove('open'); }

// ── AJAX projects loader ──────────────────────────────────────────────────────
let _currentCategory = '';
let _currentPage     = 1;
let _isLoading       = false;

function showSpinner() {
    document.getElementById('grid-spinner').classList.add('visible');
    document.getElementById('projects-container').style.opacity = '0';
    document.getElementById('pagination-container').innerHTML   = '';
}

function hideSpinner() {
    document.getElementById('grid-spinner').classList.remove('visible');
    document.getElementById('projects-container').style.opacity = '1';
}

async function loadProjects(category, page) {
    if (_isLoading) return;
    _isLoading       = true;
    _currentCategory = category;
    _currentPage     = page;

    showSpinner();

    const params = new URLSearchParams({ page });
    if (category) params.set('category', category);

    try {
        const res  = await fetch('projects-ajax.php?' + params.toString());
        const data = await res.json();

        document.getElementById('projects-container').innerHTML   = data.grid;
        document.getElementById('pagination-container').innerHTML = data.pagination;

        // Scroll to the grid area (only if not the initial load)
        if (page > 1 || _hasInitialLoaded) {
            const section = document.getElementById('projects-section');
            if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        _hasInitialLoaded = true;

    } catch (err) {
        document.getElementById('projects-container').innerHTML =
            '<p style="text-align:center;padding:4rem 0;color:var(--sage);font-size:0.9rem;">Failed to load projects. Please refresh the page.</p>';
    } finally {
        hideSpinner();
        _isLoading = false;
    }
}

function filterClick(btn) {
    // Update active tab
    document.querySelectorAll('#filter-bar .filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    loadProjects(btn.dataset.category, 1);
}

// Track whether the very first load has happened (to avoid scrolling on boot)
let _hasInitialLoaded = false;

// Auto-load "All" on page ready — no page reload needed on visit
document.addEventListener('DOMContentLoaded', () => {
    loadProjects('', 1);
});
</script>

</body>
</html>