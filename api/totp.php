<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/totp.php';

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
        case 'status':
            handleStatus($userId);
            break;
        case 'setup':
            handleSetup($userId, $input);
            break;
        case 'verify_setup':
            handleVerifySetup($userId, $input);
            break;
        case 'disable':
            handleDisable($userId, $input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleStatus(int $userId): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT is_2fa_enabled FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $enabled = (bool)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'enabled' => $enabled]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to check status']);
    }
}

function handleSetup(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $masterPassword = $input['master_password'] ?? '';
    if (empty($masterPassword)) {
        echo json_encode(['success' => false, 'message' => 'Master password is required']);
        return;
    }

    $mpResult = verifyMasterPassword($userId, $masterPassword, 'general');
    if (!$mpResult['success']) {
        echo json_encode($mpResult);
        return;
    }

    $user = getCurrentUser();
    $username = $user['username'] ?? 'user';

    $secret = generateTOTPSecret();
    $qrUrl = getTOTPQRCodeUrl($username, $secret);
    $provisioningUri = getTOTPProvisioningUri($username, $secret);

    // Store secret temporarily in session for verification
    $_SESSION['totp_pending_secret'] = $secret;

    echo json_encode([
        'success' => true,
        'secret' => $secret,
        'qr_url' => $qrUrl,
        'provisioning_uri' => $provisioningUri,
    ]);
}

function handleVerifySetup(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }

    $code = trim($input['code'] ?? '');
    $secret = $_SESSION['totp_pending_secret'] ?? '';

    if (empty($secret)) {
        echo json_encode(['success' => false, 'message' => 'No pending 2FA setup. Start setup again.']);
        return;
    }
    if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid 6-digit code']);
        return;
    }

    if (!verifyTOTP($secret, $code)) {
        echo json_encode(['success' => false, 'message' => 'Invalid code. Try again.']);
        return;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET twofa_secret = :secret, is_2fa_enabled = 1 WHERE id = :id");
        $stmt->execute([':secret' => $secret, ':id' => $userId]);
        unset($_SESSION['totp_pending_secret']);
        logActivity($userId, '2FA enabled', 'Two-factor authentication has been enabled');
        echo json_encode(['success' => true, 'message' => '2FA enabled successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to enable 2FA']);
    }
}

function handleDisable(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $masterPassword = $input['master_password'] ?? '';
    if (empty($masterPassword)) {
        echo json_encode(['success' => false, 'message' => 'Master password is required']);
        return;
    }

    $mpResult = verifyMasterPassword($userId, $masterPassword, 'general');
    if (!$mpResult['success']) {
        echo json_encode($mpResult);
        return;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET twofa_secret = NULL, is_2fa_enabled = 0 WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        logActivity($userId, '2FA disabled', 'Two-factor authentication has been disabled');
        echo json_encode(['success' => true, 'message' => '2FA disabled successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to disable 2FA']);
    }
}
