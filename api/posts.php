<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($method) {
    case 'GET':
        if ($action === 'single') {
            getSinglePost();
        } elseif ($action === 'categories') {
            getCategories();
        } else {
            listPosts();
        }
        break;
    case 'POST':
        createPost();
        break;
    case 'PUT':
        updatePost();
        break;
    case 'DELETE':
        deletePost();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function listPosts() {
    $db = getDB();
    $publicOnly = empty($_SESSION['user_id']); // If not logged in, only show published

    $sql = 'SELECT p.*, u.username as author_name, u.full_name as author_full_name FROM posts p LEFT JOIN users u ON p.author_id = u.id';
    $params = [];

    if ($publicOnly) {
        $sql .= ' WHERE p.published = 1';
    }

    // Filters (admin only)
    if (!$publicOnly) {
        $where = [];
        if (!empty($_GET['status'])) {
            if ($_GET['status'] === 'published') { $where[] = 'p.published = 1'; }
            elseif ($_GET['status'] === 'draft') { $where[] = 'p.published = 0'; }
        }
        if (!empty($_GET['category'])) {
            $where[] = 'p.category = ?';
            $params[] = $_GET['category'];
        }
        if (!empty($_GET['search'])) {
            $where[] = 'p.title LIKE ?';
            $params[] = '%' . $_GET['search'] . '%';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
    }

    $sql .= ' ORDER BY p.published_date DESC, p.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Format for frontend
    $result = array_map(function($p) {
        return [
            'id' => $p['id'],
            'slug' => $p['slug'],
            'title' => $p['title'],
            'category' => $p['category'],
            'excerpt' => $p['excerpt'],
            'content' => $p['content'],
            'img' => $p['featured_image'],
            'published' => (bool)$p['published'],
            'featured' => (bool)$p['featured'],
            'read' => $p['read_time'],
            'tags' => json_decode($p['tags'] ?: '[]', true),
            'viewCount' => (int)$p['view_count'],
            'authorId' => $p['author_id'],
            'authorName' => $p['author_full_name'] ?: $p['author_name'] ?: 'Unknown',
            'date' => $p['published_date'] ? date('M j, Y', strtotime($p['published_date'])) : '',
            'rawDate' => $p['published_date'],
            'createdAt' => $p['created_at'],
            'updatedAt' => $p['updated_at'],
        ];
    }, $posts);

    jsonResponse(['posts' => $result]);
}

function getSinglePost() {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) jsonResponse(['error' => 'Slug required'], 400);

    $db = getDB();
    $stmt = $db->prepare('SELECT p.*, u.username as author_name, u.full_name as author_full_name FROM posts p LEFT JOIN users u ON p.author_id = u.id WHERE p.slug = ? AND p.published = 1');
    $stmt->execute([$slug]);
    $p = $stmt->fetch();

    if (!$p) jsonResponse(['error' => 'Post not found'], 404);

    // Increment view count
    $db->prepare('UPDATE posts SET view_count = view_count + 1 WHERE id = ?')->execute([$p['id']]);

    // Get related posts
    $relStmt = $db->prepare('SELECT id, slug, title, featured_image, published_date, read_time FROM posts WHERE category = ? AND id != ? AND published = 1 ORDER BY published_date DESC LIMIT 3');
    $relStmt->execute([$p['category'], $p['id']]);
    $related = $relStmt->fetchAll();

    $post = [
        'id' => $p['id'],
        'slug' => $p['slug'],
        'title' => $p['title'],
        'category' => $p['category'],
        'excerpt' => $p['excerpt'],
        'content' => $p['content'],
        'img' => $p['featured_image'],
        'published' => (bool)$p['published'],
        'featured' => (bool)$p['featured'],
        'read' => $p['read_time'],
        'tags' => json_decode($p['tags'] ?: '[]', true),
        'viewCount' => (int)$p['view_count'] + 1,
        'authorName' => $p['author_full_name'] ?: $p['author_name'] ?: 'Energy Talks Team',
        'date' => $p['published_date'] ? date('M j, Y', strtotime($p['published_date'])) : '',
        'rawDate' => $p['published_date'],
    ];

    $relatedPosts = array_map(function($r) {
        return [
            'slug' => $r['slug'],
            'title' => $r['title'],
            'img' => $r['featured_image'],
            'date' => $r['published_date'] ? date('M j, Y', strtotime($r['published_date'])) : '',
            'read' => $r['read_time'],
        ];
    }, $related);

    jsonResponse(['post' => $post, 'related' => $relatedPosts]);
}

function getCategories() {
    $db = getDB();
    $stmt = $db->query('SELECT DISTINCT category FROM posts WHERE published = 1 ORDER BY category');
    $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    jsonResponse(['categories' => $cats]);
}

function createPost() {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    $title = trim($input['title'] ?? '');
    $category = trim($input['category'] ?? '');
    $excerpt = trim($input['excerpt'] ?? '');
    $content = $input['content'] ?? '';

    if (!$title || !$category || !$excerpt || !$content) {
        jsonResponse(['error' => 'Title, category, excerpt, and content are required'], 400);
    }

    $slug = !empty($input['slug']) ? $input['slug'] : makeSlug($title);

    // Ensure unique slug
    $db = getDB();
    $check = $db->prepare('SELECT id FROM posts WHERE slug = ?');
    $check->execute([$slug]);
    if ($check->fetch()) {
        $slug .= '-' . time();
    }

    $stmt = $db->prepare('INSERT INTO posts (slug, title, category, excerpt, content, featured_image, published, featured, read_time, tags, author_id, published_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $slug,
        $title,
        $category,
        $excerpt,
        $content,
        $input['img'] ?? '',
        isset($input['published']) ? (int)$input['published'] : 1,
        isset($input['featured']) ? (int)$input['featured'] : 0,
        ($input['readTime'] ?? '5') . ' min',
        json_encode($input['tags'] ?? []),
        $userId,
        $input['rawDate'] ?? date('Y-m-d'),
    ]);

    $postId = $db->lastInsertId();

    // Update user post count
    $db->prepare('UPDATE users SET post_count = post_count + 1 WHERE id = ?')->execute([$userId]);

    logActivity('blog', 'create', 'Created: ' . $title);

    jsonResponse(['success' => true, 'id' => $postId, 'slug' => $slug], 201);
}

function updatePost() {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = $input['id'] ?? ($_GET['id'] ?? 0);

    if (!$postId) jsonResponse(['error' => 'Post ID required'], 400);

    $db = getDB();

    // Check permission
    $stmt = $db->prepare('SELECT author_id FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) jsonResponse(['error' => 'Post not found'], 404);

    $user = getCurrentUser();
    $perms = json_decode($user['permissions'] ?? '{}', true);
    if ($user['role'] !== 'admin' && !($perms['editAll'] ?? false) && $post['author_id'] != $userId) {
        jsonResponse(['error' => 'No permission to edit this post'], 403);
    }

    $slug = !empty($input['slug']) ? $input['slug'] : null;
    if ($slug) {
        $check = $db->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
        $check->execute([$slug, $postId]);
        if ($check->fetch()) {
            $slug .= '-' . time();
        }
    }

    $fields = [];
    $params = [];

    $map = [
        'title' => 'title',
        'category' => 'category',
        'excerpt' => 'excerpt',
        'content' => 'content',
        'img' => 'featured_image',
    ];

    foreach ($map as $inputKey => $dbCol) {
        if (isset($input[$inputKey])) {
            $fields[] = "$dbCol = ?";
            $params[] = $input[$inputKey];
        }
    }

    if ($slug) { $fields[] = 'slug = ?'; $params[] = $slug; }
    if (isset($input['published'])) { $fields[] = 'published = ?'; $params[] = (int)$input['published']; }
    if (isset($input['featured'])) { $fields[] = 'featured = ?'; $params[] = (int)$input['featured']; }
    if (isset($input['readTime'])) { $fields[] = 'read_time = ?'; $params[] = $input['readTime'] . ' min'; }
    if (isset($input['tags'])) { $fields[] = 'tags = ?'; $params[] = json_encode($input['tags']); }
    if (isset($input['rawDate'])) { $fields[] = 'published_date = ?'; $params[] = $input['rawDate']; }

    if (empty($fields)) jsonResponse(['error' => 'Nothing to update'], 400);

    $params[] = $postId;
    $db->prepare('UPDATE posts SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

    logActivity('blog', 'edit', 'Edited: ' . ($input['title'] ?? 'Post #' . $postId));

    jsonResponse(['success' => true]);
}

function deletePost() {
    $userId = requireAuth();
    $postId = $_GET['id'] ?? 0;
    if (!$postId) jsonResponse(['error' => 'Post ID required'], 400);

    $db = getDB();
    $stmt = $db->prepare('SELECT title, author_id FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) jsonResponse(['error' => 'Post not found'], 404);

    $user = getCurrentUser();
    $perms = json_decode($user['permissions'] ?? '{}', true);
    if ($user['role'] !== 'admin' && !($perms['delete'] ?? false)) {
        jsonResponse(['error' => 'No permission to delete'], 403);
    }
    if ($user['role'] !== 'admin' && !($perms['editAll'] ?? false) && $post['author_id'] != $userId) {
        jsonResponse(['error' => 'Can only delete own posts'], 403);
    }

    $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);

    logActivity('blog', 'delete', 'Deleted: ' . $post['title']);

    jsonResponse(['success' => true]);
}
