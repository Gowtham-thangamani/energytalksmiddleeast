<?php
require_once __DIR__ . '/config.php';

/**
 * Mirror this subscriber into the DAS central Subscribers list (best-effort).
 */
if (!function_exists('das_forward_subscriber')) {
    function das_forward_subscriber($name, $email) {
        $endpoint = 'https://www.dasandpartnersengineering.com/api/subscribers';
        $payload  = json_encode(['name' => $name, 'email' => $email, 'source' => 'energytalks']);
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_POSTREDIR => 7, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
                ]);
                curl_exec($ch); curl_close($ch);
            } else {
                @file_get_contents($endpoint, false, stream_context_create([
                    'http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 8, 'ignore_errors' => true],
                ]));
            }
        } catch (Throwable $e) { /* best-effort; ignore */ }
    }
}

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

// Mirror to the DAS central Subscribers list (best-effort)
das_forward_subscriber($name, $email);

jsonResponse(['success' => true, 'message' => 'Successfully subscribed!'], 201);
