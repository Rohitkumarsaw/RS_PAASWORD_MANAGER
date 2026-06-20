<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();

// Export handlers (must be before any HTML output)
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $search = $_GET['search'] ?? '';
    $query = 'SELECT id, username, email, display_name, phone, is_admin, created_at FROM users';
    $params = [];
    if ($search) {
        $query .= ' WHERE username LIKE ? OR email LIKE ?';
        $params = ["%$search%", "%$search%"];
    }
    $query .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Username', 'Email', 'Display Name', 'Phone', 'Admin', 'Websites', 'Registered Date']);
        foreach ($users as $u) {
            // Get website count for each user
            $websiteCount = $pdo->prepare('SELECT COUNT(*) FROM websites WHERE user_id = ? AND deleted_at IS NULL');
            $websiteCount->execute([$u['id']]);
            $totalWebsites = $websiteCount->fetchColumn();
            fputcsv($out, [$u['id'], $u['username'], $u['email'], $u['display_name'] ?? '', $u['phone'] ?? '', $u['is_admin'] ? 'Yes' : 'No', $totalWebsites, date('M j, Y', strtotime($u['created_at']))]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=users.html');
        $adminCount = 0;
        $rows = '';
        foreach ($users as $i => $u) {
            $isAdmin = $u['is_admin'];
            if ($isAdmin) $adminCount++;
            $websiteCountStmt = $pdo->prepare('SELECT COUNT(*) FROM websites WHERE user_id = ? AND deleted_at IS NULL');
            $websiteCountStmt->execute([$u['id']]);
            $totalWebsites = (int)$websiteCountStmt->fetchColumn();
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8faff';
            $rows .= '<tr style="background:' . $bg . ';transition:background 0.2s">
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#64748b;font-weight:500;font-size:0.85rem">#' . $u['id'] . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#1e293b;font-weight:600">' . htmlspecialchars($u['username']) . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#475569">' . htmlspecialchars($u['email']) . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#475569">' . htmlspecialchars($u['display_name'] ?? '-') . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#64748b;font-size:0.85rem">' . htmlspecialchars($u['phone'] ?? '-') . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;text-align:center">' . ($isAdmin ? '<span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;background:#e8f5e9;color:#2e7d32">Yes</span>' : '<span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;background:#f1f5f9;color:#64748b">No</span>') . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#1e293b;font-weight:600">' . $totalWebsites . '</td>
              <td style="padding:12px 16px;border-bottom:1px solid #eef2f7;color:#475569">' . date('M j, Y', strtotime($u['created_at'])) . '</td>
            </tr>';
        }
        $total = count($users);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registered Users Report - RS PAASWORD MANAGER</title>
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
  .stat-box { flex:1; min-width:140px; background:linear-gradient(135deg:#f8faff,#f1f5f9); border-radius:12px; padding:18px 20px; border:1px solid #eef2f7; text-align:center; }
  .stat-box .stat-value { font-size:1.8rem; font-weight:800; color:#1e293b; line-height:1.2; }
  .stat-box .stat-label { font-size:0.8rem; color:#64748b; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; }
  .stat-box.accent { background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-color:#c7d2fe; }
  .stat-box.accent .stat-value { color:#4f46e5; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  thead th { padding:12px 16px; background:linear-gradient(135deg,#1e293b,#334155); color:#fff; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; text-align:left; border:none; }
  thead th:first-child { border-radius:8px 0 0 0; }
  thead th:last-child { border-radius:0 8px 0 0; }
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
    <h1>Registered Users Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . date('F j, Y \a\t g:i A') . '</span>
      <span><strong style="color:#e2e8f0">Total Users:</strong> ' . number_format($total) . '</span>
      <span><strong style="color:#e2e8f0">Admins:</strong> ' . number_format($adminCount) . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box accent">
        <div class="stat-value">' . number_format($total) . '</div>
        <div class="stat-label">Total Users</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">' . number_format($adminCount) . '</div>
        <div class="stat-label">Administrators</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">' . number_format($total - $adminCount) . '</div>
        <div class="stat-label">Standard Users</div>
      </div>
    </div>
    <table>
      <thead><tr>
        <th>ID</th><th>Username</th><th>Email</th><th>Display Name</th><th>Phone</th><th>Admin</th><th>Websites</th><th>Registered Date</th>
      </tr></thead>
      <tbody>' . $rows . '</tbody>
    </table>
    <div class="report-footer">RS PAASWORD MANAGER &bull; Confidential &bull; Generated ' . date('Y-m-d H:i:s') . ' &bull; <strong>' . number_format($total) . '</strong> user' . ($total !== 1 ? 's' : '') . '</div>
  </div>
</div>
</body>
</html>';
        exit;
    }

    if ($format === 'pdf') {
        require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

        $adminCount = 0;
        foreach ($users as $u) { if ($u['is_admin']) $adminCount++; }
        $total = count($users);

        class RSVaultPDF extends TCPDF {
            public function Header() {
                $this->SetFont('helvetica', 'B', 16);
                $this->SetTextColor(30, 41, 59);
                $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
                $this->SetFont('helvetica', '', 10);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(0, 0, 'Registered Users Report', 0, 1, 'L');
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

        $pdf = new RSVaultPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('RS PAASWORD MANAGER');
        $pdf->SetAuthor('RS PAASWORD MANAGER');
        $pdf->SetTitle('Registered Users Report');
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(15, 32, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Summary cards
        $cardW = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - 12) / 3;
        $cardH = 18;
        $startY = $pdf->GetY();

        for ($i = 0; $i < 3; $i++) {
            $col = [
                ['label' => 'Total Users', 'value' => number_format($total), 'fill' => [238, 242, 255], 'text' => [79, 70, 229]],
                ['label' => 'Administrators', 'value' => number_format($adminCount), 'fill' => [255, 247, 237], 'text' => [234, 88, 12]],
                ['label' => 'Standard Users', 'value' => number_format($total - $adminCount), 'fill' => [240, 253, 244], 'text' => [22, 163, 74]],
            ][$i];
            $x = $pdf->getMargins()['left'] + ($i * ($cardW + 6));
            $pdf->SetFillColor($col['fill'][0], $col['fill'][1], $col['fill'][2]);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Rect($x, $startY, $cardW, $cardH, 'DF');
            $pdf->SetXY($x, $startY + 3);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($cardW, 5, $col['label'], 0, 1, 'C');
            $pdf->SetX($x);
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor($col['text'][0], $col['text'][1], $col['text'][2]);
            $pdf->Cell($cardW, 8, $col['value'], 0, 1, 'C');
        }

        $pdf->SetY($startY + $cardH + 10);

        // Table
        $tableW = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colW = [12, 40, 75, 50, 25, 18, 30, 30];

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(30, 41, 59);
        $headers = ['ID', 'Username', 'Email', 'Display Name', 'Phone', 'Admin', 'Websites', 'Registered Date'];
        $aligns  = ['C', 'L', 'L', 'L', 'L', 'C', 'C', 'C'];
        foreach ($headers as $j => $h) {
            $pdf->Cell($colW[$j], 8, $h, 1, 0, $aligns[$j], 1);
        }
        $pdf->Ln();

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetDrawColor(226, 232, 240);

        foreach ($users as $i => $u) {
            $fill = ($i % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[0], 7, '#' . $u['id'], 1, 0, 'C', 1);
            $pdf->Cell($colW[1], 7, htmlspecialchars($u['username']), 1, 0, 'L', 1);
            $pdf->Cell($colW[2], 7, htmlspecialchars($u['email']), 1, 0, 'L', 1);
            $pdf->Cell($colW[3], 7, htmlspecialchars($u['display_name'] ?? '-'), 1, 0, 'L', 1);
            $pdf->Cell($colW[4], 7, htmlspecialchars($u['phone'] ?? '-'), 1, 0, 'L', 1);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[5], 7, $u['is_admin'] ? 'Yes' : 'No', 1, 0, 'C', 1);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            // Get websites count for this user for PDF
            $websiteCount = $pdo->prepare('SELECT COUNT(*) FROM websites WHERE user_id = ? AND deleted_at IS NULL');
            $websiteCount->execute([$u['id']]);
            $totalWebsites = $websiteCount->fetchColumn();
            $pdf->Cell($colW[6], 7, (string)$totalWebsites, 1, 0, 'L', 1);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[7], 7, date('M j, Y', strtotime($u['created_at'])), 1, 1, 'C', 1);
        }

        $pdf->Output('users.pdf', 'D');
        exit;
    }
}

$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/admin_header.php';

$search = $_GET['search'] ?? '';
$query = 'SELECT id, username, email, display_name, phone, is_admin, created_at FROM users';
$params = [];
if ($search) {
    $query .= ' WHERE username LIKE ? OR email LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$query .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
?>
<div class="admin-layout">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="admin-content">
    <div class="page-header">
      <h1><i class="fas fa-users"></i> Users</h1>
      <div class="page-actions">
        <form method="get" style="display:flex;gap:6px">
          <input type="text" name="search" class="form-input" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
          <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
          <?php if ($search): ?><a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
        <a href="?export=csv<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        <a href="?export=html<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-info btn-sm"><i class="fas fa-file-code"></i> HTML</a>
        <a href="?export=pdf<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-warning btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-list"></i> All Users (<?php echo $totalUsers; ?>)</h3></div>
      <div class="card-body">
        <div class="table-wrapper">
          <table class="table">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Display Name</th><th>Phone</th><th>Admin</th><th>Websites</th><th>Registered Date</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td>#<?php echo $u['id']; ?></td>
                  <td><?php echo htmlspecialchars($u['username']); ?></td>
                  <td><?php echo htmlspecialchars($u['email']); ?></td>
                  <td><?php echo htmlspecialchars($u['display_name'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                  <td><?php echo $u['is_admin'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'; ?></td>
                  <td>
                    <?php
                    // Get websites for this user to show count and first website favicon
                    $stmt = $pdo->prepare('SELECT website_name, website_url FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
                    $stmt->execute([$u['id']]);
                    $firstWebsite = $stmt->fetch();
                    $websiteCount = $pdo->prepare('SELECT COUNT(*) FROM websites WHERE user_id = ? AND deleted_at IS NULL');
                    $websiteCount->execute([$u['id']]);
                    $totalWebsites = $websiteCount->fetchColumn();
                    echo '<span style="font-weight:600">' . $totalWebsites . '</span>';
                    if ($firstWebsite && $firstWebsite['website_url']) {
                        echo '<div style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:0.75rem;color:var(--text-muted)">';
                        echo '<img src="' . getFaviconUrl($firstWebsite['website_url']) . '" alt="" style="width:12px;height:12px;border-radius:2px" onerror="this.style.display=\'none\'">';
                        echo htmlspecialchars($firstWebsite['website_name']);
                        echo '</div>';
                    }
                    ?>
                  </td>
                  <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                  <td><a href="user-detail.php?id=<?php echo $u['id']; ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($users)): ?><tr><td colspan="9" class="text-center">No users found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>