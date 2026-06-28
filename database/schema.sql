-- Database: carte_fedelta
-- Esegui questo script per creare il database MySQL

CREATE DATABASE IF NOT EXISTS carte_fedelta
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE carte_fedelta;

-- Utenti
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  2fa_secret VARCHAR(64) DEFAULT NULL,
  2fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_moderator TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  privacy_accepted TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  confirmation_token VARCHAR(128) DEFAULT NULL,
  email_confirmed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Token di autenticazione
CREATE TABLE IF NOT EXISTS auth_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(128) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Carte fedeltà
CREATE TABLE IF NOT EXISTS cards (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  store_name VARCHAR(255) NOT NULL,
  card_number VARCHAR(255) NOT NULL,
  holder_name VARCHAR(255) DEFAULT '',
  barcode_type VARCHAR(32) NOT NULL DEFAULT 'CODE128',
  logo_type ENUM('predefined','upload','none') NOT NULL DEFAULT 'none',
  logo_path VARCHAR(512) DEFAULT '',
  logo_data LONGTEXT,
  notes TEXT,
  is_private TINYINT(1) NOT NULL DEFAULT 0,
  color VARCHAR(7) DEFAULT '#ffffff',
  is_favorite TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ⚠️ Per database esistenti, esegui:
-- ALTER TABLE cards ADD COLUMN logo_data LONGTEXT AFTER logo_path;

-- Loghi personalizzati (upload utente)
CREATE TABLE IF NOT EXISTS custom_logos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  store_name VARCHAR(255) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_user_store (user_id, store_name)
) ENGINE=InnoDB;

-- Negozi (registro centralizzato con approvazione)
CREATE TABLE IF NOT EXISTS stores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  logo_type ENUM('predefined','upload') NOT NULL DEFAULT 'predefined',
  logo_path VARCHAR(512) DEFAULT '',
  logo_data LONGTEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  status ENUM('approved','pending') NOT NULL DEFAULT 'approved',
  admin_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- Reset password token
CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(128) NOT NULL UNIQUE,
  used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- Gruppi famiglia
CREATE TABLE IF NOT EXISTS family_groups (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Membri dei gruppi famiglia
CREATE TABLE IF NOT EXISTS family_group_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  invited_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (group_id) REFERENCES family_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_group_user (group_id, user_id)
) ENGINE=InnoDB;

-- Condivisione carte con gruppi famiglia
CREATE TABLE IF NOT EXISTS card_group_shares (
  card_id VARCHAR(64) NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  PRIMARY KEY (card_id, group_id),
  FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES family_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;
