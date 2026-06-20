<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDbConnection();
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: users.php'); exit; }

// User info
$stmt = $pdo->prepare('SELECT id, username, email, display_name, phone, is_admin, created_at, updated_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { header('Location: users.php'); exit; }

// Stats
$stmt = $pdo->prepare('SELECT COUNT(*) FROM websites WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([$userId]);
$websiteCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM credentials c JOIN websites w ON c.website_id = w.id WHERE w.user_id = ? AND c.deleted_at IS NULL AND w.deleted_at IS NULL');
$stmt->execute([$userId]);
$credCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([$userId]);
$cardCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM oauth_accounts WHERE user_id = ?');
$stmt->execute([$userId]);
$oauthCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = ?');
$stmt->execute([$userId]);
$catCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE user_id = ?');
$stmt->execute([$userId]);
$activityCount = (int)$stmt->fetchColumn();

// Websites + credentials
$websites = $pdo->prepare('SELECT w.*,
    (SELECT COUNT(*) FROM credentials c WHERE c.website_id = w.id AND c.deleted_at IS NULL) as cred_count
    FROM websites w WHERE w.user_id = ? AND w.deleted_at IS NULL ORDER BY w.website_name');
$websites->execute([$userId]);
$websites = $websites->fetchAll();

// Cards
$cards = $pdo->prepare('SELECT id, card_type, cardholder_name, last_four, bank_name, card_network, is_favorite, created_at FROM cards WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
$cards->execute([$userId]);
$cards = $cards->fetchAll();

// OAuth
$oauth = $pdo->prepare('SELECT id, website_name, website_url, email, created_at FROM oauth_accounts WHERE user_id = ? ORDER BY website_name');
$oauth->execute([$userId]);
$oauth = $oauth->fetchAll();

$pageTitle = 'User: ' . htmlspecialchars($user['display_name'] ?: $user['username']);
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-layout">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="admin-content">
    <div class="page-header">
      <h1><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></h1>
      <div class="page-actions">
        <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Users</a>
      </div>
    </div>

    <!-- User Info Card -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a855f7);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0"><?php echo strtoupper(substr($user['display_name'] ?: $user['username'], 0, 1)); ?></div>
        <div style="flex:1;min-width:200px">
          <div style="font-size:1.2rem;font-weight:700"><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></div>
          <div style="color:var(--text-muted);font-size:0.85rem;margin-top:2px">
            @<?php echo htmlspecialchars($user['username']); ?> &middot; <?php echo htmlspecialchars($user['email']); ?>
            <?php if ($user['is_admin']): ?>&middot; <span class="badge badge-success">Admin</span><?php endif; ?>
          </div>
          <?php if ($user['phone']): ?>
          <div style="color:var(--text-muted);font-size:0.8rem;margin-top:4px">
            Phone: <span class="phone-monospace"><?php echo htmlspecialchars($user['phone']); ?></span>
          </div>
          <?php endif; ?>
          <div style="color:var(--text-muted);font-size:0.8rem;margin-top:4px">
            Registered <?php echo date('M j, Y \\a\\t g:i A', strtotime($user['created_at'])); ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px">
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:1.8rem;font-weight:800;color:var(--accent)"><?php echo $websiteCount; ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px">Websites</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:1.8rem;font-weight:800;color:#4ade80"><?php echo $credCount; ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px">Credentials</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:1.8rem;font-weight:800;color:#fbbf24"><?php echo $cardCount; ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px">Cards</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:1.8rem;font-weight:800;color:#60a5fa"><?php echo $oauthCount; ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px">OAuth Accounts</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:1.8rem;font-weight:800;color:#f472b6"><?php echo $catCount; ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px">Categories</div>
      </div>
      <div class="card" style="text-align:center;padding:16px">
        <div style="font-size:1.8rem;font-weight:800;color:#a78bfa"><?php echo $activityCount; ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px">Activity Events</div>
      </div>
    </div>

    <!-- Websites & Credentials -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><h3><i class="fas fa-globe"></i> Websites &amp; Credentials (<?php echo count($websites); ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($websites)): ?>
          <p class="text-muted">No websites saved.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead><tr><th>Website</th><th>URL</th><th>Credentials</th><th>Created</th></tr></thead>
              <tbody>
                <?php foreach ($websites as $w): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px">
                      <?php $favicon = getFaviconUrl($w['website_url'] ?? ''); if ($favicon): ?>
                        <img src="<?php echo $favicon; ?>" alt="" style="width:18px;height:18px;border-radius:3px" onerror="this.style.display='none'">
                      <?php endif; ?>
                      <span style="font-weight:600"><?php echo htmlspecialchars($w['website_name']); ?></span>
                    </div>
                  </td>
                  <td style="color:var(--text-muted);font-size:0.85rem"><?php echo htmlspecialchars($w['website_url'] ?? '-'); ?></td>
                  <td><?php echo (int)$w['cred_count']; ?></td>
                  <td style="font-size:0.85rem;color:var(--text-muted)"><?php echo date('M j, Y', strtotime($w['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Cards -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><h3><i class="fas fa-credit-card"></i> Cards (<?php echo count($cards); ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($cards)): ?>
          <p class="text-muted">No cards saved.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead><tr><th>Cardholder</th><th>Network</th><th>Type</th><th>Last 4</th><th>Bank</th><th>Favorite</th><th>Added</th></tr></thead>
              <tbody>
                <?php foreach ($cards as $c): ?>
                <?php
                  $networkIcons = ['visa' => 'fab fa-cc-visa', 'mastercard' => 'fab fa-cc-mastercard', 'amex' => 'fab fa-cc-amex', 'discover' => 'fab fa-cc-discover'];
                  $icon = $networkIcons[$c['card_network'] ?? ''] ?? 'fas fa-credit-card';
                ?>
                <tr>
                  <td style="font-weight:600"><?php echo htmlspecialchars($c['cardholder_name']); ?></td>
                  <td><i class="<?php echo $icon; ?>" style="font-size:1.1rem"></i> <?php echo htmlspecialchars(ucfirst($c['card_network'] ?? '-')); ?></td>
                  <td><?php echo $c['card_type'] === 'debit' ? 'Debit' : 'Credit'; ?></td>
                  <td style="font-family:monospace">**** <?php echo htmlspecialchars($c['last_four'] ?? '----'); ?></td>
                  <td><?php echo htmlspecialchars($c['bank_name'] ?? '-'); ?></td>
                  <td><?php echo $c['is_favorite'] ? '<span class="badge badge-warning"><i class="fas fa-star"></i></span>' : '-'; ?></td>
                  <td style="font-size:0.85rem;color:var(--text-muted)"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- OAuth Accounts -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><h3><i class="fas fa-key"></i> OAuth Accounts (<?php echo count($oauth); ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($oauth)): ?>
          <p class="text-muted">No OAuth accounts saved.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="table">
              <thead><tr><th>Website</th><th>URL</th><th>Email</th><th>Added</th></tr></thead>
              <tbody>
                <?php foreach ($oauth as $o): ?>
                <tr>
                  <td style="font-weight:600"><?php echo htmlspecialchars($o['website_name']); ?></td>
                  <td style="color:var(--text-muted);font-size:0.85rem"><?php echo htmlspecialchars($o['website_url'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($o['email']); ?></td>
                  <td style="font-size:0.85rem;color:var(--text-muted)"><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
