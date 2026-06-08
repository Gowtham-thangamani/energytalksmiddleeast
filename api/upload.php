<?php
require_once __DIR__ . '/config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

if (empty($_FILES['image'])) {
    jsonResponse(['error' => 'No file uploaded'], 400);
}

$file = $_FILES['image'];

// Validate
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Upload failed'], 400);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonResponse(['error' => 'File too large (max 10MB)'], 400);
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    jsonResponse(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'], 400);
}

// Create uploads directory if needed
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('img_') . '_' . time() . '.' . $ext;
$filepath = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    jsonResponse(['error' => 'Failed to save file'], 500);
}

$url = UPLOAD_URL . $filename;

logActivity('blog', 'upload', 'Uploaded image: ' . $filename);

jsonResponse(['success' => true, 'url' => $url, 'filename' => $filename]);
