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
    header('Content-Disposition: attachment; filename="rs_paasword_manager_export_' . date('Y-m-d') . '.csv"');

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

    $rowsHtml = '';
    foreach ($entries as $e) {
        $fav = $e['favorite'] ? ' ★' : '';
        $rowsHtml .= '<tr>
            <td>' . $esc($e['website']) . $fav . '</td>
            <td>' . $esc($e['url']) . '</td>
            <td>' . $esc($e['username']) . '</td>
            <td class="pw">' . $esc($e['password']) . '</td>
            <td>' . $esc($e['title']) . '</td>
        </tr>' . "\n";
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RS PAASWORD MANAGER Export</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
.wrapper { max-width: 1200px; margin: 0 auto; background: rgba(255,255,255,0.95); border-radius: 16px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); backdrop-filter: blur(10px); }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #eef2ff; }
.header h1 { font-size: 1.6rem; color: #1e1b4b; font-weight: 700; letter-spacing: -0.02em; }
.header h1 span { color: #4f46e5; }
.meta { color: #64748b; font-size: 0.85rem; }
table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
thead th { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color: #fff; padding: 14px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
tbody tr { transition: background 0.15s; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr:hover { background: #eef2ff; }
td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; color: #1e293b; }
td.pw { font-family: "SF Mono", "Fira Code", "Consolas", monospace; letter-spacing: 0.03em; color: #0f172a; }
.footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; color: #94a3b8; font-size: 0.8rem; }
.badge { display: inline-block; background: #4f46e5; color: #fff; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
@media (max-width: 768px) { .wrapper { padding: 16px; } .header { flex-direction: column; gap: 8px; align-items: flex-start; } td, th { padding: 8px 10px; } }
</style>
</head>
<body>
<div class="wrapper">
<div class="header">
<h1><span>RS</span> PAASWORD MANAGER</h1>
<div class="meta">Exported ' . $date . ' &middot; <span class="badge">' . $count . ' credentials</span></div>
</div>
<table>
<thead><tr><th>Website</th><th>URL</th><th>Username</th><th>Password</th><th>Label</th></tr></thead>
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
    header('Content-Disposition: attachment; filename="rs_paasword_manager_export_' . date('Y-m-d') . '.html"');
    echo $html;
    exit;
}

function handleExportPdf(int $userId): void {
    define('K_TCPDF_EXTERNAL_CONFIG', true);
    require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

    $entries = getAllDecryptedCredentials($userId);
    $count = count($entries);
    $date = date('Y-m-d H:i');
    $esc = function($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('RS PAASWORD MANAGER');
    $pdf->SetTitle('RS PAASWORD MANAGER Export');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    $html = '<h1 style="color:#1e1b4b;font-size:18pt;margin-bottom:4px"><span style="color:#4f46e5">RS</span> PAASWORD MANAGER</h1>
    <p style="color:#64748b;font-size:9pt;margin-bottom:16px;border-bottom:2px solid #eef2ff;padding-bottom:8px">Exported ' . $date . ' &middot; ' . $count . ' credentials</p>
    <table border="1" cellpadding="5" cellspacing="0">
    <thead>
    <tr style="background-color:#4f46e5;color:#fff">
    <th style="font-size:8pt;font-weight:bold">Website</th>
    <th style="font-size:8pt;font-weight:bold">URL</th>
    <th style="font-size:8pt;font-weight:bold">Username</th>
    <th style="font-size:8pt;font-weight:bold">Password</th>
    <th style="font-size:8pt;font-weight:bold">Label</th>
    </tr>
    </thead>
    <tbody>';
    foreach ($entries as $e) {
        $html .= '<tr>
        <td style="font-size:9pt">' . $esc($e['website']) . '</td>
        <td style="font-size:9pt">' . $esc($e['url']) . '</td>
        <td style="font-size:9pt">' . $esc($e['username']) . '</td>
        <td style="font-size:9pt;font-family:monospace">' . $esc($e['password']) . '</td>
        <td style="font-size:9pt">' . $esc($e['title']) . '</td>
        </tr>';
    }
    $html .= '</tbody></table>
    <p style="color:#94a3b8;font-size:7pt;margin-top:12px;text-align:center">Generated by RS PAASWORD MANAGER &mdash; Confidential</p>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('rs_paasword_manager_export_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function handleImportCsv(int $userId, array $input): void {
    echo json_encode(['success' => false, 'message' => 'CSV import via API not supported. Use the web form.']);
}
