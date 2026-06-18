<?php

session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: login.php');
    exit;
}

require_once 'config/auth.php';

$userId = verifyResetToken($token);
if (!$userId) {
    $invalidToken = true;
} else {
    $invalidToken = false;
}

$pageTitle = 'Reset Password';
$bodyClass = 'auth-page';
include 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <button class="auth-theme-toggle" id="themeToggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
                <div class="brand-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1>Reset Password</h1>
                <p>Choose a new password for your account</p>
            </div>

            <?php if ($invalidToken): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid or expired reset token. <a href="forgot-password.php">Request a new one</a>.</div>
            <?php else: ?>
                <div id="resetAlerts"></div>

                <form id="resetForm" method="POST" action="api/auth.php?action=reset_password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-input" placeholder="Min 8 characters" required minlength="8" data-strength="true">
                            <div class="input-group-append">
                                <button type="button" class="input-group-btn pw-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar"><div class="strength-bar-fill" id="resetStrengthBar"></div></div>
                            <span class="strength-label" id="resetStrengthLabel"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Repeat password" required>
                            <div class="input-group-append">
                                <button type="button" class="input-group-btn pw-toggle" tabindex="-1"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="resetBtn">
                        <span class="btn-text">Reset Password</span>
                        <span class="spinner" style="display:none;width:20px;height:20px;border-width:2px"></span>
                    </button>
                </form>

                <div class="auth-footer">
                    <a href="login.php">Back to login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('password') && document.getElementById('password').addEventListener('input', function() {
    if (typeof updateStrengthDisplay === 'function') {
        updateStrengthDisplay(this.value, this);
    }
});

document.getElementById('resetForm') && document.getElementById('resetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var pw = document.getElementById('password').value;
    var cpw = document.getElementById('confirm_password').value;
    var btn = document.getElementById('resetBtn');
    var alerts = document.getElementById('resetAlerts');
    alerts.innerHTML = '';

    if (pw !== cpw) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Passwords do not match</div>';
        return;
    }
    if (pw.length < 8) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters</div>';
        return;
    }

    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Resetting...';
    btn.querySelector('.spinner').style.display = 'inline-block';

    fetch('api/auth.php?action=reset_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: document.querySelector('input[name="token"]').value, password: pw })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            window.location.href = 'login.php?reset=1';
        } else {
            alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + result.message + '</div>';
            btn.disabled = false;
            btn.querySelector('.btn-text').textContent = 'Reset Password';
            btn.querySelector('.spinner').style.display = 'none';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Reset Password';
        btn.querySelector('.spinner').style.display = 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
