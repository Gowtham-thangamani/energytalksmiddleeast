<?php
// ============================================
// Energy Talks Middle East - Database Config
// ============================================
// UPDATE THESE VALUES with your cPanel/Hostinger MySQL credentials

define('DB_HOST', 'localhost');
define('DB_NAME', 'energytalks_db');
define('DB_USER', 'etdbuser');
define('DB_PASS', 'Et@Energy2026!');

// Upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// CORS headers (allow same-origin requests)
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// Helper: send JSON response
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Helper: require authentication
function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    return $_SESSION['user_id'];
}

// Helper: require admin role
function requireAdmin() {
    requireAuth();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        jsonResponse(['error' => 'Admin access required'], 403);
    }
}

// Helper: get current user
function getCurrentUser() {
    if (empty($_SESSION['user_id'])) return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, email, full_name, role, status, permissions FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Helper: log activity
function logActivity($type, $action, $details = '') {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO activity_log (type, action, details, user_id, username) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $type,
        $action,
        $details,
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? 'System'
    ]);
}

// Helper: make slug
function makeSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}
