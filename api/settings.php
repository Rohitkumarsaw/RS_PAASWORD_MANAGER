<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = getCurrentUserId();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    switch ($action) {
        case 'get_profile':
            handleGetProfile($userId);
            break;
        case 'update_profile':
            handleUpdateProfile($userId, $input);
            break;
        case 'update_password':
            handleUpdatePassword($userId, $input);
            break;
        case 'update_theme':
            handleUpdateTheme($userId, $input);
            break;
        case 'update_settings':
            handleUpdateSettings($userId, $input);
            break;
        case 'get_categories':
            handleGetCategories($userId);
            break;
        case 'create_category':
            handleCreateCategory($userId, $input);
            break;
        case 'delete_category':
            handleDeleteCategory($userId, $input);
            break;
        case 'change_master_password':
            handleChangeMasterPassword($userId, $input);
            break;
        case 'delete_account':
            handleDeleteAccount($userId, $input);
            break;
        case 'get_activity':
            handleGetActivity($userId);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Settings API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleGetProfile(int $userId): void {
    $user = getCurrentUser();
    if ($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
}

function handleUpdateProfile(int $userId, array $input): void {
    $displayName = trim($input['display_name'] ?? '');

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE users SET display_name = :name WHERE id = :id');
    $stmt->execute([':name' => $displayName, ':id' => $userId]);

    logActivity($userId, 'Profile updated', 'Display name changed');
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
}

function handleUpdatePassword(int $userId, array $input): void {
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!password_verify($currentPassword, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute([':hash' => $newHash, ':id' => $userId]);

    logActivity($userId, 'Password changed', 'Account password was changed');
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
}

function handleUpdateTheme(int $userId, array $input): void {
    $theme = $input['theme'] ?? 'dark';
    if (!in_array($theme, ['dark', 'light'])) $theme = 'dark';

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE users SET theme_preference = :theme WHERE id = :id');
    $stmt->execute([':theme' => $theme, ':id' => $userId]);

    echo json_encode(['success' => true, 'message' => 'Theme updated']);
}

function handleUpdateSettings(int $userId, array $input): void {
    $itemsPerPage = min(100, max(10, (int)($input['items_per_page'] ?? 20)));

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE users SET items_per_page = :ipp WHERE id = :id');
    $stmt->execute([':ipp' => $itemsPerPage, ':id' => $userId]);

    echo json_encode(['success' => true, 'message' => 'Settings updated']);
}

function handleGetCategories(int $userId): void {
    $categories = getUserCategories($userId);
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function handleCreateCategory(int $userId, array $input): void {
    $name = trim($input['name'] ?? '');
    $icon = trim($input['icon'] ?? 'folder');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('INSERT INTO categories (user_id, name, icon) VALUES (:uid, :name, :icon)');
    try {
        $stmt->execute([':uid' => $userId, ':name' => $name, ':icon' => $icon]);
        echo json_encode(['success' => true, 'message' => 'Category created', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'Category already exists']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create category']);
        }
    }
}

function handleDeleteCategory(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);

    echo json_encode(['success' => true, 'message' => 'Category deleted']);
}

function handleGetActivity(int $userId): void {
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $activities = getRecentActivity($userId, $limit);
    echo json_encode(['success' => true, 'activities' => $activities]);
}

function handleChangeMasterPassword(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid. Reload the page.']);
        return;
    }

    $currentMp = $input['current_master_password'] ?? '';
    $newMp = $input['new_master_password'] ?? '';
    $confirmMp = $input['confirm_master_password'] ?? '';

    if (empty($currentMp) || empty($newMp) || empty($confirmMp)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    if ($newMp !== $confirmMp) {
        echo json_encode(['success' => false, 'message' => 'New master passwords do not match']);
        return;
    }
    if (strlen($newMp) < 8) {
        echo json_encode(['success' => false, 'message' => 'Master password must be at least 8 characters']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT master_password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentMp, $user['master_password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current master password is incorrect']);
        return;
    }

    $newHash = password_hash($newMp, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare('UPDATE users SET master_password_hash = :hash WHERE id = :id');
    $stmt->execute([':hash' => $newHash, ':id' => $userId]);

    lockVault();
    logActivity($userId, 'Master password changed', 'Master password was changed successfully');
    echo json_encode(['success' => true, 'message' => 'Master password changed successfully. Vault has been locked.']);
}

function handleDeleteAccount(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid. Reload the page.']);
        return;
    }

    $masterPassword = $input['master_password'] ?? '';
    if (empty($masterPassword)) {
        echo json_encode(['success' => false, 'message' => 'Master password is required']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT master_password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($masterPassword, $user['master_password_hash'])) {
        logActivity($userId, 'Account delete failed', 'Incorrect master password');
        echo json_encode(['success' => false, 'message' => 'Master password is incorrect']);
        return;
    }

    logActivity($userId, 'Account deleted', 'User account was deleted');

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);

    session_unset();
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Account deleted successfully', 'redirect' => 'login.php']);
}


