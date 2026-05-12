<?php
// ============================================================
// DATABASE CONFIGURATION — MyDesignAssistants
// Edit DB_USER and DB_PASS to match your MySQL setup
// XAMPP defaults: root / (empty password)
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mydesignassistants');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// For localhost: use http://localhost/portfolio
// For production: use https://mydesignassistants.com
define('SITE_URL', 'http://127.0.0.1/portfolio');

// Windows-safe upload paths
define('UPLOAD_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', '/portfolio/uploads/');

define('SECRET_KEY', 'mda_secret_2024_xK9pL2mN8qR5');

// ============================================================
// DATABASE — Auto-creates DB and tables if missing
// ============================================================
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // First try to connect WITH the database name
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // DB might not exist yet — try creating it
            $this->autoSetup($e->getMessage());
        }
    }

    private function autoSetup($originalError) {
        try {
            // Connect without DB name to create it
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Now connect to the new DB
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Import schema
            $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                // Split by semicolon and run each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        try { $this->pdo->exec($stmt); } catch (PDOException $ignore) {}
                    }
                }
            }
        } catch (PDOException $e2) {
            $this->showDbError($originalError, $e2->getMessage());
        }
    }

    private function showDbError($err1, $err2 = '') {
        http_response_code(500);
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Database Setup</title>
<style>
*{box-sizing:border-box}
body{font-family:Georgia,serif;background:#F5F0E8;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;padding:1rem}
.card{background:#fff;max-width:640px;width:100%;padding:2.5rem;border-left:4px solid #C9A96E;box-shadow:0 4px 30px rgba(0,0,0,.08)}
h1{font-size:1.5rem;color:#2C2C2C;margin:0 0 1rem}
p{color:#555;line-height:1.8;margin:.5rem 0}
.step{background:#FDFAF5;border-left:3px solid #C9A96E;padding:.875rem 1.25rem;margin:.75rem 0}
code{background:#F0EAE0;padding:1px 7px;border-radius:3px;font-family:monospace;font-size:.88rem}
.err{font-size:.78rem;color:#999;margin-top:1.5rem;word-break:break-all}
a{color:#A07840}
</style></head><body>
<div class="card">
<h1>⚙️ Database Setup Needed</h1>
<p>Could not connect to MySQL. Make sure <strong>XAMPP MySQL</strong> is running, then follow these steps:</p>
<div class="step"><strong>1.</strong> Open <a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a></div>
<div class="step"><strong>2.</strong> Click <strong>New</strong> on the left sidebar and create a database named: <code>mydesignassistants</code></div>
<div class="step"><strong>3.</strong> Select that database, click <strong>Import</strong>, choose <code>database.sql</code> and click Go</div>
<div class="step"><strong>4.</strong> If your MySQL password is not empty, edit <code>config.php</code> and set <code>DB_PASS</code></div>
<div class="step"><strong>5.</strong> Refresh this page — it should work now</div>
<p class="err">Error: ' . htmlspecialchars($err1) . ($err2 ? ' | ' . htmlspecialchars($err2) : '') . '</p>
</div></body></html>');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() { return $this->pdo; }
}

function db() {
    return Database::getInstance()->getConnection();
}

// ============================================================
// SESSION — no 'secure' flag for localhost HTTP
// ============================================================
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 86400,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isAdmin() {
    startSession();
    return isset($_SESSION['admin_id']) && intval($_SESSION['admin_id']) > 0;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /portfolio/admin/login.php');
        exit;
    }
}

// ============================================================
// HELPERS
// ============================================================
function getSetting($key, $default = '') {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function sanitize($str) {
    return htmlspecialchars(strip_tags((string)$str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function slugify($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    return trim($text, '-');
}

function handleUpload($file, $folder = 'projects') {
    $allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
    $maxSize      = 10 * 1024 * 1024;

    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No file selected or upload error (' . ($file['error'] ?? '?') . ')'];
    }
    if (!in_array(strtolower($file['type']), $allowedTypes)) {
        return ['error' => 'Invalid file type. Use JPG, PNG, or WebP.'];
    }
    if ($file['size'] > $maxSize) {
        return ['error' => 'File too large (max 10 MB).'];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'img_' . uniqid() . '_' . time() . '.' . $ext;
    $dir      = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['error' => 'Cannot create upload directory. Check permissions.'];
    }

    $dest = $dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['path' => UPLOAD_URL . $folder . '/' . $filename];
    }
    return ['error' => 'Failed to save file. Check folder write permissions.'];
}
