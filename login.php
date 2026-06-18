<?php

session_start();
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';

$pageTitle = 'Login';
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
                    <h1 id="authTitle">Welcome back</h1>
                    <p id="authSubtitle">Sign in to your <strong>RS PAASWORD MANAGER</strong></p>
                </div>

                <div id="alertsContainer">
                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Account created successfully. Please log in.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['timeout'])): ?>
                        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Session expired. Please log in again.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['reset'])): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Password reset successful. Please log in.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Step 1: Email & Password -->
                <form id="loginForm" method="POST" action="api/auth.php?action=login" style="display:block">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-input form-input-icon" placeholder="your@email.com" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-field-wrap">
                            <span class="input-field-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-input form-input-icon" placeholder="Enter your password" required>
                            <button type="button" class="input-field-toggle pw-toggle" tabindex="-1" onclick="togglePwField('password', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-options">
                        <label class="form-checkbox">
                            <input type="checkbox" name="remember" value="1">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="forgot-password.php" class="form-forgot">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-gradient btn-block" id="loginBtn">
                        <span class="btn-text">Sign In</span>
                        <span class="spinner" style="display:none;width:20px;height:20px;border-width:2px"></span>
                    </button>
                </form>

                <!-- Step 2: 2FA Code -->
                <form id="twofaForm" style="display:none">
                    <input type="hidden" id="twofaTempToken" name="temp_token" value="">
                    <div class="twofa-step">
                        <div class="twofa-icon-wrap">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <h3>Two-Factor Authentication</h3>
                        <p>Enter the 6-digit code from your authenticator app</p>
                        <div id="twofaAlerts" style="margin-bottom:12px"></div>
                        <div class="twofa-input-wrap">
                            <input type="text" id="twofaCode" name="code" class="twofa-input" placeholder="000000" required autocomplete="off" inputmode="numeric" pattern="[0-9]*" maxlength="6">
                        </div>
                        <button type="submit" class="btn btn-gradient btn-block" id="twofaBtn">
                            <span class="btn-text">Verify</span>
                            <span class="spinner" style="display:none;width:20px;height:20px;border-width:2px"></span>
                        </button>
                        <a href="#" id="backToLogin" class="twofa-back">Back to login</a>
                    </div>
                </form>

                <div class="auth-footer">
                    <span>Don't have an account?</span> <a href="register.php">Create one</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var tempToken = '';

document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('loginBtn');
    var formData = new FormData(this);
    var data = {};
    formData.forEach(function(value, key) { data[key] = value; });

    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Signing in...';
    btn.querySelector('.spinner').style.display = 'inline-block';

    fetch('api/auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            if (result.require_2fa) {
                tempToken = result.temp_token;
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('twofaForm').style.display = 'block';
                document.getElementById('twofaTempToken').value = tempToken;
                document.getElementById('authTitle').textContent = '2FA Required';
                document.getElementById('authSubtitle').textContent = 'Enter the code from your authenticator app';
                document.getElementById('twofaCode').value = '';
                document.getElementById('twofaCode').focus();
                btn.disabled = false;
                btn.querySelector('.btn-text').textContent = 'Sign In';
                btn.querySelector('.spinner').style.display = 'none';
            } else {
                window.location.href = result.redirect || 'dashboard.php';
            }
        } else {
            var container = document.getElementById('alertsContainer');
            var existing = document.querySelector('.alert-dynamic');
            if (existing) existing.remove();
            var alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dynamic';
            alert.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + result.message;
            container.insertBefore(alert, container.firstChild);
            btn.disabled = false;
            btn.querySelector('.btn-text').textContent = 'Sign In';
            btn.querySelector('.spinner').style.display = 'none';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Sign In';
        btn.querySelector('.spinner').style.display = 'none';
    });
});

document.getElementById('twofaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('twofaBtn');
    var code = document.getElementById('twofaCode').value;

    if (!code || code.length !== 6) {
        document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Enter a valid 6-digit code</div>';
        return;
    }

    btn.disabled = true;
    btn.querySelector('.btn-text').textContent = 'Verifying...';
    btn.querySelector('.spinner').style.display = 'inline-block';
    document.getElementById('twofaAlerts').innerHTML = '';

    fetch('api/auth.php?action=verify_2fa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code, temp_token: tempToken })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            window.location.href = result.redirect || 'dashboard.php';
        } else {
            document.getElementById('twofaAlerts').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + result.message + '</div>';
            if (result.redirect) {
                setTimeout(function() { window.location.href = result.redirect; }, 2000);
            }
            btn.disabled = false;
            btn.querySelector('.btn-text').textContent = 'Verify';
            btn.querySelector('.spinner').style.display = 'none';
            document.getElementById('twofaCode').value = '';
            document.getElementById('twofaCode').focus();
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Verify';
        btn.querySelector('.spinner').style.display = 'none';
    });
});

document.getElementById('backToLogin').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('twofaForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('authTitle').textContent = 'Welcome back';
    document.getElementById('authSubtitle').textContent = 'Sign in to your <strong>RS PAASWORD MANAGER</strong>';
    document.getElementById('twofaAlerts').innerHTML = '';
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
