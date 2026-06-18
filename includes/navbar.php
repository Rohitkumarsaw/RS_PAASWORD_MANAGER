<?php
$currentUser = getCurrentUser();
?>
<nav class="navbar">
    <div class="navbar-inner">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a href="dashboard.php" class="navbar-brand">
            <div class="brand-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <span class="brand-text">RS PAASWORD MANAGER</span>
        </a>
        <form class="navbar-search" autocomplete="off" role="search" onsubmit="return false;">
            <i class="fas fa-search search-icon"></i>
            <input type="search" id="globalSearch" name="search_query" class="search-input" placeholder="Search passwords..." autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off">
            <kbd class="search-shortcut">Ctrl+K</kbd>
        </form>
        <div class="navbar-actions">
            <button class="nav-icon-btn" id="themeToggle" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>
            <div class="user-dropdown">
                <button class="user-dropdown-trigger" id="userDropdownBtn">
                    <div class="user-avatar-small">
                        <?php if ($currentUser && $currentUser['avatar_url']): ?>
                            <img src="<?php echo sanitizeOutput($currentUser['avatar_url']); ?>" alt="Avatar">
                        <?php else: ?>
                            <span><?php echo $currentUser ? getInitials($currentUser['display_name'] ?: $currentUser['username']) : 'U'; ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="user-name-nav"><?php echo $currentUser ? sanitizeOutput($currentUser['display_name'] ?: $currentUser['username']) : 'User'; ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu" id="userDropdownMenu">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <hr class="dropdown-divider">
                    <a href="logout.php" class="dropdown-item dropdown-item-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
