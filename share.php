<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';

session_start();

$token = trim($_GET['token'] ?? '');
$error = '';
$credential = null;
$websiteName = '';
$title = '';

if (empty($token)) {
    $error = 'Invalid share link.';
} else {
    $pdo = getDbConnection();

    // Verify token
    $stmt = $pdo->prepare(
        "SELECT sl.*, c.title, c.username, c.password_encrypted, c.notes, c.phone_encrypted, w.website_name, w.website_url
         FROM share_links sl
         JOIN credentials c ON sl.credential_id = c.id
         JOIN websites w ON c.website_id = w.id
         WHERE sl.token = :token AND sl.is_revoked = 0
         LIMIT 1"
    );
    $stmt->execute([':token' => $token]);
    $link = $stmt->fetch();

    if (!$link) {
        $error = 'This share link is invalid or has been revoked.';
    } elseif (strtotime($link['expires_at']) < time()) {
        $error = 'This share link has expired.';
    } elseif ((int)$link['current_views'] >= (int)$link['max_views']) {
        $error = 'This share link has reached its maximum number of views.';
    } else {
        // Increment view count
        $stmt = $pdo->prepare('UPDATE share_links SET current_views = current_views + 1 WHERE id = :id');
        $stmt->execute([':id' => $link['id']]);

        // Decrypt the password
        $encKey = getUserEncryptionKey((int)$link['user_id']);
        $password = '';
        if ($encKey && !empty($link['password_encrypted'])) {
            try {
                $password = decryptPassword($link['password_encrypted'], $encKey['key'], $encKey['iv']);
            } catch (Exception $e) {
                $password = '[Decryption failed]';
            }
        } else {
            $password = '[Key unavailable]';
        }

        // Decrypt phone
        $phone = '';
        if ($encKey && !empty($link['phone_encrypted'])) {
            try {
                $phone = decryptPassword($link['phone_encrypted'], $encKey['key'], $encKey['iv']);
            } catch (Exception $e) {}
        }

        $credential = [
            'username' => $link['username'],
            'password' => $password,
            'website_name' => $link['website_name'],
            'website_url' => $link['website_url'],
            'title' => $link['title'],
            'notes' => $link['notes'] ?? '',
            'phone' => $phone,
        ];
        $websiteName = $link['website_name'];
        $title = $link['title'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="rgb(10, 14, 28)">
    <meta name="color-scheme" content="dark">
    <title>Shared Credential</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
    (function() {
        var t = localStorage.getItem('pm_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
        var mc = document.querySelector('meta[name=theme-color]');
        var cs = document.querySelector('meta[name=color-scheme]');
        if (mc) mc.content = t === 'dark' ? 'rgb(10,14,28)' : 'rgb(248,250,252)';
        if (cs) cs.content = t;
    })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg-page-dark: linear-gradient(135deg, #0a0e1c 0%, #121828 50%, #1a1040 100%);
            --bg-page-light: linear-gradient(135deg, #f0f4ff 0%, #e8ecf8 50%, #f0e8ff 100%);
            --card-bg-dark: rgba(24, 31, 50, 0.95);
            --card-bg-light: rgba(255, 255, 255, 0.92);
            --text-dark: #f1f5f9;
            --text-light: #1e293b;
            --muted-dark: #94a3b8;
            --muted-light: #64748b;
            --label-dark: #64748b;
            --label-light: #94a3b8;
            --border-dark: rgba(255,255,255,0.1);
            --border-light: rgba(0,0,0,0.08);
            --field-bg-dark: rgba(255,255,255,0.06);
            --field-bg-light: rgba(0,0,0,0.03);
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-page-dark);
            padding: 20px;
            transition: background 0.3s ease;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: var(--bg-page-light);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        html[data-theme="light"] body { background: var(--bg-page-light); }
        html[data-theme="light"] body::before { opacity: 0; }
        .theme-toggle-share {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border-dark);
            color: var(--text-dark);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95rem;
            z-index: 2;
        }
        .theme-toggle-share:hover { background: rgba(255,255,255,0.15); transform: rotate(15deg); }
        html[data-theme="light"] .theme-toggle-share {
            background: rgba(0,0,0,0.05);
            border-color: var(--border-light);
            color: var(--text-light);
        }
        html[data-theme="light"] .theme-toggle-share:hover { background: rgba(0,0,0,0.1); }
        .share-card {
            position: relative;
            background: var(--card-bg-dark);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-dark);
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 25px 80px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        html[data-theme="light"] .share-card {
            background: var(--card-bg-light);
            border-color: var(--border-light);
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-dark);
        }
        html[data-theme="light"] .brand { border-bottom-color: var(--border-light); }
        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
        }
        .brand-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.02em;
            transition: color 0.3s;
        }
        html[data-theme="light"] .brand-text { color: var(--text-light); }
        .brand-text span { color: #6366f1; }
        .error-card {
            text-align: center;
            padding: 40px 20px;
        }
        .error-card .error-icon {
            width: 72px; height: 72px;
            background: rgba(239,68,68,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
            color: rgb(239,68,68);
        }
        .error-card h2 { color: var(--text-dark); margin-bottom: 8px; font-size: 1.3rem; transition: color 0.3s; }
        html[data-theme="light"] .error-card h2 { color: var(--text-light); }
        .error-card p { color: var(--muted-dark); font-size: 0.9rem; transition: color 0.3s; }
        html[data-theme="light"] .error-card p { color: var(--muted-light); }
        .cred-site {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }
        .cred-site .site-icon {
            width: 48px; height: 48px;
            background: rgba(99,102,241,0.12);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            color: #6366f1;
            flex-shrink: 0;
        }
        .cred-site h1 {
            font-size: 1.15rem; font-weight: 600; color: var(--text-dark);
            word-break: break-word;
            transition: color 0.3s;
        }
        html[data-theme="light"] .cred-site h1 { color: var(--text-light); }
        .cred-site p {
            font-size: 0.8rem; color: var(--muted-dark); margin-top: 2px;
            transition: color 0.3s;
        }
        html[data-theme="light"] .cred-site p { color: var(--muted-light); }
        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--label-dark);
            font-weight: 600;
            margin-bottom: 6px;
            transition: color 0.3s;
        }
        html[data-theme="light"] .field label { color: var(--label-light); }
        .field .value {
            background: var(--field-bg-dark);
            border: 1px solid var(--border-dark);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-family: 'SF Mono', 'Fira Code', monospace;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            word-break: break-all;
            transition: all 0.3s;
        }
        html[data-theme="light"] .field .value {
            background: var(--field-bg-light);
            border-color: var(--border-light);
            color: var(--text-light);
        }
        .field .value .actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .field .value .actions button {
            background: rgba(255,255,255,0.08);
            border: none;
            color: var(--muted-dark);
            width: 32px; height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        html[data-theme="light"] .field .value .actions button {
            background: rgba(0,0,0,0.05);
            color: var(--muted-light);
        }
        .field .value .actions button:hover {
            background: rgba(255,255,255,0.15);
            color: var(--text-dark);
        }
        html[data-theme="light"] .field .value .actions button:hover {
            background: rgba(0,0,0,0.1);
            color: var(--text-light);
        }
        .meta {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border-dark);
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--label-dark);
            flex-wrap: wrap;
            gap: 8px;
            transition: all 0.3s;
        }
        html[data-theme="light"] .meta { border-top-color: var(--border-light); color: var(--label-light); }
        .meta .badge {
            background: rgba(99,102,241,0.15);
            color: #818cf8;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(34,197,94,0.95);
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            z-index: 999;
        }
        @media (max-width: 500px) {
            .share-card { padding: 24px; }
        }
    </style>
</head>
<body>
    <div class="share-card">
        <button class="theme-toggle-share" id="shareThemeToggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="brand-text">RS PAASWORD MANAGER</div>
        </div>

        <?php if ($error): ?>
            <div class="error-card">
                <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h2>Link Unavailable</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php elseif ($credential): ?>
            <div class="cred-site">
                <div class="site-icon"><i class="fas fa-globe"></i></div>
                <div>
                    <h1><?php echo htmlspecialchars($credential['website_name']); ?></h1>
                    <p><?php echo htmlspecialchars($credential['title']); ?></p>
                </div>
            </div>

            <div class="field">
                <label><i class="fas fa-user" style="margin-right:4px"></i> Username</label>
                <div class="value">
                    <span id="shareUsername"><?php echo htmlspecialchars($credential['username']); ?></span>
                    <div class="actions">
                        <button onclick="copyText('shareUsername')" title="Copy"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>

            <div class="field">
                <label><i class="fas fa-lock" style="margin-right:4px"></i> Password</label>
                <div class="value">
                    <span id="sharePassword" style="font-family:monospace"><?php echo htmlspecialchars($credential['password']); ?></span>
                    <div class="actions">
                        <button onclick="togglePw(this)" title="Show/Hide"><i class="fas fa-eye"></i></button>
                        <button onclick="copyText('sharePassword')" title="Copy"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>

            <?php if (!empty($credential['phone'])): ?>
            <div class="field">
                <label><i class="fas fa-phone" style="margin-right:4px"></i> Mobile Number</label>
                <div class="value" style="font-family:inherit;font-size:0.95rem;word-break:break-word;overflow-wrap:break-word">
                    <span id="sharePhone" class="phone-display" style="display:inline"><?php echo htmlspecialchars($credential['phone']); ?></span>
                    <div class="actions">
                        <button onclick="copyText('sharePhone')" title="Copy"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($credential['notes'])): ?>
            <div class="field">
                <label><i class="fas fa-sticky-note" style="margin-right:4px"></i> Notes</label>
                <div class="value" style="font-family:inherit;font-size:0.85rem;word-break:break-word">
                    <?php echo htmlspecialchars($credential['notes']); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($credential['website_url']): ?>
            <div style="margin-top:12px">
                <a href="<?php echo htmlspecialchars($credential['website_url']); ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;background:rgba(99,102,241,0.12);color:#818cf8;text-decoration:none;padding:8px 16px;border-radius:10px;font-size:0.85rem;font-weight:500;transition:all 0.2s" onmouseover="this.style.background='rgba(99,102,241,0.2)'" onmouseout="this.style.background='rgba(99,102,241,0.12)'">
                    <i class="fas fa-external-link-alt"></i> Visit Website
                </a>
            </div>
            <?php endif; ?>

            <div class="meta">
                <span>Shared via RS PAASWORD MANAGER</span>
                <span class="badge">Secure Link</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    (function() {
        var t = localStorage.getItem('pm_theme') || 'dark';
        var btn = document.getElementById('shareThemeToggle');
        if (btn) {
            var i = btn.querySelector('i');
            if (i) i.className = t === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        }
    })();

    document.getElementById('shareThemeToggle').addEventListener('click', function() {
        var html = document.documentElement;
        var curr = html.getAttribute('data-theme') || 'dark';
        var next = curr === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('pm_theme', next);
        var i = this.querySelector('i');
        i.className = next === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        var mc = document.querySelector('meta[name=theme-color]');
        if (mc) mc.content = next === 'dark' ? 'rgb(10,14,28)' : 'rgb(248,250,252)';
    });

    function togglePw(btn) {
        var span = document.getElementById('sharePassword');
        if (span.getAttribute('data-hidden') === 'true') {
            span.removeAttribute('data-hidden');
            span.style.webkitTextSecurity = 'none';
            btn.innerHTML = '<i class="fas fa-eye"></i>';
            span.textContent = span.getAttribute('data-original') || span.textContent;
        } else {
            var orig = span.textContent;
            span.setAttribute('data-original', orig);
            span.setAttribute('data-hidden', 'true');
            span.style.webkitTextSecurity = 'disc';
            btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
        }
    }

    function copyText(id) {
        var el = document.getElementById(id);
        var text = el.getAttribute('data-original') || el.textContent;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() { showToast('Copied!'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); document.body.removeChild(ta);
            showToast('Copied!');
        }
    }

    function showToast(msg) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.style.display = 'block';
        setTimeout(function() { t.style.display = 'none'; }, 2000);
    }
    </script>
</body>
</html>
