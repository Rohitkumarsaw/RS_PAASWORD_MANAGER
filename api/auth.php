<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/totp.php';

session_start();
header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_session':
        echo json_encode(['logged_in' => isLoggedIn()]);
        break;
    case 'verify_2fa':
        handleVerify2FA();
        break;
    case 'ping':
        echo json_encode(['logged_in' => isLoggedIn()]);
        break;
    case 'forgot_password':
        handleForgotPassword();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function handleRegister(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $masterPassword = $input['master_password'] ?? '';

    if (!validateUsername($username)) {
        echo json_encode(['success' => false, 'message' => 'Username must be 3-50 characters (letters, numbers, underscores)']);
        return;
    }
    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }
    if (strlen($masterPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Master password must be at least 8 characters']);
        return;
    }

    $result = registerUser($username, $email, $password, $masterPassword);
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Registration successful. You can now log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Registration failed']);
    }
}

function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        return;
    }

    // Validate credentials manually (don't create session yet)
    $pdo = getDbConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!checkLoginAttempts($email, $ip)) {
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($email, $ip, false);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        return;
    }

    recordLoginAttempt($email, $ip, true);

    // Check if 2FA is enabled
    if (!empty($user['twofa_secret']) && (int)$user['is_2fa_enabled'] === 1) {
        // Generate temp token for 2FA step
        $tempToken = bin2hex(random_bytes(32));
        $_SESSION['2fa_temp_user_id'] = (int)$user['id'];
        $_SESSION['2fa_temp_token'] = $tempToken;
        $_SESSION['2fa_temp_expires'] = time() + 300; // 5 min
        echo json_encode([
            'success' => true,
            'require_2fa' => true,
            'temp_token' => $tempToken,
            'message' => '2FA code required'
        ]);
        return;
    }

    // Complete login
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    logActivity($user['id'], 'User logged in', 'User logged in from IP: ' . $ip);

    if (session_regenerate_id()) {
        session_regenerate_id(true);
    }

    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
    unset($_SESSION['redirect_after_login']);
    echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => $redirect]);
}

function handleLogout(): void {
    logoutUser();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleForgotPassword(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $email = trim($input['email'] ?? '');

    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }

    $result = createPasswordResetToken($email);
    echo json_encode($result);
}

function handleResetPassword(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid reset token']);
        return;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    $userId = verifyResetToken($token);
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        return;
    }

    $result = resetPassword($userId, $password);
    echo json_encode($result);
}

function handleVerify2FA(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $code = trim($input['code'] ?? '');
    $tempToken = trim($input['temp_token'] ?? '');

    // Validate temp session
    $expectedToken = $_SESSION['2fa_temp_token'] ?? '';
    $expectedUserId = $_SESSION['2fa_temp_user_id'] ?? 0;
    $expires = $_SESSION['2fa_temp_expires'] ?? 0;

    if (empty($expectedToken) || empty($expectedUserId) || time() > $expires) {
        echo json_encode(['success' => false, 'message' => '2FA session expired. Please log in again.', 'redirect' => 'login.php']);
        return;
    }
    if (!hash_equals($expectedToken, $tempToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification session']);
        return;
    }

    // Get user's 2FA secret
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT twofa_secret FROM users WHERE id = :id AND is_2fa_enabled = 1");
    $stmt->execute([':id' => $expectedUserId]);
    $secret = $stmt->fetchColumn();

    if (empty($secret)) {
        echo json_encode(['success' => false, 'message' => '2FA is not enabled for this account']);
        return;
    }

    if (!verifyTOTP($secret, $code)) {
        echo json_encode(['success' => false, 'message' => 'Invalid 2FA code. Try again.']);
        return;
    }

    // Complete login
    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $userStmt->execute([':id' => $expectedUserId]);
    $user = $userStmt->fetch();

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Clear 2FA temp session
    unset($_SESSION['2fa_temp_user_id'], $_SESSION['2fa_temp_token'], $_SESSION['2fa_temp_expires']);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    logActivity($user['id'], 'User logged in', 'User logged in from IP: ' . $ip . ' (2FA)');

    if (session_regenerate_id()) {
        session_regenerate_id(true);
    }

    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
    unset($_SESSION['redirect_after_login']);
    echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => $redirect]);
}
