<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

function getVersionHandler($method = null, $uri = null) {
  $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');

  if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
  }

  $configPath = __DIR__ . '/config.php';
  if (!file_exists($configPath)) {
    echo json_encode(['version' => null, 'download_url' => null, 'backend_version' => null]);
    return;
  }

  require_once $configPath;
  require_once __DIR__ . '/migrate.php';

  if (!defined('BACKEND_VERSION')) define('BACKEND_VERSION', '1.2.5.back');

  try {
    $db = new PDO(
      'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
      DB_USER, DB_PASS,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    migrateRun($db);

    $table = DB_PREFIX . 'settings';
    $stmt = $db->prepare("SELECT `key`, `value` FROM `{$table}` WHERE `key` IN ('app_version', 'app_download_url')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
      'version' => $settings['app_version'] ?? null,
      'download_url' => $settings['app_download_url'] ?? null,
      'backend_version' => BACKEND_VERSION,
    ]);
  } catch (Exception $e) {
    echo json_encode(['version' => null, 'download_url' => null, 'backend_version' => BACKEND_VERSION]);
  }
}

$scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
if ($scriptFile !== '' && realpath($scriptFile) === realpath(__FILE__)) {
  getVersionHandler();
}
