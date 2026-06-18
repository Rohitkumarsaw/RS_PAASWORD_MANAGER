<?php

session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';

$pageTitle = 'Register';
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
                    <h1>Create Account</h1>
                    <p>Set up your secure <strong>RS PAASWORD MANAGER</strong></p>
                </div>

                <div id="registerAlerts"></div>

                <form id="registerForm" method="POST" action="api/auth.php?action=register">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="username" name="username" class="form-input form-input-icon" placeholder="Choose a username" required minlength="3" maxlength="50" pattern="^[a-zA-Z0-9_]+$">
                        </div>
                        <div class="form-hint">Letters, numbers, and underscores (3-50 chars)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-input form-input-icon" placeholder="your@email.com" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Account Password</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-input form-input-icon" placeholder="Min 8 characters" required minlength="8" data-strength="true">
                            <button type="button" class="input-field-toggle pw-toggle" tabindex="-1" onclick="togglePwField('password', this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar"><div class="strength-bar-fill" id="registerStrengthBar"></div></div>
                            <span class="strength-label" id="registerStrengthLabel"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="master_password">Master Password</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-key"></i></span>
                            <input type="password" id="master_password" name="master_password" class="form-input form-input-icon" placeholder="Your master encryption key" required minlength="8">
                            <button type="button" class="input-field-toggle pw-toggle" tabindex="-1" onclick="togglePwField('master_password', this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="form-hint">Used to encrypt your data. <strong>Do not forget this!</strong></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Account Password</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-check-circle"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input form-input-icon" placeholder="Repeat password" required>
                            <button type="button" class="input-field-toggle pw-toggle" tabindex="-1" onclick="togglePwField('confirm_password', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-gradient btn-block" id="registerBtn">
                        <span class="btn-text">Create Account</span>
                        <span class="spinner" style="display:none;width:20px;height:20px;border-width:2px"></span>
                    </button>
                </form>

                <div class="auth-footer">
                    <span>Already have an account?</span> <a href="login.php">Sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('password').addEventListener('input', function() {
    if (typeof updateStrengthDisplay === 'function') {
        updateStrengthDisplay(this.value, this);
    }
});

document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('registerBtn');
    var pw = document.getElementById('password').value;
    var cpw = document.getElementById('confirm_password').value;
    var mpw = document.getElementById('master_password').value;

    var alerts = document.getElementById('registerAlerts');
    alerts.innerHTML = '';

    if (pw !== cpw) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Passwords do not match</div>';
        return;
    }
    if (pw.length < 8) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters</div>';
        return;
    }
    if (mpw.length < 8) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Master password must be at least 8 characters</div>';
        return;
    }

    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Creating account...';
    btn.querySelector('.spinner').style.display = 'inline-block';

    var data = {
        username: document.getElementById('username').value,
        email: document.getElementById('email').value,
        password: pw,
        master_password: mpw
    };

    fetch('api/auth.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            window.location.href = 'login.php?registered=1';
        } else {
            alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + result.message + '</div>';
            btn.disabled = false;
            btn.querySelector('.btn-text').textContent = 'Create Account';
            btn.querySelector('.spinner').style.display = 'none';
        }
    })
    .catch(function(err) {
        alerts.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.</div>';
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Create Account';
        btn.querySelector('.spinner').style.display = 'none';
    });
});

function togglePwField(id, btn) {
    var input = document.getElementById(id);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
