<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

session_start();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = getCurrentUserId();
$query = trim($_GET['q'] ?? '');

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search query must be at least 2 characters']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT pe.id, pe.website, pe.url, pe.username, pe.is_favorite, c.name as category_name
         FROM password_entries pe
         LEFT JOIN categories c ON pe.category_id = c.id
         WHERE pe.user_id = :uid
         AND pe.is_archived = 0
         AND (pe.website LIKE :q OR pe.username LIKE :q OR pe.url LIKE :q OR pe.tags LIKE :q OR pe.notes LIKE :q)
         ORDER BY pe.is_favorite DESC, pe.updated_at DESC
         LIMIT 10'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':q' => '%' . $query . '%',
    ]);
    $results = $stmt->fetchAll();

    echo json_encode(['success' => true, 'results' => $results]);
} catch (PDOException $e) {
    error_log('Search failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Search failed']);
}
