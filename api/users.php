<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requireAuth();
        listUsers();
        break;
    case 'POST':
        requireAdmin();
        createUser();
        break;
    case 'PUT':
        requireAdmin();
        updateUser();
        break;
    case 'DELETE':
        requireAdmin();
        deleteUser();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function listUsers() {
    $db = getDB();
    $stmt = $db->query('SELECT id, username, email, full_name, role, status, permissions, post_count, last_login, created_at FROM users ORDER BY created_at');
    $users = $stmt->fetchAll();

    $result = array_map(function($u) {
        return [
            'id' => $u['id'],
            'username' => $u['username'],
            'email' => $u['email'],
            'fullName' => $u['full_name'],
            'role' => $u['role'],
            'status' => $u['status'],
            'permissions' => json_decode($u['permissions'] ?? '{}', true),
            'postCount' => (int)$u['post_count'],
            'lastLogin' => $u['last_login'],
            'createdAt' => $u['created_at'],
        ];
    }, $users);

    jsonResponse(['users' => $result]);
}

function createUser() {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim(strtolower($input['username'] ?? ''));
    $email = trim(strtolower($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    if (!$username || !$email) jsonResponse(['error' => 'Username and email required'], 400);
    if (strlen($password) < 8) jsonResponse(['error' => 'Password must be at least 8 characters'], 400);

    $db = getDB();

    // Check duplicates
    $check = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        jsonResponse(['error' => 'Username or email already exists'], 409);
    }

    $stmt = $db->prepare('INSERT INTO users (username, email, full_name, password_hash, role, status, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $username,
        $email,
        $input['fullName'] ?? '',
        password_hash($password, PASSWORD_DEFAULT),
        $input['role'] ?? 'editor',
        $input['status'] ?? 'active',
        json_encode($input['permissions'] ?? ['create' => true, 'edit' => true, 'editAll' => false, 'delete' => false, 'publish' => true, 'subscribers' => false]),
    ]);

    logActivity('user', 'create', 'Created user: ' . $username);
    jsonResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}

function updateUser() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['id'] ?? ($_GET['id'] ?? 0);
    if (!$userId) jsonResponse(['error' => 'User ID required'], 400);

    $db = getDB();

    $fields = [];
    $params = [];

    if (isset($input['email'])) { $fields[] = 'email = ?'; $params[] = trim(strtolower($input['email'])); }
    if (isset($input['fullName'])) { $fields[] = 'full_name = ?'; $params[] = $input['fullName']; }
    if (isset($input['role'])) { $fields[] = 'role = ?'; $params[] = $input['role']; }
    if (isset($input['status'])) { $fields[] = 'status = ?'; $params[] = $input['status']; }
    if (isset($input['permissions'])) { $fields[] = 'permissions = ?'; $params[] = json_encode($input['permissions']); }
    if (!empty($input['password']) && strlen($input['password']) >= 8) {
        $fields[] = 'password_hash = ?';
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) jsonResponse(['error' => 'Nothing to update'], 400);

    $params[] = $userId;
    $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

    logActivity('user', 'edit', 'Updated user #' . $userId);
    jsonResponse(['success' => true]);
}

function deleteUser() {
    $userId = $_GET['id'] ?? 0;
    if (!$userId) jsonResponse(['error' => 'User ID required'], 400);

    // Can't delete yourself
    if ($userId == $_SESSION['user_id']) {
        jsonResponse(['error' => 'Cannot delete your own account'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error' => 'User not found'], 404);

    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

    logActivity('user', 'delete', 'Deleted user: ' . $user['username']);
    jsonResponse(['success' => true]);
}
