<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');

if (!$name || strlen($name) < 2) {
    jsonResponse(['success' => false, 'message' => 'Please enter your name'], 400);
}

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email'], 400);
}

$db = getDB();

// Check duplicate
$check = $db->prepare('SELECT id FROM subscribers WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    jsonResponse(['success' => false, 'message' => 'You are already subscribed!']);
}

// Insert subscriber
$stmt = $db->prepare('INSERT INTO subscribers (name, email) VALUES (?, ?)');
try {
    $stmt->execute([$name, $email]);
} catch (PDOException $e) {
    // If name column doesn't exist, fall back to email only
    $stmt = $db->prepare('INSERT INTO subscribers (email) VALUES (?)');
    $stmt->execute([$email]);
}

jsonResponse(['success' => true, 'message' => 'Successfully subscribed!'], 201);
