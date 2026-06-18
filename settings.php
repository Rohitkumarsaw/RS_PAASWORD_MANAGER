<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();
$categories = getUserCategories($userId);

$pageTitle = 'Settings';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1>Settings</h1>
                    <p>Manage your account and preferences</p>
                </div>
            </div>

            <div class="tabs" data-tabs="settings">
                <button class="tab active" data-tab="tabGeneral">General</button>
                <button class="tab" data-tab="tabSecurity">Security</button>
                <button class="tab" data-tab="tabCategories">Categories</button>
                <button class="tab" data-tab="tabImportExport">Import / Export</button>
            </div>

            <div id="tabGeneral" class="tab-content">
                <div class="card">
                    <div class="settings-section">
                        <h3 class="settings-section-title">Display Preferences</h3>
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h4>Dark Mode</h4>
                                <p>Toggle between dark and light theme</p>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" id="themeToggleSetting" <?php echo ($user['theme_preference'] ?? 'dark') === 'dark' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h4>Items Per Page</h4>
                                <p>Number of entries shown in the vault</p>
                            </div>
                            <select id="itemsPerPage" class="form-select" style="width:auto;min-width:80px">
                                <option value="10" <?php echo ($user['items_per_page'] ?? 20) == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo ($user['items_per_page'] ?? 20) == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($user['items_per_page'] ?? 20) == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($user['items_per_page'] ?? 20) == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </div>

            <div id="tabSecurity" class="tab-content" style="display:none">
                <div class="security-grid">
                <div class="card">
                    <div class="settings-section">
                        <h3 class="settings-section-title">Change Password</h3>
                        <div id="securityAlerts"></div>
                        <form id="changePasswordForm">
                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-input" required autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password</label>
                                <div class="input-group">
                                    <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8" data-strength="true" autocomplete="new-password">
                                    <div class="input-group-append">
                                        <button type="button" class="input-group-btn pw-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="password-strength">
                                    <div class="strength-bar"><div class="strength-bar-fill" id="settingsStrengthBar"></div></div>
                                    <span class="strength-label" id="settingsStrengthLabel"></span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:20px">
                        <i class="fas fa-lock"></i> Change Master Password
                    </h3>
                    <div id="mpAlerts"></div>
                    <form id="masterPwForm">
                        <div class="form-group">
                            <label class="form-label" for="current_mp">Current Master Password</label>
                            <div class="input-group">
                                <input type="password" id="current_mp" class="form-input" required autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('current_mp', this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new_mp">New Master Password</label>
                            <div class="input-group">
                                <input type="password" id="new_mp" class="form-input" required minlength="8" autocomplete="new-password">
                                <div class="input-group-append">
                                    <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('new_mp', this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm_mp">Confirm New Master Password</label>
                            <div class="input-group">
                                <input type="password" id="confirm_mp" class="form-input" required minlength="8" autocomplete="new-password">
                                <div class="input-group-append">
                                    <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('confirm_mp', this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Master Password
                        </button>
                    </form>
                </div>

                <!-- Quick Unlock PIN Card -->
                <div class="card" id="pinCard">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:20px">
                        <i class="fas fa-mobile-screen-button" style="color:var(--info)"></i> Quick Unlock PIN
                    </h3>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px">
                        Set a numeric PIN for faster vault access. Instead of entering your master password each time, enter your PIN.
                    </p>
                    <div id="pinAlerts"></div>
                    <div id="pinSetupForm">
                        <div class="form-group">
                            <label class="form-label" for="pinInput">New PIN (4-10 digits)</label>
                            <div class="input-group">
                                <input type="password" id="pinInput" class="form-input" maxlength="10" pattern="[0-9]*" inputmode="numeric" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('pinInput', this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pinConfirm">Confirm PIN</label>
                            <div class="input-group">
                                <input type="password" id="pinConfirm" class="form-input" maxlength="10" pattern="[0-9]*" inputmode="numeric" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('pinConfirm', this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pinMp">Confirm with Master Password</label>
                            <input type="password" id="pinMp" class="form-input" autocomplete="off">
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button class="btn btn-info" id="setPinBtn" onclick="setPin()"><i class="fas fa-check"></i> Set PIN</button>
                            <button class="btn btn-ghost" id="removePinBtn" onclick="removePin()" style="display:none"><i class="fas fa-trash"></i> Remove PIN</button>
                        </div>
                    </div>
                    <div id="pinStatus" style="display:none;margin-top:12px">
                        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);border-radius:10px">
                            <i class="fas fa-check-circle" style="color:var(--success)"></i>
                            <span style="font-size:0.9rem">PIN is set</span>
                            <button class="btn btn-sm btn-ghost" onclick="showPinSetup()" style="margin-left:auto"><i class="fas fa-pen"></i> Change</button>
                        </div>
                    </div>
                </div>

                <!-- 2FA Card -->
                <div class="card" id="twofaCard">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:20px">
                        <i class="fas fa-shield-halved" style="color:var(--primary)"></i> Two-Factor Authentication (2FA)
                    </h3>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px">
                        Add an extra layer of security by requiring a 6-digit code from Google Authenticator or any TOTP app when signing in.
                    </p>
                    <div id="twofaAlerts"></div>

                    <!-- 2FA Status (when disabled) -->
                    <div id="twofaDisabled">
                        <button class="btn btn-primary" onclick="openTwoFAModal()"><i class="fas fa-qrcode"></i> Set Up 2FA</button>
                    </div>

                    <!-- 2FA Status (when enabled) -->
                    <div id="twofaEnabled" style="display:none">
                        <div style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);border-radius:10px;margin-bottom:12px">
                            <i class="fas fa-check-circle" style="color:var(--success);font-size:1.2rem"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem">2FA is enabled</div>
                                <div style="font-size:0.8rem;color:var(--text-muted)">Your account is protected with two-factor authentication</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button class="btn btn-ghost" onclick="openTwoFAModal()"><i class="fas fa-sync"></i> Reconfigure</button>
                            <button class="btn btn-danger" onclick="showDisableTwoFA()"><i class="fas fa-shield-slash"></i> Disable 2FA</button>
                        </div>
                        <div id="twofaDisableForm" style="display:none;margin-top:12px">
                            <div class="form-group">
                                <label class="form-label" for="twofaDisableMp">Enter Master Password to disable 2FA</label>
                                <div class="input-group">
                                    <input type="password" id="twofaDisableMp" class="form-input" autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('twofaDisableMp', this)"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px">
                                <button class="btn btn-danger" onclick="disableTwoFA()"><i class="fas fa-shield-slash"></i> Confirm Disable</button>
                                <button class="btn btn-ghost" onclick="cancelDisableTwoFA()">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-soft)">
                        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px">Need to turn off 2FA?</p>
                        <button class="btn btn-outline btn-sm btn-danger" onclick="openDisableTwoFAModal()"><i class="fas fa-shield-slash"></i> Disable 2FA</button>
                    </div>
                </div>

                <div class="card card-danger">
                    <h3 style="font-size:1rem;font-weight:600;margin-bottom:8px;color:var(--danger-color, #dc3545)">
                        <i class="fas fa-exclamation-triangle"></i> Danger Zone
                    </h3>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px">
                        Once you delete your account, there is no going back. All your passwords, websites, and data will be permanently removed.
                    </p>
                    <button class="btn btn-danger" onclick="showDeleteConfirm()">
                        <i class="fas fa-trash"></i> Delete My Account
                    </button>
                </div>
                </div>
            </div>

            <div id="tabCategories" class="tab-content" style="display:none">
                <div class="card">
                    <div class="settings-section">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                            <h3 class="settings-section-title" style="border:none;padding:0;margin:0">Manage Categories</h3>
                            <button class="btn btn-primary btn-sm" onclick="showAddCategory()"><i class="fas fa-plus"></i> Add</button>
                        </div>
                        <div id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <div class="category-item" data-id="<?php echo $cat['id']; ?>">
                                    <div class="category-left">
                                        <div class="category-icon"><i class="fas fa-<?php echo sanitizeOutput($cat['icon']); ?>"></i></div>
                                        <span class="category-name"><?php echo sanitizeOutput($cat['name']); ?></span>
                                    </div>
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="deleteCategory(<?php echo $cat['id']; ?>)" title="Delete category">
                                        <i class="fas fa-times" style="color:var(--danger)"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tabImportExport" class="tab-content" style="display:none">
                <div class="card">
                    <div class="settings-section">
                        <h3 class="settings-section-title">Import / Export</h3>
                        <div class="import-export-grid">
                            <div class="ie-card" onclick="exportCsv()">
                                <i class="fas fa-file-csv"></i>
                                <h4>Export CSV</h4>
                                <p>Download all passwords as a CSV file</p>
                            </div>
                            <div class="ie-card" onclick="exportHtml()">
                                <i class="fas fa-file-code"></i>
                                <h4>Export HTML</h4>
                                <p>Download all passwords as an HTML table</p>
                            </div>
                            <div class="ie-card" onclick="exportPdf()">
                                <i class="fas fa-file-pdf"></i>
                                <h4>Export PDF</h4>
                                <p>Download all passwords as a PDF document</p>
                            </div>
                            <div class="ie-card" onclick="document.getElementById('csvImport').click()">
                                <i class="fas fa-file-import"></i>
                                <h4>Import CSV</h4>
                                <p>Import passwords from a CSV file</p>
                                <form id="importForm" action="api/entries.php?action=import_csv" method="POST" enctype="multipart/form-data" style="display:none">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="file" id="csvImport" name="csv_file" accept=".csv" onchange="importCsv(this)">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tabActivity" class="tab-content" style="display:none">
            </div>
        </div>
    </main>
</div>

<!-- Delete Account Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3>Delete Account</h3>
            <button class="modal-close" onclick="closeDeleteModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="deleteAlerts"></div>
            <p style="margin-bottom:16px;font-size:0.9rem;color:var(--text-muted)">
                Enter your master password to confirm account deletion. This action cannot be undone.
            </p>
            <div class="form-group">
                <label class="form-label" for="delete_mp">Master Password</label>
                <div class="input-group">
                    <input type="password" id="delete_mp" class="form-input" required autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('delete_mp', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Add Category</h2>
            <button class="modal-close" onclick="closeModal('addCategoryModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label" for="catName">Category Name</label>
                <input type="text" id="catName" class="form-input" placeholder="e.g. Banking" maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label" for="catIcon">Icon</label>
                <select id="catIcon" class="form-select">
                    <option value="folder">Folder</option>
                    <option value="globe">Globe</option>
                    <option value="credit-card">Credit Card</option>
                    <option value="briefcase">Briefcase</option>
                    <option value="user">User</option>
                    <option value="mail">Mail</option>
                    <option value="film">Film</option>
                    <option value="shopping-cart">Shopping Cart</option>
                    <option value="lock">Lock</option>
                    <option value="music">Music</option>
                    <option value="book">Book</option>
                    <option value="heart">Heart</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveCategory()">Save</button>
        </div>
    </div>
</div>

<!-- 2FA Setup Modal -->
<div class="modal-overlay" id="twofaModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h2 id="twofaModalTitle"><i class="fas fa-shield-halved" style="color:var(--primary)"></i> Set Up Two-Factor Authentication</h2>
            <button class="modal-close" onclick="closeTwoFAModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="twofaModalAlerts"></div>

            <!-- Step 1: Master Password -->
            <div id="twofaStepMp">
                <p style="margin-bottom:16px;font-size:0.9rem;color:var(--text-muted)">
                    Enter your master password to begin 2FA setup.
                </p>
                <div class="form-group">
                    <label class="form-label" for="twofaModalMp">Master Password</label>
                    <div class="input-group">
                        <input type="password" id="twofaModalMp" class="form-input" required autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('twofaModalMp', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: QR Code + Verify -->
            <div id="twofaStepQr" style="display:none">
                <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px;text-align:center">
                    Scan this QR code with your authenticator app, then enter the 6-digit code below.
                </p>
                <div style="text-align:center;margin-bottom:12px">
                    <div style="display:inline-block;padding:12px;background:#fff;border-radius:12px">
                        <img id="twofaModalQR" src="" alt="QR Code" style="width:200px;height:200px;image-rendering:pixelated">
                    </div>
                </div>
                <p style="text-align:center;font-size:0.8rem;color:var(--text-muted)">
                    Can't scan? <a href="#" onclick="event.preventDefault();toggleModalSecret()">Show secret key</a>
                </p>
                <div id="twofaModalSecretDisplay" style="display:none;text-align:center;margin-bottom:12px">
                    <code id="twofaModalSecretCode" style="font-size:0.8rem;word-break:break-all;padding:6px 10px;background:var(--bg-card);border-radius:6px;display:inline-block"></code>
                    <button class="btn btn-sm btn-ghost" onclick="copyModalSecret()" style="margin-left:4px" title="Copy secret"><i class="fas fa-copy"></i></button>
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label class="form-label" for="twofaModalCode">Enter 6-digit code</label>
                    <input type="text" id="twofaModalCode" class="form-input" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="off" style="text-align:center;font-size:1.2rem;letter-spacing:6px;max-width:180px;margin:0 auto">
                </div>
            </div>
        </div>
        <div class="modal-footer" id="twofaModalFooter">
            <!-- Step 1 footer -->
            <div id="twofaFooterMp">
                <button class="btn btn-secondary" onclick="closeTwoFAModal()">Cancel</button>
                <button class="btn btn-primary" id="twofaModalStartBtn" onclick="startTwoFASetupModal()">
                    <span class="btn-text"><i class="fas fa-qrcode"></i> Generate QR</span>
                    <span class="spinner" style="display:none;width:18px;height:18px;border-width:2px"></span>
                </button>
            </div>
            <!-- Step 2 footer -->
            <div id="twofaFooterQr" style="display:none">
                <button class="btn btn-secondary" onclick="closeTwoFAModal()">Cancel</button>
                <button class="btn btn-primary" id="twofaModalVerifyBtn" onclick="verifyTwoFASetupModal()">
                    <span class="btn-text"><i class="fas fa-check"></i> Verify & Enable</span>
                    <span class="spinner" style="display:none;width:18px;height:18px;border-width:2px"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var csrfToken = '<?php echo generateCsrfToken(); ?>';

document.getElementById('new_password') && document.getElementById('new_password').addEventListener('input', function() {
    if (typeof updateStrengthDisplay === 'function') {
        updateStrengthDisplay(this.value, this);
    }
});

var currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
document.getElementById('themeToggleSetting').checked = currentTheme === 'dark';

document.getElementById('themeToggleSetting').addEventListener('change', function() {
    var theme = this.checked ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('pm_theme', theme);
});

function saveSettings() {
    var theme = document.getElementById('themeToggleSetting').checked ? 'dark' : 'light';
    var ipp = document.getElementById('itemsPerPage').value;

    fetch('api/settings.php?action=update_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items_per_page: ipp })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            fetch('api/settings.php?action=update_theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: theme })
            })
            .then(function(r2) { return r2.json(); })
            .then(function(d2) {
                if (typeof showToast === 'function') showToast('Settings saved', 'success');
            });
        }
    });
}

function togglePwField(id, btn) {
    var input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        btn.className = 'input-group-btn pw-toggle';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.className = 'input-group-btn pw-toggle';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var data = {
        current_password: document.getElementById('current_password').value,
        new_password: document.getElementById('new_password').value
    };
    fetch('api/settings.php?action=update_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var alerts = document.getElementById('securityAlerts');
        if (result.success) {
            alerts.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
        } else {
            alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + result.message + '</div>';
        }
    });
});

function showAddCategory() {
    document.getElementById('catName').value = '';
    openModal('addCategoryModal');
}

function saveCategory() {
    var name = document.getElementById('catName').value.trim();
    var icon = document.getElementById('catIcon').value;
    if (!name) {
        showToast('Please enter a category name', 'warning');
        return;
    }
    fetch('api/settings.php?action=create_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, icon: icon })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        closeModal('addCategoryModal');
        if (data.success) {
            showToast('Category created', 'success');
            setTimeout(function() { location.reload(); }, 500);
        } else {
            showToast(data.message || 'Failed to create category', 'error');
        }
    });
}

function deleteCategory(id) {
    if (typeof showConfirmDialog === 'function') {
        showConfirmDialog('Delete this category? Entries will become uncategorized.', 'Delete Category', 'Delete', 'Cancel', function() {
            fetch('api/settings.php?action=delete_category', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Category deleted', 'success');
                    setTimeout(function() { location.reload(); }, 500);
                }
            });
        });
    }
}

document.getElementById('masterPwForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
    var alerts = document.getElementById('mpAlerts');
    alerts.innerHTML = '';

    var data = {
        csrf_token: csrfToken,
        current_master_password: document.getElementById('current_mp').value,
        new_master_password: document.getElementById('new_mp').value,
        confirm_master_password: document.getElementById('confirm_mp').value
    };

    fetch('api/settings.php?action=change_master_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            alerts.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            document.getElementById('masterPwForm').reset();
        } else {
            alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
        }
    })
    .catch(function() {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Change Master Password';
    });
});

function showDeleteConfirm() {
    openModal(document.getElementById('deleteModal'));
    document.getElementById('deleteAlerts').innerHTML = '';
    document.getElementById('delete_mp').value = '';
    document.getElementById('delete_mp').focus();
}

function closeDeleteModal() {
    closeModal(document.getElementById('deleteModal'));
}

function confirmDelete() {
    var mp = document.getElementById('delete_mp').value;
    if (!mp) { alert('Enter your master password'); return; }
    var btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    fetch('api/settings.php?action=delete_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, master_password: mp })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            window.location.href = result.redirect || 'login.php';
        } else {
            document.getElementById('deleteAlerts').innerHTML =
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i> Permanently Delete';
        }
    })
    .catch(function() {
        document.getElementById('deleteAlerts').innerHTML =
            '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Permanently Delete';
    });
}

function exportCsv() {
    window.location.href = 'api/entries.php?action=export_csv';
}

function exportHtml() {
    window.location.href = 'api/entries.php?action=export_html';
}

function exportPdf() {
    window.location.href = 'api/entries.php?action=export_pdf';
}

function importCsv(input) {
    if (!input.files || !input.files[0]) return;
    var form = document.getElementById('importForm');
    form.submit();
}

// ====== PIN Quick Unlock ======
function checkPinStatus() {
    fetch('api/pin.php?action=status')
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.has_pin) {
            document.getElementById('pinSetupForm').style.display = 'none';
            document.getElementById('pinStatus').style.display = 'block';
            document.getElementById('setPinBtn').style.display = 'none';
            document.getElementById('removePinBtn').style.display = 'inline-flex';
        } else {
            document.getElementById('pinSetupForm').style.display = 'block';
            document.getElementById('pinStatus').style.display = 'none';
            document.getElementById('setPinBtn').style.display = 'inline-flex';
            document.getElementById('removePinBtn').style.display = 'none';
        }
    });
}

function showPinSetup() {
    document.getElementById('pinSetupForm').style.display = 'block';
    document.getElementById('pinStatus').style.display = 'none';
    document.getElementById('pinAlerts').innerHTML = '';
    document.getElementById('pinInput').value = '';
    document.getElementById('pinConfirm').value = '';
    document.getElementById('pinMp').value = '';
}

function setPin() {
    var pin = document.getElementById('pinInput').value;
    var confirm = document.getElementById('pinConfirm').value;
    var mp = document.getElementById('pinMp').value;
    var alerts = document.getElementById('pinAlerts');
    var btn = document.getElementById('setPinBtn');

    if (!pin || !confirm || !mp) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> All fields required</div>';
        return;
    }
    if (pin !== confirm) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> PINs do not match</div>';
        return;
    }
    if (pin.length < 4 || pin.length > 10) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> PIN must be 4-10 digits</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Setting PIN...';
    alerts.innerHTML = '';

    fetch('api/pin.php?action=set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, pin: pin, confirm_pin: confirm, master_password: mp })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            alerts.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            checkPinStatus();
        } else {
            alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
        }
    })
    .catch(function() {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Set PIN';
    });
}

function removePin() {
    var mp = document.getElementById('pinMp').value;
    if (!mp) {
        document.getElementById('pinAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Enter master password to remove PIN</div>';
        document.getElementById('pinMp').focus();
        return;
    }
    document.getElementById('pinAlerts').innerHTML = '';
    var btn = document.getElementById('removePinBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';

    fetch('api/pin.php?action=remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, master_password: mp })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            document.getElementById('pinAlerts').innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            checkPinStatus();
        } else {
            document.getElementById('pinAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
        }
    })
    .catch(function() {
        document.getElementById('pinAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Remove PIN';
    });
}

checkPinStatus();

// ====== 2FA ======
var twofaSetupSecret = '';

function checkTwoFAStatus() {
    fetch('api/totp.php?action=status')
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.enabled) {
            document.getElementById('twofaDisabled').style.display = 'none';
            document.getElementById('twofaSetup').style.display = 'none';
            document.getElementById('twofaEnabled').style.display = 'block';
        } else {
            document.getElementById('twofaDisabled').style.display = 'block';
            document.getElementById('twofaEnabled').style.display = 'none';
        }
    });
}

function openTwoFAModal() {
    document.getElementById('twofaModalAlerts').innerHTML = '';
    document.getElementById('twofaStepMp').style.display = 'block';
    document.getElementById('twofaStepQr').style.display = 'none';
    document.getElementById('twofaFooterMp').style.display = 'block';
    document.getElementById('twofaFooterQr').style.display = 'none';
    document.getElementById('twofaModalMp').value = '';
    document.getElementById('twofaModalCode').value = '';
    document.getElementById('twofaModalQR').src = '';
    document.getElementById('twofaModalTitle').innerHTML = '<i class="fas fa-shield-halved" style="color:var(--primary)"></i> Set Up Two-Factor Authentication';
    openModal('twofaModal');
    setTimeout(function() { document.getElementById('twofaModalMp').focus(); }, 200);
}

function startTwoFASetupModal() {
    var mp = document.getElementById('twofaModalMp').value;
    if (!mp) {
        document.getElementById('twofaModalAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Enter your master password</div>';
        return;
    }

    var btn = document.getElementById('twofaModalStartBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.querySelector('.spinner').style.display = 'inline-block';
    document.getElementById('twofaModalAlerts').innerHTML = '';

    fetch('api/totp.php?action=setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, master_password: mp })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-qrcode"></i> Generate QR';
        btn.querySelector('.spinner').style.display = 'none';

        if (result.success) {
            twofaSetupSecret = result.secret;
            document.getElementById('twofaModalQR').src = result.qr_url;
            document.getElementById('twofaModalSecretCode').textContent = result.secret;
            document.getElementById('twofaStepMp').style.display = 'none';
            document.getElementById('twofaStepQr').style.display = 'block';
            document.getElementById('twofaFooterMp').style.display = 'none';
            document.getElementById('twofaFooterQr').style.display = 'block';
            document.getElementById('twofaModalTitle').innerHTML = '<i class="fas fa-qrcode" style="color:var(--primary)"></i> Scan QR Code';
            setTimeout(function() { document.getElementById('twofaModalCode').focus(); }, 200);
        } else {
            document.getElementById('twofaModalAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-qrcode"></i> Generate QR';
        btn.querySelector('.spinner').style.display = 'none';
        document.getElementById('twofaModalAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
    });
}

function verifyTwoFASetupModal() {
    var code = document.getElementById('twofaModalCode').value;
    if (!code || code.length !== 6) {
        document.getElementById('twofaModalAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Enter a valid 6-digit code</div>';
        return;
    }
    var btn = document.getElementById('twofaModalVerifyBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    btn.querySelector('.spinner').style.display = 'inline-block';
    document.getElementById('twofaModalAlerts').innerHTML = '';

    fetch('api/totp.php?action=verify_setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, code: code })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            closeTwoFAModal();
            document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            checkTwoFAStatus();
        } else {
            document.getElementById('twofaModalAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
            btn.disabled = false;
            btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-check"></i> Verify & Enable';
            btn.querySelector('.spinner').style.display = 'none';
            document.getElementById('twofaModalCode').value = '';
            document.getElementById('twofaModalCode').focus();
        }
    })
    .catch(function() {
        document.getElementById('twofaModalAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
        btn.disabled = false;
        btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-check"></i> Verify & Enable';
        btn.querySelector('.spinner').style.display = 'none';
    });
}

function closeTwoFAModal() {
    closeModal('twofaModal');
    document.getElementById('twofaModalAlerts').innerHTML = '';
}

function toggleModalSecret() {
    var el = document.getElementById('twofaModalSecretDisplay');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function copyModalSecret() {
    var secret = document.getElementById('twofaModalSecretCode').textContent;
    navigator.clipboard.writeText(secret).then(function() {
        toast('Secret copied to clipboard', 'success');
    }).catch(function() {
        alert('Copy: ' + secret);
    });
}

function showDisableTwoFA() {
    document.getElementById('twofaDisableForm').style.display = 'block';
    document.getElementById('twofaDisableMp').value = '';
    document.getElementById('twofaDisableMp').focus();
}

function openDisableTwoFAModal() {
    // Check if 2FA is enabled first
    fetch('api/totp.php?action=status')
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (!result.enabled) {
            document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> 2FA is not currently enabled. Nothing to disable.</div>';
            return;
        }
        // 2FA is enabled, scroll to the 2FA card and show disable form
        document.getElementById('twofaCard').scrollIntoView({ behavior: 'smooth' });
        // First show the enabled section if hidden
        document.getElementById('twofaDisabled').style.display = 'none';
        document.getElementById('twofaEnabled').style.display = 'block';
        document.getElementById('twofaDisableForm').style.display = 'block';
        document.getElementById('twofaDisableMp').value = '';
        setTimeout(function() { document.getElementById('twofaDisableMp').focus(); }, 500);
    })
    .catch(function() {
        document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to check 2FA status</div>';
    });
}

function cancelDisableTwoFA() {
    document.getElementById('twofaDisableForm').style.display = 'none';
    document.getElementById('twofaAlerts').innerHTML = '';
}

function disableTwoFA() {
    var mp = document.getElementById('twofaDisableMp').value;
    if (!mp) {
        document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Enter your master password</div>';
        return;
    }
    var btns = document.querySelectorAll('#twofaDisableForm .btn-danger');
    var btn = btns[0];
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Disabling...';
    document.getElementById('twofaAlerts').innerHTML = '';

    fetch('api/totp.php?action=disable', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, master_password: mp })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            document.getElementById('twofaDisableForm').style.display = 'none';
            checkTwoFAStatus();
        } else {
            document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shield-slash"></i> Confirm Disable';
        }
    })
    .catch(function() {
        document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-slash"></i> Confirm Disable';
    });
}

checkTwoFAStatus();
</script>

<?php include 'includes/footer.php'; ?>
