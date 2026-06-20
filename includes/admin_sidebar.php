<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="index.php" class="sidebar-logo">
      <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
      <span class="brand-text">RS PAASWORD MANAGER</span>
    </a>
    <button class="sidebar-close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
  </div>
  <nav class="sidebar-nav">
    <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
      <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
    </a>
    <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
      <i class="fas fa-users"></i><span>Users</span>
    </a>
    <a href="activity.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'activity.php' ? 'active' : ''; ?>">
      <i class="fas fa-history"></i><span>Activity Log</span>
    </a>
  </nav>
  <div class="sidebar-footer" style="padding:16px;border-top:1px solid var(--border-soft);margin-top:auto">
    <div class="sidebar-user" style="display:flex;align-items:center;gap:10px">
      <div class="user-avatar-small" style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a855f7);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:#fff;flex-shrink:0">
        <span><?php echo strtoupper(substr($_SESSION['admin_display_name'] ?? 'A', 0, 1)); ?></span>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:0.85rem;font-weight:600;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($_SESSION['admin_display_name'] ?? 'Admin'); ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($_SESSION['admin_email'] ?? ''); ?></div>
      </div>
    </div>
    <div style="display:flex;gap:6px;margin-top:10px">
      <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="flex:1;text-align:center;padding:5px;font-size:0.75rem;border-radius:var(--radius-md);text-decoration:none;background:var(--card-glass);color:var(--text-main);border:1px solid var(--border-soft)"><i class="fas fa-arrow-left"></i> App</a>
      <a href="logout.php" class="btn btn-secondary btn-sm" style="flex:1;text-align:center;padding:5px;font-size:0.75rem;border-radius:var(--radius-md);text-decoration:none;background:var(--card-glass);color:var(--danger);border:1px solid var(--border-soft)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</aside>
