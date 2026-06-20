<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!empty($email) && !empty($password)) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_admin = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = (int)$user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_display_name'] = $user['display_name'] ?? $user['username'];
            $_SESSION['admin_email'] = $user['email'] ?? '';
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid credentials or not an admin';
    } else {
        $error = 'Please fill in all fields';
    }
}
$pageTitle = 'Admin Login';
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - RS PAASWORD MANAGER</title>
  <link rel="icon" type="image/svg+xml" href="../assets/default-favicon.svg">
  <link rel="alternate icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>🔐</text></svg>">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body { display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg-main); }
    .admin-login-box { width:100%;max-width:400px;padding:32px;background:var(--card-bg);border-radius:var(--radius-lg);box-shadow:var(--shadow-lg); }
    .admin-login-box h1 { font-size:1.5rem;margin-bottom:4px; }
    .admin-login-box p { color:var(--text-muted);margin-bottom:24px;font-size:0.875rem; }
    .alert { padding:10px 14px;border-radius:var(--radius-md);margin-bottom:16px;font-size:0.85rem;background:rgba(239,68,68,0.15);color:#f87171; }
  </style>
</head>
<body>
  <div class="admin-login-box">
    <h1><i class="fas fa-shield-alt" style="color:var(--accent)"></i> Admin Panel</h1>
    <p>Sign in with your admin account</p>
    <?php if ($error): ?><div class="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" placeholder="admin@email.com" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input" placeholder="Enter password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:0.8rem;color:var(--text-muted)">
      <a href="../login.php">Back to User Login</a>
    </div>
  </div>
</body>
</html>
