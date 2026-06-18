<?php
require_once 'config/database.php';

$pdo = getDbConnection();

echo "Running migrations...\n";

// Check if cards table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'cards'");
$tableExists = $stmt->fetch();

if (!$tableExists) {
    echo "Creating cards table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            card_type ENUM('debit','credit') NOT NULL DEFAULT 'credit',
            cardholder_name VARCHAR(255) NOT NULL,
            card_number_encrypted TEXT NOT NULL,
            last_four VARCHAR(4) DEFAULT NULL,
            expiry_month INT NOT NULL,
            expiry_year INT NOT NULL,
            cvv_encrypted TEXT NOT NULL,
            bank_name VARCHAR(255) DEFAULT NULL,
            card_network VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_favorite TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  -> cards table created with last_four column\n";
} else {
    // Check if last_four column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM cards LIKE 'last_four'");
    $colExists = $stmt->fetch();
    if (!$colExists) {
        echo "Adding last_four column to cards table...\n";
        $pdo->exec("ALTER TABLE cards ADD COLUMN last_four VARCHAR(4) DEFAULT NULL AFTER card_number_encrypted");
        echo "  -> last_four column added\n";
    } else {
        echo "  -> cards table already has last_four column\n";
    }
}

echo "\nDone!\n";
