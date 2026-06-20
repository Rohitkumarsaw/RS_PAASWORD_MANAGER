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

// Export handlers (before HTML output)
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $actionFilter = $_GET['action'] ?? '';
    $userFilter = $_GET['user_id'] ?? '';
    $search = $_GET['search'] ?? '';

    $query = 'SELECT al.*, u.username, u.email FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1';
    $params = [];
    if ($actionFilter) { $query .= ' AND al.action = ?'; $params[] = $actionFilter; }
    if ($userFilter) { $query .= ' AND al.user_id = ?'; $params[] = $userFilter; }
    if ($search) { $query .= ' AND (al.action LIKE ? OR al.details LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]); }
    $query .= ' ORDER BY al.created_at DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
    $total = count($activities);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=activity_log.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'User', 'Email', 'Action', 'Details', 'IP Address', 'Date']);
        foreach ($activities as $a) {
            fputcsv($out, [$a['id'], $a['username'] ?? 'N/A', $a['email'] ?? 'N/A', $a['action'], $a['details'], $a['ip_address'], $a['created_at']]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=activity_log.html');
        $rows = '';
        foreach ($activities as $i => $a) {
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8faff';
            $actionClass = 'action-default';
            if (strpos($a['action'], 'deleted') !== false || strpos($a['action'], 'trashed') !== false) $actionClass = 'action-danger';
            elseif (strpos($a['action'], 'logged in') !== false || strpos($a['action'], 'registered') !== false) $actionClass = 'action-success';
            elseif (strpos($a['action'], 'updated') !== false || strpos($a['action'], 'added') !== false || strpos($a['action'], 'enabled') !== false) $actionClass = 'action-info';
            $rows .= '<tr style="background:' . $bg . '">
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#64748b;font-size:0.85rem">#' . $a['id'] . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#1e293b;font-weight:600">' . htmlspecialchars($a['username'] ?? 'N/A') . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#475569;font-size:0.85rem">' . htmlspecialchars($a['email'] ?? 'N/A') . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7"><span class="action-badge ' . $actionClass . '">' . htmlspecialchars($a['action']) . '</span></td>
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#475569;font-size:0.85rem">' . htmlspecialchars($a['details'] ?? '-') . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#94a3b8;font-family:monospace;font-size:0.8rem">' . htmlspecialchars($a['ip_address']) . '</td>
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#64748b;font-size:0.85rem">' . date('M j, Y g:i A', strtotime($a['created_at'])) . '</td>
            </tr>';
        }

        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log Report - RS PAASWORD MANAGER</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:"Segoe UI",-apple-system,system-ui,sans-serif; background:#f1f5f9; color:#1e293b; line-height:1.6; }
  .report-wrapper { max-width:1200px; margin:0 auto; padding:30px 20px; }
  .report-header { background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); border-radius:16px 16px 0 0; padding:36px 40px 28px; position:relative; overflow:hidden; }
  .report-header::before { content:""; position:absolute; top:-50%; right:-20%; width:300px; height:300px; background:radial-gradient(circle,rgba(99,102,241,0.15) 0%,transparent 70%); border-radius:50%; }
  .report-header .brand { font-size:1.5rem; font-weight:800; background:linear-gradient(135deg,#818cf8,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
  .report-header h1 { color:#fff; font-size:1.8rem; font-weight:700; margin-top:8px; }
  .report-header .meta { color:#94a3b8; font-size:0.85rem; margin-top:6px; display:flex; gap:20px; flex-wrap:wrap; }
  .report-body { background:#fff; padding:32px 40px; border-radius:0 0 16px 16px; box-shadow:0 4px 24px rgba(0,0,0,0.06); }
  .stats-row { display:flex; gap:24px; margin-bottom:28px; flex-wrap:wrap; }
  .stat-box { flex:1; min-width:140px; background:linear-gradient(135deg,#f8faff,#f1f5f9); border-radius:12px; padding:18px 20px; border:1px solid #eef2f7; text-align:center; }
  .stat-box .stat-value { font-size:1.8rem; font-weight:800; color:#1e293b; }
  .stat-box .stat-label { font-size:0.8rem; color:#64748b; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; }
  .stat-box.accent { background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-color:#c7d2fe; }
  .stat-box.accent .stat-value { color:#4f46e5; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  thead th { padding:10px 14px; background:linear-gradient(135deg,#1e293b,#334155); color:#fff; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; text-align:left; border:none; }
  thead th:first-child { border-radius:8px 0 0 0; }
  thead th:last-child { border-radius:0 8px 0 0; }
  tbody tr:last-child td { border-bottom:none; }
  .action-badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:600; }
  .action-default { background:#f1f5f9;color:#475569; }
  .action-danger { background:#fef2f2;color:#dc2626; }
  .action-success { background:#f0fdf4;color:#16a34a; }
  .action-info { background:#eff6ff;color:#2563eb; }
  .report-footer { text-align:center;padding:24px 0 8px;color:#94a3b8;font-size:0.8rem;border-top:1px solid #eef2f7;margin-top:28px; }
  .report-footer strong { color:#475569; }
  @media print { body { background:#fff; } .report-header { border-radius:0; } .report-body { box-shadow:none; } }
  @media (max-width:768px) { .report-wrapper { padding:16px 10px; } .report-header { padding:24px 20px 20px; border-radius:12px 12px 0 0; } .report-header h1 { font-size:1.3rem; } .report-body { padding:20px 16px; } .stats-row { gap:12px; } .stat-box { min-width:100px; padding:12px 14px; } .stat-box .stat-value { font-size:1.3rem; } .report-header .meta { font-size:0.75rem; gap:10px; } thead th, tbody td { padding:6px 8px; font-size:0.7rem; } }
  @media (max-width:480px) { .report-header { padding:18px 14px 16px; } .report-header h1 { font-size:1.1rem; } .report-body { padding:14px 10px; } .stats-row { flex-direction:column; gap:8px; } .stat-box { min-width:auto; } table { font-size:0.65rem; } thead th, tbody td { padding:4px 6px; font-size:0.65rem; } .report-footer { font-size:0.65rem; } }
</style>
</head>
<body>
<div class="report-wrapper">
  <div class="report-header">
    <div class="brand">RS PAASWORD MANAGER</div>
    <h1>Activity Log Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . date('F j, Y \a\t g:i A') . '</span>
      <span><strong style="color:#e2e8f0">Total Events:</strong> ' . number_format($total) . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box accent"><div class="stat-value">' . number_format($total) . '</div><div class="stat-label">Total Events</div></div>
      <div class="stat-box"><div class="stat-value">' . number_format($pdo->query('SELECT COUNT(DISTINCT user_id) FROM activity_logs')->fetchColumn()) . '</div><div class="stat-label">Active Users</div></div>
      <div class="stat-box"><div class="stat-value">' . number_format($pdo->query('SELECT COUNT(DISTINCT action) FROM activity_logs')->fetchColumn()) . '</div><div class="stat-label">Action Types</div></div>
    </div>
    <table>
      <thead><tr>
        <th>ID</th><th>User</th><th>Email</th><th>Action</th><th>Details</th><th>IP</th><th>Date</th>
      </tr></thead>
      <tbody>' . $rows . '</tbody>
    </table>
    <div class="report-footer">RS PAASWORD MANAGER &bull; Confidential &bull; Generated ' . date('Y-m-d H:i:s') . ' &bull; <strong>' . number_format($total) . '</strong> event' . ($total !== 1 ? 's' : '') . '</div>
  </div>
</div>
</body>
</html>';
        exit;
    }

    if ($format === 'pdf') {
        require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

        $distinctUsers = $pdo->query('SELECT COUNT(DISTINCT user_id) FROM activity_logs')->fetchColumn();
        $distinctActions = $pdo->query('SELECT COUNT(DISTINCT action) FROM activity_logs')->fetchColumn();

        class RSVaultPDF extends TCPDF {
            public function Header() {
                $this->SetFont('helvetica', 'B', 16);
                $this->SetTextColor(30, 41, 59);
                $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
                $this->SetFont('helvetica', '', 10);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(0, 0, 'Activity Log Report', 0, 1, 'L');
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
        $pdf->SetTitle('Activity Log Report');
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(15, 32, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Summary cards
        $pW = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $cardW = ($pW - 16) / 3;
        $cardH = 18;
        $startY = $pdf->GetY();

        $cards = [
            ['label' => 'Total Events', 'value' => number_format($total), 'fill' => [238, 242, 255], 'text' => [79, 70, 229]],
            ['label' => 'Active Users', 'value' => number_format($distinctUsers), 'fill' => [255, 247, 237], 'text' => [234, 88, 12]],
            ['label' => 'Action Types', 'value' => number_format($distinctActions), 'fill' => [240, 253, 244], 'text' => [22, 163, 74]],
        ];
        foreach ($cards as $i => $c) {
            $x = $pdf->getMargins()['left'] + ($i * ($cardW + 8));
            $pdf->SetFillColor($c['fill'][0], $c['fill'][1], $c['fill'][2]);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Rect($x, $startY, $cardW, $cardH, 'DF');
            $pdf->SetXY($x, $startY + 3);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($cardW, 5, strtoupper($c['label']), 0, 1, 'C');
            $pdf->SetX($x);
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor($c['text'][0], $c['text'][1], $c['text'][2]);
            $pdf->Cell($cardW, 8, $c['value'], 0, 1, 'C');
        }
        $pdf->SetY($startY + $cardH + 10);

        // Table
        $colW = [10, 28, 52, 50, 72, 30, 35];
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(30, 41, 59);
        $headers = ['ID', 'User', 'Email', 'Action', 'Details', 'IP', 'Date'];
        $aligns  = ['C', 'L', 'L', 'L', 'L', 'C', 'C'];
        foreach ($headers as $j => $h) {
            $pdf->Cell($colW[$j], 8, $h, 1, 0, $aligns[$j], 1);
        }
        $pdf->Ln();

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetDrawColor(226, 232, 240);

        foreach ($activities as $i => $a) {
            $fill = ($i % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[0], 6.5, '#' . $a['id'], 1, 0, 'C', 1);

            $uname = htmlspecialchars($a['username'] ?? 'N/A');
            if (mb_strlen($uname) > 14) $uname = mb_substr($uname, 0, 12) . '..';
            $pdf->Cell($colW[1], 6.5, $uname, 1, 0, 'L', 1);

            $email = htmlspecialchars($a['email'] ?? 'N/A');
            if (mb_strlen($email) > 22) $email = mb_substr($email, 0, 20) . '..';
            $pdf->Cell($colW[2], 6.5, $email, 1, 0, 'L', 1);

            $action = htmlspecialchars($a['action']);
            if (mb_strlen($action) > 20) $action = mb_substr($action, 0, 18) . '..';
            $pdf->Cell($colW[3], 6.5, $action, 1, 0, 'L', 1);

            $details = htmlspecialchars($a['details'] ?? '-');
            if (mb_strlen($details) > 40) $details = mb_substr($details, 0, 38) . '..';
            $pdf->Cell($colW[4], 6.5, $details, 1, 0, 'L', 1);

            $pdf->Cell($colW[5], 6.5, $a['ip_address'], 1, 0, 'C', 1);
            $pdf->Cell($colW[6], 6.5, date('M j, Y', strtotime($a['created_at'])), 1, 1, 'C', 1);
        }

        $pdf->Output('activity_log.pdf', 'D');
        exit;
    }
}

$pageTitle = 'Activity Log';
include __DIR__ . '/../includes/admin_header.php';

$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$search = $_GET['search'] ?? '';

$query = 'SELECT al.*, u.username, u.email FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1';
$params = [];
if ($actionFilter) { $query .= ' AND al.action = ?'; $params[] = $actionFilter; }
if ($userFilter) { $query .= ' AND al.user_id = ?'; $params[] = $userFilter; }
if ($search) { $query .= ' AND (al.action LIKE ? OR al.details LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]); }
$query .= ' ORDER BY al.created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

$totalAll = $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();

// For filter dropdowns
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query('SELECT DISTINCT al.user_id, u.username FROM activity_logs al JOIN users u ON u.id = al.user_id ORDER BY u.username')->fetchAll();
?>
<div class="admin-layout">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="admin-content">
    <div class="page-header">
      <h1><i class="fas fa-history"></i> Activity Log</h1>
      <div class="page-actions">
        <a href="?export=csv<?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user_id=' . urlencode($userFilter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        <a href="?export=html<?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user_id=' . urlencode($userFilter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-info btn-sm"><i class="fas fa-file-code"></i> HTML</a>
        <a href="?export=pdf<?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $userFilter ? '&user_id=' . urlencode($userFilter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-warning btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
      </div>
    </div>
    <div class="card" style="margin-bottom:18px">
      <div class="card-body" style="padding:12px 20px">
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <select name="action" class="form-input" style="width:auto;min-width:130px;flex:1" onchange="this.form.submit()">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $actionFilter === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($a); ?></option>
            <?php endforeach; ?>
          </select>
          <select name="user_id" class="form-input" style="width:auto;min-width:110px;flex:1" onchange="this.form.submit()">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
            <option value="<?php echo $u['user_id']; ?>" <?php echo $userFilter == $u['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="search" class="form-input" style="min-width:140px;flex:1" placeholder="Search action, details, IP..." value="<?php echo htmlspecialchars($search); ?>">
          <button type="submit" class="btn btn-secondary" style="padding:8px 14px"><i class="fas fa-search"></i></button>
          <?php if ($actionFilter || $userFilter || $search): ?><a href="activity.php" class="btn btn-secondary" style="padding:8px 14px"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-list"></i> Event Log (<?php echo count($activities); ?><?php echo count($activities) != $totalAll ? ' / ' . number_format($totalAll) . ' total' : ''; ?>)</h3></div>
      <div class="card-body">
        <div class="table-wrapper">
          <table class="table">
            <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Action</th><th>Details</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($activities as $a): ?>
              <?php
                $actionClass = 'badge-secondary';
                if (strpos($a['action'], 'deleted') !== false || strpos($a['action'], 'trashed') !== false) $actionClass = 'badge-danger';
                elseif (strpos($a['action'], 'logged in') !== false || strpos($a['action'], 'registered') !== false) $actionClass = 'badge-success';
                elseif (strpos($a['action'], 'updated') !== false || strpos($a['action'], 'added') !== false || strpos($a['action'], 'enabled') !== false) $actionClass = 'badge-info';
              ?>
              <tr>
                <td>#<?php echo $a['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($a['username'] ?? 'N/A'); ?></strong></td>
                <td><?php echo htmlspecialchars($a['email'] ?? 'N/A'); ?></td>
                <td><span class="badge <?php echo $actionClass; ?>"><?php echo htmlspecialchars($a['action']); ?></span></td>
                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($a['details'] ?? '-'); ?></td>
                <td style="font-family:monospace;font-size:0.8rem;color:var(--text-muted)"><?php echo htmlspecialchars($a['ip_address']); ?></td>
                <td><?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($activities)): ?><tr><td colspan="7" class="text-center">No activity found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
