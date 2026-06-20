<?php

function getPasswordCount(int $userId): int {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM password_entries WHERE user_id = :uid AND is_archived = 0');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log('Failed to get password count: ' . $e->getMessage());
        return 0;
    }
}

function getCategoryCount(int $userId): int {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM categories WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log('Failed to get category count: ' . $e->getMessage());
        return 0;
    }
}

function getWeakPasswordCount(int $userId): int {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM password_entries WHERE user_id = :uid AND is_archived = 0');
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log('Failed to count weak passwords: ' . $e->getMessage());
        return 0;
    }
}

function getRecentEntries(int $userId, int $limit = 5): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT pe.*, c.name as category_name
             FROM password_entries pe
             LEFT JOIN categories c ON pe.category_id = c.id
             WHERE pe.user_id = :uid AND pe.is_archived = 0
             ORDER BY pe.updated_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get recent entries: ' . $e->getMessage());
        return [];
    }
}

function getEntriesByCategory(int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT c.name, c.icon, COUNT(pe.id) as count
             FROM categories c
             LEFT JOIN password_entries pe ON c.id = pe.category_id AND pe.is_archived = 0
             WHERE c.user_id = :uid
             GROUP BY c.id, c.name, c.icon
             ORDER BY count DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get entries by category: ' . $e->getMessage());
        return [];
    }
}

function getExpiringPasswords(int $userId, int $days = 30): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT pe.*, c.name as category_name
             FROM password_entries pe
             LEFT JOIN categories c ON pe.category_id = c.id
             WHERE pe.user_id = :uid
             AND pe.expiry_date IS NOT NULL
             AND pe.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
             AND pe.expiry_date >= CURDATE()
             AND pe.is_archived = 0
             ORDER BY pe.expiry_date ASC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get expiring passwords: ' . $e->getMessage());
        return [];
    }
}

function getRecentActivity(int $userId, int $limit = 10): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT action, details, created_at FROM activity_logs
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get recent activity: ' . $e->getMessage());
        return [];
    }
}

function getUserCategories(int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, name, icon FROM categories WHERE user_id = :uid ORDER BY name ASC');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get categories: ' . $e->getMessage());
        return [];
    }
}

function getFilteredEntries(int $userId, string $search = '', int $categoryId = 0, string $filter = ''): array {
    try {
        $pdo = getDbConnection();
        $where = 'pe.user_id = :uid AND pe.is_archived = 0';
        $params = [':uid' => $userId];
        if (!empty($search)) {
            $where .= ' AND (pe.title LIKE :search OR pe.name LIKE :search OR pe.username LIKE :search OR pe.url LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where .= ' AND pe.category_id = :cat';
            $params[':cat'] = $categoryId;
        }
        if ($filter === 'favorites') {
            $where .= ' AND pe.is_favorite = 1';
        }
        $stmt = $pdo->prepare(
            "SELECT pe.*, c.name as category_name
             FROM password_entries pe
             LEFT JOIN categories c ON pe.category_id = c.id
             WHERE $where
             ORDER BY pe.is_favorite DESC, pe.updated_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get filtered entries: ' . $e->getMessage());
        return [];
    }
}

function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) { return 'Just now'; }
    if ($diff < 3600) { return floor($diff / 60) . 'm ago'; }
    if ($diff < 86400) { return floor($diff / 3600) . 'h ago'; }
    if ($diff < 604800) { return floor($diff / 86400) . 'd ago'; }
    return date('M j, Y', $timestamp);
}

function getPasswordStrengthLabel(int $score): string {
    if ($score < 30) { return 'Very Weak'; }
    if ($score < 50) { return 'Weak'; }
    if ($score < 70) { return 'Fair'; }
    if ($score < 90) { return 'Strong'; }
    return 'Very Strong';
}

function getPasswordStrengthColor(int $score): string {
    if ($score < 30) { return 'rgb(239, 68, 68)'; }
    if ($score < 50) { return 'rgb(251, 146, 60)'; }
    if ($score < 70) { return 'rgb(234, 179, 8)'; }
    if ($score < 90) { return 'rgb(34, 197, 94)'; }
    return 'rgb(34, 197, 94)';
}

function getInitials(string $name): string {
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        if (!empty(trim($part))) {
            $initials .= strtoupper($part[0]);
        }
    }
    return substr($initials, 0, 2) ?: 'U';
}

function getWebsites(int $userId, string $search = ''): array {
    try {
        $pdo = getDbConnection();
        $where = 'w.user_id = :uid AND w.deleted_at IS NULL';
        $params = [':uid' => $userId];
        if (!empty($search)) {
            $where .= ' AND (w.website_name LIKE :search OR w.website_url LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $stmt = $pdo->prepare(
            "SELECT w.*,
                    (SELECT COUNT(*) FROM credentials c WHERE c.website_id = w.id AND c.deleted_at IS NULL) as cred_count,
                    (SELECT COUNT(*) FROM credentials c WHERE c.website_id = w.id AND c.deleted_at IS NULL AND c.is_favorite = 1) as fav_count
             FROM websites w
             WHERE $where
             ORDER BY w.website_name ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get websites: ' . $e->getMessage());
        return [];
    }
}

function getWebsiteById(int $websiteId, int $userId): ?array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT w.*,
                    (SELECT COUNT(*) FROM credentials WHERE website_id = w.id AND deleted_at IS NULL) as cred_count
             FROM websites w
             WHERE w.id = :id AND w.user_id = :uid AND w.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $websiteId, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('Failed to get website: ' . $e->getMessage());
        return null;
    }
}

function getCredentialsByWebsite(int $websiteId, int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT c.* FROM credentials c
             JOIN websites w ON c.website_id = w.id
             WHERE c.website_id = :wid AND w.user_id = :uid AND c.deleted_at IS NULL
             ORDER BY c.is_favorite DESC, c.created_at ASC"
        );
        $stmt->execute([':wid' => $websiteId, ':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get credentials: ' . $e->getMessage());
        return [];
    }
}

function getTotalCredentialCount(int $userId): int {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as count FROM credentials c
             JOIN websites w ON c.website_id = w.id
             WHERE w.user_id = :uid AND c.deleted_at IS NULL AND w.deleted_at IS NULL"
        );
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log('Failed to count credentials: ' . $e->getMessage());
        return 0;
    }
}

function getOauthAccounts(int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM oauth_accounts WHERE user_id = :uid ORDER BY website_name ASC');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get OAuth accounts: ' . $e->getMessage());
        return [];
    }
}

// ====== TRASH HELPERS ======

function getTrashedWebsites(int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT w.*,
                    (SELECT COUNT(*) FROM credentials c WHERE c.website_id = w.id AND c.deleted_at IS NOT NULL) as cred_count,
                    (SELECT COUNT(*) FROM credentials c WHERE c.website_id = w.id AND c.deleted_at IS NULL) as active_cred_count
             FROM websites w
             WHERE w.user_id = :uid AND w.deleted_at IS NOT NULL
             ORDER BY w.deleted_at DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get trashed websites: ' . $e->getMessage());
        return [];
    }
}

function getTrashedCredentials(int $userId): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT c.*, w.website_name, w.deleted_at as website_deleted_at
             FROM credentials c
             JOIN websites w ON c.website_id = w.id
             WHERE w.user_id = :uid AND c.deleted_at IS NOT NULL
             ORDER BY c.deleted_at DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Failed to get trashed credentials: ' . $e->getMessage());
        return [];
    }
}

function getFaviconUrl(string $url = ''): string {
    if (empty($url)) return '';
    $domain = parse_url($url, PHP_URL_HOST);
    if (empty($domain)) $domain = $url;
    $domain = preg_replace('/^www\./', '', $domain);
    return 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=64';
}


