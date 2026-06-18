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
    $stmt = $pdo->prepare('SELECT id, card_type, cardholder_name, last_four, expiry_month, expiry_year, bank_name, card_network, notes, is_favorite, created_at, updated_at FROM cards WHERE user_id = :uid ORDER BY is_favorite DESC, created_at DESC');
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
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE id = :id AND user_id = :uid');
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
    $stmt = $pdo->prepare('DELETE FROM cards WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    if ($stmt->rowCount() > 0) {
        logActivity($userId, 'card_deleted', 'Deleted card');
        echo json_encode(['success' => true, 'message' => 'Card deleted']);
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
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE user_id = :uid ORDER BY is_favorite DESC, created_at DESC');
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
<meta charset="UTF-8">
<title>Cards Export</title>
<style>
* { margin:0; padding:0; box-sizing:border-box }
body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; padding:30px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh }
.wrapper { max-width:1200px; margin:0 auto; background:rgba(255,255,255,0.95); border-radius:16px; padding:32px; box-shadow:0 20px 60px rgba(0,0,0,0.3) }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid #eef2ff }
.header h1 { font-size:1.6rem; color:#1e1b4b }
.header h1 span { color:#4f46e5 }
.meta { color:#64748b; font-size:0.85rem }
table { width:100%; border-collapse:separate; border-spacing:0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.06) }
thead th { background:linear-gradient(135deg,#4f46e5 0%,#6366f1 100%); color:#fff; padding:14px 16px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.06em }
tbody tr:nth-child(even) { background:#f8fafc }
td { padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:0.875rem; color:#1e293b }
.footer { margin-top:24px; padding-top:16px; border-top:1px solid #f1f5f9; text-align:center; color:#94a3b8; font-size:0.8rem }
</style>
</head>
<body>
<div class="wrapper">
<div class="header">
<h1><span>RS</span> PAASWORD MANAGER &mdash; Cards</h1>
<div class="meta">Exported ' . $date . ' &middot; ' . $count . ' card(s)</div>
</div>
<table>
<thead><tr><th>Cardholder</th><th>Card Number</th><th>Expiry</th><th>CVV</th><th>Bank</th><th>Network</th><th>Type</th><th>Notes</th></tr></thead>
<tbody>' . $rowsHtml . '</tbody>
</table>
<div class="footer">Generated by RS PAASWORD MANAGER &mdash; Confidential</div>
</div>
</body>
</html>';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="cards_export_' . date('Y-m-d') . '.html"');
    echo $html;
    exit;
}

function handleExportPdf($userId) {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

    $cards = getAllDecryptedCards($userId);
    $date = date('Y-m-d H:i');
    $count = count($cards);
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RS PAASWORD MANAGER');
    $pdf->SetTitle('Cards Export');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    $rowsHtml = '';
    foreach ($cards as $c) {
        $fav = $c['is_favorite'] ? ' ★' : '';
        $num = $c['card_number_decrypted'] ?? '****';
        $cvv = $c['cvv_decrypted'] ?? '***';
        $exp = sprintf('%02d/%d', $c['expiry_month'], $c['expiry_year']);
        $type = $c['card_type'] === 'debit' ? 'Debit' : 'Credit';
        $rowsHtml .= '<tr>
            <td style="font-size:9pt">' . $esc($c['cardholder_name']) . $fav . '</td>
            <td style="font-size:9pt;font-family:monospace">' . $esc($num) . '</td>
            <td style="font-size:9pt">' . $esc($exp) . '</td>
            <td style="font-size:9pt;font-family:monospace">' . $esc($cvv) . '</td>
            <td style="font-size:9pt">' . $esc($c['bank_name'] ?? '-') . '</td>
            <td style="font-size:9pt">' . $esc($c['card_network'] ?? '-') . '</td>
            <td style="font-size:9pt">' . $esc($type) . '</td>
            <td style="font-size:9pt">' . $esc($c['notes'] ?? '-') . '</td>
        </tr>';
    }

    $html = '<h1 style="color:#1e1b4b;font-size:18pt;margin-bottom:4px"><span style="color:#4f46e5">RS</span> PAASWORD MANAGER</h1>
    <p style="color:#64748b;font-size:9pt;margin-bottom:16px;border-bottom:2px solid #eef2ff;padding-bottom:8px">Cards Export &middot; ' . $date . ' &middot; ' . $count . ' card(s)</p>
    <table border="1" cellpadding="5" cellspacing="0">
    <thead>
    <tr style="background-color:#4f46e5;color:#fff">
    <th style="font-size:8pt;font-weight:bold">Cardholder</th>
    <th style="font-size:8pt;font-weight:bold">Card Number</th>
    <th style="font-size:8pt;font-weight:bold">Expiry</th>
    <th style="font-size:8pt;font-weight:bold">CVV</th>
    <th style="font-size:8pt;font-weight:bold">Bank</th>
    <th style="font-size:8pt;font-weight:bold">Network</th>
    <th style="font-size:8pt;font-weight:bold">Type</th>
    <th style="font-size:8pt;font-weight:bold">Notes</th>
    </tr>
    </thead>
    <tbody>' . $rowsHtml . '</tbody>
    </table>
    <p style="color:#94a3b8;font-size:7pt;margin-top:12px;text-align:center">Generated by RS PAASWORD MANAGER &mdash; Confidential</p>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('cards_export_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
