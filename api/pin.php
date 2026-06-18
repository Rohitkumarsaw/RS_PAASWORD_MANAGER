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
        case 'status':
            handlePinStatus($userId);
            break;
        case 'verify':
            handleVerifyPin($userId, $input);
            break;
        case 'set':
            handleSetPin($userId, $input);
            break;
        case 'remove':
            handleRemovePin($userId, $input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handlePinStatus(int $userId): void {
    $hasPin = isPinSet($userId);
    $remaining = PIN_MAX_ATTEMPTS - ($_SESSION['pin_attempts'] ?? 0);
    $blockedUntil = $_SESSION['pin_blocked_until'] ?? 0;
    $blocked = $blockedUntil > time();
    echo json_encode([
        'success' => true,
        'has_pin' => $hasPin,
        'attempts_remaining' => max(0, $remaining),
        'blocked' => $blocked,
        'blocked_until' => $blockedUntil,
    ]);
}

function handleVerifyPin(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $pin = $input['pin'] ?? '';
    if (empty($pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN is required']);
        return;
    }
    $result = verifyPin($userId, $pin);
    echo json_encode($result);
}

function handleSetPin(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $masterPassword = $input['master_password'] ?? '';
    $pin = $input['pin'] ?? '';
    $confirmPin = $input['confirm_pin'] ?? '';

    if (empty($masterPassword)) {
        echo json_encode(['success' => false, 'message' => 'Master password is required']);
        return;
    }
    if (empty($pin) || $pin !== $confirmPin) {
        echo json_encode(['success' => false, 'message' => 'PINs do not match']);
        return;
    }
    if (!preg_match('/^\d{4,10}$/', $pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be 4-10 digits']);
        return;
    }

    // Verify master password first
    $mpResult = verifyMasterPassword($userId, $masterPassword, 'general');
    if (!$mpResult['success']) {
        echo json_encode($mpResult);
        return;
    }

    $result = setPin($userId, $pin);
    echo json_encode($result);
}

function handleRemovePin(int $userId, array $input): void {
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

    $result = removePin($userId);
    echo json_encode($result);
}
