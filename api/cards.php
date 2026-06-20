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

// Auto-migrate: create cards table if not exists
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'cards'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                card_type ENUM('debit','credit') NOT NULL DEFAULT 'credit',
                cardholder_name VARCHAR(255) NOT NULL,
                card_number_encrypted TEXT NOT NULL,
                last_four VARCHAR(4) DEFAULT NULL,
                expiry_month INT NOT NULL,
                expiry_year INT NOT NULL,
                cvv_encrypted TEXT NOT NULL,
                bank_name VARCHAR(255) DEFAULT NULL,
                card_network VARCHAR(50) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_favorite TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        error_log('Cards table auto-created');
    } else {
        // Ensure last_four column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM cards LIKE 'last_four'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE cards ADD COLUMN last_four VARCHAR(4) DEFAULT NULL AFTER card_number_encrypted");
        }
    }
} catch (PDOException $e) {
    error_log('Auto-migration failed: ' . $e->getMessage());
}

try {
    switch ($action) {
        case 'list':
            handleList($userId);
            break;
        case 'get':
            handleGet($userId);
            break;
        case 'create':
            handleCreate($userId, $input);
            break;
        case 'update':
            handleUpdate($userId, $input);
            break;
        case 'delete':
            handleDelete($userId, $input);
            break;
        case 'restore_card':
            handleRestoreCard($userId, $input);
            break;
        case 'permanent_delete_card':
            handlePermanentDeleteCard($userId, $input);
            break;
        case 'list_trashed':
            handleListTrashed($userId);
            break;
        case 'toggle_favorite':
            handleToggleFavorite($userId, $input);
            break;
        case 'export_html':
            handleExportHtml($userId);
            break;
        case 'export_pdf':
            handleExportPdf($userId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Cards API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleList($userId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, card_type, cardholder_name, last_four, expiry_month, expiry_year, bank_name, card_network, notes, is_favorite, created_at, updated_at FROM cards WHERE user_id = :uid AND deleted_at IS NULL ORDER BY is_favorite DESC, created_at DESC');
    $stmt->execute([':uid' => $userId]);
    $cards = $stmt->fetchAll();
    echo json_encode(['success' => true, 'cards' => $cards]);
}

function handleListTrashed($userId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, card_type, cardholder_name, last_four, expiry_month, expiry_year, bank_name, card_network, notes, is_favorite, created_at, updated_at, deleted_at FROM cards WHERE user_id = :uid AND deleted_at IS NOT NULL ORDER BY deleted_at DESC');
    $stmt->execute([':uid' => $userId]);
    $cards = $stmt->fetchAll();
    echo json_encode(['success' => true, 'cards' => $cards]);
}

function handleGet($userId) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE id = :id AND user_id = :uid AND deleted_at IS NULL');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $card = $stmt->fetch();
    if (!$card) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        return;
    }
    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption key unavailable']);
        return;
    }
    $card['card_number_decrypted'] = decryptPassword($card['card_number_encrypted'], $encKey['key'], $encKey['iv']);
    $card['cvv_decrypted'] = decryptPassword($card['cvv_encrypted'], $encKey['key'], $encKey['iv']);
    echo json_encode(['success' => true, 'card' => $card]);
}

function handleCreate($userId, $input) {
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    $cardType = $input['card_type'] ?? 'credit';
    $cardholderName = trim($input['cardholder_name'] ?? '');
    $cardNumber = preg_replace('/\D/', '', $input['card_number'] ?? '');
    $expiryMonth = (int)($input['expiry_month'] ?? 0);
    $expiryYear = (int)($input['expiry_year'] ?? 0);
    $cvv = $input['cvv'] ?? '';
    $bankName = trim($input['bank_name'] ?? '');
    $cardNetwork = !empty($input['card_network']) ? $input['card_network'] : detectCardNetwork($cardNumber);
    $notes = trim($input['notes'] ?? '');

    if (!$cardholderName || !$cardNumber || !$expiryMonth || !$expiryYear || !$cvv || !$bankName) {
        echo json_encode(['success' => false, 'message' => 'Cardholder name, card number, expiry, CVV, and bank name are required']);
        return;
    }
    if ($expiryMonth < 1 || $expiryMonth > 12) {
        echo json_encode(['success' => false, 'message' => 'Invalid expiry month']);
        return;
    }
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    if ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        echo json_encode(['success' => false, 'message' => 'Card has already expired']);
        return;
    }
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        echo json_encode(['success' => false, 'message' => 'Invalid card number length']);
        return;
    }
    // Luhn algorithm check
    $sum = 0;
    $alt = false;
    for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
        $d = (int)$cardNumber[$i];
        if ($alt) { $d *= 2; if ($d > 9) $d -= 9; }
        $sum += $d;
        $alt = !$alt;
    }
    if ($sum % 10 !== 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid card number (failed checksum)']);
        return;
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CVV']);
        return;
    }

    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption key unavailable']);
        return;
    }
    $cardNumberEncrypted = encryptPassword($cardNumber, $encKey['key'], $encKey['iv']);
    $cvvEncrypted = encryptPassword($cvv, $encKey['key'], $encKey['iv']);
    $lastFour = substr($cardNumber, -4);

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('INSERT INTO cards (user_id, card_type, cardholder_name, card_number_encrypted, last_four, expiry_month, expiry_year, cvv_encrypted, bank_name, card_network, notes) VALUES (:uid, :ct, :chn, :cne, :lf, :em, :ey, :cve, :bn, :cnw, :nt)');
    $stmt->execute([
        ':uid' => $userId,
        ':ct' => $cardType,
        ':chn' => $cardholderName,
        ':cne' => $cardNumberEncrypted,
        ':lf' => $lastFour,
        ':em' => $expiryMonth,
        ':ey' => $expiryYear,
        ':cve' => $cvvEncrypted,
        ':bn' => $bankName,
        ':cnw' => $cardNetwork,
        ':nt' => $notes
    ]);

    $cardId = $pdo->lastInsertId();
    logActivity($userId, 'card_added', 'Added card: ' . $cardholderName . ' (' . $cardNetwork . ')');

    echo json_encode(['success' => true, 'message' => 'Card saved', 'id' => $cardId]);
}

function handleUpdate($userId, $input) {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        return;
    }

    $cardType = $input['card_type'] ?? $existing['card_type'];
    $cardholderName = trim($input['cardholder_name'] ?? $existing['cardholder_name']);
    $expiryMonth = (int)($input['expiry_month'] ?? $existing['expiry_month']);
    $expiryYear = (int)($input['expiry_year'] ?? $existing['expiry_year']);
    $bankName = trim($input['bank_name'] ?? $existing['bank_name'] ?? '');
    $notes = trim($input['notes'] ?? $existing['notes'] ?? '');

    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    if ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        echo json_encode(['success' => false, 'message' => 'Card has already expired']);
        return;
    }

    $cardNumberEncrypted = $existing['card_number_encrypted'];
    $cvvEncrypted = $existing['cvv_encrypted'];
    $cardNetwork = !empty($input['card_network']) ? $input['card_network'] : $existing['card_network'];
    $lastFour = $existing['last_four'] ?? null;

    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption key unavailable']);
        return;
    }
    if (!empty($input['card_number'])) {
        $cardNumber = preg_replace('/\D/', '', $input['card_number']);
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            echo json_encode(['success' => false, 'message' => 'Invalid card number length']);
            return;
        }
        $sum = 0;
        $alt = false;
        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $d = (int)$cardNumber[$i];
            if ($alt) { $d *= 2; if ($d > 9) $d -= 9; }
            $sum += $d;
            $alt = !$alt;
        }
        if ($sum % 10 !== 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid card number (failed checksum)']);
            return;
        }
        $cardNumberEncrypted = encryptPassword($cardNumber, $encKey['key'], $encKey['iv']);
        $cardNetwork = detectCardNetwork($cardNumber);
        $lastFour = substr($cardNumber, -4);
    }
    if (!empty($input['cvv'])) {
        if (!preg_match('/^\d{3,4}$/', $input['cvv'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid CVV']);
            return;
        }
        $cvvEncrypted = encryptPassword($input['cvv'], $encKey['key'], $encKey['iv']);
    }

    $stmt = $pdo->prepare('UPDATE cards SET card_type = :ct, cardholder_name = :chn, card_number_encrypted = :cne, last_four = :lf, expiry_month = :em, expiry_year = :ey, cvv_encrypted = :cve, bank_name = :bn, card_network = :cnw, notes = :nt WHERE id = :id AND user_id = :uid');
    $stmt->execute([
        ':ct' => $cardType,
        ':chn' => $cardholderName,
        ':cne' => $cardNumberEncrypted,
        ':lf' => $lastFour,
        ':em' => $expiryMonth,
        ':ey' => $expiryYear,
        ':cve' => $cvvEncrypted,
        ':bn' => $bankName,
        ':cnw' => $cardNetwork,
        ':nt' => $notes,
        ':id' => $id,
        ':uid' => $userId
    ]);

    logActivity($userId, 'card_updated', 'Updated card: ' . $cardholderName);
    echo json_encode(['success' => true, 'message' => 'Card updated']);
}

function handleDelete($userId, $input) {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE cards SET deleted_at = NOW() WHERE id = :id AND user_id = :uid AND deleted_at IS NULL');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    if ($stmt->rowCount() > 0) {
        logActivity($userId, 'card_deleted', 'Moved card to trash');
        echo json_encode(['success' => true, 'message' => 'Card moved to trash']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
}

function handleRestoreCard($userId, $input) {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE cards SET deleted_at = NULL WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    if ($stmt->rowCount() > 0) {
        logActivity($userId, 'card_restored', 'Restored card from trash');
        echo json_encode(['success' => true, 'message' => 'Card restored']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
}

function handlePermanentDeleteCard($userId, $input) {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('DELETE FROM cards WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    if ($stmt->rowCount() > 0) {
        logActivity($userId, 'card_permanent_deleted', 'Permanently deleted card');
        echo json_encode(['success' => true, 'message' => 'Card permanently deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
}

function handleToggleFavorite($userId, $input) {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE cards SET is_favorite = CASE WHEN is_favorite = 1 THEN 0 ELSE 1 END WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Toggled favorite']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
}

function detectCardNetwork($number) {
    $first = substr($number, 0, 1);
    $firstTwo = substr($number, 0, 2);
    $firstFour = substr($number, 0, 4);
    $fourInt = (int)$firstFour;
    if ($first == '4') return 'visa';
    if (in_array($firstTwo, ['51','52','53','54','55']) || ($fourInt >= 2221 && $fourInt <= 2720)) return 'mastercard';
    if (in_array($firstTwo, ['34', '37'])) return 'amex';
    if ($firstFour == '6011' || ($fourInt >= 6221 && $fourInt <= 6229) || ($fourInt >= 6440 && $fourInt <= 6499) || $firstTwo == '65') return 'discover';
    if (in_array($firstTwo, ['30', '36', '38', '39'])) return 'diners';
    if (in_array($firstFour, ['5018', '5020', '5038', '5893', '6304', '6759', '6761', '6762', '6763'])) return 'maestro';
    if ($firstFour == '6060' || $firstFour == '6070' || $firstTwo == '81' || $firstTwo == '82' || in_array($firstFour, ['5085', '3531'])) return 'rupay';
    return 'unknown';
}

function getAllDecryptedCards($userId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE user_id = :uid AND deleted_at IS NULL ORDER BY is_favorite DESC, created_at DESC');
    $stmt->execute([':uid' => $userId]);
    $cards = $stmt->fetchAll();
    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) return [];
    foreach ($cards as &$card) {
        $card['card_number_decrypted'] = decryptPassword($card['card_number_encrypted'], $encKey['key'], $encKey['iv']);
        $card['cvv_decrypted'] = decryptPassword($card['cvv_encrypted'], $encKey['key'], $encKey['iv']);
    }
    return $cards;
}

function handleExportHtml($userId) {
    $cards = getAllDecryptedCards($userId);
    $date = date('Y-m-d H:i');
    $count = count($cards);
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $rowsHtml = '';
    foreach ($cards as $c) {
        $fav = $c['is_favorite'] ? ' ★' : '';
        $num = $c['card_number_decrypted'] ?? '****';
        $cvv = $c['cvv_decrypted'] ?? '***';
        $exp = sprintf('%02d/%d', $c['expiry_month'], $c['expiry_year']);
        $type = $c['card_type'] === 'debit' ? 'Debit' : 'Credit';
        $rowsHtml .= '<tr>
            <td>' . $esc($c['cardholder_name']) . $fav . '</td>
            <td>' . $esc($num) . '</td>
            <td>' . $esc($exp) . '</td>
            <td>' . $esc($cvv) . '</td>
            <td>' . $esc($c['bank_name'] ?? '-') . '</td>
            <td>' . $esc($c['card_network'] ?? '-') . '</td>
            <td>' . $esc($type) . '</td>
            <td>' . $esc($c['notes'] ?? '-') . '</td>
        </tr>' . "\n";
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cards Report - RS PAASWORD MANAGER</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:"Segoe UI",-apple-system,system-ui,sans-serif; background:#f1f5f9; color:#1e293b; line-height:1.6; }
  .report-wrapper { max-width:1200px; margin:0 auto; padding:30px 20px; }
  .report-header { background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); border-radius:16px 16px 0 0; padding:36px 40px 28px; position:relative; overflow:hidden; }
  .report-header::before { content:""; position:absolute; top:-50%; right:-20%; width:300px; height:300px; background:radial-gradient(circle,rgba(99,102,241,0.15) 0%,transparent 70%); border-radius:50%; }
  .report-header .brand { font-size:1.5rem; font-weight:800; letter-spacing:-0.5px; background:linear-gradient(135deg,#818cf8,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
  .report-header h1 { color:#fff; font-size:1.8rem; font-weight:700; margin-top:8px; letter-spacing:-0.5px; }
  .report-header .meta { color:#94a3b8; font-size:0.85rem; margin-top:6px; display:flex; gap:20px; flex-wrap:wrap; }
  .report-body { background:#fff; padding:32px 40px; border-radius:0 0 16px 16px; box-shadow:0 4px 24px rgba(0,0,0,0.06); }
  .stats-row { display:flex; gap:24px; margin-bottom:28px; flex-wrap:wrap; }
  .stat-box { flex:1; min-width:140px; background:linear-gradient(135deg,#f8faff,#f1f5f9); border-radius:12px; padding:18px 20px; border:1px solid #eef2f7; text-align:center; }
  .stat-box .stat-value { font-size:1.8rem; font-weight:800; color:#1e293b; line-height:1.2; }
  .stat-box .stat-label { font-size:0.8rem; color:#64748b; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; }
  .stat-box.accent { background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-color:#c7d2fe; }
  .stat-box.accent .stat-value { color:#4f46e5; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  thead th { padding:12px 16px; background:linear-gradient(135deg,#1e293b,#334155); color:#fff; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; text-align:left; border:none; }
  thead th:first-child { border-radius:8px 0 0 0; }
  thead th:last-child { border-radius:0 8px 0 0; }
  tbody tr:nth-child(even) { background:#f8faff; }
  tbody td { padding:12px 16px; border-bottom:1px solid #eef2f7; font-size:0.875rem; color:#475569; }
  tbody tr:last-child td { border-bottom:none; }
  .report-footer { text-align:center; padding:24px 0 8px; color:#94a3b8; font-size:0.8rem; border-top:1px solid #eef2f7; margin-top:28px; }
  .report-footer strong { color:#475569; }
  @media print { body { background:#fff; } .report-header { border-radius:0; } .report-body { box-shadow:none; } }
  @media (max-width:768px) { .report-wrapper { padding:16px 10px; } .report-header { padding:24px 20px 20px; border-radius:12px 12px 0 0; } .report-header h1 { font-size:1.3rem; } .report-body { padding:20px 16px; } .stats-row { gap:12px; } .stat-box { min-width:100px; padding:12px 14px; } .stat-box .stat-value { font-size:1.3rem; } .report-header .meta { font-size:0.75rem; gap:10px; } thead th, tbody td { padding:8px 10px; font-size:0.7rem; } }
  @media (max-width:480px) { .report-header { padding:18px 14px 16px; } .report-header h1 { font-size:1.1rem; } .report-body { padding:14px 10px; } .stats-row { flex-direction:column; gap:8px; } .stat-box { min-width:auto; } thead th, tbody td { padding:6px 6px; font-size:0.65rem; } .report-footer { font-size:0.65rem; } }
</style>
</head>
<body>
<div class="report-wrapper">
  <div class="report-header">
    <div class="brand">RS PAASWORD MANAGER</div>
    <h1>Cards Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . $date . '</span>
      <span><strong style="color:#e2e8f0">Total Cards:</strong> ' . $count . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box accent">
        <div class="stat-value">' . $count . '</div>
        <div class="stat-label">Total Cards</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">' . count(array_filter($cards, fn($x) => $x['card_type'] === 'debit')) . '</div>
        <div class="stat-label">Debit</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">' . count(array_filter($cards, fn($x) => $x['card_type'] === 'credit')) . '</div>
        <div class="stat-label">Credit</div>
      </div>
    </div>
    <table>
      <thead><tr><th>Cardholder</th><th>Card Number</th><th>Expiry</th><th>CVV</th><th>Bank</th><th>Network</th><th>Type</th><th>Notes</th></tr></thead>
      <tbody>' . $rowsHtml . '</tbody>
    </table>
    <div class="report-footer">RS PAASWORD MANAGER &bull; Confidential &bull; Generated ' . $date . ' &bull; <strong>' . $count . '</strong> card' . ($count !== 1 ? 's' : '') . '</div>
  </div>
</div>
</body>
</html>';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="cards_export_' . date('Y-m-d') . '.html"');
    echo $html;
    exit;
}

function handleExportPdf($userId) {
    require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

    $cards = getAllDecryptedCards($userId);
    $date = date('Y-m-d H:i');
    $count = count($cards);
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    class RS_Cards_PDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 0, 'Cards Report', 0, 1, 'L');
            $this->SetDrawColor(99, 102, 241);
            $this->SetLineWidth(0.6);
            $this->Line($this->GetX(), $this->GetY() + 2, $this->GetX() + ($this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right']), $this->GetY() + 2);
            $this->Ln(6);
        }
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', '', 7.5);
            $this->SetTextColor(148, 163, 184);
            $this->Cell(0, 10, 'RS PAASWORD MANAGER  |  Confidential  |  Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    $pdf = new RS_Cards_PDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RS PAASWORD MANAGER');
    $pdf->SetAuthor('RS PAASWORD MANAGER');
    $pdf->SetTitle('Cards Report');
    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetMargins(15, 32, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $debitCount = 0; $creditCount = 0;
    foreach ($cards as $c) { if ($c['card_type'] === 'debit') $debitCount++; else $creditCount++; }

    $cardW = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - 12) / 3;
    $startY = $pdf->GetY();
    $cols = [
        ['label' => 'Total Cards', 'value' => $count, 'fill' => [238, 242, 255], 'text' => [79, 70, 229]],
        ['label' => 'Debit', 'value' => $debitCount, 'fill' => [240, 253, 244], 'text' => [22, 163, 74]],
        ['label' => 'Credit', 'value' => $creditCount, 'fill' => [239, 246, 255], 'text' => [37, 99, 235]],
    ];
    for ($i = 0; $i < 3; $i++) {
        $x = $pdf->getMargins()['left'] + ($i * ($cardW + 6));
        $pdf->SetFillColor($cols[$i]['fill'][0], $cols[$i]['fill'][1], $cols[$i]['fill'][2]);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Rect($x, $startY, $cardW, 18, 'DF');
        $pdf->SetXY($x, $startY + 3);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell($cardW, 5, $cols[$i]['label'], 0, 1, 'C');
        $pdf->SetX($x);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor($cols[$i]['text'][0], $cols[$i]['text'][1], $cols[$i]['text'][2]);
        $pdf->Cell($cardW, 8, (string)$cols[$i]['value'], 0, 1, 'C');
    }

    $pdf->SetY($startY + 18 + 10);

    $tableW = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
    $colW = [35, 45, 20, 15, 30, 25, 18, 35];

    $pdf->SetFont('helvetica', 'B', 7.5);
    $pdf->SetFillColor(30, 41, 59);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(30, 41, 59);
    $headers = ['Cardholder', 'Card Number', 'Expiry', 'CVV', 'Bank', 'Network', 'Type', 'Notes'];
    $aligns  = ['L', 'L', 'C', 'C', 'L', 'L', 'C', 'L'];
    foreach ($headers as $j => $h) {
        $pdf->Cell($colW[$j], 8, $h, 1, 0, $aligns[$j], 1);
    }
    $pdf->Ln();

    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetDrawColor(226, 232, 240);

    foreach ($cards as $i => $c) {
        $fav = $c['is_favorite'] ? ' ★' : '';
        $num = $c['card_number_decrypted'] ?? '****';
        $cvv = $c['cvv_decrypted'] ?? '***';
        $exp = sprintf('%02d/%d', $c['expiry_month'], $c['expiry_year']);
        $type = $c['card_type'] === 'debit' ? 'Debit' : 'Credit';
        $fill = ($i % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        $pdf->Cell($colW[0], 7, $esc($c['cardholder_name'] . $fav), 1, 0, 'L', 1);
        $pdf->Cell($colW[1], 7, $esc($num), 1, 0, 'L', 1);
        $pdf->Cell($colW[2], 7, $exp, 1, 0, 'C', 1);
        $pdf->Cell($colW[3], 7, $cvv, 1, 0, 'C', 1);
        $pdf->Cell($colW[4], 7, $esc($c['bank_name'] ?? '-'), 1, 0, 'L', 1);
        $pdf->Cell($colW[5], 7, $esc(ucfirst($c['card_network'] ?? '-')), 1, 0, 'L', 1);
        $pdf->Cell($colW[6], 7, $type, 1, 0, 'C', 1);
        $pdf->Cell($colW[7], 7, $esc($c['notes'] ?? '-'), 1, 1, 'L', 1);
    }

    $pdf->Output('cards_export_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
