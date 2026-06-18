<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

session_start();
requireLogin();

$userId = getCurrentUserId();

$action = $_GET['action'] ?? '';
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($action) {
    case 'scan':
        handleScan($userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleScan(int $userId): void {
    $pdo = getDbConnection();
    $encKey = getUserEncryptionKey($userId);

    if (!$encKey) {
        echo json_encode(['success' => false, 'message' => 'Encryption key unavailable']);
        return;
    }

    $entries = [];

    // Fetch from password_entries (legacy)
    try {
        $stmt = $pdo->prepare(
            'SELECT id, website, username, password_encrypted, password_changed_at, updated_at, is_favorite
             FROM password_entries
             WHERE user_id = :uid AND is_archived = 0'
        );
        $stmt->execute([':uid' => $userId]);
        $legacyEntries = $stmt->fetchAll();

        foreach ($legacyEntries as $row) {
            $decrypted = null;
            if (!empty($row['password_encrypted'])) {
                try {
                    $decrypted = decryptPassword($row['password_encrypted'], $encKey['key'], $encKey['iv']);
                } catch (Exception $e) {
                    continue;
                }
            }
            if ($decrypted === null || $decrypted === '') continue;

            $entries[] = [
                'id' => (int)$row['id'],
                'website' => $row['website'],
                'username' => $row['username'],
                'password' => $decrypted,
                'updated_at' => $row['password_changed_at'] ?: $row['updated_at'],
                'source' => 'password_entries',
                'is_favorite' => (int)$row['is_favorite'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Health scan (legacy): ' . $e->getMessage());
    }

    // Fetch from websites + credentials (new)
    try {
        $stmt = $pdo->prepare(
            'SELECT c.id, w.website_name, c.username, c.password_encrypted, c.updated_at, c.is_favorite
             FROM credentials c
             JOIN websites w ON c.website_id = w.id
             WHERE w.user_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $newEntries = $stmt->fetchAll();

        foreach ($newEntries as $row) {
            $decrypted = null;
            if (!empty($row['password_encrypted'])) {
                try {
                    $decrypted = decryptPassword($row['password_encrypted'], $encKey['key'], $encKey['iv']);
                } catch (Exception $e) {
                    continue;
                }
            }
            if ($decrypted === null || $decrypted === '') continue;

            $entries[] = [
                'id' => (int)$row['id'],
                'website' => $row['website_name'],
                'username' => $row['username'],
                'password' => $decrypted,
                'updated_at' => $row['updated_at'],
                'source' => 'credentials',
                'is_favorite' => (int)$row['is_favorite'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Health scan (new): ' . $e->getMessage());
    }

    if (empty($entries)) {
        echo json_encode([
            'success' => true,
            'overall_score' => 100,
            'total_passwords' => 0,
            'weak_passwords' => 0,
            'reused_passwords' => 0,
            'old_passwords' => 0,
            'category_breakdown' => ['strong' => 0, 'moderate' => 0, 'weak' => 0],
            'details' => [],
            'reused_groups' => [],
            'message' => 'No passwords found to analyze. Add some passwords first!',
        ]);
        return;
    }

    // Analyze each entry
    $details = [];
    $passwordMap = [];

    foreach ($entries as $entry) {
        $pw = $entry['password'];
        $len = strlen($pw);
        $hasUpper = preg_match('/[A-Z]/', $pw) === 1;
        $hasLower = preg_match('/[a-z]/', $pw) === 1;
        $hasDigit = preg_match('/[0-9]/', $pw) === 1;
        $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $pw) === 1;

        // Length score
        $lenScore = 0;
        if ($len >= 16) $lenScore = 40;
        elseif ($len >= 12) $lenScore = 30;
        elseif ($len >= 8) $lenScore = 20;
        elseif ($len >= 6) $lenScore = 10;

        // Complexity score
        $complexityCount = 0;
        if ($hasUpper) $complexityCount++;
        if ($hasLower) $complexityCount++;
        if ($hasDigit) $complexityCount++;
        if ($hasSpecial) $complexityCount++;
        $complexityScore = ($complexityCount / 4) * 30;

        // Age score
        $ageScore = 15;
        $ageDays = null;
        $now = new DateTime();
        if ($entry['updated_at']) {
            try {
                $updated = new DateTime($entry['updated_at']);
                $ageDays = (int)$now->diff($updated)->days;
                if ($ageDays < 90) $ageScore = 30;
                elseif ($ageDays < 180) $ageScore = 23;
                elseif ($ageDays < 365) $ageScore = 15;
                else $ageScore = 8;
            } catch (Exception $e) {}
        }

        $totalScore = $lenScore + $complexityScore + $ageScore;
        $totalScore = max(0, min(100, $totalScore));

        // Categorize
        $category = 'strong';
        if ($totalScore < 40) $category = 'weak';
        elseif ($totalScore < 60) $category = 'moderate';

        // Issues
        $issues = [];
        if ($len < 8) $issues[] = 'Too short (' . $len . ' chars) — use at least 8 characters';
        elseif ($len < 12) $issues[] = 'Could be longer (' . $len . ' chars) — aim for 12+';
        if (!$hasUpper) $issues[] = 'Missing uppercase letters';
        if (!$hasLower) $issues[] = 'Missing lowercase letters';
        if (!$hasDigit) $issues[] = 'Missing numbers';
        if (!$hasSpecial) $issues[] = 'Missing special characters';
        if ($ageDays !== null && $ageDays > 365) $issues[] = 'Not changed in over a year (' . $ageDays . ' days)';
        elseif ($ageDays !== null && $ageDays > 180) $issues[] = 'Not changed in 6+ months (' . $ageDays . ' days)';

        // Track for reuse detection
        $pwHash = md5($pw);
        if (!isset($passwordMap[$pwHash])) {
            $passwordMap[$pwHash] = [
                'password' => $pw,
                'count' => 0,
                'entries' => [],
            ];
        }
        $passwordMap[$pwHash]['count']++;
        $passwordMap[$pwHash]['entries'][] = $entry['website'] . ' (' . $entry['username'] . ')';

        $details[] = [
            'id' => $entry['id'],
            'website' => $entry['website'],
            'username' => $entry['username'],
            'score' => $totalScore,
            'category' => $category,
            'length' => $len,
            'has_upper' => $hasUpper,
            'has_lower' => $hasLower,
            'has_digit' => $hasDigit,
            'has_special' => $hasSpecial,
            'age_days' => $ageDays,
            'issues' => $issues,
            'is_favorite' => $entry['is_favorite'],
            'source' => $entry['source'],
        ];
    }

    // Reuse analysis
    $reusedGroups = [];
    $reusedPasswords = 0;
    $reusedEntryIds = [];
    foreach ($passwordMap as $hash => $data) {
        if ($data['count'] > 1) {
            $reusedGroups[] = $data;
            $reusedPasswords += $data['count'];
            foreach ($data['entries'] as $e) {
                $reusedEntryIds[] = $e;
            }
        }
    }

    // Mark reused entries
    foreach ($details as &$det) {
        $pw = '';
        foreach ($entries as $e) {
            if ($e['id'] === $det['id'] && $e['source'] === $det['source']) {
                $pw = $e['password'];
                break;
            }
        }
        if ($pw) {
            $pwHash = md5($pw);
            if (isset($passwordMap[$pwHash]) && $passwordMap[$pwHash]['count'] > 1) {
                $det['issues'][] = 'Reused across ' . $passwordMap[$pwHash]['count'] . ' accounts';
            }
        }
    }
    unset($det);

    // Count weak and old
    $weakCount = 0;
    $oldCount = 0;
    foreach ($details as $det) {
        if ($det['category'] === 'weak') $weakCount++;
        if ($det['age_days'] !== null && $det['age_days'] > 365) $oldCount++;
    }

    // Overall score (average)
    $totalScoreSum = array_sum(array_column($details, 'score'));
    $overallScore = count($details) > 0 ? round($totalScoreSum / count($details)) : 100;

    // Apply reuse penalty (up to -15 points)
    $reusePenalty = 0;
    if ($reusedPasswords > 0) {
        $reuseRatio = $reusedPasswords / count($details);
        $reusePenalty = (int)round(min(15, $reuseRatio * 25));
    }
    $overallScore = max(0, $overallScore - $reusePenalty);

    // Category breakdown
    $breakdown = ['strong' => 0, 'moderate' => 0, 'weak' => 0];
    foreach ($details as $det) {
        $breakdown[$det['category']]++;
    }

    echo json_encode([
        'success' => true,
        'overall_score' => $overallScore,
        'total_passwords' => count($details),
        'weak_passwords' => $weakCount,
        'reused_passwords' => $reusedPasswords,
        'old_passwords' => $oldCount,
        'reuse_penalty' => $reusePenalty,
        'category_breakdown' => $breakdown,
        'details' => $details,
        'reused_groups' => $reusedGroups,
    ]);
}
