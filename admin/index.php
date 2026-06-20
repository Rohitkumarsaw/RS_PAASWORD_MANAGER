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
$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/admin_header.php';

$pdo = getDbConnection();
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalVaults = $pdo->query('SELECT COUNT(*) FROM credentials c JOIN websites w ON w.id = c.website_id WHERE c.deleted_at IS NULL')->fetchColumn();
$totalCards = $pdo->query('SELECT COUNT(*) FROM cards')->fetchColumn();
?>
<div class="admin-layout">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="admin-content">
    <div class="page-header">
      <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
      <p style="color:var(--text-muted);font-size:0.85rem">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></p>
    </div>

    <div class="stats-grid">
      <a href="users.php" class="stat-card" style="text-decoration:none;color:inherit">
        <div class="stat-icon primary"><i class="fas fa-users"></i></div>
        <div class="stat-info"><h3><?php echo $totalUsers; ?></h3><p>Total Users</p></div>
      </a>
      <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-key"></i></div>
        <div class="stat-info"><h3><?php echo $totalVaults; ?></h3><p>Stored Passwords</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-credit-card"></i></div>
        <div class="stat-info"><h3><?php echo $totalCards; ?></h3><p>Saved Cards</p></div>
      </div>
    </div>

    <div class="card" style="margin-top:24px">
      <div class="card-header"><h3><i class="fas fa-clock"></i> Quick Links</h3></div>
      <div class="card-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <a href="users.php" class="btn btn-primary"><i class="fas fa-users"></i> Manage Users</a>
          <a href="../vault.php" class="btn btn-secondary"><i class="fas fa-key"></i> View Vault</a>
          <a href="../dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to App</a>
        </div>
      </div>
    </div>
  </main>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
