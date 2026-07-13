<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Catch all PHP errors
set_exception_handler(function ($e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
  exit;
});

// Check required PHP extensions
$requiredExts = ['pdo_mysql'];
$missing = [];
foreach ($requiredExts as $ext) {
  if (!extension_loaded($ext)) {
    $missing[] = $ext;
  }
}
if ($missing) {
  http_response_code(500);
  echo json_encode([
    'error' => 'Estensione PHP mancante: ' . implode(', ', $missing),
    'detail' => 'Installa le estensioni richieste sul server (es. apt install php-mysql).'
  ]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Determine API route regardless of subdirectory deployment
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($scriptDir !== '/' && strpos($uri, $scriptDir) === 0) {
  $uri = substr($uri, strlen($scriptDir));
}
$uri = '/' . trim($uri, '/');

// ─── Setup routes (before config check) ───
$setupRoutes = [
  '/setup/check',
  '/setup/save',
  '/setup/test-db',
  '/setup/test-mail',
];

// These routes work without config.php
$publicRoutes = ['/site-logo', '/manifest', '/auth/confirm-email', '/discover'];

if (in_array($uri, $setupRoutes, true)) {
  require_once __DIR__ . '/setup.php';
  $handlerMap = [
    '/setup/check' => ['setup.php', 'checkHandler'],
    '/setup/save' => ['setup.php', 'saveHandler'],
    '/setup/test-db' => ['setup.php', 'testDbHandler'],
    '/setup/test-mail' => ['setup.php', 'testMailHandler'],
  ];
  $handler = $handlerMap[$uri] ?? null;
  if ($handler) {
    require_once __DIR__ . '/' . $handler[0];
    $fn = $handler[1];
    if (function_exists($fn)) {
      try {
        $fn($method);
      } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
      }
    } else {
      http_response_code(500);
      echo json_encode(['error' => 'Handler non trovato: ' . $handler[1]]);
    }
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Handler non definito per: ' . $uri]);
  }
  exit;
}

// Handle public routes (no config needed for GET)
if (in_array($uri, $publicRoutes, true) && $method === 'GET') {
  if ($uri === '/discover' || $uri === '/discover.php') {
    require_once __DIR__ . '/discover.php';
    exit;
  }
  require_once __DIR__ . '/site-logo.php';
  if ($uri === '/manifest') {
    manifestHandler();
  } else {
    siteLogoHandler($method);
  }
  exit;
}

// Admin panel (standalone HTML/PHP page, before config check)
if (strpos($uri, '/admin/logos') === 0 || $uri === '/admin' || $uri === '/admin/') {
  $adminFile = __DIR__ . '/admin/logos/index.php';
  if (file_exists($adminFile)) {
    header('Content-Type: text/html; charset=utf-8');
    require $adminFile;
    exit;
  }
  http_response_code(404);
  echo json_encode(['error' => 'Admin panel non trovato']);
  exit;
}

// Load config with friendly error if missing
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
  http_response_code(500);
  echo json_encode([
    'error' => 'Configurazione assente',
    'detail' => 'Completa la configurazione iniziale.'
  ]);
  exit;
}

require_once $configPath;

// Ensure family table constants are defined (for existing configs without them)
if (!defined('TABLE_FAMILY_GROUPS')) {
  define('TABLE_FAMILY_GROUPS', DB_PREFIX . 'family_groups');
}
if (!defined('TABLE_FAMILY_MEMBERS')) {
  define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');
}
if (!defined('TABLE_SETTINGS')) {
  define('TABLE_SETTINGS', DB_PREFIX . 'settings');
}
if (!defined('TABLE_PENDING_LOGOS')) {
  define('TABLE_PENDING_LOGOS', DB_PREFIX . 'pending_logos');
}
if (!defined('TABLE_CARD_GROUP_SHARES')) {
  define('TABLE_CARD_GROUP_SHARES', DB_PREFIX . 'card_group_shares');
}

// Auto-migrate schema on every request
require_once __DIR__ . '/migrate.php';
try {
  $migrateDb = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
  migrateRun($migrateDb);
  $migrateDb = null;
} catch (Exception $e) {
  // Silently fail — the request may still work if tables already exist
}

$routes = [
  '/auth/register'    => 'auth.php@register',
  '/auth/login'       => 'auth.php@login',
  '/auth/2fa/setup'    => 'auth.php@setup2fa',
  '/auth/2fa/verify'   => 'auth.php@verify2fa',
  '/auth/me'          => 'auth.php@me',
  '/auth/confirm-email' => 'auth.php@confirmEmail',
  '/auth/forgot-password' => 'auth.php@forgotPassword',
  '/auth/reset-password'  => 'auth.php@resetPassword',
  '/auth/register-status' => 'auth.php@registerStatus',
  '/cards'            => 'cards.php@handleCards',
  '/cards/encrypt-all'  => 'cards.php@handleCardEncryptAll',
  '/cards/decrypt-all'  => 'cards.php@handleCardDecryptAll',
  '/cards/batch'      => 'cards.php@handleBatch',
  '/cards/([^/]+)/encrypt' => 'cards.php@handleCardEncrypt',
  '/cards/([^/]+)/decrypt' => 'cards.php@handleCardDecrypt',
  '/cards/(.+)'       => 'cards.php@handleCards',
  '/logos'                 => 'logos.php@handleLogos',
  '/logos/predefined'      => 'logos.php@getPredefined',
  '/logos/report-missing'  => 'logos.php@reportMissing',
  '/logos/submit'          => 'logos.php@submitLogo',
  '/logos/(.+)'            => 'logos.php@getStoreLogo',
  '/stores/brief'      => 'stores.php@listStoresBrief',
  '/stores/orphans'    => 'stores.php@listOrphanStores',
  '/stores'            => 'stores.php@handleStores',
  '/stores/(.+)'       => 'stores.php@handleStore',
  '/setup/admin'      => 'setup.php@createAdmin',
  '/setup/check-admin' => 'setup.php@checkAdmin',
  '/site-logo'        => 'site-logo.php@siteLogo',
  '/site-logo/reset'   => 'site-logo.php@siteLogo',
  '/manifest'         => 'site-logo.php@manifest',
  '/users'            => 'users.php@listUsers',
  '/users/create'     => 'users.php@createUser',
  '/users/(.+)/cards' => 'users.php@getUserCards',
  '/users/(.+)'       => 'users.php@handleUser',
  '/family(|/.*)'     => 'family.php@handleFamily',
  '/admin/settings'   => 'admin.php@handleSettings',
  '/admin/2fa-status'   => 'admin.php@handle2faStatus',
  '/admin/reveal-seed'  => 'admin.php@handleRevealSeed',
  '/admin/encryption/status' => 'admin.php@handleEncryptionStatus',
  '/admin/disable-encryption' => 'admin.php@handleDisableEncryption',
  '/admin/users'            => 'admin.php@handleAdminUsers',
  '/admin/users/(.+)'       => 'admin.php@handleAdminUsers',
  '/admin/pending-logos'    => 'admin.php@handlePendingLogos',
  '/admin/change-password'  => 'admin.php@handleAdminChangePassword',
  '/admin/forgot-password'  => 'admin.php@handleAdminForgotPassword',
  '/settings/info'    => 'admin.php@handleInfo',
  '/cron'             => 'cron.php@cron',
];

$matched = false;
foreach ($routes as $pattern => $handler) {
  if (preg_match('#^' . $pattern . '$#', $uri)) {
    [$file, $func] = explode('@', $handler);
    $filePath = __DIR__ . '/' . $file;
    if (!file_exists($filePath)) {
      http_response_code(500);
      echo json_encode(['error' => 'File handler non trovato: ' . $file]);
      exit;
    }
    require_once $filePath;
    $fn = $func . 'Handler';
    if (function_exists($fn)) {
      $matched = true;
      try {
        $fn($method, $uri);
      } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
      }
    }
    break;
  }
}

if (!$matched) {
  http_response_code(404);
  echo json_encode(['error' => 'Endpoint non trovato']);
}
