<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();
$accounts = getOauthAccounts($userId);

$pageTitle = 'Personal';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1><i class="fa-brands fa-google" style="color:#ea4335"></i> Google Sign-In</h1>
                    <p><?php echo count($accounts); ?> Google sign-in account<?php echo count($accounts) !== 1 ? 's' : ''; ?></p>
                </div>
                <div class="page-actions">
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-sm btn-ghost" onclick="exportPersonal('csv')"><i class="fas fa-file-csv"></i> CSV</button>
                        <button class="btn btn-sm btn-ghost" onclick="exportPersonal('html')"><i class="fas fa-file-code"></i> HTML</button>
                        <button class="btn btn-sm btn-ghost" onclick="exportPersonal('pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                    </div>
                    <button class="btn btn-primary" onclick="openAddOauthModal()"><i class="fas fa-plus"></i> Add Google Sign-In</button>
                </div>
            </div>

            <?php if (empty($accounts)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fa-brands fa-google" style="color:#ea4335"></i></div>
                        <h3>No Google sign-in accounts yet</h3>
                        <p>Add accounts where you sign in with Google instead of a password.</p>
                        <button class="btn btn-primary mt-4" onclick="openAddOauthModal()"><i class="fas fa-plus"></i> Add Account</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="oauth-grid">
                    <?php foreach ($accounts as $oa): ?>
                        <div class="oauth-card">
                            <div class="oauth-card-body">
                                <div class="oauth-icon"><i class="fa-brands fa-google" style="color:#ea4335"></i></div>
                                <div class="oauth-info">
                                    <div class="oauth-name"><?php echo sanitizeOutput($oa['website_name']); ?></div>
                                    <div class="oauth-email"><?php echo sanitizeOutput($oa['email']); ?></div>
                                    <?php if ($oa['website_url']): ?>
                                        <div class="oauth-url"><?php echo sanitizeOutput($oa['website_url']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($oa['notes']): ?>
                                        <div class="oauth-notes"><?php echo sanitizeOutput($oa['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="oauth-actions">
                                <button class="btn btn-ghost btn-icon btn-sm" onclick="openEditOauthModal(<?php echo $oa['id']; ?>)" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-ghost btn-icon btn-sm" onclick="deleteOauth(<?php echo $oa['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash" style="color:var(--danger)"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add OAuth Account Modal -->
<div class="modal-overlay" id="addOauthModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h2><i class="fa-brands fa-google" style="color:#ea4335"></i> Add Google Sign-In</h2>
            <button class="modal-close" onclick="closeModal('addOauthModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="addOauthForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="modal-body">
                <div id="addOauthAlerts"></div>
                <div class="form-group">
                    <label class="form-label">Website Name <span class="text-danger">*</span></label>
                    <input type="text" id="addOauthName" class="form-input" placeholder="e.g. YouTube, Drive" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Website URL</label>
                    <input type="url" id="addOauthUrl" class="form-input" placeholder="https://example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" id="addOauthEmail" class="form-input" placeholder="your.email@gmail.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <input type="text" id="addOauthNotes" class="form-input" placeholder="Optional notes">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addOauthModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="addOauthBtn" onclick="saveAddOauth()"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit OAuth Account Modal -->
<div class="modal-overlay" id="editOauthModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h2><i class="fa-brands fa-google" style="color:#ea4335"></i> Edit Google Sign-In</h2>
            <button class="modal-close" onclick="closeModal('editOauthModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="editOauthForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" id="editOauthId" value="0">
            <div class="modal-body">
                <div id="editOauthAlerts"></div>
                <div class="form-group">
                    <label class="form-label">Website Name <span class="text-danger">*</span></label>
                    <input type="text" id="editOauthName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Website URL</label>
                    <input type="url" id="editOauthUrl" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" id="editOauthEmail" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <input type="text" id="editOauthNotes" class="form-input" placeholder="Optional notes">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editOauthModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="editOauthBtn" onclick="saveEditOauth()"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
// ====== OAUTH (Google Sign-In) CRUD ======
function openAddOauthModal() {
    document.getElementById('addOauthForm').reset();
    document.getElementById('addOauthAlerts').innerHTML = '';
    openModal('addOauthModal');
}

function saveAddOauth() {
    var alerts = document.getElementById('addOauthAlerts');
    var btn = document.getElementById('addOauthBtn');
    alerts.innerHTML = '';
    var name = document.getElementById('addOauthName').value.trim();
    var url = document.getElementById('addOauthUrl').value.trim();
    var email = document.getElementById('addOauthEmail').value.trim();
    var notes = document.getElementById('addOauthNotes').value.trim();
    if (!name || !email) {
        alerts.innerHTML = '<div class="alert alert-danger">Website name and email are required.</div>';
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    fetch('api/oauth.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: document.querySelector('#addOauthForm [name="csrf_token"]').value,
            website_name: name, website_url: url, email: email, notes: notes
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save';
        if (result.success) {
            closeModal('addOauthModal');
            showToast(result.message, 'success');
            setTimeout(function() { location.reload(); }, 500);
        } else {
            alerts.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save';
        alerts.innerHTML = '<div class="alert alert-danger">Network error. Try again.</div>';
    });
}

function openEditOauthModal(id) {
    document.getElementById('editOauthAlerts').innerHTML = '';
    var btn = document.getElementById('editOauthBtn');
    btn.disabled = true;
    btn.textContent = 'Loading...';
    fetch('api/oauth.php?action=list&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.textContent = 'Save';
        if (!data.success) { showToast('Failed to load', 'error'); return; }
        var acct = null;
        for (var i = 0; i < data.accounts.length; i++) {
            if (data.accounts[i].id == id) { acct = data.accounts[i]; break; }
        }
        if (!acct) { showToast('Account not found', 'error'); return; }
        document.getElementById('editOauthId').value = acct.id;
        document.getElementById('editOauthName').value = acct.website_name;
        document.getElementById('editOauthUrl').value = acct.website_url || '';
        document.getElementById('editOauthEmail').value = acct.email;
        document.getElementById('editOauthNotes').value = acct.notes || '';
        openModal('editOauthModal');
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Save';
        showToast('Network error', 'error');
    });
}

function saveEditOauth() {
    var id = document.getElementById('editOauthId').value;
    var name = document.getElementById('editOauthName').value.trim();
    var url = document.getElementById('editOauthUrl').value.trim();
    var email = document.getElementById('editOauthEmail').value.trim();
    var notes = document.getElementById('editOauthNotes').value.trim();
    var alerts = document.getElementById('editOauthAlerts');
    var btn = document.getElementById('editOauthBtn');
    alerts.innerHTML = '';
    if (!name || !email) {
        alerts.innerHTML = '<div class="alert alert-danger">Website name and email are required.</div>';
        return;
    }
    btn.disabled = true;
    btn.textContent = 'Saving...';
    fetch('api/oauth.php?action=edit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: document.querySelector('#editOauthForm [name="csrf_token"]').value,
            id: id, website_name: name, website_url: url, email: email, notes: notes
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.textContent = 'Save';
        if (result.success) {
            closeModal('editOauthModal');
            showToast(result.message, 'success');
            setTimeout(function() { location.reload(); }, 500);
        } else {
            alerts.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Save';
        alerts.innerHTML = '<div class="alert alert-danger">Network error. Try again.</div>';
    });
}

function deleteOauth(id) {
    showConfirmDialog('Delete this Google sign-in account?', 'Delete Account', 'Delete', 'Cancel', function() {
        fetch('api/oauth.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast('Deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

function exportPersonal(format) {
    showToast('Downloading...', 'info');
    var action = 'export_' + format;
    window.location.href = 'api/oauth.php?action=' + action + '&_=' + Date.now();
}
</script>

<?php include 'includes/footer.php'; ?>
