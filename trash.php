<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();

$trashedWebsites = getTrashedWebsites($userId);
$trashedCredentials = getTrashedCredentials($userId);
$pdo = getDbConnection();
$trashedCards = $pdo->prepare('SELECT * FROM cards WHERE user_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC');
$trashedCards->execute([$userId]);
$trashedCards = $trashedCards->fetchAll();

// Export handlers (before any HTML output)
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $totalTrashed = count($trashedWebsites) + count($trashedCredentials) + count($trashedCards);

    if ($format === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="trash_export_' . date('Y-m-d') . '.html"');

        $rows = '';
        foreach ($trashedWebsites as $w) {
            $rows .= '<tr><td>Website</td><td>' . $esc($w['website_name']) . '</td><td>' . $esc($w['website_url'] ?? '-') . '</td><td>' . timeAgo($w['deleted_at']) . '</td></tr>';
        }
        foreach ($trashedCredentials as $c) {
            $rows .= '<tr><td>Credential</td><td>' . $esc($c['website_name']) . ' / ' . $esc($c['title']) . '</td><td>' . $esc($c['username']) . '</td><td>' . timeAgo($c['deleted_at']) . '</td></tr>';
        }
        foreach ($trashedCards as $c) {
            $rows .= '<tr><td>Card</td><td>' . $esc($c['cardholder_name']) . ' (****' . $esc($c['last_four'] ?? '----') . ')</td><td>' . $esc($c['bank_name'] ?? '-') . '</td><td>' . timeAgo($c['deleted_at']) . '</td></tr>';
        }

        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trash Report - RS PAASWORD MANAGER</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:"Segoe UI",-apple-system,system-ui,sans-serif; background:#f1f5f9; color:#1e293b; line-height:1.6; }
  .report-wrapper { max-width:1100px; margin:0 auto; padding:30px 20px; }
  .report-header { background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); border-radius:16px 16px 0 0; padding:36px 40px 28px; position:relative; overflow:hidden; }
  .report-header::before { content:""; position:absolute; top:-50%; right:-20%; width:300px; height:300px; background:radial-gradient(circle,rgba(99,102,241,0.15) 0%,transparent 70%); border-radius:50%; }
  .report-header .brand { font-size:1.5rem; font-weight:800; background:linear-gradient(135deg,#818cf8,#6366f1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
  .report-header h1 { color:#fff; font-size:1.8rem; font-weight:700; margin-top:8px; letter-spacing:-0.5px; }
  .report-header .meta { color:#94a3b8; font-size:0.85rem; margin-top:6px; display:flex; gap:20px; flex-wrap:wrap; }
  .report-body { background:#fff; padding:32px 40px; border-radius:0 0 16px 16px; box-shadow:0 4px 24px rgba(0,0,0,0.06); }
  .stats-row { display:flex; gap:24px; margin-bottom:28px; flex-wrap:wrap; }
  .stat-box { flex:1; min-width:120px; background:linear-gradient(135deg,#f8faff,#f1f5f9); border-radius:12px; padding:18px 20px; border:1px solid #eef2f7; text-align:center; }
  .stat-box .stat-value { font-size:1.8rem; font-weight:800; color:#1e293b; }
  .stat-box .stat-label { font-size:0.8rem; color:#64748b; font-weight:500; text-transform:uppercase; letter-spacing:0.5px; }
  table { width:100%; border-collapse:collapse; margin-top:8px; }
  thead th { padding:12px 16px; background:linear-gradient(135deg,#1e293b,#334155); color:#fff; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; text-align:left; }
  thead th:first-child { border-radius:8px 0 0 0; }
  thead th:last-child { border-radius:0 8px 0 0; }
  tbody tr:nth-child(even) { background:#f8faff; }
  tbody td { padding:12px 16px; border-bottom:1px solid #eef2f7; font-size:0.875rem; color:#475569; }
  tbody tr:last-child td { border-bottom:none; }
  .report-footer { text-align:center; padding:24px 0 8px; color:#94a3b8; font-size:0.8rem; border-top:1px solid #eef2f7; margin-top:28px; }
  .badge-type { display:inline-block; padding:2px 10px; border-radius:12px; font-size:0.75rem; font-weight:600; }
  .badge-website { background:#eef2ff; color:#4f46e5; }
  .badge-credential { background:#fef3c7; color:#d97706; }
  .badge-card { background:#fce7f3; color:#db2777; }
  @media (max-width:768px) { .report-wrapper { padding:16px 10px; } .report-header { padding:24px 20px 20px; border-radius:12px 12px 0 0; } .report-header h1 { font-size:1.3rem; } .report-body { padding:20px 16px; } .stats-row { gap:12px; } .stat-box { min-width:100px; padding:12px 14px; } .stat-box .stat-value { font-size:1.3rem; } .report-header .meta { font-size:0.75rem; gap:10px; } thead th, tbody td { padding:8px 10px; font-size:0.75rem; } }
  @media (max-width:480px) { .report-header { padding:18px 14px 16px; } .report-header h1 { font-size:1.1rem; } .report-body { padding:14px 10px; } .stats-row { flex-direction:column; gap:8px; } .stat-box { min-width:auto; } thead th, tbody td { padding:6px 6px; font-size:0.65rem; } .report-footer { font-size:0.65rem; } }
</style>
</head>
<body>
<div class="report-wrapper">
  <div class="report-header">
    <div class="brand">RS PAASWORD MANAGER</div>
    <h1>Trash Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . $date . '</span>
      <span><strong style="color:#e2e8f0">Total Items:</strong> ' . $totalTrashed . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box"><div class="stat-value" style="color:#4f46e5">' . count($trashedWebsites) . '</div><div class="stat-label">Websites</div></div>
      <div class="stat-box"><div class="stat-value" style="color:#d97706">' . count($trashedCredentials) . '</div><div class="stat-label">Credentials</div></div>
      <div class="stat-box"><div class="stat-value" style="color:#db2777">' . count($trashedCards) . '</div><div class="stat-label">Cards</div></div>
    </div>
    <table>
      <thead><tr><th>Type</th><th>Item</th><th>Detail</th><th>Deleted</th></tr></thead>
      <tbody>' . $rows . '</tbody>
    </table>
    <div class="report-footer">RS PAASWORD MANAGER &bull; Confidential &bull; Generated ' . $date . ' &bull; <strong>' . $totalTrashed . '</strong> item' . ($totalTrashed !== 1 ? 's' : '') . '</div>
  </div>
</div>
</body>
</html>';
        exit;
    }

    if ($format === 'pdf') {
        require_once __DIR__ . '/lib/tcpdf/tcpdf.php';
        class RS_Trash_PDF extends TCPDF {
            public function Header() {
                $this->SetFont('helvetica', 'B', 16);
                $this->SetTextColor(30, 41, 59);
                $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
                $this->SetFont('helvetica', '', 10);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(0, 0, 'Trash Report', 0, 1, 'L');
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
        $pdf = new RS_Trash_PDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('RS PAASWORD MANAGER');
        $pdf->SetAuthor('RS PAASWORD MANAGER');
        $pdf->SetTitle('Trash Report');
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(15, 32, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $cardW = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - 12) / 3;
        $startY = $pdf->GetY();
        $stats = [
            ['Websites', count($trashedWebsites), [238,242,255], [79,70,229]],
            ['Credentials', count($trashedCredentials), [254,243,199], [217,119,6]],
            ['Cards', count($trashedCards), [252,231,243], [219,39,119]],
        ];
        for ($i = 0; $i < 3; $i++) {
            $x = $pdf->getMargins()['left'] + ($i * ($cardW + 6));
            $pdf->SetFillColor($stats[$i][2][0], $stats[$i][2][1], $stats[$i][2][2]);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Rect($x, $startY, $cardW, 18, 'DF');
            $pdf->SetXY($x, $startY + 3);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($cardW, 5, $stats[$i][0], 0, 1, 'C');
            $pdf->SetX($x);
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->SetTextColor($stats[$i][3][0], $stats[$i][3][1], $stats[$i][3][2]);
            $pdf->Cell($cardW, 8, (string)$stats[$i][1], 0, 1, 'C');
        }
        $pdf->SetY($startY + 18 + 10);
        $colW = [25, 60, 60, 35];
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(30, 41, 59);
        foreach (['Type', 'Item', 'Detail', 'Deleted'] as $j => $h) {
            $pdf->Cell($colW[$j], 8, $h, 1, 0, 'L', 1);
        }
        $pdf->Ln();
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetDrawColor(226, 232, 240);
        $i = 0;
        foreach ($trashedWebsites as $w) {
            $fill = ($i++ % 2 === 0) ? [248,250,252] : [255,255,255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[0], 7, 'Website', 1, 0, 'L', 1);
            $pdf->Cell($colW[1], 7, $esc($w['website_name']), 1, 0, 'L', 1);
            $pdf->Cell($colW[2], 7, $esc($w['website_url'] ?? '-'), 1, 0, 'L', 1);
            $pdf->Cell($colW[3], 7, timeAgo($w['deleted_at']), 1, 1, 'L', 1);
        }
        foreach ($trashedCredentials as $c) {
            $fill = ($i++ % 2 === 0) ? [248,250,252] : [255,255,255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[0], 7, 'Credential', 1, 0, 'L', 1);
            $pdf->Cell($colW[1], 7, $esc($c['website_name']) . ' / ' . $esc($c['title']), 1, 0, 'L', 1);
            $pdf->Cell($colW[2], 7, $esc($c['username']), 1, 0, 'L', 1);
            $pdf->Cell($colW[3], 7, timeAgo($c['deleted_at']), 1, 1, 'L', 1);
        }
        foreach ($trashedCards as $c) {
            $fill = ($i++ % 2 === 0) ? [248,250,252] : [255,255,255];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell($colW[0], 7, 'Card', 1, 0, 'L', 1);
            $pdf->Cell($colW[1], 7, $esc($c['cardholder_name']), 1, 0, 'L', 1);
            $pdf->Cell($colW[2], 7, '****' . $esc($c['last_four'] ?? '----'), 1, 0, 'L', 1);
            $pdf->Cell($colW[3], 7, timeAgo($c['deleted_at']), 1, 1, 'L', 1);
        }
        $pdf->Output('trash_export_' . date('Y-m-d') . '.pdf', 'D');
        exit;
    }
}

$pageTitle = 'Trash';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-trash" style="color:var(--danger)"></i> Trash</h1>
                    <p><?php echo count($trashedWebsites); ?> website(s) &middot; <?php echo count($trashedCredentials); ?> credential(s) &middot; <?php echo count($trashedCards); ?> card(s) in trash</p>
                </div>
                <div class="page-actions" style="gap:8px">
                    <?php if (!empty($trashedWebsites) || !empty($trashedCredentials) || !empty($trashedCards)): ?>
                        <button class="btn btn-sm btn-ghost" onclick="emptyTrash()"><i class="fas fa-trash-alt"></i> Empty Trash</button>
                        <a href="?export=html" class="btn btn-sm btn-ghost" title="Export HTML"><i class="fas fa-file-code"></i></a>
                        <a href="?export=pdf" class="btn btn-sm btn-ghost" title="Export PDF"><i class="fas fa-file-pdf"></i></a>
                    <?php endif; ?>
                    <a href="vault.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Vault</a>
                </div>
            </div>

            <?php if (empty($trashedWebsites) && empty($trashedCredentials) && empty($trashedCards)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-trash" style="color:var(--text-muted)"></i></div>
                        <h3>Trash is empty</h3>
                        <p>Deleted items will appear here. You can restore them or permanently delete them.</p>
                        <a href="vault.php" class="btn btn-primary mt-4"><i class="fas fa-arrow-left"></i> Go to Vault</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($trashedWebsites)): ?>
                <div class="section-divider"><span>Deleted Websites</span></div>
                <div class="website-grid">
                    <?php foreach ($trashedWebsites as $w): ?>
                        <div class="website-group-card trashed-card">
                            <div class="website-group-header">
                                <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
                                    <div class="website-icon" style="background:rgba(239,68,68,0.15)"><?php $favicon = getFaviconUrl($w['website_url']); if ($favicon): ?><img src="<?php echo $favicon; ?>" alt="" style="width:22px;height:22px;border-radius:4px" onerror="this.parentNode.innerHTML='<i class=\'fas fa-globe\' style=\'color:var(--danger)\'></i>'"><?php else: ?><i class="fas fa-globe" style="color:var(--danger)"></i><?php endif; ?></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="website-name"><?php echo sanitizeOutput($w['website_name']); ?></div>
                                        <div class="website-meta">
                                            Deleted <?php echo timeAgo($w['deleted_at']); ?>
                                            &middot; <?php echo (int)$w['cred_count']; ?> credential(s) in trash
                                            <?php if ((int)$w['active_cred_count'] > 0): ?>
                                                &middot; <span style="color:var(--success)"><?php echo (int)$w['active_cred_count']; ?> active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <button class="btn btn-sm btn-success" onclick="restoreWebsite(<?php echo $w['id']; ?>)"><i class="fas fa-undo"></i> Restore</button>
                                    <button class="btn btn-sm btn-danger" onclick="permanentDeleteWebsite(<?php echo $w['id']; ?>)"><i class="fas fa-trash-alt"></i> Delete Forever</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($trashedCredentials)): ?>
                <div class="section-divider" style="margin-top:24px"><span>Deleted Credentials</span></div>
                <div class="card" style="margin-top:16px">
                    <div class="creds-list">
                        <?php foreach ($trashedCredentials as $c): ?>
                            <div class="cred-row">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
                                    <div style="width:3px;height:28px;border-radius:2px;background:var(--danger);flex-shrink:0"></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="cred-title"><?php echo sanitizeOutput($c['title']); ?></div>
                                        <div class="cred-username">
                                            <?php echo sanitizeOutput($c['username']); ?>
                                            &nbsp;&middot;&nbsp; <?php echo sanitizeOutput($c['website_name']); ?>
                                            &nbsp;&middot;&nbsp; Deleted <?php echo timeAgo($c['deleted_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="cred-row-actions">
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="restoreCredential(<?php echo $c['id']; ?>)" title="Restore">
                                        <i class="fas fa-undo" style="color:var(--success)"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="permanentDeleteCredential(<?php echo $c['id']; ?>)" title="Delete Forever">
                                        <i class="fas fa-trash-alt" style="color:var(--danger)"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($trashedCards)): ?>
                <div class="section-divider" style="margin-top:24px"><span>Deleted Cards</span></div>
                <div class="card" style="margin-top:16px">
                    <div class="creds-list">
                        <?php foreach ($trashedCards as $c): ?>
                        <?php
                            $network = $c['card_network'] ?? 'unknown';
                            $type = $c['card_type'] === 'debit' ? 'Debit' : 'Credit';
                            $networkIcons = ['visa' => 'fab fa-cc-visa', 'mastercard' => 'fab fa-cc-mastercard', 'amex' => 'fab fa-cc-amex', 'discover' => 'fab fa-cc-discover', 'rupay' => 'fas fa-credit-card'];
                            $icon = $networkIcons[$network] ?? 'fas fa-credit-card';
                        ?>
                            <div class="cred-row">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
                                    <div style="width:3px;height:28px;border-radius:2px;background:var(--danger);flex-shrink:0"></div>
                                    <div class="website-icon" style="width:36px;height:36px;border-radius:8px;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                        <i class="<?php echo $icon; ?>" style="color:var(--danger);font-size:1rem"></i>
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <div class="cred-title"><?php echo sanitizeOutput($c['cardholder_name']); ?></div>
                                        <div class="cred-username">
                                            **** <?php echo sanitizeOutput($c['last_four'] ?? '----'); ?>
                                            &nbsp;&middot;&nbsp; <?php echo $type; ?>
                                            &nbsp;&middot;&nbsp; <?php echo sanitizeOutput($c['bank_name'] ?? '-'); ?>
                                            &nbsp;&middot;&nbsp; Deleted <?php echo timeAgo($c['deleted_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="cred-row-actions">
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="restoreCard(<?php echo $c['id']; ?>)" title="Restore">
                                        <i class="fas fa-undo" style="color:var(--success)"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="permanentDeleteCard(<?php echo $c['id']; ?>)" title="Delete Forever">
                                        <i class="fas fa-trash-alt" style="color:var(--danger)"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function restoreWebsite(id) {
    fetch('api/credentials.php?action=restore_website', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Website restored', 'success'); setTimeout(function() { location.reload(); }, 500); }
        else { showToast('Failed', 'error'); }
    });
}

function restoreCredential(id) {
    fetch('api/credentials.php?action=restore_credential', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Credential restored', 'success'); setTimeout(function() { location.reload(); }, 500); }
        else { showToast('Failed', 'error'); }
    });
}

function permanentDeleteWebsite(id) {
    showConfirmDialog('Permanently delete this website and all its credentials? This cannot be undone.', 'Permanently Delete', 'Delete Forever', 'Cancel', function() {
        fetch('api/credentials.php?action=permanent_delete_website', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast('Permanently deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

function permanentDeleteCredential(id) {
    showConfirmDialog('Permanently delete this credential? This cannot be undone.', 'Permanently Delete', 'Delete Forever', 'Cancel', function() {
        fetch('api/credentials.php?action=permanent_delete_credential', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast('Permanently deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

function restoreCard(id) {
    fetch('api/cards.php?action=restore_card', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Card restored', 'success'); setTimeout(function() { location.reload(); }, 500); }
        else { showToast('Failed', 'error'); }
    });
}

function permanentDeleteCard(id) {
    showConfirmDialog('Permanently delete this card? This cannot be undone.', 'Permanently Delete', 'Delete Forever', 'Cancel', function() {
        fetch('api/cards.php?action=permanent_delete_card', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast('Permanently deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

function emptyTrash() {
    showConfirmDialog('Permanently delete ALL items in trash? This cannot be undone.', 'Empty Trash', 'Delete All', 'Cancel', function() {
        var promises = [];
        <?php foreach ($trashedCredentials as $c): ?>
        promises.push(
            fetch('api/credentials.php?action=permanent_delete_credential', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?php echo $c['id']; ?>, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            }).then(function(r) { return r.json(); })
        );
        <?php endforeach; ?>
        <?php foreach ($trashedWebsites as $w): ?>
        promises.push(
            fetch('api/credentials.php?action=permanent_delete_website', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?php echo $w['id']; ?>, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            }).then(function(r) { return r.json(); })
        );
        <?php endforeach; ?>
        <?php foreach ($trashedCards as $c): ?>
        promises.push(
            fetch('api/cards.php?action=permanent_delete_card', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?php echo $c['id']; ?>, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            }).then(function(r) { return r.json(); })
        );
        <?php endforeach; ?>
        Promise.all(promises).then(function() {
            showToast('Trash emptied', 'success');
            setTimeout(function() { location.reload(); }, 500);
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
