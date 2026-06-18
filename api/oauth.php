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
        case 'list':
            handleList($userId);
            break;
        case 'add':
            handleAdd($userId, $input);
            break;
        case 'edit':
            handleEdit($userId, $input);
            break;
        case 'delete':
            handleDelete($userId, $input);
            break;
        case 'export_csv':
            handleExportCsv($userId);
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
    error_log('OAuth API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function getOauthAccounts(int $userId): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM oauth_accounts WHERE user_id = :uid ORDER BY website_name ASC');
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll();
}

function handleList(int $userId): void {
    $accounts = getOauthAccounts($userId);
    echo json_encode(['success' => true, 'accounts' => $accounts]);
}

function handleAdd(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $websiteName = trim($input['website_name'] ?? '');
    $websiteUrl = trim($input['website_url'] ?? '');
    $email = trim($input['email'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if (empty($websiteName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Website name and email are required']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO oauth_accounts (user_id, website_name, website_url, email, notes) VALUES (:uid, :name, :url, :email, :notes)'
    );
    $stmt->execute([
        ':uid' => $userId, ':name' => $websiteName,
        ':url' => $websiteUrl, ':email' => $email, ':notes' => $notes
    ]);
    logActivity($userId, 'OAuth account added', "Added OAuth account: $websiteName");
    echo json_encode(['success' => true, 'message' => 'OAuth account added', 'id' => (int)$pdo->lastInsertId()]);
}

function handleEdit(int $userId, array $input): void {
    $csrfToken = $input['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
        return;
    }
    $id = (int)($input['id'] ?? 0);
    $websiteName = trim($input['website_name'] ?? '');
    $websiteUrl = trim($input['website_url'] ?? '');
    $email = trim($input['email'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if ($id <= 0 || empty($websiteName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Website name and email are required']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'UPDATE oauth_accounts SET website_name = :name, website_url = :url, email = :email, notes = :notes WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([
        ':name' => $websiteName, ':url' => $websiteUrl,
        ':email' => $email, ':notes' => $notes, ':id' => $id, ':uid' => $userId
    ]);
    logActivity($userId, 'OAuth account updated', "Updated OAuth account: $websiteName");
    echo json_encode(['success' => true, 'message' => 'OAuth account updated']);
}

function handleDelete(int $userId, array $input): void {
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
    $stmt = $pdo->prepare('DELETE FROM oauth_accounts WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    logActivity($userId, 'OAuth account deleted', "Deleted OAuth account #$id");
    echo json_encode(['success' => true, 'message' => 'OAuth account deleted']);
}

function handleExportCsv(int $userId): void {
    $accounts = getOauthAccounts($userId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="personal-accounts.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Website Name', 'Website URL', 'Email', 'Notes', 'Added On']);

    foreach ($accounts as $a) {
        fputcsv($out, [
            $a['website_name'],
            $a['website_url'] ?? '',
            $a['email'],
            $a['notes'] ?? '',
            $a['created_at']
        ]);
    }
    fclose($out);
    exit;
}

function handleExportHtml(int $userId): void {
    $accounts = getOauthAccounts($userId);
    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $count = count($accounts);

    $rowsHtml = '';
    foreach ($accounts as $a) {
        $rowsHtml .= '<tr>
            <td>' . $esc($a['website_name']) . '</td>
            <td>' . $esc($a['website_url'] ?? '') . '</td>
            <td>' . $esc($a['email']) . '</td>
            <td>' . $esc($a['notes'] ?? '') . '</td>
        </tr>' . "\n";
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Google Sign-In Accounts Export</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; padding: 30px; background: linear-gradient(135deg, #ea4335 0%, #c5221f 100%); min-height: 100vh; }
.wrapper { max-width: 1000px; margin: 0 auto; background: rgba(255,255,255,0.95); border-radius: 16px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); backdrop-filter: blur(10px); }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #fce8e6; }
.header h1 { font-size: 1.6rem; color: #1e1b4b; font-weight: 700; }
.header h1 i { color: #ea4335; }
.meta { color: #64748b; font-size: 0.85rem; }
table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
thead th { background: linear-gradient(135deg, #ea4335 0%, #c5221f 100%); color: #fff; padding: 14px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
tbody tr:nth-child(even) { background: #fce8e6; }
tbody tr:hover { background: #f8d7da; }
td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; color: #1e293b; }
.footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; color: #94a3b8; font-size: 0.8rem; }
.badge { display: inline-block; background: #ea4335; color: #fff; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
@media (max-width: 768px) { .wrapper { padding: 16px; } .header { flex-direction: column; gap: 8px; align-items: flex-start; } td, th { padding: 8px 10px; } }
</style>
</head>
<body>
<div class="wrapper">
<div class="header">
<h1><i class="fa-brands fa-google"></i> Google Sign-In Accounts</h1>
<div class="meta">Exported ' . $date . ' &middot; <span class="badge">' . $count . ' accounts</span></div>
</div>
<table>
<thead><tr><th>Website</th><th>URL</th><th>Email</th><th>Notes</th></tr></thead>
<tbody>
' . $rowsHtml . '
</tbody>
</table>
<div class="footer">
<span>Generated by RS PAASWORD MANAGER</span>
<span>Confidential &mdash; keep this file secure</span>
</div>
</div>
</body>
</html>';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="google_signin_accounts_' . date('Y-m-d') . '.html"');
    echo $html;
    exit;
}

function handleExportPdf(int $userId): void {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

    $accounts = getOauthAccounts($userId);
    $count = count($accounts);
    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RS PAASWORD MANAGER');
    $pdf->SetTitle('Google Sign-In Accounts');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    $html = '<h1 style="color:#1e1b4b;font-size:18pt;margin-bottom:4px">Google Sign-In Accounts</h1>
    <p style="color:#64748b;font-size:9pt;margin-bottom:16px;border-bottom:2px solid #fce8e6;padding-bottom:8px">Exported ' . $date . ' &middot; ' . $count . ' accounts</p>
    <table border="1" cellpadding="5" cellspacing="0">
    <thead>
    <tr style="background-color:#ea4335;color:#fff">
    <th style="font-size:8pt;font-weight:bold">Website</th>
    <th style="font-size:8pt;font-weight:bold">URL</th>
    <th style="font-size:8pt;font-weight:bold">Email</th>
    <th style="font-size:8pt;font-weight:bold">Notes</th>
    </tr>
    </thead>
    <tbody>';
    foreach ($accounts as $a) {
        $html .= '<tr>
        <td style="font-size:9pt">' . $esc($a['website_name']) . '</td>
        <td style="font-size:9pt">' . $esc($a['website_url'] ?? '') . '</td>
        <td style="font-size:9pt">' . $esc($a['email']) . '</td>
        <td style="font-size:9pt">' . $esc($a['notes'] ?? '') . '</td>
        </tr>';
    }
    $html .= '</tbody></table>
    <p style="color:#94a3b8;font-size:7pt;margin-top:12px;text-align:center">Generated by RS PAASWORD MANAGER &mdash; Confidential</p>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('google_signin_accounts_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
