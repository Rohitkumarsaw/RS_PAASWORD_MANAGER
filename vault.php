<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';

$websites = getWebsites($userId, $search);
$categories = getUserCategories($userId);

// Load credentials (no decryption - passwords stay encrypted on page load)
$encKey = getUserEncryptionKey($userId);
foreach ($websites as &$w) {
    $creds = getCredentialsByWebsite($w['id'], $userId);
    foreach ($creds as &$c) {
        unset($c['password_encrypted']);
        // Decrypt phone for display
        $c['phone_decrypted'] = '';
        if ($encKey && !empty($c['phone_encrypted'])) {
            try {
                $c['phone_decrypted'] = decryptPassword($c['phone_encrypted'], $encKey['key'], $encKey['iv']);
            } catch (Exception $e) {}
        }
        unset($c['phone_encrypted']);
    }
    unset($c);

    // Apply favorites filter at credential level
    if ($filter === 'favorites') {
        $creds = array_filter($creds, function($c) { return $c['is_favorite']; });
    }

    $w['_credentials'] = $creds;
    $w['_cred_count'] = count($creds);
}
unset($w);

// If favorites filter, remove websites with no matching credentials
if ($filter === 'favorites') {
    $websites = array_filter($websites, function($w) { return $w['_cred_count'] > 0; });
}

$totalWebsites = count($websites);
$totalCreds = array_sum(array_column($websites, '_cred_count'));

$pageTitle = 'My Vault';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1>My Vault</h1>
                    <p><?php echo $totalWebsites; ?> website<?php echo $totalWebsites !== 1 ? 's' : ''; ?> &middot; <?php echo $totalCreds; ?> credential<?php echo $totalCreds !== 1 ? 's' : ''; ?></p>
                </div>
                                <div class="page-actions">
                    <button class="btn btn-ghost" onclick="openPwGenerator()"><i class="fas fa-dice"></i> Generate</button>
                    <a href="api/entries.php?action=export_csv" class="btn btn-ghost btn-sm" title="Export CSV"><i class="fas fa-file-csv"></i></a>
                    <a href="api/entries.php?action=export_html" class="btn btn-ghost btn-sm" title="Export HTML"><i class="fas fa-file-code"></i></a>
                    <a href="api/entries.php?action=export_pdf" class="btn btn-ghost btn-sm" title="Export PDF"><i class="fas fa-file-pdf"></i></a>
                    <button class="btn btn-primary" onclick="openAddPasswordModal()"><i class="fas fa-key"></i> Add Password</button>
                </div>
            </div>

            <div class="filter-bar">
                <div style="position:relative;flex:1;max-width:320px">
                    <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.85rem"></i>
                    <input type="text" id="vaultSearch" class="form-input" style="padding-left:36px" placeholder="Search websites..." autocomplete="off">
                </div>
                <a href="vault.php?filter=favorites" class="btn btn-sm <?php echo $filter === 'favorites' ? 'btn-primary' : 'btn-ghost'; ?>"><i class="fas fa-star"></i> Favorites</a>
                <a href="vault.php" class="btn btn-sm btn-ghost <?php echo empty($search) && empty($filter) ? 'd-none' : ''; ?>"><i class="fas fa-times"></i> Clear</a>
            </div>

            <!-- Bulk Action Bar -->
            <div class="bulk-action-bar" id="bulkActionBar" style="display:none">
                <span id="bulkSelectedCount">0 selected</span>
                <div class="bulk-actions">
                    <button class="btn btn-sm btn-danger" onclick="bulkDeleteSelected()"><i class="fas fa-trash"></i> Delete Selected</button>
                </div>
            </div>

            <?php if (empty($websites)): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-globe"></i></div>
                        <h3>Your vault is empty</h3>
                        <p><?php echo $search ? 'No results for "' . sanitizeOutput($search) . '"' : 'Add your first website password to get started'; ?></p>
                        <?php if ($search || $filter): ?>
                            <a href="vault.php" class="btn btn-secondary mt-4">Clear Filters</a>
                        <?php else: ?>
                            <button class="btn btn-primary mt-4" onclick="openAddPasswordModal()"><i class="fas fa-plus"></i> Add Password</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="website-grid">
                    <?php foreach ($websites as $w): ?>
                        <div class="website-group-card">
                            <div class="website-group-header">
                                <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
                                    <div class="website-icon"><?php $favicon = getFaviconUrl($w['website_url']); if ($favicon): ?><img src="<?php echo $favicon; ?>" alt="" style="width:22px;height:22px;border-radius:4px" onerror="this.parentNode.innerHTML='<i class=\'fas fa-globe\'></i>'"><?php else: ?><i class="fas fa-globe"></i><?php endif; ?></div>
                                    <div style="flex:1;min-width:0">
                                        <div class="website-name"><?php echo sanitizeOutput($w['website_name']); ?></div>
                                        <div class="website-meta">
                                            <?php echo $w['_cred_count']; ?> credential<?php echo $w['_cred_count'] !== 1 ? 's' : ''; ?>
                                            <?php if ($w['website_url']): ?>
                                                &middot; <span style="font-size:0.75rem"><?php echo sanitizeOutput($w['website_url']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openEditWebsiteModal(<?php echo $w['id']; ?>, '<?php echo sanitizeOutput($w['website_name']); ?>', '<?php echo sanitizeOutput($w['website_url']); ?>')" title="Edit website">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();deleteWebsite(<?php echo $w['id']; ?>)" title="Delete website">
                                        <i class="fas fa-trash" style="color:var(--danger)"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openAddCredentialToWebsite(<?php echo $w['id']; ?>, '<?php echo sanitizeOutput($w['website_name']); ?>', '<?php echo sanitizeOutput($w['website_url']); ?>')" title="Add credential to this website">
                                        <i class="fas fa-plus" style="color:var(--primary)"></i>
                                    </button>
                                    <i class="fas fa-chevron-down expand-icon" id="expandIcon_<?php echo $w['id']; ?>"></i>
                                </div>
                            </div>
                            <div class="website-group-body" id="websiteBody_<?php echo $w['id']; ?>">
                                <?php if (empty($w['_credentials'])): ?>
                                    <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.85rem">No credentials yet.</div>
                                <?php else: ?>
                                    <div class="creds-list">
                                        <?php foreach ($w['_credentials'] as $c): ?>
                                            <div class="cred-row">
                                                <div class="cred-check-col">
                                                    <input type="checkbox" class="cred-checkbox" value="<?php echo $c['id']; ?>" onchange="updateBulkBar()">
                                                </div>
                                                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
                                                    <div style="width:3px;height:28px;border-radius:2px;background:<?php echo $c['is_favorite'] ? 'rgb(251,191,36)' : 'var(--primary)'; ?>;flex-shrink:0"></div>
                                                    <div style="flex:1;min-width:0">
                                                        <div class="cred-title"><?php echo sanitizeOutput($c['title']); ?></div>
                                                        <div class="cred-username"><?php echo sanitizeOutput($c['username']); ?> <?php if (!empty($c['phone_decrypted'])): ?>&middot; <span class="phone-display" style="color:var(--info)"><i class="fas fa-phone"></i> <?php echo sanitizeOutput($c['phone_decrypted']); ?></span><?php endif; ?></div>
                                                    </div>
                                                </div>
                                                <div class="cred-pw-area">
                                                    <span class="pw-dots" id="vPwText_<?php echo $c['id']; ?>">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</span>
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();togglePw(<?php echo $c['id']; ?>)" title="Show/Hide">
                                                        <i class="fas fa-eye" id="vPwToggle_<?php echo $c['id']; ?>"></i>
                                                    </button>
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();copyPw(<?php echo $c['id']; ?>)" title="Copy">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();viewPasswordHistory(<?php echo $c['id']; ?>)" title="Password History">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                </div>
                                                <div class="cred-row-actions">
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();shareCredential(<?php echo $c['id']; ?>)" title="Share via link">
                                                        <i class="fas fa-share-nodes" style="color:var(--info)"></i>
                                                    </button>
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openEditCredentialModal(<?php echo $c['id']; ?>)" title="Edit">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();toggleCredFav(<?php echo $c['id']; ?>, <?php echo $c['is_favorite'] ? 0 : 1; ?>)" title="Favorite">
                                                        <i class="<?php echo $c['is_favorite'] ? 'fas' : 'far'; ?> fa-star" style="<?php echo $c['is_favorite'] ? 'color:rgb(251,191,36)' : ''; ?>"></i>
                                                    </button>
                                                    <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();deleteCredential(<?php echo $c['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash" style="color:var(--danger)"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Floating Add Button -->
<button class="fab" onclick="openAddPasswordModal()" title="Add Password"><i class="fas fa-plus"></i></button>

<!-- Add Password Modal (Dynamic Credential Rows) -->
<div class="modal-overlay" id="addPasswordModal">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <h2><i class="fas fa-key" style="color:var(--primary)"></i> Add Password</h2>
            <button class="modal-close" onclick="closeModal('addPasswordModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="addPasswordForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="modal-body" style="max-height:60vh;overflow-y:auto;padding-bottom:4px">
                <div id="pwFormAlerts"></div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Website Name <span class="text-danger">*</span></label>
                        <input type="text" id="pwWebsiteName" class="form-input" placeholder="e.g. Gmail, Instagram" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website URL</label>
                        <input type="url" id="pwWebsiteUrl" class="form-input" placeholder="https://example.com">
                    </div>
                </div>
                <div class="section-divider">
                    <span>Credentials</span>
                </div>
                <div id="credentialRows"></div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
                <button type="button" class="btn btn-sm btn-ghost" onclick="addCredentialRow()">
                    <i class="fas fa-plus"></i> Add Another Credential
                </button>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addPasswordModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pwSaveAllBtn" onclick="saveAllCredentials()">
                        <i class="fas fa-save"></i> Save All
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Website Modal -->
<div class="modal-overlay" id="editWebsiteModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h2><i class="fas fa-globe" style="color:var(--primary)"></i> Edit Website</h2>
            <button class="modal-close" onclick="closeModal('editWebsiteModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="editWebsiteForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" id="editWebsiteId" value="0">
            <div class="modal-body">
                <div id="editWebsiteAlerts"></div>
                <div class="form-group">
                    <label class="form-label">Website Name <span class="text-danger">*</span></label>
                    <input type="text" id="editWsName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Website URL</label>
                    <input type="url" id="editWsUrl" class="form-input" placeholder="https://example.com">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editWebsiteModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="editWebsiteBtn" onclick="saveEditWebsite()">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Credential Modal -->
<div class="modal-overlay" id="editCredentialModal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h2><i class="fas fa-key" style="color:var(--primary)"></i> Edit Credential</h2>
            <button class="modal-close" onclick="closeModal('editCredentialModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="editCredForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" id="editCredId" value="0">
            <div class="modal-body">
                <div id="editCredAlerts"></div>
                <div class="form-group">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" id="editCredTitle" class="form-input" placeholder="e.g. Personal, Work" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" id="editCredUsername" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span class="text-danger">*</span> <span class="text-muted" style="font-weight:400;font-size:0.75rem">(min 8 chars)</span></label>
                    <div class="input-group">
                        <input type="password" id="editCredPassword" class="form-input" required minlength="8">
                        <div class="input-group-append">
                            <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="toggleEditPw(this)"><i class="fas fa-eye"></i></button>
                            <button type="button" class="input-group-btn" tabindex="-1" onclick="genEditPw()" title="Generate"><i class="fas fa-dice"></i></button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Mobile Number</label>
                    <input type="tel" id="editCredPhone" class="form-input" placeholder="+1 555-123-4567">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select id="editCredCategory" class="form-input">
                            <option value="">No category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo sanitizeOutput($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" id="editCredNotes" class="form-input" placeholder="Optional notes">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCredentialModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="editCredBtn" onclick="saveEditCredential()">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Share Credential Modal -->
<div class="modal-overlay" id="shareModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h2><i class="fas fa-share-nodes" style="color:var(--info)"></i> Share Credential</h2>
            <button class="modal-close" onclick="closeModal('shareModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="shareForm" onsubmit="return false;">
            <input type="hidden" id="shareCredId" value="0">
            <div class="modal-body">
                <div id="shareAlerts"></div>
                <p style="margin-bottom:16px;color:var(--text-muted);font-size:0.85rem">Generate a secure, time-limited link to share this credential. The password will be visible to anyone with the link.</p>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Expires after</label>
                        <select id="shareExpire" class="form-input">
                            <option value="1">1 hour</option>
                            <option value="6">6 hours</option>
                            <option value="24" selected>24 hours</option>
                            <option value="48">48 hours</option>
                            <option value="72">3 days</option>
                            <option value="168">7 days</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max views</label>
                        <select id="shareMaxViews" class="form-input">
                            <option value="1" selected>1 view</option>
                            <option value="3">3 views</option>
                            <option value="5">5 views</option>
                            <option value="10">10 views</option>
                            <option value="0">Unlimited</option>
                        </select>
                    </div>
                </div>
                <div id="shareResult" style="display:none;margin-top:16px">
                    <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:10px;padding:16px">
                        <label class="form-label" style="font-size:0.75rem">Share Link</label>
                        <div class="input-group" style="margin-top:6px">
                            <input type="text" id="shareLinkUrl" class="form-input" readonly style="font-size:0.8rem;word-break:break-all" onclick="this.select()">
                            <div class="input-group-append">
                                <button type="button" class="input-group-btn" onclick="copyShareLink()" title="Copy"><i class="fas fa-copy"></i></button>
                                <button type="button" class="input-group-btn" onclick="openShareLink()" title="Open"><i class="fas fa-external-link-alt"></i></button>
                            </div>
                        </div>
                        <div style="margin-top:8px;display:flex;gap:16px;font-size:0.75rem;color:var(--text-muted)">
                            <span><i class="fas fa-clock"></i> Expires: <span id="shareExpiresAt"></span></span>
                            <span><i class="fas fa-eye"></i> Max views: <span id="shareMaxViewsDisplay"></span></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="shareModalFooter">
                <button type="button" class="btn btn-secondary" onclick="closeModal('shareModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="shareCreateBtn" onclick="createShareLink()">
                    <i class="fas fa-link"></i> Generate Link
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Password Generator Modal -->
<div class="modal-overlay" id="pwGeneratorModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h2><i class="fas fa-dice" style="color:var(--primary)"></i> Password Generator</h2>
            <button class="modal-close" onclick="closeModal('pwGeneratorModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="gen-pw-display">
                <input type="text" id="genPwOutput" class="form-input" readonly onclick="this.select()" style="font-family:monospace;font-size:1.1rem;text-align:center;padding:14px 12px;letter-spacing:0.05em">
                <div style="display:flex;gap:8px;margin-top:10px;justify-content:center">
                    <button class="btn btn-primary" onclick="generatePassword()"><i class="fas fa-arrows-rotate"></i> Regenerate</button>
                    <button class="btn btn-secondary" id="genPwCopyBtn" onclick="copyGeneratedPw()"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <div id="genPwStrength" class="gen-pw-strength" style="margin-top:10px"></div>
            </div>
            <div class="section-divider" style="margin-top:18px"><span>Options</span></div>
            <div class="gen-options">
                <div class="gen-option-row">
                    <label class="gen-option-label">Length: <span id="genLengthVal">16</span></label>
                    <input type="range" id="genLength" min="6" max="64" value="16" oninput="document.getElementById('genLengthVal').textContent=this.value;generatePassword()">
                </div>
                <div class="gen-option-row">
                    <label class="gen-option-label"><input type="checkbox" id="genUpper" checked onchange="generatePassword()"> Uppercase (A-Z)</label>
                </div>
                <div class="gen-option-row">
                    <label class="gen-option-label"><input type="checkbox" id="genLower" checked onchange="generatePassword()"> Lowercase (a-z)</label>
                </div>
                <div class="gen-option-row">
                    <label class="gen-option-label"><input type="checkbox" id="genNumbers" checked onchange="generatePassword()"> Numbers (0-9)</label>
                </div>
                <div class="gen-option-row">
                    <label class="gen-option-label"><input type="checkbox" id="genSymbols" onchange="generatePassword()"> Symbols (!@#$%^&*)</label>
                </div>
                <div class="gen-option-row">
                    <label class="gen-option-label"><input type="checkbox" id="genAmbiguous" onchange="generatePassword()"> Avoid ambiguous (Il1O0)</label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('pwGeneratorModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="closeModal('pwGeneratorModal')">Done</button>
        </div>
    </div>
</div>

<!-- Master Password Verification Modal -->
<div class="modal-overlay" id="masterPwModal">
    <div class="modal modal-verify" style="max-width:420px">
        <div class="modal-header">
            <h2><i class="fas fa-shield-halved" style="color:var(--primary)"></i> Verify Master Password</h2>
            <button class="modal-close" onclick="closeModal('masterPwModal');masterPwCancel()"><i class="fas fa-times"></i></button>
        </div>
        <form id="masterPwForm" onsubmit="return false;">
            <div class="modal-body">
                <p style="margin-bottom:16px;color:var(--text-muted);font-size:0.85rem" id="masterPwDesc">Enter your master password to continue.</p>
                <div id="masterPwAlerts"></div>
                <div class="form-group">
                    <label class="form-label" for="masterPwInput">Master Password</label>
                    <div class="input-group">
                        <input type="password" id="masterPwInput" class="form-input" placeholder="Enter master password" required autocomplete="off" spellcheck="false">
                        <div class="input-group-append">
                            <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="toggleMasterPw(this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <p style="margin-top:12px;font-size:0.8rem;text-align:center;display:none" id="masterPwPinFallback">
                    <a href="#" onclick="event.preventDefault();switchToPin()"><i class="fas fa-mobile-screen-button"></i> Use PIN instead</a>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('masterPwModal');masterPwCancel()">Cancel</button>
                <button type="button" class="btn btn-primary" id="masterPwBtn" onclick="submitMasterPw()">
                    <span class="btn-text">Verify</span>
                    <span class="spinner" style="display:none;width:18px;height:18px;border-width:2px"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PIN Quick Unlock Modal -->
<div class="modal-overlay" id="pinModal">
    <div class="modal modal-verify" style="max-width:400px">
        <div class="modal-header">
            <h2><i class="fas fa-mobile-screen-button" style="color:var(--info)"></i> Quick Unlock</h2>
            <button class="modal-close" onclick="closeModal('pinModal');masterPwCancel()"><i class="fas fa-times"></i></button>
        </div>
        <form id="pinVerifyForm" onsubmit="return false;">
            <div class="modal-body">
                <p style="margin-bottom:16px;color:var(--text-muted);font-size:0.85rem" id="pinDesc">Enter your PIN to continue.</p>
                <div id="pinVerifyAlerts"></div>
                <div class="form-group">
                    <label class="form-label" for="pinInput">PIN</label>
                    <div class="input-group">
                        <input type="password" id="pinInput" class="form-input" placeholder="Enter PIN" required autocomplete="off" inputmode="numeric" pattern="[0-9]*" maxlength="10" spellcheck="false">
                        <div class="input-group-append">
                            <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="toggleMasterPw(this, 'pinInput')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <p style="margin-top:12px;font-size:0.8rem;text-align:center">
                    <a href="#" onclick="event.preventDefault();switchToMasterPw()"><i class="fas fa-shield-halved"></i> Use master password instead</a>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('pinModal');masterPwCancel()">Cancel</button>
                <button type="button" class="btn btn-info" id="pinVerifyBtn" onclick="submitPin()">
                    <span class="btn-text">Unlock</span>
                    <span class="spinner" style="display:none;width:18px;height:18px;border-width:2px"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ====== MASTER PASSWORD VERIFICATION ======
var pendingPwAction = null;
var pendingPwActionName = 'general';

function requireMasterPw(actionName, callback) {
    pendingPwAction = callback;
    pendingPwActionName = actionName || 'general';

    // Check server-side if action is already verified (session persistent)
    var csrf = '<?php echo generateCsrfToken(); ?>';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/credentials.php?action=check_verification', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
        var data = JSON.parse(xhr.responseText);
        if (data.verified) {
            pendingPwAction = null;
            if (callback) callback();
        } else {
            checkPinAndShowModal();
        }
    };
    xhr.onerror = function() { checkPinAndShowModal(); };
    xhr.send(JSON.stringify({ action: actionName, csrf_token: csrf }));
}

function checkPinAndShowModal() {
    fetch('api/pin.php?action=status')
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.has_pin && !result.blocked) {
            showPinModal();
        } else {
            showVerifyModal();
        }
    })
    .catch(function() {
        showVerifyModal();
    });
}

function showPinModal() {
    document.getElementById('pinVerifyAlerts').innerHTML = '';
    document.getElementById('pinInput').value = '';
    document.getElementById('pinInput').disabled = false;
    var btn = document.getElementById('pinVerifyBtn');
    btn.disabled = false;
    btn.querySelector('.btn-text').textContent = 'Unlock';
    btn.querySelector('.spinner').style.display = 'none';

    var labels = {
        'view_password': 'Enter your PIN to view this password.',
        'copy_password': 'Enter your PIN to copy this password.',
        'edit_credential': 'Enter your PIN to edit this credential.',
        'delete_credential': 'Enter your PIN to delete this credential.',
        'delete_website': 'Enter your PIN to delete this website and all its credentials.'
    };
    document.getElementById('pinDesc').textContent = labels[pendingPwActionName] || 'Enter your PIN to continue.';

    openModal('pinModal');
    setTimeout(function() { document.getElementById('pinInput').focus(); }, 200);
}

function submitPin() {
    var pin = document.getElementById('pinInput').value;
    if (!pin) {
        document.getElementById('pinVerifyAlerts').innerHTML = '<div class="alert alert-danger">Enter your PIN.</div>';
        return;
    }
    var btn = document.getElementById('pinVerifyBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Verifying...';
    btn.querySelector('.spinner').style.display = 'inline-block';
    document.getElementById('pinVerifyAlerts').innerHTML = '';
    document.getElementById('pinInput').disabled = true;

    var csrf = '<?php echo generateCsrfToken(); ?>';
    fetch('api/pin.php?action=verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pin: pin, csrf_token: csrf })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            closeModal('pinModal');
            var cb = pendingPwAction;
            pendingPwAction = null;
            pendingPwActionName = 'general';
            if (cb) cb();
        } else {
            document.getElementById('pinVerifyAlerts').innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
            if (result.message && result.message.indexOf('blocked') !== -1 || result.message && result.message.indexOf('Too many') !== -1) {
                setTimeout(function() {
                    closeModal('pinModal');
                    showVerifyModal();
                }, 2000);
            } else {
                btn.disabled = false;
                btn.querySelector('.btn-text').textContent = 'Unlock';
                btn.querySelector('.spinner').style.display = 'none';
                document.getElementById('pinInput').disabled = false;
                document.getElementById('pinInput').focus();
                document.getElementById('pinInput').select();
            }
        }
    })
    .catch(function() {
        document.getElementById('pinVerifyAlerts').innerHTML = '<div class="alert alert-danger">Network error</div>';
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Unlock';
        btn.querySelector('.spinner').style.display = 'none';
        document.getElementById('pinInput').disabled = false;
    });
}

function switchToMasterPw() {
    closeModal('pinModal');
    showVerifyModal();
}

function switchToPin() {
    closeModal('masterPwModal');
    showPinModal();
}

function showVerifyModal() {
    document.getElementById('masterPwAlerts').innerHTML = '';
    document.getElementById('masterPwInput').value = '';
    document.getElementById('masterPwInput').disabled = false;
    var btn = document.getElementById('masterPwBtn');
    btn.disabled = false;
    btn.querySelector('.btn-text').textContent = 'Verify';
    btn.querySelector('.spinner').style.display = 'none';

    var labels = {
        'view_password': 'Enter your master password to view this password.',
        'copy_password': 'Enter your master password to copy this password.',
        'edit_credential': 'Enter your master password to edit this credential.',
        'delete_credential': 'Enter your master password to delete this credential.',
        'delete_website': 'Enter your master password to delete this website and all its credentials.'
    };
    document.getElementById('masterPwDesc').textContent = labels[pendingPwActionName] || 'Enter your master password to continue.';

    // Show PIN fallback link if PIN is set
    var fp = document.getElementById('masterPwPinFallback');
    if (fp) {
        fetch('api/pin.php?action=status')
        .then(function(r) { return r.json(); })
        .then(function(s) { fp.style.display = s.has_pin && !s.blocked ? 'block' : 'none'; })
        .catch(function() { fp.style.display = 'none'; });
    }

    openModal('masterPwModal');
    setTimeout(function() { document.getElementById('masterPwInput').focus(); }, 200);
}

function submitMasterPw() {
    var pw = document.getElementById('masterPwInput').value;
    if (!pw) {
        document.getElementById('masterPwAlerts').innerHTML = '<div class="alert alert-danger">Enter your master password.</div>';
        return;
    }
    var btn = document.getElementById('masterPwBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Verifying...';
    btn.querySelector('.spinner').style.display = 'inline-block';
    document.getElementById('masterPwInput').disabled = true;

    fetch('api/credentials.php?action=verify_master_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            master_password: pw,
            action: pendingPwActionName,
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Verify';
        btn.querySelector('.spinner').style.display = 'none';
        document.getElementById('masterPwInput').disabled = false;

        if (data.success) {
            closeModal('masterPwModal');
            if (pendingPwAction) {
                var cb = pendingPwAction;
                pendingPwAction = null;
                cb();
            }
        } else {
            document.getElementById('masterPwAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
            document.getElementById('masterPwInput').value = '';
            document.getElementById('masterPwInput').focus();
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Verify';
        btn.querySelector('.spinner').style.display = 'none';
        document.getElementById('masterPwInput').disabled = false;
        document.getElementById('masterPwAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error. Try again.</div>';
    });
}

function masterPwCancel() {
    pendingPwAction = null;
}

function toggleMasterPw(btn, elementId) {
    var id = elementId || 'masterPwInput';
    var input = document.getElementById(id);
    if (input && input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else if (input) {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

// Set up Enter key handlers
document.addEventListener('DOMContentLoaded', function() {
    var mpInput = document.getElementById('masterPwInput');
    if (mpInput) {
        mpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); submitMasterPw(); }
        });
    }
    var pinInput = document.getElementById('pinInput');
    if (pinInput) {
        pinInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); submitPin(); }
        });
    }
});

// ====== SEARCH & FILTER ======
document.getElementById('vaultSearch') && document.getElementById('vaultSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        var url = new URL(window.location.href);
        var q = this.value.trim();
        if (q) url.searchParams.set('search', q);
        else url.searchParams.delete('search');
        url.searchParams.delete('filter');
        window.location.href = url.toString();
    }
});

// ====== WEBSITE EXPAND/COLLAPSE (event delegation) ======
document.addEventListener('click', function(e) {
    var header = e.target.closest('.website-group-header');
    if (!header) return;
    var card = header.closest('.website-group-card');
    if (!card) return;
    var body = card.querySelector('.website-group-body');
    var icon = card.querySelector('.expand-icon');
    if (!body) return;
    var isOpen = body.classList.contains('open');
    if (isOpen) {
        body.classList.remove('open');
        if (icon) icon.style.transform = '';
    } else {
        body.classList.add('open');
        if (icon) icon.style.transform = 'rotate(180deg)';
    }
});

// ====== DYNAMIC CREDENTIAL ROWS ======
var credRowCount = 0;

// ====== ADD CREDENTIAL TO EXISTING WEBSITE ======
function openAddCredentialToWebsite(id, name, url) {
    openAddPasswordModal();
    document.getElementById('pwWebsiteName').value = name;
    document.getElementById('pwWebsiteUrl').value = url;
}

function openAddPasswordModal() {
    document.getElementById('addPasswordForm').reset();
    document.getElementById('pwFormAlerts').innerHTML = '';
    document.getElementById('credentialRows').innerHTML = '';
    credRowCount = 0;
    addCredentialRow();
    openModal('addPasswordModal');
}

function addCredentialRow(data) {
    data = data || {};
    var idx = ++credRowCount;
    var categoriesHtml = '<option value="">No category</option>';
    if (typeof categories !== 'undefined' && categories) {
        for (var ci = 0; ci < categories.length; ci++) {
            var sel = (data.category_id && data.category_id == categories[ci].id) ? ' selected' : '';
            categoriesHtml += '<option value="' + categories[ci].id + '"' + sel + '>' + escHtml(categories[ci].name) + '</option>';
        }
    }
    var div = document.createElement('div');
    div.className = 'modal-cred-row';
    div.id = 'credRow_' + idx;
    div.innerHTML =
        '<div class="modal-cred-header">' +
            '<span class="modal-cred-number">#' + idx + '</span>' +
            '<button type="button" class="btn btn-sm btn-danger" onclick="removeCredentialRow(' + idx + ')"><i class="fas fa-trash"></i> Remove</button>' +
        '</div>' +
        '<div class="grid-2">' +
            '<div class="form-group">' +
                '<label class="form-label">Title <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-input cred-title" placeholder="e.g. Personal, Work" value="' + escHtml(data.title || '') + '" required>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Username <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-input cred-username" value="' + escHtml(data.username || '') + '" required>' +
            '</div>' +
        '</div>' +
        '<div class="form-group">' +
            '<label class="form-label">Password <span class="text-danger">*</span> <span class="text-muted" style="font-weight:400;font-size:0.75rem">(min 8 chars)</span></label>' +
            '<div class="input-group">' +
                '<input type="password" class="form-input cred-password" value="' + escHtml(data.password || '') + '" required minlength="8">' +
                '<div class="input-group-append">' +
                    '<button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="toggleRowPw(this)"><i class="fas fa-eye"></i></button>' +
                    '<button type="button" class="input-group-btn" tabindex="-1" onclick="genRowPw(this)" title="Generate"><i class="fas fa-dice"></i></button>' +
                '</div>' +
            '</div>' +
        '</div>' +
        '<div class="form-group">' +
            '<label class="form-label">Mobile Number</label>' +
            '<input type="tel" class="form-input cred-phone" placeholder="+1 555-123-4567" value="' + escHtml(data.phone || '') + '">' +
        '</div>' +
        '<div class="grid-2">' +
            '<div class="form-group">' +
                '<label class="form-label">Category</label>' +
                '<select class="form-input cred-category">' + categoriesHtml + '</select>' +
            '</div>' +
            '<div class="form-group">' +
                '<label class="form-label">Notes</label>' +
                '<input type="text" class="form-input cred-notes" placeholder="Optional notes" value="' + escHtml(data.notes || '') + '">' +
            '</div>' +
        '</div>';
    document.getElementById('credentialRows').appendChild(div);
    // Auto-focus title input
    var titleInput = div.querySelector('.cred-title');
    if (titleInput) setTimeout(function() { titleInput.focus(); }, 100);
}

function removeCredentialRow(idx) {
    var row = document.getElementById('credRow_' + idx);
    if (row) row.remove();
}

function toggleRowPw(btn) {
    var input = btn.closest('.input-group').querySelector('.cred-password');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

function genRowPw(btn) {
    var c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    var pw = '';
    for (var i = 0; i < 16; i++) pw += c.charAt(Math.floor(Math.random() * c.length));
    btn.closest('.input-group').querySelector('.cred-password').value = pw;
}

function escHtml(s) {
    if (typeof s !== 'string') return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function saveAllCredentials() {
    var alerts = document.getElementById('pwFormAlerts');
    var btn = document.getElementById('pwSaveAllBtn');
    alerts.innerHTML = '';

    var websiteName = document.getElementById('pwWebsiteName').value.trim();
    if (!websiteName) {
        alerts.innerHTML = '<div class="alert alert-danger">Website name is required.</div>';
        document.getElementById('pwWebsiteName').focus();
        return;
    }
    var websiteUrl = document.getElementById('pwWebsiteUrl').value.trim();

    var rows = document.querySelectorAll('.modal-cred-row');
    var credentials = [];
    var errors = [];

    rows.forEach(function(row) {
        var title = row.querySelector('.cred-title').value.trim();
        var username = row.querySelector('.cred-username').value.trim();
        var password = row.querySelector('.cred-password').value;
        var categoryId = row.querySelector('.cred-category') ? row.querySelector('.cred-category').value : '';
        var notes = row.querySelector('.cred-notes') ? row.querySelector('.cred-notes').value.trim() : '';
        var phone = row.querySelector('.cred-phone') ? row.querySelector('.cred-phone').value.trim() : '';
        if (!title || !username || !password) {
            var idx = row.id.replace('credRow_', '');
            errors.push('Row #' + idx + ': title, username, and password are required.');
            return;
        }
        if (password.length < 8) {
            var idx = row.id.replace('credRow_', '');
            errors.push('Row #' + idx + ': password must be at least 8 characters.');
            return;
        }
        var cred = { title: title, username: username, password: password };
        if (categoryId) cred.category_id = parseInt(categoryId, 10);
        if (notes) cred.notes = notes;
        if (phone) cred.phone = phone;
        credentials.push(cred);
    });

    if (errors.length) {
        alerts.innerHTML = '<div class="alert alert-warning">' + errors.join('<br>') + '</div>';
        return;
    }
    if (!credentials.length) {
        alerts.innerHTML = '<div class="alert alert-warning">Add at least one credential row.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('api/credentials.php?action=bulk_add_credentials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: document.querySelector('#addPasswordForm [name="csrf_token"]').value,
            website_name: websiteName,
            website_url: websiteUrl,
            credentials: credentials
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save All';
        if (result.success) {
            closeModal('addPasswordModal');
            showToast(result.message, 'success');
            setTimeout(function() { location.reload(); }, 500);
        } else {
            alerts.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save All';
        alerts.innerHTML = '<div class="alert alert-danger">Network error. Try again.</div>';
    });
}

// ====== WEBSITE / CREDENTIAL CRUD ======
function deleteWebsite(id) {
    requireMasterPw('delete_website', function() {
        showConfirmDialog('Move this website and ALL its credentials to trash? You can restore later.', 'Delete Website', 'Move to Trash', 'Cancel', function() {
            fetch('api/credentials.php?action=delete_website', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { showToast('Website deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
                else { showToast('Failed', 'error'); }
            });
        });
    });
}

function deleteCredential(id) {
    requireMasterPw('delete_credential', function() {
        showConfirmDialog('Move this credential to trash? You can restore it later.', 'Delete Credential', 'Move to Trash', 'Cancel', function() {
            fetch('api/credentials.php?action=delete_credential', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, csrf_token: '<?php echo generateCsrfToken(); ?>' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { showToast('Credential deleted', 'success'); setTimeout(function() { location.reload(); }, 500); }
                else { showToast('Failed', 'error'); }
            });
        });
    });
}

function toggleCredFav(id, fav) {
    fetch('api/credentials.php?action=toggle_favorite', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, is_favorite: fav })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) { location.reload(); }
    });
}

// ====== EDIT WEBSITE ======
function openEditWebsiteModal(id, name, url) {
    document.getElementById('editWebsiteId').value = id;
    document.getElementById('editWsName').value = name;
    document.getElementById('editWsUrl').value = url || '';
    document.getElementById('editWebsiteAlerts').innerHTML = '';
    openModal('editWebsiteModal');
}

function saveEditWebsite() {
    var id = document.getElementById('editWebsiteId').value;
    var name = document.getElementById('editWsName').value.trim();
    var url = document.getElementById('editWsUrl').value.trim();
    var alerts = document.getElementById('editWebsiteAlerts');
    var btn = document.getElementById('editWebsiteBtn');

    if (!name) {
        alerts.innerHTML = '<div class="alert alert-danger">Website name is required.</div>';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch('api/credentials.php?action=edit_website', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: document.querySelector('#editWebsiteForm [name="csrf_token"]').value,
            id: id, website_name: name, website_url: url
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.textContent = 'Save';
        if (result.success) {
            closeModal('editWebsiteModal');
            showToast(result.message, 'success');
            setTimeout(function() { location.reload(); }, 500);
        } else {
            alerts.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
        }
    });
}

// ====== EDIT CREDENTIAL ======
function openEditCredentialModal(id) {
    requireMasterPw('edit_credential', function() {
        document.getElementById('editCredAlerts').innerHTML = '';
        var btn = document.getElementById('editCredBtn');
        btn.disabled = true;
        btn.textContent = 'Loading...';

        fetch('api/credentials.php?action=get_credential&id=' + id + '&_=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Save';
            if (!data.success) { showToast('Failed to load credential', 'error'); return; }
            document.getElementById('editCredId').value = data.credential.id;
            document.getElementById('editCredTitle').value = data.credential.title;
            document.getElementById('editCredUsername').value = data.credential.username;
            document.getElementById('editCredPassword').value = data.credential.password_decrypted;
            document.getElementById('editCredCategory').value = data.credential.category_id || '';
            document.getElementById('editCredNotes').value = data.credential.notes || '';
            document.getElementById('editCredPhone').value = data.credential.phone_decrypted || '';
            openModal('editCredentialModal');
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Save';
            showToast('Network error', 'error');
        });
    });
}

function saveEditCredential() {
    var id = document.getElementById('editCredId').value;
    var title = document.getElementById('editCredTitle').value.trim();
    var username = document.getElementById('editCredUsername').value.trim();
    var password = document.getElementById('editCredPassword').value;
    var alerts = document.getElementById('editCredAlerts');
    var btn = document.getElementById('editCredBtn');

    if (!title || !username || !password) {
        alerts.innerHTML = '<div class="alert alert-danger">All fields are required.</div>';
        return;
    }
    if (password.length < 8) {
        alerts.innerHTML = '<div class="alert alert-danger">Password must be at least 8 characters.</div>';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    var categoryId = document.getElementById('editCredCategory').value;
    var notes = document.getElementById('editCredNotes').value.trim();
    var phone = document.getElementById('editCredPhone').value.trim();
    var payload = {
        csrf_token: document.querySelector('#editCredForm [name="csrf_token"]').value,
        id: id, title: title, username: username, password: password
    };
    if (categoryId) payload.category_id = parseInt(categoryId, 10);
    if (notes) payload.notes = notes;
    if (phone) payload.phone = phone;

    fetch('api/credentials.php?action=edit_credential', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.textContent = 'Save';
        if (result.success) {
            closeModal('editCredentialModal');
            showToast(result.message, 'success');
            setTimeout(function() { location.reload(); }, 500);
        } else {
            alerts.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
        }
    });
}

function toggleEditPw(btn) {
    var input = document.getElementById('editCredPassword');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

function genEditPw() {
    var c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    var pw = '';
    for (var i = 0; i < 16; i++) pw += c.charAt(Math.floor(Math.random() * c.length));
    document.getElementById('editCredPassword').value = pw;
}

// ====== PASSWORD GENERATOR ======
function openPwGenerator() {
    openModal('pwGeneratorModal');
    generatePassword();
}

function generatePassword() {
    var len = parseInt(document.getElementById('genLength').value, 10);
    var useUpper = document.getElementById('genUpper').checked;
    var useLower = document.getElementById('genLower').checked;
    var useNum = document.getElementById('genNumbers').checked;
    var useSym = document.getElementById('genSymbols').checked;
    var avoidAmb = document.getElementById('genAmbiguous').checked;

    var upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var lower = 'abcdefghijklmnopqrstuvwxyz';
    var nums = '0123456789';
    var syms = '!@#$%^&*()_+~-={}|[]:;<>,.?/';
    var ambiguous = 'Il1O0';

    var chars = '';
    var mandatory = [];
    if (useUpper) { var u = avoidAmb ? upper.replace(/[Il1O0]/g, '') : upper; chars += u; mandatory.push(u.charAt(Math.floor(Math.random() * u.length))); }
    if (useLower) { var l = avoidAmb ? lower.replace(/[Il1O0]/g, '') : lower; chars += l; mandatory.push(l.charAt(Math.floor(Math.random() * l.length))); }
    if (useNum) { var n = avoidAmb ? nums.replace(/[Il1O0]/g, '') : nums; chars += n; mandatory.push(n.charAt(Math.floor(Math.random() * n.length))); }
    if (useSym) { chars += syms; mandatory.push(syms.charAt(Math.floor(Math.random() * syms.length))); }

    if (!chars) { document.getElementById('genPwOutput').value = 'Select at least one charset'; return; }

    var pw = mandatory.join('');
    for (var i = pw.length; i < len; i++) {
        pw += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    pw = pw.split('').sort(function() { return Math.random() - 0.5; }).join('');

    document.getElementById('genPwOutput').value = pw;
    updatePwStrength(pw);
}

function updatePwStrength(pw) {
    var el = document.getElementById('genPwStrength');
    var score = 0;
    if (pw.length >= 8) score += 1;
    if (pw.length >= 12) score += 1;
    if (pw.length >= 16) score += 1;
    if (pw.length >= 24) score += 1;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score += 1;
    if (/\d/.test(pw)) score += 1;
    if (/[^a-zA-Z0-9]/.test(pw)) score += 1;

    var label = score <= 2 ? 'Weak' : score <= 4 ? 'Fair' : score <= 5 ? 'Good' : 'Strong';
    var color = score <= 2 ? 'var(--danger)' : score <= 4 ? 'var(--warning)' : score <= 5 ? 'var(--info)' : 'var(--success)';
    var pct = Math.min(100, (score / 7) * 100);
    el.innerHTML = '<div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px"><span>Strength</span><span style="font-weight:600;color:' + color + '">' + label + '</span></div><div style="height:4px;border-radius:2px;background:var(--border-soft)"><div style="height:100%;border-radius:2px;width:' + pct + '%;background:' + color + ';transition:width 0.3s ease"></div></div>';
}

function copyGeneratedPw() {
    var input = document.getElementById('genPwOutput');
    if (!input.value) return;
    input.select();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value).then(function() { showToast('Copied!', 'success'); });
    } else {
        document.execCommand('copy');
        showToast('Copied!', 'success');
    }
}

// ====== PASSWORD UTILITIES ======
function togglePw(id) {
    requireMasterPw('view_password', function() { doTogglePw(id); });
}

var pwAutoHideTimers = {};

function doTogglePw(id) {
    var text = document.getElementById('vPwText_' + id);
    var toggle = document.getElementById('vPwToggle_' + id);
    if (text.textContent !== '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022') {
        text.textContent = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
        if (toggle) toggle.className = 'fas fa-eye';
        if (pwAutoHideTimers[id]) { clearTimeout(pwAutoHideTimers[id]); delete pwAutoHideTimers[id]; }
        return;
    }
    fetch('api/credentials.php?action=get_decrypted_password&id=' + id + '&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) { showToast('Failed', 'error'); return; }
        text.textContent = data.password;
        if (toggle) toggle.className = 'fas fa-eye-slash';
        pwAutoHideTimers[id] = setTimeout(function() {
            text.textContent = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
            if (toggle) toggle.className = 'fas fa-eye';
            delete pwAutoHideTimers[id];
        }, 30000);
    })
    .catch(function() { showToast('Network error', 'error'); });
}

function copyPw(id) {
    requireMasterPw('copy_password', function() { doCopyPw(id); });
}

function doCopyPw(id) {
    fetch('api/credentials.php?action=get_decrypted_password&id=' + id + '&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) { showToast('Failed to copy', 'error'); return; }
        if (navigator.clipboard) {
            navigator.clipboard.writeText(data.password).then(function() { showToast('Copied!', 'success'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = data.password; document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); document.body.removeChild(ta);
            showToast('Copied!', 'success');
        }
    })
    .catch(function() { showToast('Network error', 'error'); });
}

var categories = <?php echo json_encode($categories); ?>;

// ====== SHARE CREDENTIAL ======
function shareCredential(id) {
    document.getElementById('shareCredId').value = id;
    document.getElementById('shareAlerts').innerHTML = '';
    document.getElementById('shareResult').style.display = 'none';
    document.getElementById('shareModalFooter').style.display = 'flex';
    openModal('shareModal');
}

function createShareLink() {
    var id = document.getElementById('shareCredId').value;
    var expire = document.getElementById('shareExpire').value;
    var maxViews = document.getElementById('shareMaxViews').value;
    var btn = document.getElementById('shareCreateBtn');
    var alerts = document.getElementById('shareAlerts');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    alerts.innerHTML = '';

    fetch('api/shares.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            credential_id: parseInt(id),
            expire_hours: parseInt(expire),
            max_views: parseInt(maxViews) || 999,
            csrf_token: '<?php echo generateCsrfToken(); ?>'
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-link"></i> Generate Link';
        if (data.success) {
            document.getElementById('shareLinkUrl').value = data.url;
            document.getElementById('shareExpiresAt').textContent = data.expires_at;
            document.getElementById('shareMaxViewsDisplay').textContent = data.max_views;
            document.getElementById('shareResult').style.display = 'block';
            document.getElementById('shareModalFooter').style.display = 'none';
        } else {
            alerts.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-link"></i> Generate Link';
        alerts.innerHTML = '<div class="alert alert-danger">Network error. Try again.</div>';
    });
}

function copyShareLink() {
    var input = document.getElementById('shareLinkUrl');
    input.select();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(input.value).then(function() { showToast('Link copied!', 'success'); });
    } else {
        document.execCommand('copy');
        showToast('Link copied!', 'success');
    }
}

function openShareLink() {
    var url = document.getElementById('shareLinkUrl').value;
    if (url) window.open(url, '_blank');
}

// ====== BULK SELECTION ======
function updateBulkBar() {
    var checks = document.querySelectorAll('.cred-checkbox:checked');
    var bar = document.getElementById('bulkActionBar');
    var count = document.getElementById('bulkSelectedCount');
    if (checks.length > 0) {
        bar.style.display = 'flex';
        count.textContent = checks.length + ' selected';
    } else {
        bar.style.display = 'none';
    }
}

function bulkDeleteSelected() {
    var checks = document.querySelectorAll('.cred-checkbox:checked');
    var ids = Array.from(checks).map(function(c) { return parseInt(c.value); });
    if (!ids.length) return;
    showConfirmDialog('Delete ' + ids.length + ' selected credential(s)? They will be moved to trash.', 'Delete Selected', 'Delete', 'Cancel', function() {
        fetch('api/credentials.php?action=bulk_delete_credentials', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids, csrf_token: '<?php echo generateCsrfToken(); ?>' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { showToast(data.message, 'success'); setTimeout(function() { location.reload(); }, 500); }
            else { showToast('Failed', 'error'); }
        });
    });
}

// ====== PASSWORD HISTORY ======
function viewPasswordHistory(id) {
    fetch('api/credentials.php?action=get_password_history&id=' + id + '&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success || !data.history.length) {
            showModal('<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-history" style="color:var(--text-muted)"></i></div><h3>No History Yet</h3><p>Password change history will appear here once you update this credential\'s password.</p></div>', 'Password History');
            return;
        }
        var html = '<div style="max-height:400px;overflow-y:auto"><table class="history-table"><thead><tr><th>#</th><th>Password</th><th>Changed At</th></tr></thead><tbody>';
        data.history.forEach(function(h, i) {
            html += '<tr><td>' + (i + 1) + '</td><td style="font-family:monospace">' + escHtml(h.password_decrypted) + '</td><td>' + h.created_at + '</td></tr>';
        });
        html += '</tbody></table></div>';
        showModal(html, 'Password History');
    })
    .catch(function() { showToast('Failed to load history', 'error'); });
}

function showModal(content, title) {
    var overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.display = 'flex';
    overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
    overlay.innerHTML =
        '<div class="modal" style="max-width:600px">' +
            '<div class="modal-header">' +
                '<h2><i class="fas fa-history" style="color:var(--primary)"></i> ' + (title || '') + '</h2>' +
                '<button class="modal-close" onclick="this.closest(\'.modal-overlay\').remove()"><i class="fas fa-times"></i></button>' +
            '</div>' +
            '<div class="modal-body">' + content + '</div>' +
            '<div class="modal-footer">' +
                '<button type="button" class="btn btn-secondary" onclick="this.closest(\'.modal-overlay\').remove()">Close</button>' +
            '</div>' +
        '</div>';
    document.body.appendChild(overlay);
}

</script>

<?php include 'includes/footer.php'; ?>
