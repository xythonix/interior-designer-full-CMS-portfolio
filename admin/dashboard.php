<?php
$pageTitle = 'Dashboard';
include '_header.php';

// Stats
$projectCount = db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$testiCount = db()->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
$msgCount = db()->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$unreadMsg = db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
$importantMsg = db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_important=1")->fetchColumn();

// Recent messages
$recentMsgs = db()->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Monthly message chart data
$chartData = db()->query("
    SELECT DATE_FORMAT(created_at,'%b') as month, COUNT(*) as cnt 
    FROM contact_messages 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY YEAR(created_at), MONTH(created_at) 
    ORDER BY created_at ASC
")->fetchAll();
?>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php $stats = [
        ['Projects', $projectCount, '#2C2C2C', 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
        ['Testimonials', $testiCount, '#C9A96E', 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
        ['Total Messages', $msgCount, '#7A8C7E', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['Unread', $unreadMsg, '#C17B5C', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
    ]; ?>
    <?php foreach($stats as $i => $stat): ?>
    <div class="admin-card" style="<?= $i === 0 ? 'border-left:0px solid '.$stat[2] : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.75rem;">
            <div style="width:36px;height:36px;border-radius:6px;background:<?= $stat[2] ?>18;display:flex;align-items:center;justify-content:center;">
                <svg width="18" height="18" fill="none" stroke="<?= $stat[2] ?>" stroke-width="1.5" viewBox="0 0 24 24"><path d="<?= $stat[3] ?>"/></svg>
            </div>
        </div>
        <div style="font-family:'Cormorant Garamond',serif;font-size:2.5rem;font-weight:600;color:<?= $stat[2] ?>;line-height:1;"><?= $stat[1] ?></div>
        <div style="font-size:0.72rem;letter-spacing:0.12em;text-transform:uppercase;color:#9A9080;margin-top:4px;"><?= $stat[0] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Chart -->
    <div class="admin-card lg:col-span-2">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:400;">Message Activity</h3>
            <span style="font-size:0.7rem;color:var(--sage);">Last 6 months</span>
        </div>
        <canvas id="msgChart" height="140"></canvas>
    </div>

    <!-- Quick Links -->
    <div class="admin-card">
        <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:400;margin-bottom:1.25rem;">Quick Actions</h3>
        <div class="space-y-3">
            <a href="projects.php?action=new" class="btn-sand w-full text-center block" style="display:block;text-align:center;">+ Add New Project</a>
            <a href="testimonials.php?action=new" class="btn-ghost w-full text-center block" style="display:block;text-align:center;">+ Add Testimonial</a>
            <a href="messages.php" class="btn-ghost w-full text-center block" style="display:block;text-align:center;">View Messages <?php if($unreadMsg > 0): ?>(<?= $unreadMsg ?>)<?php endif; ?></a>
            <a href="<?= SITE_URL ?>" target="_blank" class="btn-ghost w-full text-center block" style="display:block;text-align:center;">Preview Portfolio →</a>
        </div>
    </div>
</div>

<!-- Recent Messages -->
<div class="admin-card mt-6">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
        <h3 style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:400;">Recent Messages</h3>
        <a href="messages.php" style="font-size:0.78rem;color:var(--sand-dark);text-decoration:none;">View All →</a>
    </div>
    <?php if(empty($recentMsgs)): ?>
    <p style="color:#B5A898;font-size:0.875rem;text-align:center;padding:2rem;">No messages yet.</p>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($recentMsgs as $msg): ?>
            <tr>
                <td>
                    <div style="font-weight:500;"><?= htmlspecialchars($msg['name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--sage);"><?= htmlspecialchars($msg['email']) ?></div>
                </td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($msg['subject']) ?></td>
                <td style="font-size:0.8rem;color:var(--sage);"><?= date('d M', strtotime($msg['created_at'])) ?></td>
                <td>
                    <?php if(!$msg['is_read']): ?>
                    <span style="display:inline-block;padding:2px 8px;background:#EFF7ED;color:#4A6B50;font-size:0.65rem;letter-spacing:0.1em;text-transform:uppercase;">New</span>
                    <?php endif; ?>
                    <?php if($msg['is_important']): ?>
                    <span style="color:#C9A96E;">★</span>
                    <?php endif; ?>
                </td>
                <td><a href="<?= SITE_URL ?>/admin/messages.php?view=<?= $msg['id'] ?>" style="font-size:0.78rem;color:var(--sand-dark);text-decoration:none;">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
// Message chart
const ctx = document.getElementById('msgChart').getContext('2d');
const chartLabels = <?= json_encode(array_column($chartData,'month')) ?>;
const chartValues = <?= json_encode(array_column($chartData,'cnt')) ?>;

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartLabels.length ? chartLabels : ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
            label: 'Messages',
            data: chartValues.length ? chartValues : [0,0,0,0,0,0],
            backgroundColor: 'rgba(201,169,110,0.2)',
            borderColor: '#C9A96E',
            borderWidth: 1.5,
            borderRadius: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Jost' } }, grid: { color: '#F3EDE3' } },
            x: { ticks: { font: { family: 'Jost' } }, grid: { display: false } }
        }
    }
});
</script>

<?php include '_footer.php'; ?>
