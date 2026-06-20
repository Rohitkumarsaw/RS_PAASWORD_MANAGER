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
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

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
        case 'toggle_archive':
            handleToggleArchive($userId, $input);
            break;
        case 'bulk_delete':
            handleBulkDelete($userId, $input);
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
        case 'import_csv':
            handleImportCsv($userId, $input);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Entries API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleList(int $userId): void {
    $pdo = getDbConnection();
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $filter = trim($_GET['filter'] ?? '');
    $sort = trim($_GET['sort'] ?? 'updated_at');
    $order = strtoupper(trim($_GET['order'] ?? 'DESC'));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $allowedSorts = ['website', 'username', 'created_at', 'updated_at'];
    if (!in_array($sort, $allowedSorts)) $sort = 'updated_at';
    if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';

    $where = 'pe.user_id = :uid';
    $params = [':uid' => $userId];

    if ($filter === 'favorites') {
        $where .= ' AND pe.is_favorite = 1';
    } elseif ($filter === 'archived') {
        $where .= ' AND pe.is_archived = 1';
    } else {
        $where .= ' AND pe.is_archived = 0';
    }

    if (!empty($category)) {
        $where .= ' AND pe.category_id = :category';
        $params[':category'] = (int)$category;
    }

    if (!empty($search)) {
        $where .= ' AND (pe.website LIKE :search OR pe.username LIKE :search OR pe.url LIKE :search OR pe.tags LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM password_entries pe WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['total'];

    $stmt = $pdo->prepare(
        "SELECT pe.*, c.name as category_name
         FROM password_entries pe
         LEFT JOIN categories c ON pe.category_id = c.id
         WHERE $where
         ORDER BY pe.is_favorite DESC, pe.$sort $order
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    if (!empty($category)) $stmt->bindValue(':category', (int)$category, PDO::PARAM_INT);
    if (!empty($search)) $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $entries = $stmt->fetchAll();

    $encKey = getUserEncryptionKey($userId);
    $key = $encKey ? $encKey['key'] : null;
    $iv = $encKey ? $encKey['iv'] : null;

    foreach ($entries as &$entry) {
        if ($key && $iv) {
            try {
                $entry['password_decrypted'] = decryptPassword($entry['password_encrypted'], $key, $iv);
            } catch (Exception $e) {
                $entry['password_decrypted'] = '[Decryption failed]';
            }
        } else {
            $entry['password_decrypted'] = '[Encryption key unavailable]';
        }
        unset($entry['password_encrypted']);
    }

    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => max(1, ceil($total / $limit)),
    ]);
}

function handleGet(int $userId): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT pe.*, c.name as category_name
         FROM password_entries pe
         LEFT JOIN categories c ON pe.category_id = c.id
         WHERE pe.id = :id AND pe.user_id = :uid'
    );
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
        return;
    }

    $encKey = getUserEncryptionKey($userId);
    if ($encKey) {
        try {
            $entry['password_decrypted'] = decryptPassword($entry['password_encrypted'], $encKey['key'], $encKey['iv']);
        } catch (Exception $e) {
            $entry['password_decrypted'] = '[Decryption failed]';
        }
    }
    unset($entry['password_encrypted']);

    echo json_encode(['success' => true, 'entry' => $entry]);
}

function handleCreate(int $userId, array $input): void {
    $website = trim($input['website'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $url = trim($input['url'] ?? '');
    $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
    $notes = trim($input['notes'] ?? '');
    $tags = trim($input['tags'] ?? '');
    $expiryDate = !empty($input['expiry_date']) ? $input['expiry_date'] : null;

    if (empty($website) || empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Website, username, and password are required']);
        return;
    }

    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption key unavailable']);
        return;
    }

    try {
        $encrypted = encryptPassword($password, $encKey['key'], $encKey['iv']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Encryption failed']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO password_entries (user_id, category_id, website, url, username, password_encrypted, notes, tags, expiry_date)
         VALUES (:uid, :cid, :website, :url, :username, :encrypted, :notes, :tags, :expiry)'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':cid' => $categoryId,
        ':website' => $website,
        ':url' => $url,
        ':username' => $username,
        ':encrypted' => $encrypted,
        ':notes' => $notes,
        ':tags' => $tags,
        ':expiry' => $expiryDate,
    ]);

    $entryId = $pdo->lastInsertId();
    logActivity($userId, 'Password entry created', "Created entry for $website");

    echo json_encode(['success' => true, 'message' => 'Entry created successfully', 'id' => $entryId]);
}

function handleUpdate(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM password_entries WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
        return;
    }

    $website = trim($input['website'] ?? $existing['website']);
    $username = trim($input['username'] ?? $existing['username']);
    $password = $input['password'] ?? '';
    $url = trim($input['url'] ?? $existing['url']);
    $categoryId = isset($input['category_id']) ? (!empty($input['category_id']) ? (int)$input['category_id'] : null) : $existing['category_id'];
    $notes = trim($input['notes'] ?? $existing['notes']);
    $tags = trim($input['tags'] ?? $existing['tags']);
    $expiryDate = $input['expiry_date'] ?? $existing['expiry_date'];

    $encKey = getUserEncryptionKey($userId);
    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption key unavailable']);
        return;
    }

    $encrypted = $existing['password_encrypted'];
    $passwordChanged = false;

    if (!empty($password) && $password !== '[ENCRYPTED]') {
        try {
            $encrypted = encryptPassword($password, $encKey['key'], $encKey['iv']);
            $passwordChanged = true;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Encryption failed']);
            return;
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE password_entries SET
            category_id = :cid, website = :website, url = :url, username = :username,
            password_encrypted = :encrypted, notes = :notes, tags = :tags, expiry_date = :expiry
            ' . ($passwordChanged ? ', password_changed_at = NOW()' : '') . '
         WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([
        ':cid' => $categoryId,
        ':website' => $website,
        ':url' => $url,
        ':username' => $username,
        ':encrypted' => $encrypted,
        ':notes' => $notes,
        ':tags' => $tags,
        ':expiry' => $expiryDate,
        ':id' => $id,
        ':uid' => $userId,
    ]);

    logActivity($userId, 'Password entry updated', "Updated entry for $website");

    echo json_encode(['success' => true, 'message' => 'Entry updated successfully']);
}

function handleDelete(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT website FROM password_entries WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM password_entries WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $userId]);

    logActivity($userId, 'Password entry deleted', "Deleted entry for " . $entry['website']);

    echo json_encode(['success' => true, 'message' => 'Entry deleted successfully']);
}

function handleToggleFavorite(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    $fav = !empty($input['favorite']) ? 1 : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE password_entries SET is_favorite = :fav WHERE id = :id AND user_id = :uid');
    $stmt->execute([':fav' => $fav, ':id' => $id, ':uid' => $userId]);

    logActivity($userId, $fav ? 'Entry favorited' : 'Entry unfavorited', "Entry #$id " . ($fav ? 'favorited' : 'unfavorited'));

    echo json_encode(['success' => true]);
}

function handleToggleArchive(int $userId, array $input): void {
    $id = (int)($input['id'] ?? 0);
    $archive = !empty($input['archive']) ? 1 : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('UPDATE password_entries SET is_archived = :arch WHERE id = :id AND user_id = :uid');
    $stmt->execute([':arch' => $archive, ':id' => $id, ':uid' => $userId]);

    logActivity($userId, $archive ? 'Entry archived' : 'Entry unarchived', "Entry #$id " . ($archive ? 'archived' : 'unarchived'));

    echo json_encode(['success' => true]);
}

function handleBulkDelete(int $userId, array $input): void {
    $ids = $input['ids'] ?? [];
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No entries selected']);
        return;
    }

    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge($ids, [$userId]);

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("DELETE FROM password_entries WHERE id IN ($placeholders) AND user_id = ?");
    $stmt->execute($params);

    logActivity($userId, 'Bulk delete', 'Deleted ' . count($ids) . ' entries');

    echo json_encode(['success' => true, 'message' => 'Deleted ' . count($ids) . ' entries']);
}

function getAllDecryptedCredentials(int $userId): array {
    $pdo = getDbConnection();
    $encKey = getUserEncryptionKey($userId);
    $rows = [];

    // New tables: websites + credentials
    try {
        $stmt = $pdo->prepare(
            'SELECT w.website_name, w.website_url, c.title, c.username, c.password_encrypted, c.is_favorite
             FROM credentials c
             JOIN websites w ON c.website_id = w.id
             WHERE w.user_id = :uid AND c.deleted_at IS NULL AND w.deleted_at IS NULL
             ORDER BY w.website_name ASC, c.title ASC'
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {}

    $results = [];
    foreach ($rows as $row) {
        $decrypted = '[Encrypted]';
        if ($encKey) {
            try {
                $decrypted = decryptPassword($row['password_encrypted'], $encKey['key'], $encKey['iv']);
            } catch (Exception $e) {}
        }
        $results[] = [
            'website'     => $row['website_name'],
            'url'         => $row['website_url'] ?? '',
            'username'    => $row['username'],
            'password'    => $decrypted,
            'title'       => $row['title'],
            'favorite'    => $row['is_favorite'] ?? 0,
        ];
    }

    // Also get from old password_entries table for backward compat
    try {
        $stmt2 = $pdo->prepare(
            'SELECT pe.website, pe.url, pe.username, pe.password_encrypted, pe.notes, pe.tags, c.name as category_name
             FROM password_entries pe
             LEFT JOIN categories c ON pe.category_id = c.id
             WHERE pe.user_id = :uid AND pe.is_archived = 0
             ORDER BY pe.website ASC'
        );
        $stmt2->execute([':uid' => $userId]);
        $oldRows = $stmt2->fetchAll();

        foreach ($oldRows as $row) {
            $decrypted = '[Encrypted]';
            if ($encKey) {
                try {
                    $decrypted = decryptPassword($row['password_encrypted'], $encKey['key'], $encKey['iv']);
                } catch (Exception $e) {}
            }
            $results[] = [
                'website'     => $row['website'],
                'url'         => $row['url'] ?? '',
                'username'    => $row['username'],
                'password'    => $decrypted,
                'title'       => $row['username'],
                'favorite'    => 0,
            ];
        }
    } catch (PDOException $e) {}

    return $results;
}

function handleExportCsv(int $userId): void {
    $entries = getAllDecryptedCredentials($userId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="credentials_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Website', 'URL', 'Username', 'Password', 'Label']);

    foreach ($entries as $e) {
        fputcsv($output, [$e['website'], $e['url'], $e['username'], $e['password'], $e['title']]);
    }
    fclose($output);
    exit;
}

function handleExportHtml(int $userId): void {
    $entries = getAllDecryptedCredentials($userId);

    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $count = count($entries);

    $favCount = 0;
    $rowsHtml = '';
    foreach ($entries as $e) {
        $fav = $e['favorite'] ? ' ★' : '';
        if ($e['favorite']) $favCount++;
        $rowsHtml .= '<tr>
            <td>' . $esc($e['website']) . $fav . '</td>
            <td>' . $esc($e['url']) . '</td>
            <td>' . $esc($e['username']) . '</td>
            <td>' . $esc($e['password']) . '</td>
            <td>' . $esc($e['title']) . '</td>
        </tr>' . "\n";
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Credentials Report - RS PAASWORD MANAGER</title>
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
    <h1>Credentials Report</h1>
    <div class="meta">
      <span><strong style="color:#e2e8f0">Generated:</strong> ' . $date . '</span>
      <span><strong style="color:#e2e8f0">Total Credentials:</strong> ' . $count . '</span>
    </div>
  </div>
  <div class="report-body">
    <div class="stats-row">
      <div class="stat-box accent">
        <div class="stat-value">' . $count . '</div>
        <div class="stat-label">Total Credentials</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">' . $favCount . '</div>
        <div class="stat-label">Favorites</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">' . count(array_unique(array_column($entries, 'website'))) . '</div>
        <div class="stat-label">Websites</div>
      </div>
    </div>
    <table>
      <thead><tr><th>Website</th><th>URL</th><th>Username</th><th>Password</th><th>Label</th></tr></thead>
      <tbody>
' . $rowsHtml . '
      </tbody>
    </table>
    <div class="report-footer">RS PAASWORD MANAGER &bull; Confidential &bull; Generated ' . $date . ' &bull; <strong>' . $count . '</strong> credential' . ($count !== 1 ? 's' : '') . '</div>
  </div>
</div>
</body>
</html>';

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="credentials_export_' . date('Y-m-d') . '.html"');
    echo $html;
    exit;
}

function handleExportPdf(int $userId): void {
    require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

    $entries = getAllDecryptedCredentials($userId);
    $count = count($entries);
    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    class RS_Creds_PDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(0, 8, 'RS PAASWORD MANAGER', 0, 1, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 0, 'Credentials Report', 0, 1, 'L');
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

    $favCount = 0;
    $sites = [];
    foreach ($entries as $e) {
        if ($e['favorite']) $favCount++;
        $sites[$e['website']] = true;
    }

    $pdf = new RS_Creds_PDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RS PAASWORD MANAGER');
    $pdf->SetAuthor('RS PAASWORD MANAGER');
    $pdf->SetTitle('Credentials Report');
    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetMargins(15, 32, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(12);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $cardW = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - 12) / 3;
    $startY = $pdf->GetY();
    $cols = [
        ['label' => 'Total Credentials', 'value' => $count, 'fill' => [238, 242, 255], 'text' => [79, 70, 229]],
        ['label' => 'Favorites', 'value' => $favCount, 'fill' => [255, 247, 237], 'text' => [234, 88, 12]],
        ['label' => 'Websites', 'value' => count($sites), 'fill' => [240, 253, 244], 'text' => [22, 163, 74]],
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
    $colW = [35, 50, 40, 45, 30];

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(30, 41, 59);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(30, 41, 59);
    $headers = ['Website', 'URL', 'Username', 'Password', 'Label'];
    $aligns  = ['L', 'L', 'L', 'L', 'L'];
    foreach ($headers as $j => $h) {
        $pdf->Cell($colW[$j], 8, $h, 1, 0, $aligns[$j], 1);
    }
    $pdf->Ln();

    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetDrawColor(226, 232, 240);

    foreach ($entries as $i => $e) {
        $fav = $e['favorite'] ? ' ★' : '';
        $fill = ($i % 2 === 0) ? [248, 250, 252] : [255, 255, 255];
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        $pdf->Cell($colW[0], 7, $esc($e['website'] . $fav), 1, 0, 'L', 1);
        $pdf->Cell($colW[1], 7, $esc($e['url']), 1, 0, 'L', 1);
        $pdf->Cell($colW[2], 7, $esc($e['username']), 1, 0, 'L', 1);
        $pdf->Cell($colW[3], 7, $esc($e['password']), 1, 0, 'L', 1);
        $pdf->Cell($colW[4], 7, $esc($e['title']), 1, 1, 'L', 1);
    }

    $pdf->Output('credentials_export_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function handleImportCsv(int $userId, array $input): void {
    echo json_encode(['success' => false, 'message' => 'CSV import via API not supported. Use the web form.']);
}
