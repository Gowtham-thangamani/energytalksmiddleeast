<?php
require_once __DIR__ . '/config.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db = getDB();
    $type = $_GET['type'] ?? 'all';

    $sql = 'SELECT * FROM activity_log';
    $params = [];

    if ($type !== 'all') {
        $sql .= ' WHERE type = ?';
        $params[] = $type;
    }

    $sql .= ' ORDER BY created_at DESC LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $result = array_map(function($a) {
        return [
            'id' => $a['id'],
            'type' => $a['type'],
            'action' => $a['action'],
            'details' => $a['details'],
            'userId' => $a['user_id'],
            'username' => $a['username'],
            'timestamp' => $a['created_at'],
        ];
    }, $logs);

    jsonResponse(['logs' => $result]);
} elseif ($method === 'GET' && ($_GET['action'] ?? '') === 'stats') {
    getStats();
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
