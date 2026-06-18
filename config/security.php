<?php

define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('SESSION_LIFETIME', getenv('SESSION_LIFETIME') ?: 1800);
define('MAX_LOGIN_ATTEMPTS', getenv('MAX_LOGIN_ATTEMPTS') ?: 5);
define('LOGIN_TIMEOUT', getenv('LOGIN_TIMEOUT') ?: 900);

function encryptPassword(string $plaintext, string $key, string $iv): string {
    $encrypted = openssl_encrypt($plaintext, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        error_log('Encryption failed: ' . openssl_error_string());
        throw new RuntimeException('Encryption failed');
    }
    return base64_encode($encrypted);
}

function decryptPassword(string $ciphertext, string $key, string $iv): string {
    $decoded = base64_decode($ciphertext, true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid ciphertext encoding');
    }
    $decrypted = openssl_decrypt($decoded, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        error_log('Decryption failed: ' . openssl_error_string());
        throw new RuntimeException('Decryption failed');
    }
    return $decrypted;
}

function generateEncryptionKey(): string {
    return openssl_random_pseudo_bytes(32);
}

function generateIv(): string {
    $ivLen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    return openssl_random_pseudo_bytes($ivLen);
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function sanitizeOutput(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateUsername(string $username): bool {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username) === 1;
}

function validatePasswordStrength(string $password): array {
    $score = 0;
    $feedback = [];

    if (strlen($password) >= 8) { $score += 20; }
    if (strlen($password) >= 12) { $score += 10; }
    if (preg_match('/[A-Z]/', $password)) { $score += 15; }
    if (preg_match('/[a-z]/', $password)) { $score += 15; }
    if (preg_match('/[0-9]/', $password)) { $score += 15; }
    if (preg_match('/[^a-zA-Z0-9]/', $password)) { $score += 15; }
    if (strlen($password) >= 16) { $score += 10; }

    if ($score < 30) {
        $feedback[] = 'Very weak - add more characters and variety';
    } elseif ($score < 50) {
        $feedback[] = 'Weak - include uppercase, numbers, and symbols';
    } elseif ($score < 70) {
        $feedback[] = 'Fair - consider making it longer';
    } elseif ($score < 90) {
        $feedback[] = 'Strong';
    } else {
        $feedback[] = 'Very strong';
    }

    return ['score' => min(100, $score), 'feedback' => $feedback];
}

function generateStrongPassword(int $length = 20, bool $useUpper = true, bool $useLower = true, bool $useDigits = true, bool $useSymbols = true): string {
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $symbols = '!@#$%^&*()-_=+[]{}|;:,.<>?';

    $chars = '';
    if ($useUpper) { $chars .= $upper; }
    if ($useLower) { $chars .= $lower; }
    if ($useDigits) { $chars .= $digits; }
    if ($useSymbols) { $chars .= $symbols; }

    if (empty($chars)) {
        $chars = $lower . $digits;
    }

    $max = strlen($chars) - 1;
    $password = '';

    if ($useUpper) { $password .= $upper[random_int(0, 25)]; }
    if ($useLower) { $password .= $lower[random_int(0, 25)]; }
    if ($useDigits) { $password .= $digits[random_int(0, 9)]; }
    if ($useSymbols) { $password .= $symbols[random_int(0, strlen($symbols) - 1)]; }

    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }

    $password = str_shuffle($password);
    return $password;
}

function generateRememberToken(): string {
    return bin2hex(random_bytes(32));
}

function logActivity(int $userId, string $action, string $details = null): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (:user_id, :action, :details, :ip_address, :user_agent)'
        );
        $stmt->execute([
            ':user_id'   => $userId,
            ':action'    => $action,
            ':details'   => $details,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

function checkLoginAttempts(string $email, string $ip): bool {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as attempts FROM login_attempts
             WHERE (email = :email OR ip_address = :ip)
             AND success = 0
             AND attempted_at > DATE_SUB(NOW(), INTERVAL :timeout SECOND)'
        );
        $stmt->execute([
            ':email'   => $email,
            ':ip'      => $ip,
            ':timeout' => LOGIN_TIMEOUT,
        ]);
        $result = $stmt->fetch();
        return $result['attempts'] < MAX_LOGIN_ATTEMPTS;
    } catch (PDOException $e) {
        error_log('Failed to check login attempts: ' . $e->getMessage());
        return true;
    }
}

function recordLoginAttempt(string $email, string $ip, bool $success): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, success) VALUES (:email, :ip, :success)'
        );
        $stmt->execute([
            ':email'   => $email,
            ':ip'      => $ip,
            ':success' => $success ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        error_log('Failed to record login attempt: ' . $e->getMessage());
    }
}

function checkSessionTimeout(): void {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
