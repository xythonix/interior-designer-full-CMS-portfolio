<?php
require_once __DIR__ . '/../config.php';

// Cache headers
header('Cache-Control: public, max-age=300');
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = db()->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode($project);