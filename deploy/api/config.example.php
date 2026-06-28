<?php

// ─── Database ───
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'carte_fedelta');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PREFIX', 'cards_');
define('DB_CHARSET', 'utf8mb4');

define('TABLE_USERS', DB_PREFIX . 'users');
define('TABLE_AUTH_TOKENS', DB_PREFIX . 'auth_tokens');
define('TABLE_CARDS', DB_PREFIX . 'cards');
define('TABLE_CUSTOM_LOGOS', DB_PREFIX . 'custom_logos');
define('TABLE_STORES', DB_PREFIX . 'stores');
define('TABLE_PASSWORD_RESETS', DB_PREFIX . 'password_resets');
define('TABLE_FAMILY_GROUPS', DB_PREFIX . 'family_groups');
define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');

// ─── Mail ───
define('MAIL_MODE', 'mail'); // 'mail' or 'smtp'

define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Carte Fedeltà');
define('MAIL_REPLY_TO', 'noreply@example.com');
define('MAIL_RETURN_PATH', 'noreply@example.com');

define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl' or ''

// ─── Paths ───
define('UPLOAD_DIR', __DIR__ . '/../uploads/logos/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
