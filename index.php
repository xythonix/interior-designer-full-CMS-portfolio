<?php
require_once 'config.php';

// ── AJAX contact form handler ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Discard any accidental prior output so the response is pure JSON
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $name    = sanitize($_POST['name'] ?? '');
    $email   = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if ($name && $email && $subject && $message && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare("INSERT INTO contact_messages (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? '']);
        jsonResponse(['success' => true]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Please fill all fields correctly.']);
    }
}
// SEO Meta
$siteName = getSetting('site_name', 'A. Moeed - MyDesignAssistants');
$metaDesc = getSetting('meta_description', 'A. Moeed - Professional Interior Designer');
$heroTitle = getSetting('hero_title', 'Where Vision Meets Design Excellence');
$heroSub = getSetting('hero_subtitle', 'Award-winning interior design solutions');
$aboutText = getSetting('about_text', '');

// Fetch featured projects
$projectsStmt = db()->query("SELECT * FROM projects ORDER BY is_featured DESC, sort_order ASC, created_at DESC LIMIT 6");
$projects = $projectsStmt->fetchAll();

// Fetch testimonials
$testiStmt = db()->query("SELECT * FROM testimonials WHERE is_featured = 1 ORDER BY sort_order ASC, created_at DESC");
$testimonials = $testiStmt->fetchAll();

// Handle contact form
$contactSuccess = false;
$contactError = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    <meta name="keywords" content="interior design, A. Moeed, MyDesignAssistants, luxury interior, home design, commercial design, mydesignassistants.com">
    <meta name="author" content="A. Moeed Professional Interior Designer">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://mydesignassistants.com/">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://mydesignassistants.com/">
    <meta property="og:image" content="https://mydesignassistants.com/assets/images/og-image.jpg">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($siteName) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDesc) ?>">

    <!-- Preload critical assets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Three.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

    <!-- Swiper for testimonials -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            /* --sage: #7A8C7E; */
            --sage: #424242;
            --terracotta: #C17B5C;
            --warm-white: #FDFAF5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; max-width: 100%;  }

        html {
            scroll-behavior: auto; /* JS handles scrolling */
            overflow-x: hidden;
        }

        body {
            font-family: 'Jost', sans-serif;
            background: var(--warm-white);
            color: var(--charcoal);
            overflow-x: hidden !important;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--cream); }
        ::-webkit-scrollbar-thumb { background: var(--sand); border-radius: 10px; }

        /* Cursor — GPU accelerated, zero lag */
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
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -3px; left: 0;
            width: 0; height: 1px;
            background: var(--sand);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after { width: 100%; }
        .nav-link:hover { color: var(--sand-dark); }

        /* Hero — Split Layout */
        #hero {
            min-height: 100vh;
            background: var(--warm-white);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        /* Subtle grain overlay */
        #hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
        }

        /* Decorative background circle */
        #hero::after {
            content: '';
            position: absolute;
            right: -5vw;
            top: 50%;
            transform: translateY(-50%);
            width: 55vw;
            height: 55vw;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,169,110,0.08) 0%, rgba(201,169,110,0.03) 50%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .hero-content { position: relative; z-index: 2; }

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

        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.8rem, 5.5vw, 6rem);
            font-weight: 300;
            line-height: 1.05;
            color: var(--charcoal);
            letter-spacing: -0.02em;
        }
        .hero-title em {
            font-style: italic;
            color: var(--sand-dark);
        }

        .hero-divider {
            width: 60px; height: 2px;
            background: linear-gradient(90deg, var(--sand), transparent);
        }

        /* Right image panel */
        .hero-image-panel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Floating badge */
        .hero-badge {
            position: absolute;
            background: white;
            border: 1px solid rgba(201,169,110,0.25);
            box-shadow: 0 20px 60px rgba(44,44,44,0.1);
            padding: 1rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        .hero-badge-icon {
            width: 36px; height: 36px;
            background: var(--cream-dark);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        @keyframes heroFloat {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-8px); }
        }

        /* Stat pill */
        .hero-stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.5rem 1rem;
            background: var(--charcoal);
            color: var(--cream);
        }
        .hero-stat-pill span { font-size: 0.7rem; letter-spacing: 0.15em; text-transform: uppercase; }

        /* Isometric room image */
        .hero-room-img {
            width: 100%;
            max-width: 520px;
            filter: drop-shadow(0 40px 80px rgba(44,44,44,0.18));
            animation: roomFloat 6s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }
        @keyframes roomFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50%       { transform: translateY(-12px) rotate(0.5deg); }
        }

        /* Decorative ring behind image */
        .hero-ring {
            position: absolute;
            width: 460px; height: 460px;
            border-radius: 50%;
            border: 1px solid rgba(201,169,110,0.2);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            animation: ringPulse 8s ease-in-out infinite;
        }
        .hero-ring-2 {
            position: absolute;
            width: 560px; height: 560px;
            border-radius: 50%;
            border: 1px solid rgba(201,169,110,0.08);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            animation: ringPulse 8s ease-in-out infinite 1s;
        }
        @keyframes ringPulse {
            0%, 100% { opacity: 0.5; transform: translate(-50%,-50%) scale(1); }
            50%       { opacity: 1; transform: translate(-50%,-50%) scale(1.03); }
        }

        /* Users badge */
        .hero-users-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(44,44,44,0.05);
            border: 1px solid rgba(44,44,44,0.1);
            border-radius: 50px;
            padding: 0.4rem 1rem 0.4rem 0.4rem;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            color: var(--charcoal-light);
        }
        .hero-users-dot {
            width: 8px; height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: dotBlink 2s ease-in-out infinite;
        }
        @keyframes dotBlink {
            0%,100%{opacity:1} 50%{opacity:0.4}
        }

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
        .btn-outline:hover {
            background: var(--charcoal);
            color: var(--cream);
        }

        /* Profile image */
        .profile-frame {
            position: relative;
            display: inline-block;
        }
        .profile-frame::before {
            content: '';
            position: absolute;
            top: -15px; right: -15px;
            width: 100%; height: 100%;
            border: 2px solid var(--sand);
            z-index: 0;
        }
        .profile-frame::after {
            content: '';
            position: absolute;
            bottom: -15px; left: -15px;
            width: 60%; height: 60%;
            background: var(--cream-dark);
            z-index: 0;
        }
        .profile-img {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            display: block;
            filter: grayscale(8%) contrast(1.05);
        }

        /* Stats bar */
        .stat-number {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem;
            font-weight: 600;
            color: var(--sand-dark);
            line-height: 1;
        }

        /* Section titles */
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
            width: 30px; height: 1px;
            background: var(--sand);
        }

        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 400;
            color: var(--charcoal);
            line-height: 1.15;
        }
        .section-title em {
            font-style: italic;
            color: var(--sand-dark);
        }

        /* Portfolio grid */
        .portfolio-item {
            position: relative;
            cursor: pointer;
            background: white;
            border: 1px solid rgba(201,169,110,0.18);
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.4s ease, border-color 0.4s ease, transform 0.4s ease;
        }
        .portfolio-item:hover {
            box-shadow: 0 16px 48px rgba(160,120,64,0.18), 0 2px 12px rgba(44,44,44,0.08);
            border-color: rgba(201,169,110,0.55);
            transform: translateY(-4px);
        }
        .portfolio-img-wrap {
            width: 100%;
            height: 240px;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--cream-dark);
        }
        .portfolio-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: block;
        }
        .portfolio-item:hover .portfolio-img-wrap img {
            transform: scale(1.09);
        }
        .portfolio-info {
            padding: 1.1rem 1.3rem 1.3rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-top: 1px solid rgba(201,169,110,0.12);
            background: white;
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
        /* hide old overlay — kept in PHP but invisible */
        .portfolio-overlay { display: none !important; }

        /* Testimonials */
        .testimonial-card {
            background: white;
            border: 1px solid rgba(201, 169, 110, 0.2);
            padding: 2.5rem;
            position: relative;
            transition: all 0.4s ease;
            height: 100%;
        }
        .testimonial-card::before {
            content: '"';
            font-family: 'Cormorant Garamond', serif;
            font-size: 8rem;
            color: var(--sand);
            opacity: 0.15;
            position: absolute;
            top: -1rem;
            left: 1.5rem;
            line-height: 1;
        }
        .testimonial-card:hover {
            border-color: var(--sand);
            box-shadow: 0 20px 60px rgba(201, 169, 110, 0.12);
            transform: translateY(-4px);
        }

        .star-filled { color: #C9A96E; }
        .star-half { color: #C9A96E; opacity: 0.6; }
        .star-empty { color: #D1C4B0; }

        .testimonial-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--sand-light);
        }

        /* Platform badges */
        .platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .badge-upwork { background: #E8F4E8; color: #14A800; }
        .badge-fiverr { background: #FFF0E8; color: #1DBF73; }

        /* Contact */
        .contact-input {
            width: 100%;
            padding: 1rem 1.25rem;
            background: white;
            border: 1px solid #E5DDD0;
            font-family: 'Jost', sans-serif;
            font-size: 0.9rem;
            color: var(--charcoal);
            outline: none;
            transition: border-color 0.3s ease;
        }
        .contact-input:focus { border-color: var(--sand); }
        .contact-input::placeholder { color: #B5A898; }

        /* Lightbox */
        #lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20,18,16,0.95);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        #lightbox.active { display: flex; }
        #lightbox img {
            max-width: 90vw;
            max-height: 90vh;
            object-fit: contain;
            border: none;
        }
        #lightbox-close {
            position: absolute;
            top: 1.5rem; right: 1.5rem;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            width: 44px; height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        #lightbox-close:hover {
            background: var(--sand);
            border-color: var(--sand);
        }

        /* Project Modal */
        #project-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9000;
            overflow-y: auto;
        }
        #project-modal.active { display: block; }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(20,18,16,0.92);
        }
        .modal-content {
            position: relative;
            z-index: 1;
            max-width: 960px;
            margin: 4rem auto;
            background: var(--warm-white);
            padding: 0;
            overflow: hidden;
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

        /* Marquee */
        .marquee-wrapper { overflow: hidden; }
        .marquee-track {
            display: flex;
            gap: 3rem;
            animation: marquee 25s linear infinite;
            white-space: nowrap;
        }
        @keyframes marquee {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }
        .marquee-item {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.2rem;
            font-style: italic;
            color: rgba(44,44,44,0.25);
            flex-shrink: 0;
        }

        /* Decorative line */
        .deco-line {
            width: 1px;
            height: 80px;
            background: linear-gradient(to bottom, transparent, var(--sand), transparent);
            margin: 0 auto;
        }

        /* Loading screen */
        #loader {
            position: fixed;
            inset: 0;
            background: var(--warm-white);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: opacity 0.6s ease, visibility 0.6s ease;
            opacity: 1;
            visibility: visible;
        }
        #loader.hidden,
        #loader[style*="opacity: 0"] {
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
        }
        .loader-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            font-weight: 300;
            color: var(--charcoal);
            letter-spacing: 0.15em;
        }
        .loader-bar {
            width: 200px; height: 1px;
            background: var(--cream-dark);
            margin-top: 1.5rem;
            overflow: hidden;
        }
        .loader-progress {
            height: 100%;
            background: var(--sand);
            animation: loadProgress 1.8s ease forwards;
        }
        @keyframes loadProgress {
            from { width: 0; }
            to { width: 100%; }
        }

        /* Swiper custom */
        .swiper-pagination-bullet { background: var(--sand-light) !important; opacity: 0.5; }
        .swiper-pagination-bullet-active { background: var(--sand-dark) !important; opacity: 1; }

        /* Brand logos section */
        .brand-logo {
            opacity: 0.9;
            transition: opacity 0.3s;
            filter: grayscale(100%);
        }
        .brand-logo:hover { opacity: 0.9; filter: grayscale(0%); }

        /* ── SweetAlert2 — portfolio theme ── */
        .swal-portfolio-popup {
            font-family: 'Jost', sans-serif !important;
            border-radius: 0 !important;
            padding: 2.5rem 2rem !important;
            border-top: 3px solid var(--sand) !important;
        }
        .swal-portfolio-title {
            font-family: 'Cormorant Garamond', serif !important;
            font-size: 2rem !important;
            font-weight: 400 !important;
            color: var(--charcoal) !important;
        }
        .swal-portfolio-body {
            font-size: 0.95rem !important;
            color: var(--charcoal-light, #4A4A4A) !important;
            line-height: 1.8 !important;
        }
        .swal-portfolio-btn {
            font-family: 'Jost', sans-serif !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            letter-spacing: 0.2em !important;
            text-transform: uppercase !important;
            padding: 0.9rem 2.5rem !important;
            background: var(--charcoal) !important;
            color: var(--cream) !important;
            border: none !important;
            border-radius: 0 !important;
            cursor: pointer !important;
            transition: background 0.3s !important;
        }
        .swal-portfolio-btn:hover { background: var(--sand-dark) !important; }
        .swal-portfolio-btn--error { background: var(--terracotta, #C17B5C) !important; }
        .swal2-icon.swal2-success { border-color: var(--sand) !important; }
        .swal2-icon.swal2-success [class^=swal2-success-line] { background: var(--sand) !important; }
        .swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(201,169,110,0.3) !important; }

        /* Mobile menu */
        #mobile-menu {
            position: fixed;
            inset: 0;
            background: var(--warm-white);
            z-index: 999;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2.5rem;
        }
        #mobile-menu.open { display: flex; }

        .mobile-nav-link {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.5rem;
            font-weight: 300;
            color: var(--charcoal);
            text-decoration: none;
            transition: color 0.3s;
        }
        .mobile-nav-link:hover { color: var(--sand-dark); }

        /* ── Mobile overflow safety ── */
        @media (max-width: 767px) {
            img, svg, video, canvas { max-width: 100%; }
            section, footer, nav { max-width: 100vw; overflow-x: hidden; }
            .marquee-wrapper { max-width: 100vw; }
            .swiper { max-width: 100%; }
            /* Ensure testimonial cards don't overflow */
            .testimonial-card { padding: 1.75rem; }
        }
    </style>

    <!-- Structured Data / Schema.org -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Person",
      "name": "A. Moeed",
      "jobTitle": "Interior Designer & Founder",
      "worksFor": {
        "@type": "Organization",
        "name": "MyDesignAssistants",
        "url": "https://mydesignassistants.com"
      },
      "url": "https://mydesignassistants.com",
      "description": "<?= htmlspecialchars($metaDesc) ?>",
      "sameAs": [
        "<?= getSetting('upwork_url') ?>",
        "<?= getSetting('fiverr_url') ?>"
      ]
    }
    </script>
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
    <div id="lightbox-close">&#x2715;</div>
    <!-- <img id="lightbox-img" src="" alt=""> -->
</div>

<!-- Project Modal -->
<div id="project-modal">
    <div class="modal-backdrop" id="modal-backdrop"></div>
    <div class="modal-content mx-4" id="modal-inner">
        <!-- Dynamic content -->
    </div>
</div>

<!-- Mobile Menu -->
<div id="mobile-menu">
    <a href="#hero" class="mobile-nav-link" onclick="closeMobileMenu()">Home</a>
    <a href="#about" class="mobile-nav-link" onclick="closeMobileMenu()">About</a>
    <a href="#portfolio" class="mobile-nav-link" onclick="closeMobileMenu()">Portfolio</a>
    <a href="#process" class="mobile-nav-link" onclick="closeMobileMenu()">Work with Me</a>
    <a href="#testimonials" class="mobile-nav-link" onclick="closeMobileMenu()">Reviews</a>
    <a href="#contact" class="mobile-nav-link" onclick="closeMobileMenu()">Contact</a>
    <div style="display:flex;gap:2rem;margin-top:1rem;">
        <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="mobile-nav-link" style="font-size:1rem;">Upwork</a>
        <a href="<?= getSetting('fiverr_url') ?>" target="_blank" class="mobile-nav-link" style="font-size:1rem;">Fiverr</a>
    </div>
</div>

<!-- NAVBAR -->
<nav id="navbar">
    <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
        <!-- Logo -->
        <a href="#hero" class="flex flex-col" style="text-decoration:none;">
            <span style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:600;color:var(--charcoal);letter-spacing:0.05em;line-height:1;">
                A. Moeed
            </span>
            <span style="font-size:0.58rem;letter-spacing:0.28em;text-transform:uppercase;color:var(--sand);font-weight:500;">
                MyDesignAssistants
            </span>
        </a>

        <!-- Desktop Nav -->
        <div class="hidden md:flex items-center gap-10">
            <a href="#about" class="nav-link">About</a>
            <a href="#portfolio" class="nav-link">Portfolio</a>
            <a href="#process" class="nav-link">Work with Me</a>
            <a href="#testimonials" class="nav-link">Reviews</a>
            <a href="#contact" class="nav-link">Contact</a>
        </div>

        <!-- CTA + Mobile toggle -->
        <div class="flex items-center gap-4">
            <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="hidden md:flex items-center gap-2 btn-primary" style="padding:0.6rem 1.5rem;">
                <span>Hire Me</span>
            </a>
            <button id="mobile-toggle" class="md:hidden flex flex-col gap-1.5 p-2" onclick="toggleMobileMenu()">
                <span class="w-6 h-px bg-charcoal block transition-all"></span>
                <span class="w-4 h-px bg-charcoal block transition-all"></span>
                <span class="w-6 h-px bg-charcoal block transition-all"></span>
            </button>
        </div>
    </div>
</nav>

<!-- HERO SECTION — Split Layout -->
<section id="hero">

    <div class="max-w-7xl mx-auto px-6 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-8 items-center min-h-screen py-32">

            <!-- LEFT: Text Content -->
            <div class="hero-content" data-aos="fade-right" data-aos-duration="900">

                <!-- Live badge -->
                <!-- <div class="mb-8">
                    <span class="hero-users-badge">
                        <span class="hero-users-dot"></span>
                        10,000+ Happy Clients Worldwide
                    </span>
                </div> -->

                <p class="hero-eyebrow mb-6">Interior Design Excellence</p>

                <h1 class="hero-title mb-6" style="font-weight:900;">
                    <?= htmlspecialchars($heroTitle) ?>
                </h1>

                <div class="hero-divider mb-6"></div>

                <p style="font-family:'Jost',sans-serif;font-size:1rem;font-weight:300;line-height:1.9;color:var(--charcoal-light);max-width:480px;" class="mb-10">
                    <?= htmlspecialchars($heroSub) ?>
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-wrap gap-4 mb-12">
                    <a href="#portfolio" class="btn-primary"><span>View Portfolio</span></a>
                    <a href="#contact" class="btn-outline">Get in Touch</a>
                </div>

                <!-- Platform links -->
                <div class="flex items-center gap-6">
                    <span style="font-size:0.7rem;letter-spacing:0.2em;text-transform:uppercase;color:#B5A898;">Available on</span>
                    <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="brand-logo" style="text-decoration:none;">
                        <svg width="100" height="24" viewBox="0 0 80 24" fill="none">
                            <text y="18" font-family="'Jost',sans-serif" font-weight="700" font-size="25" fill="#14A800">Upwork</text>
                        </svg>
                    </a>
                    <a href="<?= getSetting('fiverr_url') ?>" target="_blank" class="brand-logo" style="text-decoration:none;">
                        <svg width="75" height="24" viewBox="0 0 55 24" fill="none">
                            <text y="18" font-family="'Jost',sans-serif" font-weight="700" font-size="25" fill="#21af6d">Fiverr</text>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- RIGHT: Isometric Room Image -->
            <div class="hero-image-panel" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="150">

                <!-- Decorative rings -->
                <div class="hero-ring hidden lg:block"></div>
                <div class="hero-ring-2 hidden lg:block"></div>

                <!-- Main isometric image -->
                <img
                    src="uploads/avatars/hero-room.png"
                    onerror="this.onerror=null;this.src='uploads/avatars/hero-room.png';"
                    alt="Beautifully designed interior space"
                    class="hero-room-img"
                >

                <!-- Floating badge: Top Rated -->
                <div class="hero-badge" style="bottom:-10%;left:-2%;margin-bottom:40px !important;" data-aos="fade-up" data-aos-delay="400">
                    <div class="hero-badge-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--sand-dark)" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--charcoal);line-height:1;">Top Rated</div>
                        <div style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-top:2px;">On Upwork & Fiverr</div>
                    </div>
                </div>

                <!-- Floating badge: Projects -->
                <div class="hero-badge" style="top:10%;right:-2%;" data-aos="fade-up" data-aos-delay="600">
                    <div class="hero-badge-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--sand-dark)" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--charcoal);line-height:1;">200+ Projects</div>
                        <div style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-top:2px;">Delivered Globally</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scroll indicator -->
    <div style="position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;gap:8px;opacity:0.45;z-index:2;">
        <span style="font-size:0.6rem;letter-spacing:0.3em;text-transform:uppercase;">Scroll</span>
        <div style="width:1px;height:40px;background:linear-gradient(to bottom,var(--sand),transparent);animation:pulse 2s infinite;"></div>
    </div>
</section>

<!-- MARQUEE -->
<div class="marquee-wrapper py-5 border-y" style="border-color:var(--cream-dark);">
    <div class="marquee-track" >
        <?php $items = ['Interior Design', 'Space Planning', '3D Visualization', 'Concept Development', 'Luxury Residential', 'Commercial Design', 'Furniture Selection', 'Color Consultation', 'Interior Design', 'Space Planning', '3D Visualization', 'Concept Development', 'Luxury Residential', 'Commercial Design'];
        foreach ($items as $item): ?>
        <span class="marquee-item" style="color:#2c2c2c !important;"><?= $item ?> &nbsp; &mdash;</span>
        <?php endforeach; ?>
    </div>
</div>

<!-- STATS -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div data-aos="fade-up" data-aos-delay="0">
                <div class="stat-number">7+</div>
                <div class="deco-line my-3"></div>
                <p style="font-size:0.78rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);">Years Experience</p>
            </div>
            <div data-aos="fade-up" data-aos-delay="100">
                <div class="stat-number">200+</div>
                <div class="deco-line my-3"></div>
                <p style="font-size:0.78rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);">Projects Done</p>
            </div>
            <div data-aos="fade-up" data-aos-delay="200">
                <div class="stat-number">98%</div>
                <div class="deco-line my-3"></div>
                <p style="font-size:0.78rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);">Client Satisfaction</p>
            </div>
            <div data-aos="fade-up" data-aos-delay="300">
                <div class="stat-number">15+</div>
                <div class="deco-line my-3"></div>
                <p style="font-size:0.78rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);">Countries Served</p>
            </div>
        </div>
    </div>
</section>

<!-- ABOUT SECTION -->
<section id="about" class="py-28" style="background:var(--cream);">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-20 items-center">
            <!-- Left: Decorative image block -->
            <div class="relative pb-12 lg:pb-0" data-aos="fade-right">
                <div style="background:var(--cream-dark);width:100%;padding-bottom:110%;position:relative;overflow:hidden;">
                    <img src="uploads/avatars/profile.png"
                         onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22660%22><rect width=%22600%22 height=%22660%22 fill=%22%23EDE6D6%22/><text x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Georgia,serif%22 font-size=%2232%22 fill=%22%232C2C2C%22>A. Moeed</text></svg>'"
                         alt="A. Moeed at work"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top;">
                </div>
                <!-- Floating card -->
                <div style="position:absolute;bottom:-2rem;right:1.8rem;background:white;padding:1.5rem 2rem;box-shadow:0 20px 60px rgba(44,44,44,0.12);min-width:180px;max-width:calc(100% - 2rem);" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-number" style="font-size:2rem;">Top Rated</div>
                    <p style="font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-top:4px;">On Upwork & Fiverr</p>
                    <div style="display:flex;margin-top:8px;">
                        <?php for($i=0;$i<5;$i++): ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#C9A96E"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Text -->
            <div data-aos="fade-left">
                <p class="section-eyebrow mb-4">The Designer</p>
                <h2 class="section-title mb-6">A. Moeed <br><em style="font-size:25px;">founder of My Design Assistants</em></h2>    
                <div style="width:50px;height:2px;background:linear-gradient(90deg,var(--sand),transparent);margin-bottom:2rem;"></div>
                <div style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);">
                    <?= $aboutText ?>
                </div>

                <!-- Skills / Services -->
                <div class="grid grid-cols-2 gap-4 mt-10">
                    <?php $skills = ['Space Planning','3D Rendering','Color Theory','Furniture Design','Lighting Design','Project Management']; ?>
                    <?php foreach($skills as $i => $skill): ?>
                    <div class="flex items-center gap-3" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
                        <div style="width:6px;height:6px;background:var(--sand);border-radius:50%;flex-shrink:0;"></div>
                        <span style="font-size:0.85rem;font-weight:500;letter-spacing:0.05em;"><?= $skill ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex flex-wrap gap-4 mt-10">
                    <a href="#portfolio" class="btn-primary"><span>See My Work</span></a>
                    <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="btn-outline">Hire on Upwork</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PORTFOLIO SECTION -->
<section id="portfolio" class="py-28 bg-white">
    <div class="max-w-7xl mx-auto px-6">
        <!-- Header -->
        <div class="text-center mb-16" data-aos="fade-up">
            <p class="section-eyebrow justify-center mb-4">My Work</p>
            <h2 class="section-title mb-4" style="font-weight:600;">Featured <em>Projects</em>
        <!-- <svg style="margin-top:-15px !important;transform:rotateZ(180deg);width:330px; margin:auto;display:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1213 73"><path d="M1212.41 5.51c3.05 12.87-22.36 11.93-30.26 15.68-94.32 20.51-269.09 32.42-365.48 37.51-77.91 3.82-155.66 9.93-233.67 11.67-57.49 2.56-115.05-.19-172.57 1.58-121.28.91-243.17 1.88-363.69-13.33-12.51-2.64-25.8-2.92-37.77-7.45-30.66-21.42 26.02-21.53 38.52-19.26 359.95 29.05 364.68 27.36 638.24 17.85 121-3.78 241.22-19.21 426.76-41.46 4.72-.65 9.18 3.56 8.45 8.36a941.74 941.74 0 0 0 54.29-9.21c9.33-2.33 18.7-4.56 27.95-7.19a7.59 7.59 0 0 1 9.23 5.24Z" fill="#a19b9349"></path></svg> -->
                
        </h2>
            <p style="font-size:0.9rem;color:var(--charcoal-light);max-width:500px;margin:0 auto;">Each project is a unique journey — from conceptual sketches to breathtaking final spaces.</p>
        </div>

        <?php if(empty($projects)): ?>
        <!-- Placeholder projects when no DB projects -->
         <p class="section-eyebrow justify-center mb-4">Not available yet.</p>
        <!-- <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php $placeholders = [
                ['Modern Living Room Redesign','Residential','SketchUp, 3ds Max','https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?w=600&h=400&fit=crop'],
                ['Luxury Hotel Suite','Hospitality','AutoCAD, Lumion','https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&h=400&fit=crop'],
                ['Executive Office Space','Commercial','Revit, V-Ray','https://images.unsplash.com/photo-1497366216548-37526070297c?w=600&h=400&fit=crop'],
                ['Contemporary Kitchen','Residential','SketchUp, Photoshop','https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=600&h=400&fit=crop'],
                ['Master Bedroom Suite','Residential','3ds Max, Corona','https://images.unsplash.com/photo-1616594039964-ae9021a400a0?w=600&h=400&fit=crop'],
                ['Restaurant Interior','Commercial','AutoCAD, Lumion','https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600&h=400&fit=crop'],
            ];
            foreach($placeholders as $i => $p): ?>
            <div class="portfolio-item" style="height:320px;" data-aos="fade-up" data-aos-delay="<?= ($i%3)*100 ?>">
                <img src="<?= $p[3] ?>" alt="<?= $p[0] ?>" loading="lazy">
                <div class="portfolio-overlay">
                    <div class="portfolio-overlay-content">
                        <p style="font-size:0.65rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand-light);margin-bottom:6px;"><?= $p[1] ?></p>
                        <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:400;color:white;margin-bottom:8px;"><?= $p[0] ?></h3>
                        <p style="font-size:0.75rem;color:rgba(255,255,255,0.6);"><?= $p[2] ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div> -->
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-7">
            <?php foreach($projects as $i => $project):
                $images = json_decode($project['images'] ?? '[]', true);
                $thumb = $project['thumbnail'] ?? ($images[0] ?? '');
            ?>
            <div class="portfolio-item" data-aos="fade-up" data-aos-delay="<?= ($i%3)*100 ?>"
                 onclick="window.location.href='project-detail.php?id=<?= $project['id'] ?>'">

                <!-- Image wrapper (zoom on hover) -->
                <div class="portfolio-img-wrap">
                    <?php if($thumb): ?>
                    <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($project['title']) ?>" loading="lazy">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                        <span style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;color:var(--sage);">Interior Design</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Details below image -->
                <div class="portfolio-info">
                    <span class="portfolio-info-category"><?= htmlspecialchars($project['category'] ?? 'Interior Design') ?></span>
                    <h3 class="portfolio-info-title"><?= htmlspecialchars($project['title']) ?></h3>
                    <?php if(!empty($project['software_used'])): ?>
                    <span class="portfolio-info-software"><?= htmlspecialchars($project['software_used']) ?></span>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="text-center mt-14" data-aos="fade-up">
            <a href="<?= SITE_URL ?>/projects.php" class="btn-outline">View All Projects</a>
        </div>
    </div>
</section>

<!-- SERVICES STRIP -->
<section style="background:var(--charcoal);padding:5rem 0;">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">

            <!-- Residential Design -->
            <div class="text-center" data-aos="fade-up" data-aos-delay="0">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border:1px solid rgba(201,169,110,0.35);margin-bottom:1.5rem;position:relative;">
                    <div style="position:absolute;inset:0;background:rgba(201,169,110,0.06);"></div>
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 10.5L12 3l9 7.5"/>
                        <path d="M5 8.8V20a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1V8.8"/>
                        <rect x="9.5" y="10" width="2.5" height="2.5"/>
                        <path d="M15.5 4V6.5"/>
                    </svg>
                </div>
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;color:white;margin-bottom:0.75rem;font-weight:400;">Residential Design</h3>
                <p style="font-size:0.88rem;color:rgba(255,255,255,0.55);line-height:1.8;">Transforming homes into personal sanctuaries with thoughtful space planning and curated aesthetics.</p>
            </div>

            <!-- Commercial Design -->
            <div class="text-center" data-aos="fade-up" data-aos-delay="150">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border:1px solid rgba(201,169,110,0.35);margin-bottom:1.5rem;position:relative;">
                    <div style="position:absolute;inset:0;background:rgba(201,169,110,0.06);"></div>
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="17"/>
                        <path d="M3 8h18"/>
                        <rect x="6.5" y="10.5" width="2.5" height="2.5"/>
                        <rect x="10.75" y="10.5" width="2.5" height="2.5"/>
                        <rect x="15" y="10.5" width="2.5" height="2.5"/>
                        <rect x="6.5" y="15" width="2.5" height="2.5"/>
                        <rect x="15" y="15" width="2.5" height="2.5"/>
                        <path d="M10.75 15h2.5v6.1h-2.5z"/>
                    </svg>
                </div>
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;color:white;margin-bottom:0.75rem;font-weight:400;">Commercial Design</h3>
                <p style="font-size:0.88rem;color:rgba(255,255,255,0.55);line-height:1.8;">Creating productive, inspiring workplaces that reflect your brand identity and culture.</p>
            </div>

            <!-- 3D Visualization -->
            <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border:1px solid rgba(201,169,110,0.35);margin-bottom:1.5rem;position:relative;">
                    <div style="position:absolute;inset:0;background:rgba(201,169,110,0.06);"></div>
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3L21 8v8l-9 5-9-5V8z"/>
                        <path d="M12 3v13"/>
                        <path d="M3 8l9 5 9-5"/>
                        <path d="M7.5 10.5L12 13" stroke-opacity="0.5" stroke-width="0.9"/>
                        <path d="M16.5 10.5L12 13" stroke-opacity="0.5" stroke-width="0.9"/>
                    </svg>
                </div>
                <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.5rem;color:white;margin-bottom:0.75rem;font-weight:400;">3D Visualization</h3>
                <p style="font-size:0.88rem;color:rgba(255,255,255,0.55);line-height:1.8;">Photorealistic renders that bring your vision to life before a single piece of furniture moves.</p>
            </div>

        </div>
    </div>
</section>


<!-- BRANDS / PLATFORMS -->
<section style="padding:4rem 0;background:white;border-top:1px solid var(--cream-dark);">
    <div class="max-w-7xl mx-auto px-6">
        <p class="text-center mb-10" style="font-size:0.7rem;letter-spacing:0.3em;text-transform:uppercase;color:#B5A898;">Trusted Platforms & Software</p>
        <div class="flex flex-wrap justify-center items-center gap-12">
            <?php $brands = ['SketchUp','AutoCAD','3ds Max','Lumion','V-Ray','Photoshop','Revit','Corona']; ?>
            <?php foreach($brands as $brand): ?>
            <div class="brand-logo">
                <span style="font-family:'Jost',sans-serif;font-weight:600;font-size:0.85rem;letter-spacing:0.1em;color:var(--charcoal-light);"><?= $brand ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- HOW IT WORKS SECTION -->
<section id="process" style="background:var(--charcoal);padding:7rem 0;overflow:hidden;">
    <div class="max-w-7xl mx-auto px-6">

        <!-- Header -->
        <div class="grid grid-cols-1 lg:grid-cols-1 gap-12 items-end mb-20" data-aos="fade-up">
            <div>
                <p style="font-size:0.72rem;font-weight:500;letter-spacing:0.35em;text-transform:uppercase;color:var(--sand-light);display:flex;align-items:center;gap:12px;margin-bottom:1.25rem;">
                    <span style="width:28px;height:1px;background:var(--sand-light);display:inline-block;flex-shrink:0;"></span>
                    The Process
                </p>
                <h2 style="font-family:'Cormorant Garamond',serif;font-size:clamp(2.4rem,4.5vw,4rem);font-weight:600;color:white;line-height:1.1;letter-spacing:-0.01em;">
                    How We <em style="font-style:italic;color:var(--sand-light);">Create</em> Together
                </h2>
                <p style="font-size:0.92rem;line-height:1.95;color:rgba(255, 255, 255, 0.48);max-width:100%;margin-top:1.5rem;">
                    Every great space begins with a conversation. Here is the step-by-step journey from your first enquiry to a finished interior that exceeds your expectations.
                </p>
            </div>
        </div>

        <!-- Steps -->
        <div class="hiw-steps">

            <!-- Step 1 -->
            <div class="hiw-step" data-aos="fade-up" data-aos-delay="0">
                <div class="hiw-number-col">
                    <span class="hiw-number">01</span>
                    <div class="hiw-line"></div>
                </div>
                <div class="hiw-body">
                    <div class="hiw-icon-wrap">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                    </div>
                    <div class="hiw-text">
                        <h3 class="hiw-title">Initial Consultation</h3>
                        <p class="hiw-desc">We begin with a detailed discovery call to understand your vision, lifestyle, and goals. This is where your ideas take their first shape — no brief is too big or too bold.</p>
                        <!-- <div class="hiw-tags">
                            <span class="hiw-tag">Free Call</span>
                            <span class="hiw-tag">30–60 min</span>
                            <span class="hiw-tag">No Commitment</span>
                        </div> -->
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="hiw-step" data-aos="fade-up" data-aos-delay="80">
                <div class="hiw-number-col">
                    <span class="hiw-number">02</span>
                    <div class="hiw-line"></div>
                </div>
                <div class="hiw-body">
                    <div class="hiw-icon-wrap">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                        </svg>
                    </div>
                    <div class="hiw-text">
                        <h3 class="hiw-title">Concept &amp; Proposal</h3>
                        <p class="hiw-desc">I develop a tailored design concept — mood boards, spatial layouts, material palettes — and present a transparent project proposal with scope, timeline, and pricing.</p>
                        <div class="hiw-tags">
                            <span class="hiw-tag">Mood Boards</span>
                            <span class="hiw-tag">Floor Plans</span>
                            <span class="hiw-tag">Clear Pricing</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="hiw-step" data-aos="fade-up" data-aos-delay="160">
                <div class="hiw-number-col">
                    <span class="hiw-number">03</span>
                    <div class="hiw-line"></div>
                </div>
                <div class="hiw-body">
                    <div class="hiw-icon-wrap">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3L21 8v8l-9 5-9-5V8z"/>
                            <path d="M12 3v13M3 8l9 5 9-5"/>
                        </svg>
                    </div>
                    <div class="hiw-text">
                        <h3 class="hiw-title">3D Design &amp; Visualisation</h3>
                        <p class="hiw-desc">Using industry-leading tools — SketchUp, 3ds Max, V-Ray — I create photorealistic renders of your space so you can see exactly how it will look before anything moves.</p>
                        <div class="hiw-tags">
                            <span class="hiw-tag">Photorealistic Renders</span>
                            <span class="hiw-tag">Walkthroughs</span>
                            <span class="hiw-tag">Revisions Included</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="hiw-step" data-aos="fade-up" data-aos-delay="240">
                <div class="hiw-number-col">
                    <span class="hiw-number">04</span>
                    <div class="hiw-line"></div>
                </div>
                <div class="hiw-body">
                    <div class="hiw-icon-wrap">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#C9A96E" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                        </svg>
                    </div>
                    <div class="hiw-text">
                        <h3 class="hiw-title">Refinement &amp; Approval</h3>
                        <p class="hiw-desc">Your feedback shapes the design. We iterate together — adjusting materials, furniture, lighting, and finishes — until every detail feels perfectly right and you are fully satisfied.</p>
                        <div class="hiw-tags">
                            <span class="hiw-tag">Unlimited Feedback</span>
                            <span class="hiw-tag">Collaborative</span>
                            <span class="hiw-tag">Your Approval First</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="hiw-step hiw-step--last" data-aos="fade-up" data-aos-delay="320">
                <div class="hiw-number-col">
                    <span class="hiw-number">05</span>
                </div>
                <div class="hiw-body" style="border-bottom:none;">
                    <div class="hiw-icon-wrap" style="background:var(--sand);border-color:var(--sand);">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2C2C2C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <path d="M22 4L12 14.01l-3-3"/>
                        </svg>
                    </div>
                    <div class="hiw-text">
                        <h3 class="hiw-title" style="color:var(--sand-light);">Final Delivery</h3>
                        <p class="hiw-desc">You receive a complete, production-ready design package — technical drawings, material specifications, supplier lists, and all 3D assets — ready to hand to your contractor.</p>
                        <div class="hiw-tags">
                            <span class="hiw-tag">Full Design Package</span>
                            <span class="hiw-tag">Source Files</span>
                            <span class="hiw-tag">Post-Project Support</span>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /hiw-steps -->

        <!-- CTA -->
        <div class="text-center mt-20" data-aos="fade-up">
            <a href="#contact" class="btn-primary" style="border:1px solid rgba(201,169,110,0.4);background:transparent;color:var(--sand-light);">
                <span>Start Your Project</span>
            </a>
        </div>

    </div>
</section>

<style>
.hiw-steps { display:flex; flex-direction:column; }
.hiw-step { display:grid; grid-template-columns:90px 1fr; gap:0 2.5rem; border:none !important; }
.hiw-step--last .hiw-body { border:none !important; }
.hiw-number-col { display:flex; flex-direction:column; align-items:center; padding-top:4px; }
.hiw-number {
    font-family:'Cormorant Garamond',serif;
    font-size:3.8rem; font-weight:300; line-height:1;
    color:rgba(255,255,255,0.06); letter-spacing:-0.03em;
    flex-shrink:0; transition:color 0.4s;
}
.hiw-step:hover .hiw-number { color:rgba(201,169,110,0.18); }
.hiw-line {
    flex:1; width:1px; min-height:40px; margin:10px 0;
    background:linear-gradient(to bottom,rgba(201,169,110,0.22),rgba(201,169,110,0.03));
}
.hiw-body {
    display:flex; gap:1.75rem;
    padding:0.25rem 0 3.5rem;
    border-bottom:none !important;
    transition:border-color 0.4s;
}
.hiw-step:hover .hiw-body { border-color:rgba(201,169,110,0.14); }
.hiw-icon-wrap {
    flex-shrink:0; width:56px; height:56px;
    border:1px solid rgba(201,169,110,0.22);
    background:rgba(201,169,110,0.05);
    display:flex; align-items:center; justify-content:center;
    transition:all 0.4s ease;
}
.hiw-step:hover .hiw-icon-wrap { background:rgba(201,169,110,0.1); border-color:rgba(201,169,110,0.45); }
.hiw-text { flex:1; }
.hiw-title {
    font-family:'Cormorant Garamond',serif;
    font-size:1.55rem; font-weight:400; color:white;
    margin-bottom:0.7rem; line-height:1.2; transition:color 0.3s;
}
.hiw-step:hover .hiw-title { color:var(--sand-light); }
.hiw-desc {
    font-size:0.88rem; line-height:1.9;
    color:rgba(255,255,255,0.4); max-width:560px; margin-bottom:1.2rem;
}
.hiw-tags { display:flex; flex-wrap:wrap; gap:8px; }
.hiw-tag {
    font-size:0.65rem; letter-spacing:0.12em; text-transform:uppercase;
    color:rgba(201,169,110,0.65);
    border:1px solid rgba(201,169,110,0.16); padding:4px 12px;
    transition:all 0.3s;
}
.hiw-step:hover .hiw-tag { border-color:rgba(201,169,110,0.32); color:var(--sand-light); }
@media (max-width:640px) {
    .hiw-step { grid-template-columns:54px 1fr; gap:0 1.25rem; }
    .hiw-number { font-size:2.6rem; }
    .hiw-body { flex-direction:column; gap:1rem; padding-bottom:2.5rem; }
    .hiw-icon-wrap { width:48px; height:48px; }
    .hiw-title { font-size:1.3rem; }
}
</style>

<!-- TESTIMONIALS SECTION -->
<section id="testimonials" style="background:var(--cream);padding:6rem 0;">
    <div class="max-w-7xl mx-auto px-6">
        <!-- Header -->
        <div class="text-center mb-16" data-aos="fade-up">
            <p class="section-eyebrow justify-center mb-4">Client Stories</p>
            <h2 class="section-title mb-4" style="font-weight:600;">What Clients <em>Say</em></h2>
            <p style="font-size:0.9rem;color:var(--charcoal-light);max-width:500px;margin:0 auto;">Real experiences from real clients — each space tells a story of collaboration and transformation.</p>
        </div>

        <?php if(!empty($testimonials)): ?>
        <!-- Swiper Testimonials -->
        <div class="swiper testimonialSwiper" data-aos="fade-up" data-aos-delay="200">
            <div class="swiper-wrapper pt-5 pb-12">
                <?php foreach($testimonials as $t): ?>
                <div class="swiper-slide h-auto">
                    <div class="testimonial-card h-full">
                        <!-- Stars -->
                        <div class="flex items-center gap-1 mb-5">
                            <?php
                            $rating = floatval($t['rating']);
                            $full = floor($rating);
                            $half = ($rating - $full) >= 0.3 && ($rating - $full) < 0.8 ? 1 : 0;
                            $empty = 5 - $full - $half;
                            for($s=0;$s<$full;$s++): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#C9A96E"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            <?php endfor; if($half): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24"><defs><linearGradient id="hg<?= $t['id'] ?>"><stop offset="50%" stop-color="#C9A96E"/><stop offset="50%" stop-color="#D1C4B0"/></linearGradient></defs><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="url(#hg<?= $t['id'] ?>)"/></svg>
                            <?php endif; for($s=0;$s<$empty;$s++): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#D1C4B0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            <?php endfor; ?>
                            <span style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--sand-dark);margin-left:6px;"><?= number_format($rating,1) ?></span>
                        </div>

                        <!-- Review text -->
                        <p style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);position:relative;z-index:1;margin-bottom:1.5rem;">
                            "<?= htmlspecialchars($t['review_text']) ?>"
                        </p>

                        <!-- Divider -->
                        <div style="width:40px;height:1px;background:var(--sand-light);margin-bottom:1.5rem;"></div>

                        <!-- Client info -->
                        <div class="flex items-center gap-4">
                            <?php if($t['client_image']): ?>
                            <img src="<?= htmlspecialchars($t['client_image']) ?>" alt="<?= htmlspecialchars($t['client_name']) ?>" class="testimonial-avatar">
                            <?php else: ?>
                            <div style="width:56px;height:56px;border-radius:50%;background:var(--cream-dark);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <span style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--sand-dark);"><?= strtoupper(substr($t['client_name'],0,1)) ?></span>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h4 style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--charcoal);"><?= htmlspecialchars($t['client_name']) ?></h4>
                                <?php if($t['country']): ?>
                                <p style="font-size:0.75rem;color:var(--sage);letter-spacing:0.08em;"><?= htmlspecialchars($t['country']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="ml-auto">
                                <?php $platform = strtolower($t['platform'] ?? 'upwork'); ?>
                                <span class="platform-badge badge-<?= $platform ?>">
                                    <?= ucfirst($platform) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
        <?php else: ?>
        <!-- Sample testimonials if none in DB -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php $samples = [
                ['Sarah M.','United States',5.0,'Moeed transformed our living room into an absolute masterpiece. His attention to detail and understanding of our vision was incredible. Highly recommended!','upwork'],
                ['James T.','United Kingdom',4.5,'Exceptional work on our commercial office redesign. Professional, creative, and delivered on time. The space now feels modern and welcoming.','upwork'],
                ['Aisha R.','UAE',5.0,'Working with A. Moeed was a dream. He brought creativity that exceeded all expectations. Our villa now looks like it belongs in a luxury magazine!','fiverr'],
            ]; ?>
            <?php foreach($samples as $i => $s): ?>
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="<?= $i*150 ?>">
                <div class="flex items-center gap-1 mb-5">
                    <?php $full=floor($s[2]); for($x=0;$x<$full;$x++): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#C9A96E"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <?php endfor; ?>
                    <span style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--sand-dark);margin-left:6px;"><?= $s[2] ?>.0</span>
                </div>
                <p style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);position:relative;z-index:1;margin-bottom:1.5rem;">"<?= $s[3] ?>"</p>
                <div style="width:40px;height:1px;background:var(--sand-light);margin-bottom:1.5rem;"></div>
                <div class="flex items-center gap-4">
                    <div style="width:56px;height:56px;border-radius:50%;background:var(--cream-dark);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--sand-dark);"><?= $s[0][0] ?></span>
                    </div>
                    <div>
                        <h4 style="font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--charcoal);"><?= $s[0] ?></h4>
                        <p style="font-size:0.75rem;color:var(--sage);letter-spacing:0.08em;"><?= $s[1] ?></p>
                    </div>
                    <div class="ml-auto">
                        <span class="platform-badge badge-<?= $s[4] ?>"><?= ucfirst($s[4]) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>


<!-- CONTACT SECTION -->
<!-- <section id="contact" style="background:var(--cream);padding:6rem 0;"> -->
<section id="contact" style="background:white;padding:6rem 0;">

    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-20">
            <!-- Left: Info -->
            <div data-aos="fade-right">
                <p class="section-eyebrow mb-4">Get In Touch</p>
                <h2 class="section-title mb-6" style="font-weight:600;">Let's Create <em>Together</em></h2>
                <div style="width:50px;height:2px;background:linear-gradient(90deg,var(--sand),transparent);margin-bottom:2rem;"></div>
                <p style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);margin-bottom:3rem;">
                    Have a project in mind? I'd love to hear about your vision and explore how we can transform your space into something extraordinary.
                </p>

                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div style="width:44px;height:44px;border:1px solid var(--sand-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--sand-dark)" stroke-width="1.5"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-bottom:2px;">Email</p>
                            <a href="mailto:<?= getSetting('email') ?>" style="font-size:0.95rem;color:var(--charcoal);text-decoration:none;"><?= getSetting('email') ?></a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div style="width:44px;height:44px;border:1px solid var(--sand-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--sand-dark)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 
                                    19.79 19.79 0 0 1-8.63-3.07 
                                    19.5 19.5 0 0 1-6-6 
                                    19.79 19.79 0 0 1-3.07-8.67 
                                    A2 2 0 0 1 4.11 2h3 
                                    a2 2 0 0 1 2 1.72 
                                    12.84 12.84 0 0 0 .7 2.81 
                                    2 2 0 0 1-.45 2.11L8.09 9.91 
                                    a16 16 0 0 0 6 6l1.27-1.27 
                                    a2 2 0 0 1 2.11-.45 
                                    12.84 12.84 0 0 0 2.81.7 
                                    A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </div>
                        <div>
                            <p style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-bottom:2px;">Phone</p>
                            <a href="tel:<?= getSetting('phone') ?>" style="font-size:0.95rem;color:var(--charcoal);text-decoration:none;"><?= getSetting('phone') ?></a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div style="width:44px;height:44px;border:1px solid var(--sand-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--sand-dark)" stroke-width="1.5"><path d="M21 2H3v16h5l4 4 4-4h5V2zm-7 9H7m10-4H7"/></svg>
                        </div>
                        <div>
                            <p style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);margin-bottom:2px;">Platforms</p>
                            <div class="flex gap-3">
                                <a href="<?= getSetting('upwork_url') ?>" target="_blank" style="font-size:0.9rem;color:var(--charcoal);text-decoration:none;">Upwork</a>
                                <span style="color:var(--sage);">·</span>
                                <a href="<?= getSetting('fiverr_url') ?>" target="_blank" style="font-size:0.9rem;color:var(--charcoal);text-decoration:none;">Fiverr</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Form -->
            <div data-aos="fade-left">
                <form method="POST" class="space-y-5" id="contact-form">
                    <!-- Hidden field ensures contact_submit is always present in FormData via JS fetch -->
                    <input type="hidden" name="contact_submit" value="1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);display:block;margin-bottom:8px;">Your Name</label>
                            <input type="text" name="name" required placeholder="Xythonix" class="contact-input" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>
                        <div>
                            <label style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);display:block;margin-bottom:8px;">Email Address</label>
                            <input type="email" name="email" required placeholder="xythonix@example.com" class="contact-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);display:block;margin-bottom:8px;">Subject</label>
                        <input type="text" name="subject" required placeholder="Project Inquiry — Living Room Design" class="contact-input" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                    </div>
                    <div>
                        <label style="font-size:0.7rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--sage);display:block;margin-bottom:8px;">Message</label>
                        <textarea name="message" required rows="6" placeholder="Tell me about your project, space dimensions, style preferences, and budget..." class="contact-input" style="resize:vertical;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full text-center" style="display:block;">
                        <span>Send Message</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
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
                    <a href="#hero" class="footer-link">Home</a>
                    <a href="#about" class="footer-link">About</a>
                    <a href="#portfolio" class="footer-link">Portfolio</a>
                    <a href="#testimonials" class="footer-link">Reviews</a>
                    <a href="#contact" class="footer-link">Contact</a>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.7rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand-light);margin-bottom:1.25rem;">Find Me Online</h4>
                <div class="flex flex-col gap-2">
                    <a href="<?= getSetting('upwork_url') ?>" target="_blank" class="footer-link">Upwork Profile</a>
                    <a href="<?= getSetting('fiverr_url') ?>" target="_blank" class="footer-link">Fiverr Gigs</a>
                    <a href="mailto:<?= getSetting('email') ?>" class="footer-link"><?= getSetting('email') ?></a>
                </div>
            </div>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:2rem;display:flex;flex-direction:column;gap:0.5rem;text-align:center;" class="sm:flex-row sm:justify-between sm:text-left sm:items-center">
            <p style="font-size:0.78rem;color:rgba(255,255,255,0.3);">© <?= date('Y') ?> A. Moeed · MyDesignAssistants. All rights reserved.</p>
            <!-- <p style="font-size:0.78rem;color:rgba(255,255,255,0.3);">mydesignassistants.com</p> -->
        </div>
    </div>
</footer>

<script>
// Loader — bulletproof: always hides no matter what
function hideLoader() {
    var loader = document.getElementById('loader');
    if (loader) {
        loader.style.opacity = '0';
        loader.style.visibility = 'hidden';
        loader.style.pointerEvents = 'none';
        loader.classList.add('hidden');
    }
}
// Method 1: on full page load
window.addEventListener('load', function() { setTimeout(hideLoader, 1500); });
// Method 2: DOMContentLoaded fallback
document.addEventListener('DOMContentLoaded', function() { setTimeout(hideLoader, 2500); });
// Method 3: Hard timeout — ALWAYS hides after 3s no matter what
setTimeout(hideLoader, 3000);

// AOS
AOS.init({ once: true, offset: 60, duration: 700 });

// Smooth scroll — slow start, then fast (ease-in style)
function easedScrollTo(targetY, duration) {
    var startY = window.scrollY;
    var diff = targetY - startY;
    var startTime = null;

    // Custom easing: cubic-ease-in (slow start, accelerates)
    function easeIn(t) {
        return t * t * t;
    }

    function step(currentTime) {
        if (!startTime) startTime = currentTime;
        var elapsed = currentTime - startTime;
        var progress = Math.min(elapsed / duration, 1);
        window.scrollTo(0, startY + diff * easeIn(progress));
        if (elapsed < duration) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

// Intercept all anchor links pointing to sections
document.querySelectorAll('a[href^="#"]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        var targetId = this.getAttribute('href').slice(1);
        var target = document.getElementById(targetId);
        if (!target) return;
        e.preventDefault();
        var offsetTop = target.getBoundingClientRect().top + window.scrollY - 70;
        easedScrollTo(offsetTop, 500); // 0.5s total: slow for 1s then snaps fast
    });
});

// Navbar scroll
window.addEventListener('scroll', () => {
    const nav = document.getElementById('navbar');
    if (window.scrollY > 80) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
});

// Custom cursor — smooth with requestAnimationFrame, zero setTimeout lag
(function() {
    const dot    = document.getElementById('cursor');
    const ring   = document.getElementById('cursor-follower');
    if (!dot || !ring) return;

    let mouseX = window.innerWidth / 2,  mouseY = window.innerHeight / 2;
    let ringX  = mouseX, ringY = mouseY;

    // Track raw mouse instantly
    document.addEventListener('mousemove', function(e) {
        mouseX = e.clientX;
        mouseY = e.clientY;
    }, { passive: true });

    // rAF loop — dot snaps instantly, ring lerps smoothly behind
    function loop() {
        // Dot: instant (no lag)
        dot.style.transform = 'translate(' + (mouseX - 4) + 'px, ' + (mouseY - 4) + 'px)';

        // Ring: lerp towards mouse (0.12 = smooth follow speed)
        ringX += (mouseX - ringX) * 0.12;
        ringY += (mouseY - ringY) * 0.12;
        ring.style.transform = 'translate(' + (ringX - 14) + 'px, ' + (ringY - 14) + 'px)';

        requestAnimationFrame(loop);
    }
    loop();
})();

// Mobile menu
function toggleMobileMenu() {
    document.getElementById('mobile-menu').classList.toggle('open');
}
function closeMobileMenu() {
    document.getElementById('mobile-menu').classList.remove('open');
}

// Lightbox
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}
document.getElementById('lightbox-close').addEventListener('click', () => {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
});
document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Project Modal
function openProject(id) {
    fetch(`/api/project.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.id) return;
            const images = data.images ? JSON.parse(data.images) : [];
            const features = data.features ? data.features.split('\n').filter(f => f.trim()) : [];
            const software = data.software_used ? data.software_used.split(',').map(s => s.trim()) : [];

            let imagesHTML = '';
            if (images.length > 0) {
                imagesHTML = `<div class="grid grid-cols-2 gap-2 p-6" style="background:var(--cream);">
                    ${images.map(img => `<img src="${img}" alt="${data.title}" onclick="openLightbox('${img}')" style="width:100%;height:200px;object-fit:cover;cursor:zoom-in;transition:opacity 0.3s;" onmouseover="this.style.opacity=0.85" onmouseout="this.style.opacity=1">`).join('')}
                </div>`;
            }

            document.getElementById('modal-inner').innerHTML = `
                <div style="position:relative;">
                    <button onclick="closeProject()" style="position:absolute;top:1rem;right:1rem;z-index:10;width:40px;height:40px;background:var(--charcoal);color:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;">✕</button>
                    ${data.thumbnail ? `<img src="${data.thumbnail}" alt="${data.title}" style="width:100%;height:380px;object-fit:cover;">` : ''}
                    <div style="padding:2.5rem;">
                        <p style="font-size:0.65rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--sand);margin-bottom:0.5rem;">${data.category || 'Interior Design'}</p>
                        <h2 style="font-family:'Cormorant Garamond',serif;font-size:2.2rem;font-weight:400;color:var(--charcoal);margin-bottom:1.5rem;">${data.title}</h2>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem;">
                            ${software.map(s => `<span style="padding:4px 12px;background:var(--cream-dark);font-size:0.75rem;color:var(--charcoal-light);">${s}</span>`).join('')}
                        </div>
                        <div style="font-size:0.95rem;line-height:1.9;color:var(--charcoal-light);margin-bottom:2rem;">${data.description || ''}</div>
                        ${features.length ? `<div style="margin-bottom:1.5rem;"><h4 style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;margin-bottom:1rem;">Key Features</h4>${features.map(f => `<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;"><span style="color:var(--sand);margin-top:2px;">◆</span><span style="font-size:0.88rem;color:var(--charcoal-light);">${f}</span></div>`).join('')}</div>` : ''}
                    </div>
                    ${imagesHTML}
                </div>`;

            document.getElementById('project-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        });
}
function closeProject() {
    document.getElementById('project-modal').classList.remove('active');
    document.body.style.overflow = '';
}
document.getElementById('modal-backdrop').addEventListener('click', closeProject);

// Testimonials Swiper
<?php if(!empty($testimonials)): ?>
new Swiper('.testimonialSwiper', {
    slidesPerView: 1,
    spaceBetween: 24,
    pagination: { el: '.swiper-pagination', clickable: true },
    breakpoints: {
        768: { slidesPerView: 2 },
        1024: { slidesPerView: 3 }
    }
});
<?php endif; ?>

// Hero image loaded

// ── Contact form — AJAX, no page refresh ────────────────────────────────────
document.getElementById('contact-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const btn  = form.querySelector('button[type="submit"]');
    const originalHTML = btn.innerHTML;

    // Loading state
    btn.disabled = true;
    btn.innerHTML = '<span style="opacity:0.6;">Sending...</span>';

    fetch(window.location.href, {
        method: 'POST',
        body: new FormData(form),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            form.reset();
            Swal.fire({
                title: 'Message Sent!',
                html: 'Thank you for reaching out.<br>I\'ll get back to you within <strong>24 hours</strong>.',
                icon: 'success',
                confirmButtonText: 'Sounds Good',
                customClass: {
                    popup:         'swal-portfolio-popup',
                    title:         'swal-portfolio-title',
                    htmlContainer: 'swal-portfolio-body',
                    confirmButton: 'swal-portfolio-btn',
                },
                buttonsStyling: false,
            });
        } else {
            Swal.fire({
                title: 'Oops!',
                html: data.error || 'Please fill all fields correctly.',
                icon: 'error',
                confirmButtonText: 'Try Again',
                customClass: {
                    popup:         'swal-portfolio-popup',
                    title:         'swal-portfolio-title',
                    htmlContainer: 'swal-portfolio-body',
                    confirmButton: 'swal-portfolio-btn swal-portfolio-btn--error',
                },
                buttonsStyling: false,
            });
        }
    })
    .catch(() => {
        Swal.fire({
            title: 'Connection Error',
            html: 'Something went wrong. Please try again.',
            icon: 'error',
            confirmButtonText: 'OK',
            customClass: {
                popup:         'swal-portfolio-popup',
                title:         'swal-portfolio-title',
                htmlContainer: 'swal-portfolio-body',
                confirmButton: 'swal-portfolio-btn swal-portfolio-btn--error',
            },
            buttonsStyling: false,
        });
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
});
</script>

<!-- WhatsApp Fixed Button -->
<style>
.wa-wrapper {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 9990;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

/* Tooltip bubble */
.wa-tooltip {
    background: white;
    color: var(--charcoal);
    font-family: 'Jost', sans-serif;
    font-size: 0.8rem;
    font-weight: 500;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    white-space: nowrap;
    opacity: 0;
    transform: translateX(10px);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    pointer-events: none;
    position: relative;
}
.wa-tooltip::after {
    content: '';
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-right: none;
    border-left-color: white;
}
.wa-wrapper:hover .wa-tooltip {
    opacity: 1;
    transform: translateX(0);
}

/* Main button */
.wa-btn {
    position: relative;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: #25D366;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 24px rgba(37,211,102,0.45);
    text-decoration: none;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
    animation: wa-float 3s ease-in-out infinite;
}
.wa-btn:hover {
    transform: scale(1.12);
    box-shadow: 0 10px 32px rgba(37,211,102,0.6);
    animation-play-state: paused;
}
.wa-btn svg {
    width: 30px;
    height: 30px;
    fill: white;
}

/* Pulse ring */
.wa-btn::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid rgba(37,211,102,0.4);
    animation: wa-pulse 2.5s ease-out infinite;
}
.wa-btn::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid rgba(37,211,102,0.2);
    animation: wa-pulse 2.5s ease-out infinite 0.6s;
}

/* Notification dot */
.wa-dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    background: transparent;
    border-radius: 50%;
    border: none;
    animation: wa-dot-blink 2s ease-in-out infinite;
}

@keyframes wa-float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-6px); }
}
@keyframes wa-pulse {
    0%   { transform: scale(1); opacity: 1; }
    100% { transform: scale(1.7); opacity: 0; }
}
@keyframes wa-dot-blink {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.3; }
}
</style>

<div class="wa-wrapper">
    <div class="wa-tooltip">Chat on WhatsApp</div>
    <a href="https://wa.me/923064879877?text=Hi%20A.%20Moeed!%20I%20saw%20your%20portfolio%20and%20would%20like%20to%20discuss%20a%20project."
       target="_blank"
       rel="noopener noreferrer"
       class="wa-btn"
       aria-label="Chat with A. Moeed on WhatsApp">
        <div class="wa-dot"></div>
        <!-- Official WhatsApp SVG icon -->
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M16.004 0C7.164 0 0 7.163 0 16c0 2.822.737 5.469 2.027 7.77L0 32l8.463-2.007A15.93 15.93 0 0016.004 32C24.837 32 32 24.837 32 16S24.837 0 16.004 0zm0 29.23a13.19 13.19 0 01-6.72-1.832l-.482-.286-4.996 1.185 1.23-4.858-.315-.5A13.157 13.157 0 012.77 16c0-7.294 5.94-13.23 13.234-13.23S29.23 8.706 29.23 16 23.296 29.23 16.004 29.23zm7.254-9.918c-.397-.199-2.35-1.16-2.715-1.292-.364-.133-.63-.199-.895.199-.265.397-1.028 1.292-1.26 1.558-.232.265-.464.298-.86.1-.398-.2-1.678-.619-3.197-1.973-1.181-1.054-1.978-2.355-2.21-2.752-.232-.397-.025-.612.175-.81.18-.177.397-.464.596-.696.199-.232.265-.397.397-.662.133-.265.066-.497-.033-.696-.1-.2-.895-2.156-1.226-2.952-.322-.774-.65-.669-.895-.681l-.762-.013c-.265 0-.695.1-1.06.497-.364.397-1.392 1.358-1.392 3.314s1.425 3.844 1.624 4.11c.199.264 2.804 4.28 6.793 6.003.95.41 1.691.655 2.269.839.953.303 1.82.26 2.505.158.764-.114 2.35-.96 2.682-1.888.332-.927.332-1.723.232-1.888-.099-.166-.364-.265-.762-.464z"/>
        </svg>
    </a>
</div>
</body>
</html>