-- Migration: Add websites and credentials tables
-- Run this in phpMyAdmin or MySQL CLI

CREATE TABLE IF NOT EXISTS websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    website_name VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_website_per_user (user_id, website_name),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    website_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password_encrypted TEXT NOT NULL,
    is_favorite TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_website_id (website_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE credentials ADD COLUMN category_id INT DEFAULT NULL AFTER is_favorite;
ALTER TABLE credentials ADD COLUMN notes TEXT DEFAULT NULL AFTER category_id;
ALTER TABLE credentials ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Migration 2: Soft delete (Trash) support
ALTER TABLE websites ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
ALTER TABLE credentials ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_websites_deleted ON websites(deleted_at);
CREATE INDEX idx_credentials_deleted ON credentials(deleted_at);

-- Migration 3: Add password_changed_at to credentials
ALTER TABLE credentials ADD COLUMN password_changed_at TIMESTAMP NULL DEFAULT NULL AFTER notes;

-- Migration 4: Password history table
CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credential_id INT NOT NULL,
    password_encrypted TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (credential_id) REFERENCES credentials(id) ON DELETE CASCADE,
    INDEX idx_credential_id (credential_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 5: Add phone_encrypted to credentials
ALTER TABLE credentials ADD COLUMN phone_encrypted TEXT NULL DEFAULT NULL AFTER notes;

-- Migration 6: Add PIN support to users
ALTER TABLE users ADD COLUMN pin_hash VARCHAR(255) NULL DEFAULT NULL AFTER encryption_iv;
ALTER TABLE users ADD COLUMN pin_set_at TIMESTAMP NULL DEFAULT NULL AFTER pin_hash;

-- Migration 7: Share links table
CREATE TABLE IF NOT EXISTS share_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credential_id INT NOT NULL,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    max_views INT DEFAULT 1,
    current_views INT DEFAULT 0,
    is_revoked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (credential_id) REFERENCES credentials(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 8: Saved debit/credit cards
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 9: Add admin & phone support to users
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER encryption_iv;
ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER display_name;

-- Migration 10: Create OAuth accounts table
CREATE TABLE IF NOT EXISTS oauth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    website_name VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    provider VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 11: Add soft delete to cards
ALTER TABLE cards ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
CREATE INDEX idx_cards_deleted ON cards(deleted_at);
