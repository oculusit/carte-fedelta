<?php

/**
 * Auto-migration: runs at API startup to keep DB schema up to date.
 * Creates missing tables and adds missing columns.
 *
 * Note: This runs BEFORE the routes, so table constants may not be defined yet.
 * We accept the prefix string directly.
 */

function migrateRun(PDO $db): void {
  // Determine prefix — it may or may not be defined yet
  $p = defined('DB_PREFIX') ? DB_PREFIX : 'cards_';

  // ── family_groups ──
  $db->exec("
    CREATE TABLE IF NOT EXISTS `{$p}family_groups` (
      id VARCHAR(64) NOT NULL PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      owner_id INT UNSIGNED NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
  ");

  // ── family_group_members ──
  $db->exec("
    CREATE TABLE IF NOT EXISTS `{$p}family_group_members` (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      group_id VARCHAR(64) NOT NULL,
      user_id INT UNSIGNED NOT NULL,
      status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
      invited_by INT UNSIGNED NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (group_id) REFERENCES `{$p}family_groups`(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE,
      FOREIGN KEY (invited_by) REFERENCES `{$p}users`(id) ON DELETE CASCADE,
      UNIQUE KEY uk_group_user (group_id, user_id)
    ) ENGINE=InnoDB
  ");

  // ── is_private on cards ──
  $stmt = $db->prepare("SHOW COLUMNS FROM `{$p}cards` LIKE 'is_private'");
  $stmt->execute();
  if (!$stmt->fetch()) {
    $db->exec("ALTER TABLE `{$p}cards` ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER notes");
  }

  // ── settings table ──
  $db->exec("
    CREATE TABLE IF NOT EXISTS `{$p}settings` (
      `key` VARCHAR(64) NOT NULL PRIMARY KEY,
      `value` TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
  ");

  // ── card_group_shares ──
  $db->exec("
    CREATE TABLE IF NOT EXISTS `{$p}card_group_shares` (
      card_id VARCHAR(64) NOT NULL,
      group_id VARCHAR(64) NOT NULL,
      PRIMARY KEY (card_id, group_id),
      FOREIGN KEY (card_id) REFERENCES `{$p}cards`(id) ON DELETE CASCADE,
      FOREIGN KEY (group_id) REFERENCES `{$p}family_groups`(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
  ");

  // ── is_favorite on cards ──
  $stmt = $db->prepare("SHOW COLUMNS FROM `{$p}cards` LIKE 'is_favorite'");
  $stmt->execute();
  if (!$stmt->fetch()) {
    $db->exec("ALTER TABLE `{$p}cards` ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER color");
  }

  // ── logo_data on cards ──
  $stmt = $db->prepare("SHOW COLUMNS FROM `{$p}cards` LIKE 'logo_data'");
  $stmt->execute();
  if (!$stmt->fetch()) {
    $db->exec("ALTER TABLE `{$p}cards` ADD COLUMN logo_data LONGTEXT AFTER logo_path");
  }

  // ── pending_logos (user-submitted logos awaiting approval) ──
  $db->exec("
    CREATE TABLE IF NOT EXISTS `{$p}pending_logos` (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED DEFAULT NULL,
      store_name VARCHAR(255) NOT NULL,
      image_data LONGTEXT NOT NULL,
      status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      admin_notes TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      reviewed_at TIMESTAMP NULL DEFAULT NULL,
      FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE SET NULL,
      INDEX idx_status (status),
      INDEX idx_store (store_name)
    ) ENGINE=InnoDB
  ");

  // ── admin_role column on users ──
  $stmt = $db->prepare("SHOW COLUMNS FROM `{$p}users` LIKE 'admin_role'");
  $stmt->execute();
  if (!$stmt->fetch()) {
    $db->exec("ALTER TABLE `{$p}users` ADD COLUMN admin_role ENUM('superadmin','admin') DEFAULT NULL AFTER is_moderator");
  }

  // ── Ensure group owners have a family_group_members entry ──
  $db->exec("
    INSERT IGNORE INTO `{$p}family_group_members` (group_id, user_id, status, invited_by)
    SELECT g.id, g.owner_id, 'accepted', g.owner_id
    FROM `{$p}family_groups` g
    LEFT JOIN `{$p}family_group_members` m ON m.group_id = g.id AND m.user_id = g.owner_id
    WHERE m.id IS NULL
  ");
}
