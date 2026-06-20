<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
session_start();
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();
$userId = $currentUser['id'];

// Export handlers
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $actionFilter = $_GET['action'] ?? '';
    $search = $_GET['search'] ?? '';

    $query = 'SELECT * FROM activity_logs WHERE user_id = ?';
    $params = [$userId];
    if ($actionFilter) { $query .= ' AND action = ?'; $params[] = $actionFilter; }
    if ($search) { $query .= ' AND (action LIKE ? OR details LIKE ? OR ip_address LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
    $query .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
    $total = count($activities);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=my_activity.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Action', 'Details', 'IP Address', 'Date']);
        foreach ($activities as $a) {
            fputcsv($out, [$a['id'], $a['action'], $a['details'], $a['ip_address'], $a['created_at']]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=my_activity.html');
        $rows = '';
        foreach ($activities as $i => $a) {
            $bg = $i % 2 === 0 ? '#ffffff' : '#f8faff';
            $actionClass = 'action-default';
            if (strpos($a['action'], 'deleted') !== false || strpos($a['action'], 'trashed') !== false) $actionClass = 'action-danger';
            elseif (strpos($a['action'], 'logged in') !== false || strpos($a['action'], 'registered') !== false) $actionClass = 'action-success';
            elseif (strpos($a['action'], 'updated') !== false || strpos($a['action'], 'added') !== false || strpos($a['action'], 'enabled') !== false) $actionClass = 'action-info';
            $rows .= '<tr style="background:' . $bg . '">
              <td style="padding:10px 14px;border-bottom:1px solid #eef2f7;color:#64748b;font-size:0.85rem">#' . $a['id'] . '</td>
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
<title>My Activity Report - RS PAASWORD MANAGER</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:"Segoe UI",-apple-system,system-ui,sans-serif; background:#f1f5f9; color:#1e293b; line-height:1.6; }
  .report-wrapper { max-width:1100px; margin:0 auto; padding:30px 20px; }
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
  @media (max-width:768px) { .report-wrapper { padding:16px 10px; } .report-header { padding:24px 20px 20px; border-radius:12px 12px 0 0; } .report-header h1 { font-size:1.3rem; } .report-body { padding:20px 16px; } .stats-row { gap:12px; } .stat-box { min-width:100px; padding:12px 14px; } .stat-box .stat-value { font-size:1.3rem; } .report-header .meta { font-size:0.75rem; gap:10px; } thead th, tbody td { padding:6px 8px; font-size:0.7rem; } .report-footer { font-size:0.7rem; } }
  @media (max-width:480px) { .report-header { padding:18px 14px 16px; } .report-header h1 { font-size:1.1rem; } .report-body { padding:14px 10px; border-radius:0 0 12px 12px; } .stats-row { flex-direction:column; gap:8px; } .stat-box { min-width:auto; } table { font-size:0.65rem; } thead th, tbody td { padding:4px 6px; font-size:0.65rem; } .report-footer { font-size:0.65rem; padding:16px 0 4px; } }
</style>
</head>
<body>
<div class="report-wrapper">
  <div class="report-header">
    <div class="brand">RS PAASWORD MANAGER</div>
    <h1>My Activity Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . date('F j, Y \a\t g:i A') . '</span>
      <span><strong style="color:#e2e8f0">Total Events:</strong> ' . number_format($total) . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box accent"><div class="stat-value">' . number_format($total) . '</div><div class="stat-label">Total Events</div></div>
      <div class="stat-box"><div class="stat-value">' . number_format($pdo->query('SELECT COUNT(DISTINCT action) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn()) . '</div><div class="stat-label">Action Types</div></div>
      <div class="stat-box"><div class="stat-value">' . number_format($pdo->query('SELECT COUNT(DISTINCT DATE(created_at)) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn()) . '</div><div class="stat-label">Active Days</div></div>
    </div>
    <table>
      <thead><tr>
        <th>ID</th><th>Action</th><th>Details</th><th>IP</th><th>Date</th>
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
        require_once __DIR__ . '/lib/tcpdf/tcpdf.php';

        $distinctActions = $pdo->query('SELECT COUNT(DISTINCT action) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn();
        $activeDays = $pdo->query('SELECT COUNT(DISTINCT DATE(created_at)) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn();

        class RSVaultPDF extends TCPDF {
            public function Header() {
                $this->SetFont('helvetica', 'B', 16);
                $this->SetTextColor(30, 41, 59);
                $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
                $this->SetFont('helvetica', '', 10);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(0, 0, 'My Activity Report', 0, 1, 'L');
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
        $pdf->SetAuthor(htmlspecialchars($currentUser['username'] ?? 'User'));
        $pdf->SetTitle('My Activity Report');
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(15, 32, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $pW = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $cardW = ($pW - 16) / 3;
        $cardH = 18;
        $startY = $pdf->GetY();

        $cards = [
            ['label' => 'Total Events', 'value' => number_format($total), 'fill' => [238, 242, 255], 'text' => [79, 70, 229]],
            ['label' => 'Action Types', 'value' => number_format($distinctActions), 'fill' => [255, 247, 237], 'text' => [234, 88, 12]],
            ['label' => 'Active Days', 'value' => number_format($activeDays), 'fill' => [240, 253, 244], 'text' => [22, 163, 74]],
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

        $colW = [10, 55, 85, 35, 35];
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(30, 41, 59);
        $headers = ['ID', 'Action', 'Details', 'IP', 'Date'];
        $aligns  = ['C', 'L', 'L', 'C', 'C'];
        foreach ($headers as $j => $h) {
            $pdf->Cell($colW[$j], 8, $h, 1, 0, $aligns[$j], 1);
        }
        $pdf->Ln();

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetDrawColor(226, 232, 240);

        foreach ($activities as $i => $a) {
            $fill = ($i % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[0], 7, '#' . $a['id'], 1, 0, 'C', 1);

            $action = htmlspecialchars($a['action']);
            if (mb_strlen($action) > 22) $action = mb_substr($action, 0, 20) . '..';
            $pdf->Cell($colW[1], 7, $action, 1, 0, 'L', 1);

            $details = htmlspecialchars($a['details'] ?? '-');
            if (mb_strlen($details) > 50) $details = mb_substr($details, 0, 48) . '..';
            $pdf->Cell($colW[2], 7, $details, 1, 0, 'L', 1);
            $pdf->Cell($colW[3], 7, $a['ip_address'], 1, 0, 'C', 1);
            $pdf->Cell($colW[4], 7, date('M j, Y', strtotime($a['created_at'])), 1, 1, 'C', 1);
        }

        $pdf->Output('my_activity.pdf', 'D');
        exit;
    }
}

$pageTitle = 'My Activity';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';

$actionFilter = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';

$query = 'SELECT * FROM activity_logs WHERE user_id = ?';
$params = [$userId];
if ($actionFilter) { $query .= ' AND action = ?'; $params[] = $actionFilter; }
if ($search) { $query .= ' AND (action LIKE ? OR details LIKE ? OR ip_address LIKE ?)'; $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]); }
$query .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

$totalAll = $pdo->query('SELECT COUNT(*) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn();
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs WHERE user_id = ' . $userId . ' ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$distinctActions = $pdo->query('SELECT COUNT(DISTINCT action) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn();
$activeDays = $pdo->query('SELECT COUNT(DISTINCT DATE(created_at)) FROM activity_logs WHERE user_id = ' . $userId)->fetchColumn();
?>
<style>
.activity-hero { background:linear-gradient(135deg,var(--bg-soft),var(--card-bg)); border-radius:16px; padding:32px 36px 28px; margin-bottom:20px; position:relative; overflow:hidden; border:1px solid var(--border-soft); }
.activity-hero::before { content:""; position:absolute; top:-50%; right:-15%; width:280px; height:280px; background:radial-gradient(circle,rgba(99,102,241,0.12) 0%,transparent 70%); border-radius:50%; }
.activity-hero .hero-top { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:4px; position:relative;z-index:1; }
.activity-hero .hero-top h1 { color:var(--text-main); font-size:1.5rem; margin:0; display:flex; align-items:center; gap:10px; }
.activity-hero .hero-top h1 i { color:var(--primary); }
.activity-hero .hero-top .hero-badge { background:rgba(99,102,241,0.15); color:var(--primary); padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:600; border:1px solid rgba(99,102,241,0.25); }
.activity-hero .hero-sub { color:var(--text-muted); font-size:0.85rem; margin-top:4px; position:relative;z-index:1; }
.activity-stats { display:flex; gap:14px; margin-top:18px; position:relative;z-index:1; flex-wrap:wrap; }
.activity-stats .as-card { flex:1; min-width:130px; background:var(--card-glass); border:1px solid var(--border-soft); border-radius:12px; padding:14px 18px; text-align:center; backdrop-filter:blur(4px); }
.activity-stats .as-card .as-value { font-size:1.6rem; font-weight:800; line-height:1.2; }
.activity-stats .as-card .as-label { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:500; margin-top:3px; }
.activity-stats .as-card:nth-child(1) .as-value { color:var(--primary); }
.activity-stats .as-card:nth-child(1) .as-label { color:var(--text-muted); }
.activity-stats .as-card:nth-child(2) .as-value { color:var(--warning); }
.activity-stats .as-card:nth-child(2) .as-label { color:var(--text-muted); }
.activity-stats .as-card:nth-child(3) .as-value { color:var(--success); }
.activity-stats .as-card:nth-child(3) .as-label { color:var(--text-muted); }
.activity-filter-card { background:var(--card-bg); border:1px solid var(--border-soft); border-radius:12px; padding:14px 20px; margin-bottom:18px; }
.activity-table-card { background:var(--card-bg); border:1px solid var(--border-soft); border-radius:12px; overflow:hidden; }
.activity-table-card .at-header { padding:16px 20px; border-bottom:1px solid var(--border-soft); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.activity-table-card .at-header h3 { margin:0; font-size:0.95rem; display:flex; align-items:center; gap:8px; color:var(--text-main); }
.activity-table-card .at-header .at-count { background:var(--card-glass); padding:2px 10px; border-radius:12px; font-size:0.75rem; color:var(--text-muted); }
.activity-table-card .table { margin:0; }
.activity-table-card .table thead th { background:var(--bg-soft); color:var(--text-muted); font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; padding:10px 16px; border-bottom:1px solid var(--border-soft); }
.activity-table-card .table tbody td { padding:10px 16px; font-size:0.85rem; border-bottom:1px solid var(--border-soft); }
.activity-table-card .table tbody tr:last-child td { border-bottom:none; }
</style>
<div class="app-layout">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="page-container">

      <div class="activity-hero">
        <div class="hero-top">
          <h1><i class="fas fa-history"></i> My Activity</h1>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="?export=csv<?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:0.8rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;background:rgba(34,197,94,0.2);color:#4ade80;border:1px solid rgba(34,197,94,0.25);transition:all 0.2s"><i class="fas fa-file-csv"></i> CSV</a>
            <a href="?export=html<?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:0.8rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;background:rgba(59,130,246,0.2);color:#60a5fa;border:1px solid rgba(59,130,246,0.25);transition:all 0.2s"><i class="fas fa-file-code"></i> HTML</a>
            <a href="?export=pdf<?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:0.8rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;background:rgba(251,191,36,0.2);color:#fbbf24;border:1px solid rgba(251,191,36,0.25);transition:all 0.2s"><i class="fas fa-file-pdf"></i> PDF</a>
          </div>
        </div>
        <div class="hero-sub">Track your account activity and security events</div>
        <div class="activity-stats">
          <div class="as-card"><div class="as-value"><?php echo number_format(count($activities)); ?></div><div class="as-label">Events</div></div>
          <div class="as-card"><div class="as-value"><?php echo number_format($distinctActions); ?></div><div class="as-label">Action Types</div></div>
          <div class="as-card"><div class="as-value"><?php echo number_format($activeDays); ?></div><div class="as-label">Active Days</div></div>
        </div>
      </div>

      <div class="activity-filter-card">
        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0">
          <select name="action" class="form-input" style="width:auto;min-width:150px" onchange="this.form.submit()">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $actionFilter === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($a); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="search" class="form-input" style="min-width:180px;flex:1" placeholder="Search action, details, IP..." value="<?php echo htmlspecialchars($search); ?>">
          <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
          <?php if ($actionFilter || $search): ?><a href="activity.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
      </div>

      <div class="activity-table-card">
        <div class="at-header">
          <h3><i class="fas fa-list"></i> Event Log <span class="at-count"><?php echo count($activities); ?><?php echo count($activities) != $totalAll ? ' / ' . number_format($totalAll) : ''; ?></span></h3>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead><tr><th>ID</th><th>Action</th><th>Details</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($activities as $i => $a): ?>
              <?php
                $cls = 'badge-secondary';
                if (strpos($a['action'], 'deleted') !== false || strpos($a['action'], 'trashed') !== false) $cls = 'badge-danger';
                elseif (strpos($a['action'], 'logged in') !== false || strpos($a['action'], 'registered') !== false) $cls = 'badge-success';
                elseif (strpos($a['action'], 'updated') !== false || strpos($a['action'], 'added') !== false || strpos($a['action'], 'enabled') !== false) $cls = 'badge-info';
              ?>
              <tr<?php echo $i % 2 === 0 ? '' : ' class="alt-row"'; ?>>
                <td style="color:var(--text-muted);font-size:0.8rem">#<?php echo $a['id']; ?></td>
                <td><span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($a['action']); ?></span></td>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($a['details'] ?? '-'); ?></td>
                <td><code style="font-size:0.8rem;color:var(--text-muted)"><?php echo htmlspecialchars($a['ip_address']); ?></code></td>
                <td style="color:var(--text-muted);font-size:0.8rem"><?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($activities)): ?><tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)">No activity found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
