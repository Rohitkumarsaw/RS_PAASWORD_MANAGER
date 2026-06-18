<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

session_start();
header('Content-Type: application/json');

$userId = getCurrentUserId();
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    switch ($action) {
        case 'create':
            handleCreateShare($userId, $input);
            break;
        case 'revoke':
            handleRevokeShare($userId, $input);
            break;
        case 'list':
            handleListShares($userId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Shares API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleCreateShare(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }

    $credentialId = (int)($input['credential_id'] ?? 0);
    $expireHours = max(1, min(168, (int)($input['expire_hours'] ?? 24)));
    $maxViews = max(1, min(100, (int)($input['max_views'] ?? 1)));

    if ($credentialId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid credential ID']);
        return;
    }

    // Verify credential belongs to user
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT c.title, c.notes, c.phone_encrypted, w.website_name FROM credentials c
         JOIN websites w ON c.website_id = w.id
         WHERE c.id = :id AND w.user_id = :uid AND c.deleted_at IS NULL'
    );
    $stmt->execute([':id' => $credentialId, ':uid' => $userId]);
    $cred = $stmt->fetch();

    if (!$cred) {
        echo json_encode(['success' => false, 'message' => 'Credential not found']);
        return;
    }

    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$expireHours hours"));

    $stmt = $pdo->prepare(
        'INSERT INTO share_links (credential_id, user_id, token, expires_at, max_views)
         VALUES (:cid, :uid, :token, :expires, :maxv)'
    );
    $stmt->execute([
        ':cid' => $credentialId,
        ':uid' => $userId,
        ':token' => $token,
        ':expires' => $expiresAt,
        ':maxv' => $maxViews,
    ]);

    $shareUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/share.php?token=' . $token;

    logActivity($userId, 'Share link created', "Created share link for {$cred['website_name']} / {$cred['title']}");

    echo json_encode([
        'success' => true,
        'message' => 'Share link created',
        'token' => $token,
        'url' => $shareUrl,
        'expires_at' => $expiresAt,
        'max_views' => $maxViews,
    ]);
}

function handleRevokeShare(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE share_links SET is_revoked = 1 WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    echo json_encode(['success' => true, 'message' => 'Share link revoked']);
}

function handleListShares(int $userId): void {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "SELECT sl.id, sl.token, sl.expires_at, sl.max_views, sl.current_views, sl.is_revoked, sl.created_at,
                c.title as credential_title, w.website_name
         FROM share_links sl
         JOIN credentials c ON sl.credential_id = c.id
         JOIN websites w ON c.website_id = w.id
         WHERE sl.user_id = :uid
         ORDER BY sl.created_at DESC
         LIMIT 50"
    );
    $stmt->execute([':uid' => $userId]);
    $links = $stmt->fetchAll();

    // Generate full URL for each
    foreach ($links as &$link) {
        $link['url'] = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/share.php?token=' . $link['token'];
        $link['is_expired'] = strtotime($link['expires_at']) < time();
    }

    echo json_encode(['success' => true, 'links' => $links]);
}
