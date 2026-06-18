<?php
$currentUserId = getCurrentUserId();
$sidebarEntries = [
    ['icon' => 'fas fa-chart-pie', 'label' => 'Dashboard', 'link' => 'dashboard.php', 'active' => basename($_SERVER['PHP_SELF']) === 'dashboard.php'],
    ['icon' => 'fas fa-heartbeat', 'label' => 'Password Health', 'link' => 'password-health.php', 'active' => basename($_SERVER['PHP_SELF']) === 'password-health.php'],
    ['icon' => 'fas fa-globe', 'label' => 'My Vault', 'link' => 'vault.php', 'active' => basename($_SERVER['PHP_SELF']) === 'vault.php' && !isset($_GET['filter'])],
    ['icon' => 'fas fa-credit-card', 'label' => 'Cards', 'link' => 'cards.php', 'active' => basename($_SERVER['PHP_SELF']) === 'cards.php'],
    ['icon' => 'fas fa-star', 'label' => 'Favorites', 'link' => 'vault.php?filter=favorites', 'active' => strpos($_SERVER['QUERY_STRING'] ?? '', 'favorites') !== false],
    ['icon' => 'fa-brands fa-google', 'label' => 'Google Sign-In', 'link' => 'google-signin.php', 'active' => basename($_SERVER['PHP_SELF']) === 'google-signin.php'],
    ['icon' => 'fas fa-trash',         'label' => 'Trash',            'link' => 'trash.php',                             'active' => basename($_SERVER['PHP_SELF']) === 'trash.php'],
    ['icon' => 'fas fa-history',       'label' => 'Activity Log',     'link' => 'dashboard.php?view=activity',           'active' => strpos($_SERVER['QUERY_STRING'] ?? '', 'activity') !== false],
    ['icon' => 'fas fa-cog', 'label' => 'Settings', 'link' => 'settings.php', 'active' => basename($_SERVER['PHP_SELF']) === 'settings.php'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <div class="brand-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <span>RS PAASWORD MANAGER</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($sidebarEntries as $entry): ?>
                <?php if (isset($entry['type']) && $entry['type'] === 'separator'): ?>
                    <div class="sidebar-section-label"><?php echo $entry['label']; ?></div>
                <?php else: ?>
                    <a href="<?php echo $entry['link']; ?>"
                       class="sidebar-link <?php echo $entry['active'] ? 'active' : ''; ?>">
                         <i class="<?php echo $entry['icon']; ?>"></i>
                        <span><?php echo $entry['label']; ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar-small">
                    <?php $user = getCurrentUser(); ?>
                    <span><?php echo $user ? getInitials($user['display_name'] ?: $user['username']) : 'U'; ?></span>
                </div>
                <div class="sidebar-user-info">
                    <span class="sidebar-user-name"><?php echo $user ? sanitizeOutput($user['display_name'] ?: $user['username']) : 'User'; ?></span>
                    <span class="sidebar-user-email"><?php echo $user ? sanitizeOutput($user['email']) : ''; ?></span>
                </div>
            </div>
            <a href="logout.php" class="sidebar-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
