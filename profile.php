<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();

$totalPasswords = getPasswordCount($userId);
$totalCategories = getCategoryCount($userId);
$totalActivity = 0;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM activity_logs WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    $totalActivity = (int)$stmt->fetch()['count'];
} catch (PDOException $e) {}

$pageTitle = 'Profile';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1>Profile</h1>
                    <p>Your account information</p>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo getInitials($user['display_name'] ?: $user['username']); ?>
                        </div>
                        <h2><?php echo sanitizeOutput($user['display_name'] ?: $user['username']); ?></h2>
                        <p><?php echo sanitizeOutput($user['email']); ?></p>
                        <span class="badge badge-green" style="margin-top:8px">
                            <i class="fas fa-shield-halved"></i> Active Account
                        </span>
                    </div>

                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo $totalPasswords; ?></div>
                            <div class="profile-stat-label">Passwords</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo $totalCategories; ?></div>
                            <div class="profile-stat-label">Categories</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?php echo $totalActivity; ?></div>
                            <div class="profile-stat-label">Activities</div>
                        </div>
                    </div>

                    <div style="font-size:0.85rem;color:var(--text-muted);text-align:center;padding-top:12px;border-top:1px solid var(--border-soft)">
                        Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>

                <div class="card">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:20px">Edit Profile</h3>
                    <div id="profileAlerts"></div>
                    <form id="profileForm">
                        <div class="form-group">
                            <label class="form-label" for="display_name">Display Name</label>
                            <input type="text" id="display_name" name="display_name" class="form-input" value="<?php echo sanitizeOutput($user['display_name'] ?? ''); ?>" placeholder="Your display name">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number <span class="form-hint" style="display:inline;font-size:0.75rem">(optional)</span></label>
                            <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo sanitizeOutput($user['phone'] ?? ''); ?>" placeholder="+1 (555) 123-4567">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" class="form-input" value="<?php echo sanitizeOutput($user['email']); ?>" disabled>
                            <div class="form-hint">Email cannot be changed</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" class="form-input" value="<?php echo sanitizeOutput($user['username']); ?>" disabled>
                            <div class="form-hint">Username cannot be changed</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var data = {
        display_name: document.getElementById('display_name').value,
        phone: document.getElementById('phone').value
    };
    fetch('api/settings.php?action=update_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var alerts = document.getElementById('profileAlerts');
        if (result.success) {
            alerts.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile updated</div>';
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
