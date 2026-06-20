<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_COOKIE['admin_theme'] ?? 'dark'; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle ?? 'Admin Panel'; ?> - RS PAASWORD MANAGER</title>
  <link rel="icon" type="image/svg+xml" href="../assets/default-favicon.svg">
  <link rel="alternate icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>🔐</text></svg>">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root { --admin-topbar-h: 56px; }
    .admin-topbar { display:flex;align-items:center;justify-content:space-between;padding:12px 24px;background:var(--card-bg);border-bottom:1px solid var(--border-soft);position:fixed;top:0;left:0;right:0;z-index:101;height:var(--admin-topbar-h); }
    .admin-topbar .sidebar-toggle { display:none;background:none;border:none;color:var(--text-muted);font-size:1.2rem;cursor:pointer;padding:4px 8px; }
    .admin-topbar .topbar-right { display:flex;align-items:center;gap:12px; }
    .admin-topbar .topbar-right .nav-icon-btn { background:none;border:none;color:var(--text-muted);font-size:1.1rem;cursor:pointer;padding:6px 10px;border-radius:var(--radius-md);transition:all 0.2s; }
    .admin-topbar .topbar-right .nav-icon-btn:hover { background:var(--card-glass);color:var(--text-main); }
    .admin-topbar .topbar-title { font-size:0.9rem;font-weight:600;color:var(--text-main); }
    .admin-layout { padding-top:var(--admin-topbar-h); }
    .admin-page .sidebar { top:var(--admin-topbar-h);height:calc(100vh - var(--admin-topbar-h)); }
    @media (max-width:768px) {
      .admin-topbar .sidebar-toggle { display:block; }
      .admin-topbar .topbar-right { gap:6px; }
      .admin-page .sidebar { top:var(--admin-topbar-h);height:calc(100vh - var(--admin-topbar-h)); }
    }
    @media (max-width:480px) {
      :root { --admin-topbar-h: 48px; }
      .admin-topbar { padding:10px 12px; }
      .admin-topbar .admin-user-name-text { display:none; }
      .admin-topbar .topbar-right .nav-icon-btn { padding:4px 6px; font-size:1rem; }
    }
    .admin-page .page-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px; }
    .admin-page .page-header h1 { font-size:1.5rem;margin:0;display:flex;align-items:center;gap:10px; }
    .admin-page .page-actions { display:flex;gap:8px;flex-wrap:wrap; }
    .admin-page .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--radius-md);font-size:0.85rem;font-weight:500;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s; }
    .admin-page .btn-primary { background:var(--accent);color:#fff; }
    .admin-page .btn-primary:hover { filter:brightness(1.1); }
    .admin-page .btn-secondary { background:var(--card-glass);color:var(--text-main);border:1px solid var(--border-soft); }
    .admin-page .btn-secondary:hover { background:var(--border-soft); }
    .admin-page .btn-sm { padding:5px 10px;font-size:0.8rem; }
    .admin-page .btn-success { background:rgba(34,197,94,0.2);color:#4ade80;border:1px solid rgba(34,197,94,0.3); }
    .admin-page .btn-success:hover { background:rgba(34,197,94,0.3); }
    .admin-page .btn-info { background:rgba(59,130,246,0.2);color:#60a5fa;border:1px solid rgba(59,130,246,0.3); }
    .admin-page .btn-info:hover { background:rgba(59,130,246,0.3); }
    .admin-page .btn-warning { background:rgba(251,191,36,0.2);color:#fbbf24;border:1px solid rgba(251,191,36,0.3); }
    .admin-page .btn-warning:hover { background:rgba(251,191,36,0.3); }
    .admin-page .form-input { background:var(--bg-soft);border:1px solid var(--border-soft);color:var(--text-main);padding:8px 12px;border-radius:var(--radius-md);font-size:0.85rem;outline:none; }
    .admin-page .form-input:focus { border-color:var(--accent); }
    .admin-page .table-wrapper { overflow-x:auto; }
    .admin-page .toast { position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:var(--radius-md);font-size:0.85rem;z-index:9999;transform:translateY(100px);opacity:0;transition:all 0.3s; }
    .admin-page .toast.show { transform:translateY(0);opacity:1; }
    .admin-page .toast-success { background:rgba(34,197,94,0.9);color:#fff; }
    .admin-page .toast-error { background:rgba(239,68,68,0.9);color:#fff; }
  </style>
</head>
<body class="admin-page">
  <div class="admin-topbar">
    <div>
      <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
      <span class="topbar-title"><?php echo $pageTitle ?? 'Admin Panel'; ?></span>
    </div>
    <div class="topbar-right">
      <div style="display:flex;align-items:center;gap:8px">
        <div class="user-avatar-small" style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#a855f7);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:#fff;flex-shrink:0">
          <span><?php echo strtoupper(substr($_SESSION['admin_display_name'] ?? 'A', 0, 1)); ?></span>
        </div>
        <span class="admin-user-name-text" style="font-size:0.85rem;font-weight:500;color:var(--text-main)"><?php echo htmlspecialchars($_SESSION['admin_display_name'] ?? 'Admin'); ?></span>
      </div>
      <button class="nav-icon-btn" id="adminThemeToggle" title="Toggle theme"><i class="fas fa-moon"></i></button>
      <a href="../dashboard.php" class="nav-icon-btn" title="Back to App"><i class="fas fa-external-link-alt"></i></a>
      <a href="logout.php" class="nav-icon-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
<script>
var _sidebarScroll=0;function toggleSidebar(){var s=document.getElementById('sidebar');if(!s.classList.contains('open')){_sidebarScroll=window.scrollY}else{window.scrollTo(0,_sidebarScroll)}s.classList.toggle('open');document.body.classList.toggle('sidebar-open')}
document.addEventListener('DOMContentLoaded', function() {
  var tt = document.getElementById('adminThemeToggle');
  if (tt) {
    var html = document.documentElement;
    var cur = html.getAttribute('data-theme') || 'dark';
    tt.innerHTML = cur === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    tt.onclick = function() {
      var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      tt.innerHTML = next === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
      document.cookie = 'admin_theme=' + next + ';path=/;max-age=31536000';
    };
  }
});
</script>
