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
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    switch ($action) {
        case 'bulk_add_credentials':
            handleBulkAddCredentials($userId, $input);
            break;
        case 'delete_website':
            handleDeleteWebsite($userId, $input);
            break;
        case 'delete_credential':
            handleDeleteCredential($userId, $input);
            break;
        case 'restore_website':
            handleRestoreWebsite($userId, $input);
            break;
        case 'restore_credential':
            handleRestoreCredential($userId, $input);
            break;
        case 'permanent_delete_website':
            handlePermanentDeleteWebsite($userId, $input);
            break;
        case 'permanent_delete_credential':
            handlePermanentDeleteCredential($userId, $input);
            break;
        case 'bulk_delete_credentials':
            handleBulkDeleteCredentials($userId, $input);
            break;
        case 'bulk_restore_credentials':
            handleBulkRestoreCredentials($userId, $input);
            break;
        case 'bulk_permanent_delete_credentials':
            handleBulkPermanentDeleteCredentials($userId, $input);
            break;
        case 'get_password_history':
            handleGetPasswordHistory($userId);
            break;
        case 'get_credential':
            handleGetCredential($userId);
            break;
        case 'edit_website':
            handleEditWebsite($userId, $input);
            break;
        case 'edit_credential':
            handleEditCredential($userId, $input);
            break;
        case 'toggle_favorite':
            handleToggleFavorite($userId, $input);
            break;
        case 'verify_master_password':
            handleVerifyMasterPassword($userId, $input);
            break;
        case 'check_verification':
            handleCheckVerification($userId, $input);
            break;
        case 'lock_vault':
            handleLockVault();
            break;
        case 'get_decrypted_password':
            handleGetDecryptedPassword($userId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Credentials API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleBulkAddCredentials(int $userId, array $input): void {
    $websiteName = trim($input['website_name'] ?? '');
    $websiteUrl = trim($input['website_url'] ?? '');
    $credentials = $input['credentials'] ?? [];

    if (empty($websiteName)) {
        echo json_encode(['success' => false, 'message' => 'Website name is required']);
        return;
    }
    if (empty($credentials) || !is_array($credentials)) {
        echo json_encode(['success' => false, 'message' => 'At least one credential is required']);
        return;
    }

    $pdo = getDbConnection();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT id, website_url FROM websites WHERE user_id = :uid AND website_name = :name');
        $stmt->execute([':uid' => $userId, ':name' => $websiteName]);
        $existing = $stmt->fetch();

        if ($existing) {
            $websiteId = (int)$existing['id'];
            if (!empty($websiteUrl) && empty($existing['website_url'])) {
                $pdo->prepare('UPDATE websites SET website_url = :url WHERE id = :id')
                    ->execute([':url' => $websiteUrl, ':id' => $websiteId]);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO websites (user_id, website_name, website_url) VALUES (:uid, :name, :url)');
            $stmt->execute([':uid' => $userId, ':name' => $websiteName, ':url' => $websiteUrl]);
            $websiteId = (int)$pdo->lastInsertId();
            logActivity($userId, 'Website created', "Created website: $websiteName");
        }

        $encKey = getUserEncryptionKey($userId);
        if (!$encKey) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Encryption unavailable']);
            return;
        }

            $insertStmt = $pdo->prepare(
            'INSERT INTO credentials (website_id, title, username, password_encrypted, category_id, notes, phone_encrypted, password_changed_at) VALUES (:wid, :title, :uname, :pw, :cat, :notes, :phone, NOW())'
        );

        $savedCount = 0;
        $errors = [];

        foreach ($credentials as $index => $cred) {
            $title = trim($cred['title'] ?? '');
            $username = trim($cred['username'] ?? '');
            $password = $cred['password'] ?? '';
            $categoryId = !empty($cred['category_id']) ? (int)$cred['category_id'] : null;
            $notes = trim($cred['notes'] ?? '');
            $phone = trim($cred['phone'] ?? '');

            if (empty($title) || empty($username) || empty($password)) {
                $errors[] = 'Row ' . ($index + 1) . ': title, username, and password are required';
                continue;
            }
            if (strlen($password) < 8) {
                $errors[] = 'Row ' . ($index + 1) . ': password must be at least 8 characters';
                continue;
            }

            try {
                $encrypted = encryptPassword($password, $encKey['key'], $encKey['iv']);
            } catch (Exception $e) {
                $errors[] = 'Row ' . ($index + 1) . ': encryption failed';
                continue;
            }

            $phoneEncrypted = !empty($phone) ? encryptPassword($phone, $encKey['key'], $encKey['iv']) : null;
            $insertStmt->execute([
                ':wid' => $websiteId, ':title' => $title,
                ':uname' => $username, ':pw' => $encrypted,
                ':cat' => $categoryId, ':notes' => $notes,
                ':phone' => $phoneEncrypted,
            ]);
            $newCredId = (int)$pdo->lastInsertId();
            // Save initial password to history
            $histStmt = $pdo->prepare('INSERT INTO password_history (credential_id, password_encrypted) VALUES (:cid, :pw)');
            $histStmt->execute([':cid' => $newCredId, ':pw' => $encrypted]);
            $savedCount++;
        }

        if ($savedCount === 0 && !empty($errors)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
            return;
        }

        $pdo->commit();

        $msg = "Saved $savedCount credential(s) under $websiteName";
        logActivity($userId, 'Credentials added', $msg);

        $response = ['success' => true, 'message' => $msg, 'website_id' => $websiteId];
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        echo json_encode($response);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteWebsite(int $userId, array $input): void {
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
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE websites SET deleted_at = NOW() WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $id, ':uid' => $userId]);
        $pdo->prepare('UPDATE credentials SET deleted_at = NOW() WHERE website_id = :id')
            ->execute([':id' => $id]);
        $pdo->commit();
        logActivity($userId, 'Website deleted', "Moved website #$id to trash");
        echo json_encode(['success' => true, 'message' => 'Website and all credentials moved to trash']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handleDeleteCredential(int $userId, array $input): void {
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
    $stmt = $pdo->prepare(
        'UPDATE credentials c JOIN websites w ON c.website_id = w.id SET c.deleted_at = NOW() WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    logActivity($userId, 'Credential deleted', "Moved credential #$id to trash");
    echo json_encode(['success' => true, 'message' => 'Credential moved to trash']);
}

function handleGetCredential(int $userId): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }

    requireActionVerified('edit_credential');

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT c.* FROM credentials c JOIN websites w ON c.website_id = w.id WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $cred = $stmt->fetch();

    if (!$cred) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        return;
    }

    $encKey = getUserEncryptionKey($userId);
    if ($encKey && !empty($cred['password_encrypted'])) {
        try {
            $cred['password_decrypted'] = decryptPassword($cred['password_encrypted'], $encKey['key'], $encKey['iv']);
        } catch (Exception $e) {
            $cred['password_decrypted'] = '[Decryption failed]';
        }
    } else {
        $cred['password_decrypted'] = '[Key unavailable]';
    }
    // Decrypt phone number
    if ($encKey && !empty($cred['phone_encrypted'])) {
        try {
            $cred['phone_decrypted'] = decryptPassword($cred['phone_encrypted'], $encKey['key'], $encKey['iv']);
        } catch (Exception $e) {
            $cred['phone_decrypted'] = '';
        }
    } else {
        $cred['phone_decrypted'] = '';
    }
    unset($cred['password_encrypted']);
    unset($cred['phone_encrypted']);

    echo json_encode(['success' => true, 'credential' => $cred]);
}

function handleVerifyMasterPassword(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid. Reload the page.']);
        return;
    }

    $masterPassword = $input['master_password'] ?? '';
    $action = $input['action'] ?? 'general';

    $validActions = ['view_password', 'copy_password', 'edit_credential', 'delete_credential', 'delete_website', 'bulk_delete', 'general'];
    if (!in_array($action, $validActions)) {
        $action = 'general';
    }

    $result = verifyMasterPassword($userId, $masterPassword, $action);
    echo json_encode($result);
}

function handleLockVault(): void {
    lockVault();
    echo json_encode(['success' => true, 'message' => 'Vault locked']);
}

function handleCheckVerification(int $userId, array $input): void {
    $action = $input['action'] ?? 'general';
    $validActions = ['view_password', 'copy_password', 'edit_credential', 'delete_credential', 'delete_website', 'bulk_delete', 'general'];
    if (!in_array($action, $validActions)) {
        $action = 'general';
    }
    $verified = isActionVerified($action);
    echo json_encode(['success' => true, 'verified' => $verified]);
}

function handleGetDecryptedPassword(int $userId): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }

    requireActionVerified('view_password');

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT c.password_encrypted FROM credentials c JOIN websites w ON c.website_id = w.id WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['password_encrypted'])) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        return;
    }

    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption unavailable']);
        return;
    }

    try {
        $decrypted = decryptPassword($row['password_encrypted'], $encKey['key'], $encKey['iv']);
        echo json_encode(['success' => true, 'password' => $decrypted]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Decryption failed']);
    }
}

function handleEditWebsite(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['website_name'] ?? '');
    $url = trim($input['website_url'] ?? '');

    if ($id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Website name is required']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE websites SET website_name = :name, website_url = :url WHERE id = :id AND user_id = :uid');
    $stmt->execute([':name' => $name, ':url' => $url, ':id' => $id, ':uid' => $userId]);
    logActivity($userId, 'Website updated', "Updated website: $name");
    echo json_encode(['success' => true, 'message' => 'Website updated']);
}

function handleEditCredential(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $id = (int)($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
    $notes = trim($input['notes'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if ($id <= 0 || empty($title) || empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Title, username, and password are required']);
        return;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    $pdo = getDbConnection();
    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption unavailable']);
        return;
    }

    try {
        $encrypted = encryptPassword($password, $encKey['key'], $encKey['iv']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Encryption failed']);
        return;
    }

    // Get old password before updating (for history tracking)
    $oldPwEncrypted = null;
    $oldStmt = $pdo->prepare(
        'SELECT c.password_encrypted FROM credentials c JOIN websites w ON c.website_id = w.id WHERE c.id = :id AND w.user_id = :uid'
    );
    $oldStmt->execute([':id' => $id, ':uid' => $userId]);
    $oldRow = $oldStmt->fetch();
    if ($oldRow) {
        $oldPwEncrypted = $oldRow['password_encrypted'];
    }

    $passwordReallyChanged = ($oldPwEncrypted && $oldPwEncrypted !== $encrypted);

    $phoneEncrypted = !empty($phone) ? encryptPassword($phone, $encKey['key'], $encKey['iv']) : null;
    $stmt = $pdo->prepare(
        'UPDATE credentials c JOIN websites w ON c.website_id = w.id
         SET c.title = :title, c.username = :uname, c.password_encrypted = :pw, c.category_id = :cat, c.notes = :notes, c.phone_encrypted = :phone' .
         ($passwordReallyChanged ? ', c.password_changed_at = NOW()' : '') .
         ' WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':title' => $title, ':uname' => $username, ':pw' => $encrypted, ':cat' => $categoryId, ':notes' => $notes, ':phone' => $phoneEncrypted, ':id' => $id, ':uid' => $userId]);

    // Save old password to history if password changed
    if ($passwordReallyChanged) {
        $histStmt = $pdo->prepare('INSERT INTO password_history (credential_id, password_encrypted) VALUES (:cid, :pw)');
        $histStmt->execute([':cid' => $id, ':pw' => $oldPwEncrypted]);
    }

    logActivity($userId, 'Credential updated', "Updated credential #$id");
    echo json_encode(['success' => true, 'message' => 'Credential updated']);
}

function handleToggleFavorite(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    $fav = !empty($input['is_favorite']) ? 1 : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'UPDATE credentials c JOIN websites w ON c.website_id = w.id SET c.is_favorite = :fav WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':fav' => $fav, ':id' => $id, ':uid' => $userId]);
    echo json_encode(['success' => true, 'message' => 'Updated']);
}

function handleRestoreWebsite(int $userId, array $input): void {
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
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE websites SET deleted_at = NULL WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $id, ':uid' => $userId]);
        $pdo->prepare('UPDATE credentials SET deleted_at = NULL WHERE website_id = :id')
            ->execute([':id' => $id]);
        $pdo->commit();
        logActivity($userId, 'Website restored', "Restored website #$id from trash");
        echo json_encode(['success' => true, 'message' => 'Website and credentials restored']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handleRestoreCredential(int $userId, array $input): void {
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
    $stmt = $pdo->prepare(
        'UPDATE credentials c JOIN websites w ON c.website_id = w.id SET c.deleted_at = NULL WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    logActivity($userId, 'Credential restored', "Restored credential #$id from trash");
    echo json_encode(['success' => true, 'message' => 'Credential restored']);
}

function handlePermanentDeleteWebsite(int $userId, array $input): void {
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
    $stmt = $pdo->prepare('DELETE FROM websites WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    logActivity($userId, 'Website permanently deleted', "Permanently deleted website #$id");
    echo json_encode(['success' => true, 'message' => 'Website permanently deleted']);
}

function handlePermanentDeleteCredential(int $userId, array $input): void {
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
    $stmt = $pdo->prepare(
        'DELETE c FROM credentials c JOIN websites w ON c.website_id = w.id WHERE c.id = :id AND w.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    logActivity($userId, 'Credential permanently deleted', "Permanently deleted credential #$id");
    echo json_encode(['success' => true, 'message' => 'Credential permanently deleted']);
}

function handleBulkDeleteCredentials(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $ids = $input['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No credentials selected']);
        return;
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "UPDATE credentials c JOIN websites w ON c.website_id = w.id
         SET c.deleted_at = NOW()
         WHERE c.id IN ($placeholders) AND w.user_id = ?"
    );
    $params = array_merge($ids, [$userId]);
    $stmt->execute($params);
    logActivity($userId, 'Bulk delete', 'Moved ' . count($ids) . ' credentials to trash');
    echo json_encode(['success' => true, 'message' => count($ids) . ' credentials moved to trash']);
}

function handleBulkRestoreCredentials(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $ids = $input['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No credentials selected']);
        return;
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "UPDATE credentials c JOIN websites w ON c.website_id = w.id
         SET c.deleted_at = NULL
         WHERE c.id IN ($placeholders) AND w.user_id = ?"
    );
    $params = array_merge($ids, [$userId]);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'message' => count($ids) . ' credentials restored']);
}

function handleBulkPermanentDeleteCredentials(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $ids = $input['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No credentials selected']);
        return;
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "DELETE c FROM credentials c JOIN websites w ON c.website_id = w.id
         WHERE c.id IN ($placeholders) AND w.user_id = ?"
    );
    $params = array_merge($ids, [$userId]);
    $stmt->execute($params);
    logActivity($userId, 'Bulk permanent delete', 'Permanently deleted ' . count($ids) . ' credentials');
    echo json_encode(['success' => true, 'message' => count($ids) . ' credentials permanently deleted']);
}

function handleGetPasswordHistory(int $userId): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        "SELECT ph.id, ph.password_encrypted, ph.created_at
         FROM password_history ph
         JOIN credentials c ON ph.credential_id = c.id
         JOIN websites w ON c.website_id = w.id
         WHERE ph.credential_id = :id AND w.user_id = :uid
         ORDER BY ph.created_at DESC
         LIMIT 20"
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $history = $stmt->fetchAll();

    $encKey = getUserEncryptionKey($userId);

    foreach ($history as &$h) {
        if ($encKey && !empty($h['password_encrypted'])) {
            try {
                $h['password_decrypted'] = decryptPassword($h['password_encrypted'], $encKey['key'], $encKey['iv']);
            } catch (Exception $e) {
                $h['password_decrypted'] = '[Decryption failed]';
            }
        } else {
            $h['password_decrypted'] = '[Key unavailable]';
        }
        unset($h['password_encrypted']);
    }

    echo json_encode(['success' => true, 'history' => $history]);
}
