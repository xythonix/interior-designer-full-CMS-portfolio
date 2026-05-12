<?php
require_once 'config.php';
header('Content-Type: application/xml; charset=UTF-8');

$projects = db()->query("SELECT slug, updated_at FROM projects ORDER BY updated_at DESC")->fetchAll();
$today = date('Y-m-d');
?>
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://mydesignassistants.com/</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <?php foreach($projects as $p): ?>
    <url>
        <loc>https://mydesignassistants.com/portfolio/<?= htmlspecialchars($p['slug']) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($p['updated_at'])) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>
</urlset>
