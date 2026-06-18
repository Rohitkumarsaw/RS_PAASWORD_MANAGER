<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

session_start();
requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();

$pageTitle = 'Password Health';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-heartbeat" style="color:var(--primary)"></i> Password Health</h1>
                    <p>Analyze the strength and security of all your stored passwords</p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" id="scanBtn" onclick="runHealthScan()">
                        <i class="fas fa-sync-alt"></i> Scan Now
                    </button>
                </div>
            </div>

            <!-- Score Ring + Summary -->
            <div class="health-summary" id="healthSummary" style="display:none">
                <div class="health-score-card">
                    <div class="score-ring-container">
                        <svg class="score-ring" viewBox="0 0 120 120">
                            <circle class="score-ring-bg" cx="60" cy="60" r="54" />
                            <circle class="score-ring-fill" id="scoreRingFill" cx="60" cy="60" r="54"
                                    stroke-dasharray="339.292" stroke-dashoffset="339.292"
                                    stroke-linecap="round" />
                        </svg>
                        <div class="score-ring-text">
                            <span class="score-number" id="overallScore">0</span>
                            <span class="score-label">Score</span>
                        </div>
                    </div>
                    <div class="score-grade" id="scoreGrade">N/A</div>
                    <div class="score-message" id="scoreMessage"></div>
                </div>
                <div class="health-stats-grid">
                    <div class="health-stat-card good">
                        <div class="health-stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="health-stat-body">
                            <span class="health-stat-value" id="statStrong">0</span>
                            <span class="health-stat-label">Strong</span>
                        </div>
                    </div>
                    <div class="health-stat-card warning">
                        <div class="health-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="health-stat-body">
                            <span class="health-stat-value" id="statModerate">0</span>
                            <span class="health-stat-label">Moderate</span>
                        </div>
                    </div>
                    <div class="health-stat-card danger">
                        <div class="health-stat-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="health-stat-body">
                            <span class="health-stat-value" id="statWeak">0</span>
                            <span class="health-stat-label">Weak</span>
                        </div>
                    </div>
                    <div class="health-stat-card info">
                        <div class="health-stat-icon"><i class="fas fa-history"></i></div>
                        <div class="health-stat-body">
                            <span class="health-stat-value" id="statOld">0</span>
                            <span class="health-stat-label">Old (>1yr)</span>
                        </div>
                    </div>
                    <div class="health-stat-card danger">
                        <div class="health-stat-icon"><i class="fas fa-copy"></i></div>
                        <div class="health-stat-body">
                            <span class="health-stat-value" id="statReused">0</span>
                            <span class="health-stat-label">Reused</span>
                        </div>
                    </div>
                    <div class="health-stat-card total">
                        <div class="health-stat-icon"><i class="fas fa-key"></i></div>
                        <div class="health-stat-body">
                            <span class="health-stat-value" id="statTotal">0</span>
                            <span class="health-stat-label">Total</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div class="health-loading" id="healthLoading">
                <div class="loading-spinner"></div>
                <p>Analyzing your passwords...</p>
            </div>

            <!-- Empty -->
            <div class="health-empty" id="healthEmpty" style="display:none">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-shield-halved" style="font-size:3rem;color:var(--primary)"></i></div>
                    <h3>No Passwords to Analyze</h3>
                    <p class="text-muted">Add some passwords to your vault first.</p>
                    <a href="vault.php" class="btn btn-primary" style="margin-top:12px"><i class="fas fa-plus"></i> Add Password</a>
                </div>
            </div>

            <!-- Alert -->
            <div id="healthAlert" style="display:none"></div>

            <!-- Reused Groups -->
            <div class="card" id="reusedCard" style="display:none;margin-top:20px">
                <div class="card-header">
                    <h3><i class="fas fa-copy" style="color:var(--danger)"></i> Reused Passwords</h3>
                    <p class="text-muted">These passwords are used on multiple accounts</p>
                </div>
                <div class="card-body" id="reusedGroups"></div>
            </div>

            <!-- Detail Table -->
            <div class="card" id="detailCard" style="display:none;margin-top:20px">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Password Analysis</h3>
                    <p class="text-muted">Detailed breakdown of each password</p>
                </div>
                <div class="card-body" style="padding:0">
                    <div class="health-table-wrap">
                        <table class="health-table">
                            <thead>
                                <tr>
                                    <th>Website</th>
                                    <th>Username</th>
                                    <th>Score</th>
                                    <th>Length</th>
                                    <th>Complexity</th>
                                    <th>Age</th>
                                    <th>Issues</th>
                                </tr>
                            </thead>
                            <tbody id="healthTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
async function runHealthScan() {
    const btn = document.getElementById('scanBtn');
    const loading = document.getElementById('healthLoading');
    const summary = document.getElementById('healthSummary');
    const empty = document.getElementById('healthEmpty');
    const alert = document.getElementById('healthAlert');
    const detailCard = document.getElementById('detailCard');
    const reusedCard = document.getElementById('reusedCard');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
    summary.style.display = 'none';
    detailCard.style.display = 'none';
    reusedCard.style.display = 'none';
    alert.style.display = 'none';
    empty.style.display = 'none';
    loading.style.display = 'block';

    try {
        const resp = await fetch('api/health.php?action=scan', {
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await resp.json();
        loading.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Scan Now';

        if (!data.success) {
            alert.style.display = 'block';
            alert.className = 'alert alert-danger';
            alert.innerHTML = data.message || 'Scan failed';
            return;
        }

        if (data.total_passwords === 0) {
            empty.style.display = 'block';
            return;
        }

        // Score ring
        const score = data.overall_score;
        const circumference = 339.292;
        const offset = circumference - (score / 100) * circumference;
        document.getElementById('scoreRingFill').style.strokeDashoffset = offset;
        document.getElementById('overallScore').textContent = score;

        // Grade
        let grade, gradeColor, message;
        if (score >= 80) { grade = 'A'; gradeColor = 'var(--success)'; message = 'Excellent — your passwords are in great shape!'; }
        else if (score >= 60) { grade = 'B'; gradeColor = 'var(--primary)'; message = 'Good — a few improvements would help.'; }
        else if (score >= 40) { grade = 'C'; gradeColor = 'rgb(251,191,36)'; message = 'Fair — several passwords need attention.'; }
        else if (score >= 20) { grade = 'D'; gradeColor = 'rgb(251,146,60)'; message = 'Poor — most passwords need improvement.'; }
        else { grade = 'F'; gradeColor = 'var(--danger)'; message = 'Critical — your passwords are at high risk!'; }

        const gradeEl = document.getElementById('scoreGrade');
        gradeEl.textContent = grade;
        gradeEl.style.color = gradeColor;
        document.getElementById('scoreRingFill').style.stroke = gradeColor;
        document.getElementById('scoreMessage').textContent = message;

        // Stats
        document.getElementById('statStrong').textContent = data.category_breakdown.strong;
        document.getElementById('statModerate').textContent = data.category_breakdown.moderate;
        document.getElementById('statWeak').textContent = data.category_breakdown.weak;
        document.getElementById('statOld').textContent = data.old_passwords;
        document.getElementById('statReused').textContent = data.reused_passwords;
        document.getElementById('statTotal').textContent = data.total_passwords;

        summary.style.display = 'grid';

        // Reused groups
        if (data.reused_groups && data.reused_groups.length > 0) {
            reusedCard.style.display = 'block';
            const container = document.getElementById('reusedGroups');
            container.innerHTML = '';
            data.reused_groups.forEach(group => {
                const div = document.createElement('div');
                div.className = 'reused-group';
                div.innerHTML = `
                    <div class="reused-group-header">
                        <code class="reused-pw">${'*'.repeat(Math.min(group.password.length, 20))}</code>
                        <span class="reused-count">${group.count} accounts</span>
                    </div>
                    <ul class="reused-entries">
                        ${group.entries.map(e => `<li>${escapeHtml(e)}</li>`).join('')}
                    </ul>
                `;
                container.appendChild(div);
            });
        }

        // Table
        detailCard.style.display = 'block';
        const tbody = document.getElementById('healthTableBody');
        tbody.innerHTML = '';

        data.details.forEach(entry => {
            const scoreColor = entry.score >= 80 ? 'var(--success)' : entry.score >= 60 ? 'var(--primary)' : entry.score >= 40 ? 'rgb(251,191,36)' : 'var(--danger)';
            const complexityIcons = [];
            if (entry.has_upper) complexityIcons.push('<span class="complexity-dot yes" title="Uppercase">A</span>');
            else complexityIcons.push('<span class="complexity-dot no" title="Missing uppercase">A</span>');
            if (entry.has_lower) complexityIcons.push('<span class="complexity-dot yes" title="Lowercase">a</span>');
            else complexityIcons.push('<span class="complexity-dot no" title="Missing lowercase">a</span>');
            if (entry.has_digit) complexityIcons.push('<span class="complexity-dot yes" title="Numbers">1</span>');
            else complexityIcons.push('<span class="complexity-dot no" title="Missing numbers">1</span>');
            if (entry.has_special) complexityIcons.push('<span class="complexity-dot yes" title="Special">*</span>');
            else complexityIcons.push('<span class="complexity-dot no" title="Missing special">*</span>');

            let ageText = 'N/A';
            let ageClass = '';
            if (entry.age_days !== null) {
                ageText = entry.age_days + 'd';
                if (entry.age_days > 365) { ageText = Math.floor(entry.age_days / 365) + 'yr'; ageClass = 'age-old'; }
                else if (entry.age_days > 180) ageClass = 'age-warn';
                else ageClass = 'age-fresh';
            }

            const issuesHtml = entry.issues.length > 0
                ? entry.issues.map(i => `<span class="issue-tag">${escapeHtml(i)}</span>`).join(' ')
                : '<span class="text-muted" style="font-size:0.8rem">None</span>';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="health-website">
                    <i class="fas fa-globe" style="color:var(--primary);width:16px;text-align:center"></i>
                    ${escapeHtml(entry.website)}
                </td>
                <td class="health-username">${escapeHtml(entry.username)}</td>
                <td>
                    <span class="score-badge" style="background:${scoreColor}20;color:${scoreColor}">
                        ${entry.score}
                    </span>
                </td>
                <td>
                    <span class="length-text ${entry.length < 8 ? 'length-bad' : entry.length < 12 ? 'length-ok' : 'length-good'}">
                        ${entry.length}
                    </span>
                </td>
                <td class="complexity-cell">${complexityIcons.join(' ')}</td>
                <td><span class="age-text ${ageClass}">${ageText}</span></td>
                <td class="issues-cell">${issuesHtml}</td>
            `;
            tbody.appendChild(row);
        });

        // Scroll to results
        summary.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
        loading.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Scan Now';
        alert.style.display = 'block';
        alert.className = 'alert alert-danger';
        alert.innerHTML = 'Network error: ' + err.message;
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Auto-run on load
document.addEventListener('DOMContentLoaded', runHealthScan);
</script>

<?php include 'includes/footer.php'; ?>
