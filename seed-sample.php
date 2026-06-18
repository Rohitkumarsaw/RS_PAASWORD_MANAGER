<?php
require_once 'config/database.php';
require_once 'config/security.php';

$userId = 1;

// Manually get encryption key (same as getUserEncryptionKey logic)
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT encryption_key_hash, encryption_iv FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) die("User not found\n");

$key = base64_decode($user['encryption_key_hash']);
$iv = base64_decode($user['encryption_iv']);

if ($key === false || $iv === false) die("Failed to decode encryption key\n");

// === Sample 1: Single credential ===
$stmt = $pdo->prepare("INSERT IGNORE INTO websites (user_id, website_name, website_url) VALUES (?, ?, ?)");
$stmt->execute([$userId, 'Netflix', 'https://netflix.com']);
$netflixId = $pdo->lastInsertId();
if (!$netflixId || $netflixId == 0) {
    $netflixId = $pdo->query("SELECT id FROM websites WHERE user_id = $userId AND website_name = 'Netflix'")->fetchColumn();
}

$pw1 = encryptPassword('Stream@123!', $key, $iv);
$pdo->prepare("INSERT IGNORE INTO credentials (website_id, title, username, password_encrypted, is_favorite) VALUES (?, ?, ?, ?, ?)")
    ->execute([$netflixId, 'Primary', 'rohit@email.com', $pw1, 1]);
echo "Netflix added: ID=$netflixId\n";

// === Sample 2: Multiple credentials ===
$stmt = $pdo->prepare("INSERT IGNORE INTO websites (user_id, website_name, website_url) VALUES (?, ?, ?)");
$stmt->execute([$userId, 'GitHub', 'https://github.com']);
$githubId = $pdo->lastInsertId();
if (!$githubId || $githubId == 0) {
    $githubId = $pdo->query("SELECT id FROM websites WHERE user_id = $userId AND website_name = 'GitHub'")->fetchColumn();
}

$pw2a = encryptPassword('Dev@2024!secure', $key, $iv);
$pw2b = encryptPassword('OpenSource@456', $key, $iv);
$pw2c = encryptPassword('Bot@access#789', $key, $iv);

$pdo->prepare("INSERT IGNORE INTO credentials (website_id, title, username, password_encrypted, is_favorite) VALUES (?, ?, ?, ?, ?)")
    ->execute([$githubId, 'Personal', 'rohit.github', $pw2a, 1]);
$pdo->prepare("INSERT IGNORE INTO credentials (website_id, title, username, password_encrypted, is_favorite) VALUES (?, ?, ?, ?, ?)")
    ->execute([$githubId, 'Work', 'rohit.work@company.com', $pw2b, 0]);
$pdo->prepare("INSERT IGNORE INTO credentials (website_id, title, username, password_encrypted, is_favorite) VALUES (?, ?, ?, ?, ?)")
    ->execute([$githubId, 'Bot Account', 'rohit-bot', $pw2c, 0]);
echo "GitHub added: ID=$githubId (3 credentials)\n";

// === Sample Card ===
try {
    $cardNumEnc = encryptPassword('4532015112890367', $key, $iv);
    $cvvEnc = encryptPassword('123', $key, $iv);

    $pdo->prepare("INSERT IGNORE INTO cards (user_id, card_type, cardholder_name, card_number_encrypted, last_four, expiry_month, expiry_year, cvv_encrypted, bank_name, card_network, notes, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$userId, 'credit', 'ROHIT KUMAR', $cardNumEnc, '0367', 12, 2028, $cvvEnc, 'HDFC Bank', 'visa', 'Primary credit card', 1]);
    echo "Sample credit card added\n";

    $cardNumEnc2 = encryptPassword('5424180123456789', $key, $iv);
    $cvvEnc2 = encryptPassword('456', $key, $iv);

    $pdo->prepare("INSERT IGNORE INTO cards (user_id, card_type, cardholder_name, card_number_encrypted, last_four, expiry_month, expiry_year, cvv_encrypted, bank_name, card_network, notes, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$userId, 'debit', 'ROHIT KUMAR', $cardNumEnc2, '6789', 6, 2027, $cvvEnc2, 'State Bank of India', 'mastercard', 'Salary account debit card', 0]);
    echo "Sample debit card added\n";
} catch (PDOException $e) {
    echo "Skipping sample cards (table not ready): " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
