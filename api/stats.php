<?php
require_once __DIR__ . '/config.php';
requireAuth();

$db = getDB();

$views = $db->query('SELECT COALESCE(SUM(view_count), 0) as total FROM posts')->fetch()['total'];
$published = $db->query('SELECT COUNT(*) as c FROM posts WHERE published = 1')->fetch()['c'];
$drafts = $db->query('SELECT COUNT(*) as c FROM posts WHERE published = 0')->fetch()['c'];
$subs = $db->query('SELECT COUNT(*) as c FROM subscribers')->fetch()['c'];
$userCount = $db->query('SELECT COUNT(*) as c FROM users')->fetch()['c'];

// Recent activity
$recentStmt = $db->query('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5');
$recent = $recentStmt->fetchAll();

$recentLogs = array_map(function($a) {
    return [
        'id' => $a['id'],
        'type' => $a['type'],
        'action' => $a['action'],
        'details' => $a['details'],
        'username' => $a['username'],
        'timestamp' => $a['created_at'],
    ];
}, $recent);

jsonResponse([
    'views' => (int)$views,
    'published' => (int)$published,
    'drafts' => (int)$drafts,
    'subscribers' => (int)$subs,
    'users' => (int)$userCount,
    'recentActivity' => $recentLogs,
]);
