<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();

$trashedWebsites = getTrashedWebsites($userId);
$trashedCredentials = getTrashedCredentials($userId);

$pageTitle = 'Trash';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-trash" style="color:var(--danger)"></i> Trash</h1>
                    <p><?php echo count($trashedWebsites); ?> website(s) &middot; <?php echo count($trashedCredentials); ?> credential(s) in trash</p>
                </div>
                <div class="page-actions" style="gap:8px">
                    <?php if (!empty($trashedWebsites) || !empty($trashedCredentials)): ?>
                        <button class="btn btn-sm btn-ghost" onclick="emptyTrash()"><i class="fas fa-trash-alt"></i> Empty Trash</button>
                    <?php endif; ?>
                    <a href="vault.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Vault</a>
                </div>
            </div>

            <?php if (empty($trashedWebsites) && empty($trashedCredentials)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-trash" style="color:var(--text-muted)"></i></div>
                        <h3>Trash is empty</h3>
                        <p>Deleted items will appear here. You can restore them or permanently delete them.</p>
                        <a href="vault.php" class="btn btn-primary mt-4"><i class="fas fa-arrow-left"></i> Go to Vault</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($trashedWebsites)): ?>
                <div class="section-divider"><span>Deleted Websites</span></div>
                <div class="website-grid">
                    <?php foreach ($trashedWebsites as $w): ?>
                        <div class="website-group-card trashed-card">
                            <div class="website-group-header">
                                <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
                                    <div class="website-icon" style="background:rgba(239,68,68,0.15)"><i class="fas fa-globe" style="color:var(--danger)"></i></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="website-name"><?php echo sanitizeOutput($w['website_name']); ?></div>
                                        <div class="website-meta">
                                            Deleted <?php echo timeAgo($w['deleted_at']); ?>
                                            &middot; <?php echo (int)$w['cred_count']; ?> credential(s) in trash
                                            <?php if ((int)$w['active_cred_count'] > 0): ?>
                                                &middot; <span style="color:var(--success)"><?php echo (int)$w['active_cred_count']; ?> active</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <button class="btn btn-sm btn-success" onclick="restoreWebsite(<?php echo $w['id']; ?>)"><i class="fas fa-undo"></i> Restore</button>
                                    <button class="btn btn-sm btn-danger" onclick="permanentDeleteWebsite(<?php echo $w['id']; ?>)"><i class="fas fa-trash-alt"></i> Delete Forever</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($trashedCredentials)): ?>
                <div class="section-divider" style="margin-top:24px"><span>Deleted Credentials</span></div>
                <div class="card" style="margin-top:16px">
                    <div class="creds-list">
                        <?php foreach ($trashedCredentials as $c): ?>
                            <div class="cred-row">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
                                    <div style="width:3px;height:28px;border-radius:2px;background:var(--danger);flex-shrink:0"></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="cred-title"><?php echo sanitizeOutput($c['title']); ?></div>
                                        <div class="cred-username">
                                            <?php echo sanitizeOutput($c['username']); ?>
                                            &nbsp;&middot;&nbsp; <?php echo sanitizeOutput($c['website_name']); ?>
                                            &nbsp;&middot;&nbsp; Deleted <?php echo timeAgo($c['deleted_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="cred-row-actions">
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="restoreCredential(<?php echo $c['id']; ?>)" title="Restore">
                                        <i class="fas fa-undo" style="color:var(--success)"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="permanentDeleteCredential(<?php echo $c['id']; ?>)" title="Delete Forever">
                                        <i class="fas fa-trash-alt" style="color:var(--danger)"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function restoreWebsite(id) {
    fetch('api/credentials.php?action=restore_website', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Website restored', 'success'); setTimeout(function() { location.reload(); }, 500); }
        else { showToast('Failed', 'error'); }
    });
}

function restoreCredential(id) {
    fetch('api/credentials.php?action=restore_credential', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Credential restored', 'success'); setTimeout(function() { location.reload(); }, 500); }
        else { showToast('Failed', 'error'); }
    });
}

function permanentDeleteWebsite(id) {
    showConfirmDialog('Permanently delete this website and all its credentials? This cannot be undone.', 'Permanently Delete', 'Delete Forever', 'Cancel', function() {
        fetch('api/credentials.php?action=permanent_delete_website', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast('Permanently deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

function permanentDeleteCredential(id) {
    showConfirmDialog('Permanently delete this credential? This cannot be undone.', 'Permanently Delete', 'Delete Forever', 'Cancel', function() {
        fetch('api/credentials.php?action=permanent_delete_credential', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast('Permanently deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

function emptyTrash() {
    showConfirmDialog('Permanently delete ALL items in trash? This cannot be undone.', 'Empty Trash', 'Delete All', 'Cancel', function() {
        // Get all trashed credential IDs and delete them
        var promises = [];
        <?php foreach ($trashedCredentials as $c): ?>
        promises.push(
            fetch('api/credentials.php?action=permanent_delete_credential', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?php echo $c['id']; ?>, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            }).then(function(r) { return r.json(); })
        );
        <?php endforeach; ?>
        <?php foreach ($trashedWebsites as $w): ?>
        promises.push(
            fetch('api/credentials.php?action=permanent_delete_website', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?php echo $w['id']; ?>, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            }).then(function(r) { return r.json(); })
        );
        <?php endforeach; ?>
        Promise.all(promises).then(function() {
            showToast('Trash emptied', 'success');
            setTimeout(function() { location.reload(); }, 500);
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
