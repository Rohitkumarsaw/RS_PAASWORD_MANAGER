<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    checkSessionTimeout();
    if (session_regenerate_id()) {
        session_regenerate_id();
    }
}

function getCurrentUserId(): int {
    return $_SESSION['user_id'] ?? 0;
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, display_name, phone, avatar_url, theme_preference, items_per_page, is_admin, created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => getCurrentUserId()]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('Failed to get current user: ' . $e->getMessage());
        return null;
    }
}

function getUserEncryptionKey(int $userId): ?array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT encryption_key_hash, encryption_iv FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'key' => base64_decode($row['encryption_key_hash']),
                'iv'  => base64_decode($row['encryption_iv']),
            ];
        }
        return null;
    } catch (PDOException $e) {
        error_log('Failed to get encryption key: ' . $e->getMessage());
        return null;
    }
}

function registerUser(string $username, string $email, string $password, string $masterPassword): array {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username');
        $stmt->execute([':email' => $email, ':username' => $username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already taken'];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $masterPasswordHash = password_hash($masterPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $encKey = generateEncryptionKey();
        $iv = generateIv();

        $keyHash = base64_encode($encKey);
        $ivB64 = base64_encode($iv);

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, master_password_hash, encryption_key_hash, encryption_iv)
             VALUES (:username, :email, :password_hash, :master_password_hash, :encryption_key_hash, :encryption_iv)'
        );
        $stmt->execute([
            ':username'             => $username,
            ':email'                => $email,
            ':password_hash'        => $passwordHash,
            ':master_password_hash' => $masterPasswordHash,
            ':encryption_key_hash'  => $keyHash,
            ':encryption_iv'        => $ivB64,
        ]);

        $userId = $pdo->lastInsertId();

        $defaultCategories = ['Social', 'Finance', 'Work', 'Personal', 'Email', 'Entertainment', 'Shopping', 'Other'];
        $catStmt = $pdo->prepare('INSERT INTO categories (user_id, name, icon) VALUES (:uid, :name, :icon)');
        foreach ($defaultCategories as $cat) {
            $catStmt->execute([':uid' => $userId, ':name' => $cat, ':icon' => 'folder']);
        }

        logActivity($userId, 'User registered', "User $username registered successfully");

        return ['success' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        error_log('Registration failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

function authenticateUser(string $email, string $password): array {
    try {
        $pdo = getDbConnection();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!checkLoginAttempts($email, $ip)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            recordLoginAttempt($email, $ip, false);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        recordLoginAttempt($email, $ip, true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        logActivity($user['id'], 'User logged in', 'User logged in from IP: ' . $ip);

        if (session_regenerate_id()) {
            session_regenerate_id(true);
        }

        return ['success' => true, 'user' => $user];
    } catch (PDOException $e) {
        error_log('Authentication failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication failed. Please try again.'];
    }
}

function logoutUser(): void {
    if (isLoggedIn()) {
        logActivity(getCurrentUserId(), 'User logged out', 'User logged out');
    }
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    session_destroy();
}

function createPasswordResetToken(string $email): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'If the email exists, a reset link has been sent'];
        }

        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at)
             VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );
        $stmt->execute([':user_id' => $user['id'], ':token' => $hashedToken]);

        logActivity($user['id'], 'Password reset requested', 'Password reset token created');

        // Build reset link
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname(dirname($scriptPath)), '/');
        $resetLink = "$protocol://$host$basePath/reset-password.php?token=" . urlencode($token);

        return [
            'success' => true,
            'message' => 'Reset link generated successfully',
            'reset_link' => $resetLink,
        ];
    } catch (PDOException $e) {
        error_log('Password reset token creation failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create reset token'];
    }
}

function verifyResetToken(string $token): ?int {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT pr.id, pr.user_id, pr.token FROM password_resets pr
             WHERE pr.expires_at > NOW() AND pr.used = 0
             ORDER BY pr.created_at DESC LIMIT 1'
        );
        $stmt->execute();
        $resets = $pdo->query(
            'SELECT pr.id, pr.user_id, pr.token FROM password_resets pr
             WHERE pr.expires_at > NOW() AND pr.used = 0'
        )->fetchAll();

        foreach ($resets as $reset) {
            if (password_verify($token, $reset['token'])) {
                return (int)$reset['user_id'];
            }
        }
        return null;
    } catch (PDOException $e) {
        error_log('Reset token verification failed: ' . $e->getMessage());
        return null;
    }
}

function resetPassword(int $userId, string $newPassword): array {
    try {
        $pdo = getDbConnection();
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $passwordHash, ':id' => $userId]);

        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);

        logActivity($userId, 'Password reset', 'Password was reset successfully');

        return ['success' => true, 'message' => 'Password has been reset successfully'];
    } catch (PDOException $e) {
        error_log('Password reset failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to reset password'];
    }
}

define('MP_VERIFICATION_TTL', 300); // 5 minutes
define('MP_MAX_ATTEMPTS', 5);
define('MP_BLOCK_DURATION', 900); // 15 minutes

function verifyMasterPassword(int $userId, string $masterPassword, string $action = 'general'): array {
    // Check if currently blocked
    if (isVerificationBlocked()) {
        $remaining = ($_SESSION['mp_blocked_until'] ?? 0) - time();
        logActivity($userId, 'MP verification blocked', "Blocked for action $action, ${remaining}s remaining");
        return ['success' => false, 'message' => "Too many attempts. Try again in " . ceil($remaining / 60) . " minutes."];
    }

    if (empty($masterPassword)) {
        return ['success' => false, 'message' => 'Master password is required'];
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT master_password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if ($row && password_verify($masterPassword, $row['master_password_hash'])) {
            markActionVerified($action);
            $_SESSION['mp_attempts'] = 0;
            logActivity($userId, 'MP verification success', "Verified for action: $action");
            return ['success' => true, 'message' => 'Verified'];
        }

        // Failed attempt
        $_SESSION['mp_attempts'] = ($_SESSION['mp_attempts'] ?? 0) + 1;
        $remaining = MP_MAX_ATTEMPTS - $_SESSION['mp_attempts'];
        logActivity($userId, 'MP verification failed', "Incorrect for action $action, $remaining attempts remaining");

        if ($_SESSION['mp_attempts'] >= MP_MAX_ATTEMPTS) {
            $_SESSION['mp_blocked_until'] = time() + MP_BLOCK_DURATION;
            logActivity($userId, 'MP verification blocked', "Blocked for " . (MP_BLOCK_DURATION / 60) . " minutes");
            return ['success' => false, 'message' => 'Too many incorrect attempts. Blocked for 15 minutes.'];
        }

        return ['success' => false, 'message' => "Incorrect master password. $remaining attempt(s) remaining."];
    } catch (PDOException $e) {
        error_log('Master password verification failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Verification failed. Try again.'];
    }
}

function isVerificationBlocked(): bool {
    if (!empty($_SESSION['mp_blocked_until']) && $_SESSION['mp_blocked_until'] > time()) {
        return true;
    }
    if (!empty($_SESSION['mp_blocked_until']) && $_SESSION['mp_blocked_until'] <= time()) {
        unset($_SESSION['mp_blocked_until']);
        $_SESSION['mp_attempts'] = 0;
    }
    return false;
}

function markActionVerified(string $action): void {
    if (!isset($_SESSION['mp_actions'])) {
        $_SESSION['mp_actions'] = [];
    }
    $_SESSION['mp_actions'][$action] = time() + MP_VERIFICATION_TTL;
}

function isActionVerified(string $action): bool {
    if (empty($_SESSION['mp_actions'][$action])) {
        return false;
    }
    if ($_SESSION['mp_actions'][$action] < time()) {
        unset($_SESSION['mp_actions'][$action]);
        return false;
    }
    return true;
}

function requireActionVerified(string $action): void {
    if (!isActionVerified($action)) {
        echo json_encode(['success' => false, 'message' => 'Master password required', 'code' => 'MASTER_PASSWORD_REQUIRED']);
        exit;
    }
}

function getAllVerifiedActions(): array {
    $valid = [];
    if (!empty($_SESSION['mp_actions'])) {
        $now = time();
        foreach ($_SESSION['mp_actions'] as $action => $expires) {
            if ($expires > $now) {
                $valid[] = $action;
            }
        }
    }
    return $valid;
}

function lockVault(): void {
    unset($_SESSION['mp_actions']);
    unset($_SESSION['mp_attempts']);
    unset($_SESSION['mp_blocked_until']);
}

function getRemainingAttempts(): int {
    $used = $_SESSION['mp_attempts'] ?? 0;
    return max(0, MP_MAX_ATTEMPTS - $used);
}

function isMasterPasswordVerified(): bool {
    return !empty(getAllVerifiedActions());
}

// ====== PIN QUICK UNLOCK ======

define('PIN_MAX_ATTEMPTS', 3);

function isPinSet(int $userId): bool {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT pin_hash FROM users WHERE id = :id AND pin_hash IS NOT NULL');
        $stmt->execute([':id' => $userId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function setPin(int $userId, string $pin): array {
    if (!preg_match('/^\d{4,10}$/', $pin)) {
        return ['success' => false, 'message' => 'PIN must be 4-10 digits'];
    }
    try {
        $pdo = getDbConnection();
        $hash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $pdo->prepare('UPDATE users SET pin_hash = :hash, pin_set_at = NOW() WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $userId]);
        logActivity($userId, 'PIN set', 'Quick unlock PIN has been set');
        return ['success' => true, 'message' => 'PIN set successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to set PIN'];
    }
}

function removePin(int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE users SET pin_hash = NULL, pin_set_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        unset($_SESSION['pin_verified']);
        logActivity($userId, 'PIN removed', 'Quick unlock PIN has been removed');
        return ['success' => true, 'message' => 'PIN removed'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to remove PIN'];
    }
}

function verifyPin(int $userId, string $pin): array {
    if (!empty($_SESSION['pin_verified']) && $_SESSION['pin_verified'] > time()) {
        return ['success' => true, 'message' => 'Already verified'];
    }

    if (!empty($_SESSION['pin_blocked_until']) && $_SESSION['pin_blocked_until'] > time()) {
        $remaining = $_SESSION['pin_blocked_until'] - time();
        return ['success' => false, 'message' => "Too many attempts. Try again in $remaining seconds."];
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT pin_hash FROM users WHERE id = :id AND pin_hash IS NOT NULL');
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($pin, $hash)) {
            $_SESSION['pin_verified'] = time() + MP_VERIFICATION_TTL;
            $_SESSION['pin_attempts'] = 0;
            // Mark all common actions as verified
            $actions = ['view_password', 'copy_password', 'edit_credential', 'delete_credential', 'delete_website', 'bulk_delete', 'general'];
            foreach ($actions as $a) {
                markActionVerified($a);
            }
            logActivity($userId, 'PIN verified', 'Quick unlock via PIN');
            return ['success' => true, 'message' => 'PIN verified'];
        }

        $_SESSION['pin_attempts'] = ($_SESSION['pin_attempts'] ?? 0) + 1;
        $remaining = PIN_MAX_ATTEMPTS - $_SESSION['pin_attempts'];

        if ($_SESSION['pin_attempts'] >= PIN_MAX_ATTEMPTS) {
            $_SESSION['pin_blocked_until'] = time() + 300; // 5 min block
            logActivity($userId, 'PIN blocked', 'PIN attempts exceeded, blocked for 5 minutes');
            return ['success' => false, 'message' => 'Too many incorrect attempts. Blocked for 5 minutes.'];
        }

        return ['success' => false, 'message' => "Incorrect PIN. $remaining attempt(s) remaining."];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Verification failed'];
    }
}

function isPinSessionValid(): bool {
    return !empty($_SESSION['pin_verified']) && $_SESSION['pin_verified'] > time();
}

