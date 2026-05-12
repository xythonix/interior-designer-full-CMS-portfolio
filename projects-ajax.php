<?php
/**
 * projects-ajax.php
 * AJAX endpoint — returns JSON with rendered HTML for the projects grid and pagination.
 * Called by projects.php via fetch(); never visited directly by users.
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Input ────────────────────────────────────────────────────────────────────
$perPage        = 9;
$currentPage    = max(1, intval($_GET['page'] ?? 1));
$categoryFilter = trim($_GET['category'] ?? '');

// ── Count ────────────────────────────────────────────────────────────────────
if ($categoryFilter) {
    $countStmt = db()->prepare("SELECT COUNT(*) FROM projects WHERE category = ?");
    $countStmt->execute([$categoryFilter]);
} else {
    $countStmt = db()->query("SELECT COUNT(*) FROM projects");
}
$totalProjects = (int) $countStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalProjects / $perPage));
$currentPage   = min($currentPage, $totalPages);
$offset        = ($currentPage - 1) * $perPage;

// ── Fetch projects ────────────────────────────────────────────────────────────
if ($categoryFilter) {
    $stmt = db()->prepare(
        "SELECT * FROM projects WHERE category = ?
         ORDER BY is_featured DESC, sort_order ASC, created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$categoryFilter, $perPage, $offset]);
} else {
    $stmt = db()->prepare(
        "SELECT * FROM projects
         ORDER BY is_featured DESC, sort_order ASC, created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$perPage, $offset]);
}
$projects = $stmt->fetchAll();

// ── Render grid HTML ──────────────────────────────────────────────────────────
ob_start();
if (empty($projects)): ?>
<div class="empty-state">
    <div style="width:72px;height:72px;border:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:center;margin:0 auto 2rem;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--sand)" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 10.5L12 3l9 7.5"/>
            <path d="M5 8.8V20a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1V8.8"/>
        </svg>
    </div>
    <p class="section-eyebrow justify-center mb-4">No Projects Found</p>
    <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:300;color:var(--charcoal);margin-bottom:1rem;">Nothing here yet</h3>
    <p style="font-size:0.9rem;color:var(--charcoal-light);margin-bottom:2rem;">
        <?= $categoryFilter ? 'No projects in this category. Try another filter.' : 'Projects will appear here once added.' ?>
    </p>
    <?php if ($categoryFilter): ?>
    <button class="btn-outline" onclick="loadProjects('', 1)">View All Projects</button>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-7" id="projects-grid">
    <?php foreach ($projects as $i => $project):
        $images = json_decode($project['images'] ?? '[]', true);
        $thumb  = $project['thumbnail'] ?? ($images[0] ?? '');
    ?>
    <div class="portfolio-item"
         style="animation: fadeInUp 0.45s ease both; animation-delay: <?= ($i % 3) * 80 ?>ms;"
         onclick="window.location.href='project-detail.php?id=<?= $project['id'] ?>'">

        <div class="portfolio-img-wrap">
            <?php if ($thumb): ?>
            <img src="<?= htmlspecialchars($thumb) ?>"
                 alt="<?= htmlspecialchars($project['title']) ?>"
                 loading="lazy">
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                <span style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;color:var(--sage);">Interior Design</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="portfolio-info">
            <span class="portfolio-info-category"><?= htmlspecialchars($project['category'] ?? 'Interior Design') ?></span>
            <h3 class="portfolio-info-title"><?= htmlspecialchars($project['title']) ?></h3>
            <?php if (!empty($project['software_used'])): ?>
            <span class="portfolio-info-software"><?= htmlspecialchars($project['software_used']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
$gridHtml = ob_get_clean();

// ── Render pagination HTML ────────────────────────────────────────────────────
ob_start();
if ($totalPages > 1 && !empty($projects)):
    $window = 2;
    $start  = max(1, $currentPage - $window);
    $end    = min($totalPages, $currentPage + $window);
    $cat    = $categoryFilter; // shorthand for JS calls
?>
<nav class="pagination" aria-label="Projects pagination">

    <!-- Prev -->
    <?php if ($currentPage > 1): ?>
    <button class="page-btn" onclick="loadProjects('<?= addslashes($cat) ?>', <?= $currentPage - 1 ?>)" aria-label="Previous page">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 18l-6-6 6-6"/></svg></span>
    </button>
    <?php else: ?>
    <span class="page-btn disabled" aria-disabled="true">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 18l-6-6 6-6"/></svg></span>
    </span>
    <?php endif; ?>

    <?php if ($start > 1): ?>
        <button class="page-btn" onclick="loadProjects('<?= addslashes($cat) ?>', 1)"><span>1</span></button>
        <?php if ($start > 2): ?><span class="page-ellipsis">···</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($p = $start; $p <= $end; $p++): ?>
        <?php if ($p === $currentPage): ?>
        <span class="page-btn current" aria-current="page"><span><?= $p ?></span></span>
        <?php else: ?>
        <button class="page-btn" onclick="loadProjects('<?= addslashes($cat) ?>', <?= $p ?>)"><span><?= $p ?></span></button>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?><span class="page-ellipsis">···</span><?php endif; ?>
        <button class="page-btn" onclick="loadProjects('<?= addslashes($cat) ?>', <?= $totalPages ?>)"><span><?= $totalPages ?></span></button>
    <?php endif; ?>

    <!-- Next -->
    <?php if ($currentPage < $totalPages): ?>
    <button class="page-btn" onclick="loadProjects('<?= addslashes($cat) ?>', <?= $currentPage + 1 ?>)" aria-label="Next page">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18l6-6-6-6"/></svg></span>
    </button>
    <?php else: ?>
    <span class="page-btn disabled" aria-disabled="true">
        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18l6-6-6-6"/></svg></span>
    </span>
    <?php endif; ?>

</nav>
<p class="page-info">
    Page <?= $currentPage ?> of <?= $totalPages ?>
    &nbsp;·&nbsp;
    <?= $totalProjects ?> <?= $totalProjects === 1 ? 'project' : 'projects' ?> total
</p>
<?php endif; ?>
<?php
$paginationHtml = ob_get_clean();

// ── JSON response ─────────────────────────────────────────────────────────────
echo json_encode([
    'grid'          => $gridHtml,
    'pagination'    => $paginationHtml,
    'totalProjects' => $totalProjects,
    'currentPage'   => $currentPage,
    'totalPages'    => $totalPages,
]);