<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requireAuth();
        listSubscribers();
        break;
    case 'POST':
        addSubscriber();
        break;
    case 'DELETE':
        requireAuth();
        deleteSubscriber();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function listSubscribers() {
    $db = getDB();
    $stmt = $db->query('SELECT id, name, email, created_at FROM subscribers ORDER BY created_at DESC');
    $subs = $stmt->fetchAll();

    $result = array_map(function($s) {
        return [
            'id' => $s['id'],
            'name' => $s['name'] ?? '',
            'email' => $s['email'],
            'date' => date('M j, Y', strtotime($s['created_at'])),
        ];
    }, $subs);

    jsonResponse(['subscribers' => $result]);
}

function addSubscriber() {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Valid email required'], 400);
    }

    $db = getDB();

    // Check duplicate
    $check = $db->prepare('SELECT id FROM subscribers WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        jsonResponse(['error' => 'Already subscribed'], 409);
    }

    $stmt = $db->prepare('INSERT INTO subscribers (email) VALUES (?)');
    $stmt->execute([$email]);

    jsonResponse(['success' => true], 201);
}

function deleteSubscriber() {
    $id = $_GET['id'] ?? 0;
    if (!$id) jsonResponse(['error' => 'ID required'], 400);

    $db = getDB();
    $db->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);

    logActivity('user', 'delete', 'Removed subscriber #' . $id);
    jsonResponse(['success' => true]);
}
