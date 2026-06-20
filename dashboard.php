<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();

$totalWebsites = count(getWebsites($userId));
$totalCredentials = getTotalCredentialCount($userId);
$totalCategories = getCategoryCount($userId);
$websites = getWebsites($userId);
$recentActivity = getRecentActivity($userId, 8);
$categories = getUserCategories($userId);

$pageTitle = 'Dashboard';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo sanitizeOutput($user['display_name'] ?: $user['username']); ?></p>
                </div>
                <div class="page-actions">
                    <a href="api/entries.php?action=export_csv" class="btn btn-ghost btn-sm" title="Export CSV"><i class="fas fa-file-csv"></i></a>
                    <a href="api/entries.php?action=export_html" class="btn btn-ghost btn-sm" title="Export HTML"><i class="fas fa-file-code"></i></a>
                    <a href="api/entries.php?action=export_pdf" class="btn btn-ghost btn-sm" title="Export PDF"><i class="fas fa-file-pdf"></i></a>
                    <a href="vault.php" class="btn btn-secondary"><i class="fas fa-globe"></i> My Vault</a>
                    <button class="btn btn-primary" onclick="window.location.href='vault.php'"><i class="fas fa-key"></i> Add Password</button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-globe"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalWebsites; ?></h3>
                        <p>Websites</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-user-lock"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalCredentials; ?></h3>
                        <p>Credentials</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-tags"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalCategories; ?></h3>
                        <p>Categories</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <h3><?php echo array_sum(array_column($websites, 'fav_count')); ?></h3>
                        <p>Favorites</p>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:16px">Your Websites</h3>
                    <?php if (empty($websites)): ?>
                        <div class="empty-state" style="padding:30px 10px">
                            <p class="text-muted">No websites yet. <a href="vault.php">Add your first password</a></p>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <?php foreach (array_slice($websites, 0, 6) as $w): ?>
                                <a href="vault.php" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:var(--radius-md);transition:var(--transition);text-decoration:none;color:var(--text-main)">
                                    <?php $favicon = getFaviconUrl($w['website_url']); if ($favicon): ?><img src="<?php echo $favicon; ?>" alt="" style="width:20px;height:20px;border-radius:4px;flex-shrink:0" onerror="this.outerHTML='<i class=\'fas fa-globe\' style=\'color:var(--primary);font-size:1rem;width:20px;text-align:center\'></i>'"><?php else: ?><i class="fas fa-globe" style="color:var(--primary);font-size:1rem;width:20px;text-align:center"></i><?php endif; ?>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-weight:500;font-size:0.875rem"><?php echo sanitizeOutput($w['website_name']); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted)"><?php echo $w['cred_count']; ?> credential<?php echo $w['cred_count'] !== 1 ? 's' : ''; ?></div>
                                    </div>
                                    <?php if ($w['fav_count'] > 0): ?>
                                        <i class="fas fa-star" style="color:rgb(251,191,36);font-size:0.8rem"></i>
                                    <?php endif; ?>
                                    <i class="fas fa-chevron-right" style="color:var(--text-muted);font-size:0.75rem"></i>
                                </a>
                            <?php endforeach; ?>
                            <?php if (count($websites) > 6): ?>
                                <a href="vault.php" class="btn btn-ghost btn-sm" style="margin-top:4px">View all (<?php echo count($websites); ?>)</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:16px">Categories</h3>
                    <?php if (empty($categories)): ?>
                        <div class="empty-state" style="padding:30px 10px"><p class="text-muted">No categories</p></div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:4px">
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-item">
                                    <div class="category-left">
                                        <div class="category-icon"><i class="fas fa-<?php echo sanitizeOutput($cat['icon']); ?>"></i></div>
                                        <span class="category-name"><?php echo sanitizeOutput($cat['name']); ?></span>
                                    </div>
                                    <a href="vault.php?category=<?php echo $cat['id']; ?>" class="category-count" style="text-decoration:none">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-2" style="margin-top:20px">
                <div class="card">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:16px">Recent Activity</h3>
                    <?php if (empty($recentActivity)): ?>
                        <div class="empty-state" style="padding:20px 10px"><p class="text-muted">No recent activity</p></div>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($recentActivity as $log): ?>
                                <li class="activity-item">
                                    <?php
                                    $dotClass = 'login';
                                    if (strpos($log['action'], 'created') !== false || strpos($log['action'], 'added') !== false) $dotClass = 'created';
                                    elseif (strpos($log['action'], 'updated') !== false || strpos($log['action'], 'changed') !== false) $dotClass = 'updated';
                                    elseif (strpos($log['action'], 'deleted') !== false) $dotClass = 'deleted';
                                    ?>
                                    <div class="activity-dot <?php echo $dotClass; ?>"></div>
                                    <div class="activity-content">
                                        <div class="activity-action"><?php echo sanitizeOutput($log['action']); ?></div>
                                        <div class="activity-details"><?php echo sanitizeOutput($log['details'] ?? ''); ?></div>
                                    </div>
                                    <span class="activity-time"><?php echo timeAgo($log['created_at']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>



<?php include 'includes/footer.php'; ?>
