<?php
require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();
$pageTitle = 'Cards';
$bodyClass = 'vault-page';
include 'includes/header.php';
include 'includes/navbar.php';
?>
<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-credit-card" style="color:var(--primary)"></i> Cards</h1>
                    <p>Manage your saved debit and credit cards</p>
                </div>
                <div class="page-actions">
                    <div class="dropdown" style="position:relative;display:inline-block">
                        <button class="btn btn-secondary" onclick="toggleExportMenu()" title="Export">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div id="exportMenu" style="display:none;position:absolute;right:0;top:100%;margin-top:4px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.15);z-index:100;min-width:160px;overflow:hidden">
                            <button class="dropdown-item" onclick="exportCards('html')" style="display:flex;align-items:center;gap:8px;width:100%;padding:10px 16px;border:none;background:none;cursor:pointer;font-size:0.875rem;color:var(--text);transition:background 0.15s" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='none'"><i class="fas fa-file-code" style="color:var(--primary);width:18px"></i> Export as HTML</button>
                            <button class="dropdown-item" onclick="exportCards('pdf')" style="display:flex;align-items:center;gap:8px;width:100%;padding:10px 16px;border:none;background:none;cursor:pointer;font-size:0.875rem;color:var(--text);transition:background 0.15s" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='none'"><i class="fas fa-file-pdf" style="color:var(--danger);width:18px"></i> Export as PDF</button>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="openAddCardModal()">
                        <i class="fas fa-plus"></i> Add Card
                    </button>
                </div>
            </div>

            <div id="cardsList" class="cards-grid">
                <div style="text-align:center;padding:60px 20px;color:var(--text-muted)">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;margin-bottom:16px"></i>
                    <p>Loading cards...</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Card Modal -->
<div class="modal-overlay" id="addCardModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h2><i class="fas fa-credit-card" style="color:var(--primary)"></i> Add Card</h2>
            <button class="modal-close" onclick="closeModal('addCardModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="addCardForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="modal-body">
                <div id="cardFormAlerts"></div>
                <div class="form-group">
                    <label class="form-label">Card Type <span class="text-danger">*</span></label>
                    <select id="addCardType" class="form-input" required>
                        <option value="">Select type</option>
                        <option value="credit">Credit Card</option>
                        <option value="debit">Debit Card</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cardholder Name <span class="text-danger">*</span></label>
                    <input type="text" id="addCardholderName" class="form-input" placeholder="Name on card" required maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Card Number <span class="text-danger">*</span></label>
                    <input type="text" id="addCardNumber" class="form-input" placeholder="0000 0000 0000 0000" inputmode="numeric" maxlength="19" required autocomplete="off">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Expiry Month <span class="text-danger">*</span></label>
                        <select id="addExpiryMonth" class="form-input">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo sprintf('%02d', $m); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Year <span class="text-danger">*</span></label>
                        <select id="addExpiryYear" class="form-input">
                            <?php for ($y = date('Y'); $y <= date('Y') + 15; $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">CVV <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" id="addCvv" class="form-input" placeholder="***" inputmode="numeric" maxlength="4" required autocomplete="off">
                            <div class="input-group-append">
                                <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('addCvv', this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" id="addBankName" class="form-input" placeholder="e.g. HDFC, SBI" maxlength="255" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Card Network <span class="text-danger">*</span></label>
                    <select id="addCardNetwork" class="form-input" required>
                        <option value="">Auto-detect</option>
                        <option value="visa">Visa</option>
                        <option value="mastercard">Mastercard</option>
                        <option value="rupay">RuPay</option>
                        <option value="amex">American Express</option>
                        <option value="discover">Discover</option>
                        <option value="diners">Diners Club</option>
                        <option value="maestro">Maestro</option>
                        <option value="unknown">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea id="addCardNotes" class="form-input" rows="2" placeholder="Optional notes" maxlength="1000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCardModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCardBtn" onclick="saveCard()"><i class="fas fa-save"></i> Save Card</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Card Modal -->
<div class="modal-overlay" id="editCardModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h2><i class="fas fa-credit-card" style="color:var(--primary)"></i> Edit Card</h2>
            <button class="modal-close" onclick="closeModal('editCardModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="editCardForm" onsubmit="return false;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" id="editCardId" value="0">
            <div class="modal-body">
                <div id="editCardFormAlerts"></div>
                <div class="form-group">
                    <label class="form-label">Card Type <span class="text-danger">*</span></label>
                    <select id="editCardType" class="form-input" required>
                        <option value="">Select type</option>
                        <option value="credit">Credit Card</option>
                        <option value="debit">Debit Card</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cardholder Name <span class="text-danger">*</span></label>
                    <input type="text" id="editCardholderName" class="form-input" required maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Card Number</label>
                    <input type="text" id="editCardNumber" class="form-input" placeholder="Leave blank to keep existing" inputmode="numeric" maxlength="19" autocomplete="off">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Expiry Month <span class="text-danger">*</span></label>
                        <select id="editExpiryMonth" class="form-input">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo sprintf('%02d', $m); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Year <span class="text-danger">*</span></label>
                        <select id="editExpiryYear" class="form-input">
                            <?php for ($y = date('Y'); $y <= date('Y') + 15; $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">CVV</label>
                        <div class="input-group">
                            <input type="password" id="editCvv" class="form-input" placeholder="Leave blank to keep existing" inputmode="numeric" maxlength="4" autocomplete="off">
                            <div class="input-group-append">
                                <button type="button" class="input-group-btn pw-toggle" tabindex="-1" onclick="togglePwField('editCvv', this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" id="editBankName" class="form-input" maxlength="255" required>
                    </div>
                </div>
                    <div class="form-group">
                        <label class="form-label">Card Network</label>
                        <select id="editCardNetwork" class="form-input">
                            <option value="">Auto-detect (keep existing)</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="rupay">RuPay</option>
                            <option value="amex">American Express</option>
                            <option value="discover">Discover</option>
                            <option value="diners">Diners Club</option>
                            <option value="maestro">Maestro</option>
                            <option value="unknown">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea id="editCardNotes" class="form-input" rows="2" maxlength="1000"></textarea>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCardModal')">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateCardBtn" onclick="updateCard()"><i class="fas fa-save"></i> Update Card</button>
            </div>
        </form>
    </div>
</div>

<!-- View Card Modal -->
<div class="modal-overlay" id="viewCardModal">
    <div class="modal" style="max-width:460px">
        <div class="modal-header">
            <h2 id="viewCardModalTitle"><i class="fas fa-credit-card" style="color:var(--primary)"></i> Card Details</h2>
            <button class="modal-close" onclick="closeModal('viewCardModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="viewCardAlerts"></div>
            <div id="viewCardContent" style="text-align:center">
                <div class="card-visual" id="viewCardVisual">
                    <div class="card-visual-chip"></div>
                    <div class="card-visual-number" id="viewCardNumber">**** **** **** ****</div>
                    <div style="display:flex;justify-content:space-between;margin-top:16px">
                        <div style="text-align:left">
                            <div class="card-visual-label">CARDHOLDER</div>
                            <div class="card-visual-value" id="viewCardholderName">-</div>
                        </div>
                        <div style="text-align:right">
                            <div class="card-visual-label">EXPIRY</div>
                            <div class="card-visual-value" id="viewExpiry">--/--</div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
                    <button class="btn btn-sm btn-ghost" onclick="toggleViewCardNumber()"><i class="fas fa-eye"></i> <span id="viewNumBtnText">Show Number</span></button>
                    <button class="btn btn-sm btn-ghost" onclick="copyViewCardNumber()"><i class="fas fa-copy"></i> Copy Number</button>
                </div>
                <div style="display:flex;gap:8px;justify-content:center;margin-top:6px">
                    <button class="btn btn-sm btn-ghost" onclick="toggleViewCardCvv()"><i class="fas fa-eye"></i> <span id="viewCvvBtnText">Show CVV</span></button>
                    <button class="btn btn-sm btn-ghost" onclick="copyViewCardCvv()"><i class="fas fa-copy"></i> Copy CVV</button>
                </div>
                <div style="margin-top:12px;padding:12px;background:var(--card-glass);border-radius:8px">
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--text-muted)">CVV</span>
                        <span id="viewCvv">***</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--text-muted)">Type</span>
                        <span id="viewCardType">-</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--text-muted)">Network</span>
                        <span id="viewCardNetwork">-</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--text-muted)">Bank</span>
                        <span id="viewCardBank">-</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--text-muted)">Notes</span>
                        <span id="viewCardNotes">-</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewCardModal')">Close</button>
        </div>
    </div>
</div>

<script>
var csrfToken = '<?php echo generateCsrfToken(); ?>';
var viewCardNumberVisible = false;
var viewCardData = null;

document.addEventListener('DOMContentLoaded', function() {
    loadCards();
});

function loadCards() {
    fetch('api/cards.php?action=list')
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var container = document.getElementById('cardsList');
        if (result.success && result.cards && result.cards.length > 0) {
            container.innerHTML = '';
            result.cards.forEach(function(c) {
                container.innerHTML += renderCard(c);
            });
        } else {
            container.innerHTML =
                '<div style="text-align:center;padding:60px 20px;color:var(--text-muted)">' +
                '<i class="fas fa-credit-card" style="font-size:3rem;margin-bottom:16px;opacity:0.3"></i>' +
                '<p style="font-size:1.1rem;margin-bottom:8px">No cards saved yet</p>' +
                '<p style="font-size:0.85rem;margin-bottom:20px">Add your debit or credit cards for quick access</p>' +
                '<button class="btn btn-primary" onclick="openAddCardModal()"><i class="fas fa-plus"></i> Add Card</button>' +
                '</div>';
        }
    })
    .catch(function() {
        document.getElementById('cardsList').innerHTML =
            '<div style="text-align:center;padding:60px 20px;color:var(--text-muted)">' +
            '<i class="fas fa-exclamation-circle" style="font-size:2rem;margin-bottom:16px"></i>' +
            '<p>Failed to load cards</p></div>';
    });
}

function renderCard(c) {
    var network = c.card_network || 'unknown';
    var networkIcon = getNetworkIcon(network);
    var networkColor = getNetworkColor(network);
    var typeLabel = c.card_type === 'debit' ? 'Debit' : 'Credit';
    var expiry = ('0' + c.expiry_month).slice(-2) + '/' + c.expiry_year;
    var maskNum = '**** **** **** ' + (c.last_four || '****');

    return '<div class="card-item ' + (c.is_favorite ? 'fav' : '') + '" data-id="' + c.id + '" onclick="openViewCardModal(' + c.id + ')">' +
        '<div class="card-item-visual" style="background:' + networkColor + '">' +
            '<div class="card-item-type">' + typeLabel + '</div>' +
            '<div class="card-item-network"><i class="' + networkIcon + '"></i></div>' +
            '<div class="card-item-number">' + maskNum + '</div>' +
            '<div class="card-item-footer">' +
                '<span>' + esc(c.cardholder_name) + '</span>' +
                '<span>' + expiry + '</span>' +
            '</div>' +
        '</div>' +
        '<div class="card-item-info">' +
            '<div class="card-item-name">' + esc(c.cardholder_name) + '</div>' +
            '<div class="card-item-meta">' +
                '<span>' + esc(c.bank_name || typeLabel) + '</span>' +
                (c.is_favorite ? '<span style="color:rgb(251,191,36)"><i class="fas fa-star"></i></span>' : '') +
            '</div>' +
        '</div>' +
        '<div class="card-item-actions">' +
            '<button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();toggleCardFav(' + c.id + ', this)" title="Favorite"><i class="' + (c.is_favorite ? 'fas' : 'far') + ' fa-star" style="' + (c.is_favorite ? 'color:rgb(251,191,36)' : '') + '"></i></button>' +
            '<button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openEditCardModal(' + c.id + ')" title="Edit"><i class="fas fa-pen"></i></button>' +
            '<button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();deleteCard(' + c.id + ')" title="Delete"><i class="fas fa-trash" style="color:var(--danger)"></i></button>' +
        '</div>' +
    '</div>';
}

function openAddCardModal() {
    document.getElementById('addCardForm').reset();
    document.getElementById('cardFormAlerts').innerHTML = '';
    openModal('addCardModal');
}

function saveCard() {
    var data = {
        csrf_token: csrfToken,
        card_type: document.getElementById('addCardType').value,
        cardholder_name: document.getElementById('addCardholderName').value.trim(),
        card_number: document.getElementById('addCardNumber').value.replace(/\s/g, ''),
        expiry_month: document.getElementById('addExpiryMonth').value,
        expiry_year: document.getElementById('addExpiryYear').value,
        cvv: document.getElementById('addCvv').value,
        bank_name: document.getElementById('addBankName').value.trim(),
        card_network: document.getElementById('addCardNetwork').value,
        notes: document.getElementById('addCardNotes').value.trim()
    };

    if (!data.card_type || !data.cardholder_name || !data.card_number || !data.expiry_month || !data.expiry_year || !data.cvv || !data.bank_name) {
        document.getElementById('cardFormAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> All fields except notes are required</div>';
        return;
    }

    var btn = document.getElementById('saveCardBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('api/cards.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            closeModal('addCardModal');
            showToast('Card saved successfully', 'success');
            loadCards();
        } else {
            document.getElementById('cardFormAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Card';
        }
    })
    .catch(function() {
        document.getElementById('cardFormAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Card';
    });
}

function openEditCardModal(id) {
    document.getElementById('editCardFormAlerts').innerHTML = '';
    document.getElementById('editCardId').value = id;

    fetch('api/cards.php?action=get&id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            var c = result.card;
            document.getElementById('editCardType').value = c.card_type;
            document.getElementById('editCardholderName').value = c.cardholder_name;
            document.getElementById('editCardNumber').value = '';
            document.getElementById('editExpiryMonth').value = c.expiry_month;
            document.getElementById('editExpiryYear').value = c.expiry_year;
            document.getElementById('editCvv').value = '';
            document.getElementById('editBankName').value = c.bank_name || '';
            document.getElementById('editCardNotes').value = c.notes || '';
            openModal('editCardModal');
        } else {
            showToast(result.message || 'Failed to load card', 'error');
        }
    });
}

function updateCard() {
    var id = document.getElementById('editCardId').value;
    var data = {
        id: id,
        csrf_token: csrfToken,
        card_type: document.getElementById('editCardType').value,
        cardholder_name: document.getElementById('editCardholderName').value.trim(),
        card_number: document.getElementById('editCardNumber').value.replace(/\s/g, ''),
        expiry_month: document.getElementById('editExpiryMonth').value,
        expiry_year: document.getElementById('editExpiryYear').value,
        cvv: document.getElementById('editCvv').value,
        bank_name: document.getElementById('editBankName').value.trim(),
        card_network: document.getElementById('editCardNetwork').value,
        notes: document.getElementById('editCardNotes').value.trim()
    };

    var btn = document.getElementById('updateCardBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

    fetch('api/cards.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            closeModal('editCardModal');
            showToast('Card updated', 'success');
            loadCards();
        } else {
            document.getElementById('editCardFormAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + (result.message || 'Failed') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Update Card';
        }
    })
    .catch(function() {
        document.getElementById('editCardFormAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Network error</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Update Card';
    });
}

function deleteCard(id) {
    if (typeof showConfirmDialog === 'function') {
        showConfirmDialog('Move this card to trash? You can restore it later.', 'Delete Card', 'Delete', 'Cancel', function() {
            fetch('api/cards.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, csrf_token: csrfToken })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    showToast('Card moved to trash', 'success');
                    loadCards();
                } else {
                    showToast(result.message || 'Failed to delete', 'error');
                }
            });
        });
    }
}

function toggleCardFav(id, btn) {
    fetch('api/cards.php?action=toggle_favorite', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            loadCards();
        }
    });
}

function openViewCardModal(id) {
    document.getElementById('viewCardAlerts').innerHTML = '';
    viewCardNumberVisible = false;

    fetch('api/cards.php?action=get&id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            viewCardData = result.card;
            var c = result.card;
            var num = c.card_number_decrypted || '****';
            var masked = '**** **** **** ' + num.slice(-4);
            var typeLabel = c.card_type === 'debit' ? 'Debit' : 'Credit';
            var networkIcon = getNetworkIcon(c.card_network);
            var networkColor = getNetworkColor(c.card_network);

            document.getElementById('viewCardVisual').style.background = networkColor;
            document.getElementById('viewCardNumber').textContent = masked;
            document.getElementById('viewCardholderName').textContent = c.cardholder_name;
            document.getElementById('viewExpiry').textContent = ('0' + c.expiry_month).slice(-2) + '/' + c.expiry_year;
            document.getElementById('viewCvv').textContent = '***';
            document.getElementById('viewCardType').textContent = typeLabel;
            document.getElementById('viewCardNetwork').innerHTML = '<i class="' + networkIcon + '"></i> ' + (c.card_network || 'Unknown');
            document.getElementById('viewCardBank').textContent = c.bank_name || '-';
            document.getElementById('viewCardNotes').textContent = c.notes || '-';

            openModal('viewCardModal');
        } else {
            showToast(result.message || 'Failed to load card', 'error');
        }
    });
}

function toggleViewCardNumber() {
    if (!viewCardData) return;
    viewCardNumberVisible = !viewCardNumberVisible;
    var el = document.getElementById('viewCardNumber');
    var btn = document.getElementById('viewNumBtnText');
    if (viewCardNumberVisible) {
        el.textContent = viewCardData.card_number_decrypted || '****';
        btn.textContent = 'Hide Number';
    } else {
        el.textContent = '**** **** **** ' + (viewCardData.card_number_decrypted || '').slice(-4);
        btn.textContent = 'Show Number';
    }
}

function copyViewCardNumber() {
    if (viewCardData && viewCardData.card_number_decrypted) {
        navigator.clipboard.writeText(viewCardData.card_number_decrypted).then(function() {
            showToast('Card number copied', 'success');
        });
    }
}

var viewCvvVisible = false;

function toggleViewCardCvv() {
    if (!viewCardData) return;
    viewCvvVisible = !viewCvvVisible;
    var el = document.getElementById('viewCvv');
    var btn = document.getElementById('viewCvvBtnText');
    if (viewCvvVisible) {
        el.textContent = viewCardData.cvv_decrypted || '***';
        btn.textContent = 'Hide CVV';
    } else {
        el.textContent = '***';
        btn.textContent = 'Show CVV';
    }
}

function copyViewCardCvv() {
    if (viewCardData && viewCardData.cvv_decrypted) {
        navigator.clipboard.writeText(viewCardData.cvv_decrypted).then(function() {
            showToast('CVV copied', 'success');
        });
    }
}

function toggleExportMenu() {
    var menu = document.getElementById('exportMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', function(e) {
    var menu = document.getElementById('exportMenu');
    if (menu && !e.target.closest('.dropdown')) {
        menu.style.display = 'none';
    }
});

function exportCards(format) {
    window.location.href = 'api/cards.php?action=export_' + format;
}

function getNetworkIcon(network) {
    switch (network) {
        case 'visa': return 'fab fa-cc-visa';
        case 'mastercard': return 'fab fa-cc-mastercard';
        case 'amex': return 'fab fa-cc-amex';
        case 'discover': return 'fab fa-cc-discover';
        case 'diners': return 'fab fa-cc-diners-club';
        case 'maestro': return 'fab fa-cc-stripe';
        case 'rupay': return 'fas fa-credit-card';
        default: return 'fas fa-credit-card';
    }
}

function getNetworkColor(network) {
    switch (network) {
        case 'visa': return 'linear-gradient(135deg, #1a1f71, #1a3f8f)';
        case 'mastercard': return 'linear-gradient(135deg, #1a1f71, #f79e1b)';
        case 'amex': return 'linear-gradient(135deg, #2e77bc, #1a4d7a)';
        case 'discover': return 'linear-gradient(135deg, #ff6000, #333)';
        case 'diners': return 'linear-gradient(135deg, #888, #555)';
        case 'rupay': return 'linear-gradient(135deg, #097a3f, #e6743b)';
        default: return 'linear-gradient(135deg, var(--primary-dark, #1a1f71), var(--primary, #3b82f6))';
    }
}

function detectNetworkFromNumber(num) {
    var s = num.replace(/\s/g, '');
    if (!s) return '';
    var first = s[0];
    var firstTwo = s.substring(0, 2);
    var firstFour = s.substring(0, 4);
    if (first == '4') return 'visa';
    if (['51','52','53','54','55'].indexOf(firstTwo) >= 0 || (parseInt(firstFour) >= 2221 && parseInt(firstFour) <= 2720)) return 'mastercard';
    if (['34','37'].indexOf(firstTwo) >= 0) return 'amex';
    if (firstFour == '6011' || (parseInt(firstFour) >= 6221 && parseInt(firstFour) <= 6229) || (parseInt(firstFour) >= 6440 && parseInt(firstFour) <= 6499) || firstTwo == '65') return 'discover';
    if (['30','36','38','39'].indexOf(firstTwo) >= 0) return 'diners';
    if (['5018','5020','5038','5893','6304','6759','6761','6762','6763'].indexOf(firstFour) >= 0) return 'maestro';
    if (firstFour == '6060' || firstFour == '6070' || firstTwo == '81' || firstTwo == '82' || ['5085','3531'].indexOf(firstFour) >= 0) return 'rupay';
    return 'unknown';
}

document.addEventListener('input', function(e) {
    if (e.target.id === 'addCardNumber') {
        var detected = detectNetworkFromNumber(e.target.value);
        var sel = document.getElementById('addCardNetwork');
        if (detected && !sel.value) {
            sel.value = detected;
        } else if (!detected) {
            sel.value = '';
        }
    }
});

var esc = function(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
};
</script>

<?php include 'includes/footer.php'; ?>
