<?php

session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';

$pageTitle = 'Forgot Password';
$bodyClass = 'auth-page';
include 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-bg-shapes">
        <div class="auth-shape auth-shape-1"></div>
        <div class="auth-shape auth-shape-2"></div>
        <div class="auth-shape auth-shape-3"></div>
    </div>
    <div class="auth-container">
        <div class="auth-card-glass">
            <div class="auth-card-glow"></div>
            <div class="auth-card-inner">
                <button class="auth-theme-toggle" id="themeToggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>

                <div class="auth-header">
                    <div class="brand-icon-wrap">
                        <div class="brand-icon-ring"></div>
                        <div class="brand-icon">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                    </div>
                    <h1>Forgot Password</h1>
                    <p id="forgotSubtitle">Enter your email to generate a reset link</p>
                </div>

                <div id="forgotAlerts"></div>

                <form id="forgotForm" method="POST" action="api/auth.php?action=forgot_password">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-input form-input-icon" placeholder="your@email.com" required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gradient btn-block" id="forgotBtn">
                        <span class="btn-text">Generate Reset Link</span>
                        <span class="spinner" style="display:none;width:20px;height:20px;border-width:2px"></span>
                    </button>
                </form>

                <div class="auth-footer">
                    <span>Remember your password?</span> <a href="login.php">Sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Link Modal -->
<div class="modal-overlay" id="resetLinkModal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3><i class="fas fa-link" style="color:var(--primary)"></i> Password Reset Link</h3>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:12px;font-size:0.85rem;color:var(--text-muted)">
                Copy this link or open it in a new tab to reset your password. This link expires in 1 hour.
            </p>
            <div style="background:var(--card-glass);border:1px solid var(--border-soft);border-radius:var(--radius-sm);padding:12px;margin-bottom:16px;word-break:break-all">
                <code id="resetLinkDisplay" style="font-size:0.8rem"></code>
            </div>
            <div style="display:flex;gap:10px;justify-content:center">
                <button class="btn btn-gradient" onclick="openResetLink()">
                    <i class="fas fa-external-link-alt"></i> Open
                </button>
                <button class="btn btn-outline" onclick="copyResetLink()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <a href="login.php" class="btn btn-ghost">Back to Login</a>
        </div>
    </div>
</div>

<script>
var resetLink = '';

document.getElementById('forgotForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('forgotBtn');
    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Generating...';
    btn.querySelector('.spinner').style.display = 'inline-block';

    fetch('api/auth.php?action=forgot_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: document.getElementById('email').value })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Generate Reset Link';
        btn.querySelector('.spinner').style.display = 'none';

        if (result.success && result.reset_link) {
            resetLink = result.reset_link;
            document.getElementById('resetLinkDisplay').textContent = resetLink;
            document.getElementById('forgotForm').style.display = 'none';
            document.getElementById('forgotSubtitle').textContent = 'Reset link generated successfully';
            openModal('resetLinkModal');
        } else {
            var alerts = document.getElementById('forgotAlerts');
            alerts.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> ' + result.message + '</div>';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Generate Reset Link';
        btn.querySelector('.spinner').style.display = 'none';
    });
});

function openResetLink() {
    if (resetLink) window.open(resetLink, '_blank');
}

function copyResetLink() {
    if (!resetLink) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(resetLink).then(function() {
            showToast('Link copied to clipboard', 'success');
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = resetLink;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Link copied to clipboard', 'success');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
