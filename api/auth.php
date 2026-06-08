<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($method !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'me':
        handleMe();
        break;
    case 'change-password':
        if ($method !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        handleChangePassword();
        break;
    case 'reset-password':
        if ($method !== 'POST') jsonResponse(['error' => 'POST required'], 405);
        handleResetPassword();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(['error' => 'Username and password required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND status = "active"');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Invalid username or password'], 401);
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // Update last login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    logActivity('login', 'login', 'Logged in successfully');

    jsonResponse([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'fullName' => $user['full_name'],
            'role' => $user['role'],
            'permissions' => json_decode($user['permissions'], true)
        ]
    ]);
}

function handleLogout() {
    if (!empty($_SESSION['user_id'])) {
        logActivity('login', 'logout', 'Logged out');
    }
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleMe() {
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    $user['permissions'] = json_decode($user['permissions'], true);
    $user['fullName'] = $user['full_name'];
    unset($user['full_name']);
    jsonResponse(['user' => $user]);
}

function handleChangePassword() {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    $current = $input['current'] ?? '';
    $newPw = $input['newPassword'] ?? '';

    if (strlen($newPw) < 8) {
        jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        jsonResponse(['error' => 'Current password is incorrect'], 400);
    }

    $hash = password_hash($newPw, PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);

    logActivity('user', 'edit', 'Changed password');
    jsonResponse(['success' => true]);
}

function handleResetPassword() {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $newPw = $input['newPassword'] ?? '';

    if (!$username || !$email || strlen($newPw) < 8) {
        jsonResponse(['error' => 'All fields required, password min 8 chars'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND email = ?');
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['error' => 'No account found with this username and email'], 404);
    }

    $hash = password_hash($newPw, PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);

    logActivity('user', 'password-reset', 'Password reset via login page');
    jsonResponse(['success' => true]);
}
