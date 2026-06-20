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
    header('Content-Disposition: attachment; filename="oauth_accounts_' . date('Y-m-d') . '.csv"');

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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OAuth Accounts Report - RS PAASWORD MANAGER</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:"Segoe UI",-apple-system,system-ui,sans-serif; background:#f1f5f9; color:#1e293b; line-height:1.6; }
  .report-wrapper { max-width:1100px; margin:0 auto; padding:30px 20px; }
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
    <h1>OAuth Accounts Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . $date . '</span>
      <span><strong style="color:#e2e8f0">Total Accounts:</strong> ' . $count . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box accent">
        <div class="stat-value">' . $count . '</div>
        <div class="stat-label">Total Accounts</div>
      </div>
    </div>
    <table>
      <thead><tr><th>Website</th><th>URL</th><th>Email</th><th>Notes</th></tr></thead>
      <tbody>
' . $rowsHtml . '
      </tbody>
    </table>
    <div class="report-footer">RS PAASWORD MANAGER &bull; Confidential &bull; Generated ' . $date . ' &bull; <strong>' . $count . '</strong> account' . ($count !== 1 ? 's' : '') . '</div>
  </div>
</div>
</body>
</html>';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="oauth_accounts_' . date('Y-m-d') . '.html"');
    echo $html;
    exit;
}

function handleExportPdf(int $userId): void {
    require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

    $accounts = getOauthAccounts($userId);
    $count = count($accounts);
    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    class RS_OAuth_PDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 0, 'OAuth Accounts Report', 0, 1, 'L');
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

    $pdf = new RS_OAuth_PDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RS PAASWORD MANAGER');
    $pdf->SetAuthor('RS PAASWORD MANAGER');
    $pdf->SetTitle('OAuth Accounts Report');
    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetMargins(15, 32, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $cardW = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - 6) / 2;
    $startY = $pdf->GetY();
    $pdf->SetFillColor(238, 242, 255);
    $pdf->SetDrawColor(199, 210, 254);
    $pdf->Rect($pdf->getMargins()['left'], $startY, $cardW, 18, 'DF');
    $pdf->SetXY($pdf->getMargins()['left'], $startY + 2);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell($cardW, 5, 'TOTAL ACCOUNTS', 0, 1, 'C');
    $pdf->SetX($pdf->getMargins()['left']);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(79, 70, 229);
    $pdf->Cell($cardW, 8, (string)$count, 0, 1, 'C');

    $pdf->SetY($startY + 18 + 10);

    $tableW = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
    $colW = [50, 60, 60, 80];

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(30, 41, 59);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(30, 41, 59);
    $headers = ['Website', 'URL', 'Email', 'Notes'];
    $aligns  = ['L', 'L', 'L', 'L'];
    foreach ($headers as $j => $h) {
        $pdf->Cell($colW[$j], 8, $h, 1, 0, $aligns[$j], 1);
    }
    $pdf->Ln();

    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetDrawColor(226, 232, 240);

    foreach ($accounts as $i => $a) {
        $fill = ($i % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        $pdf->Cell($colW[0], 7, $esc($a['website_name']), 1, 0, 'L', 1);
        $pdf->Cell($colW[1], 7, $esc($a['website_url'] ?? ''), 1, 0, 'L', 1);
        $pdf->Cell($colW[2], 7, $esc($a['email']), 1, 0, 'L', 1);
        $pdf->Cell($colW[3], 7, $esc($a['notes'] ?? ''), 1, 1, 'L', 1);
    }

    $pdf->Output('oauth_accounts_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
