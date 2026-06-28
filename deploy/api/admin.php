<?php

function getSettingsPdo(): PDO {
  require_once __DIR__ . '/config.php';
  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}

function authenticateAdmin(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $pdo = getSettingsPdo();
  $stmt = $pdo->prepare('SELECT user_id FROM ' . TABLE_AUTH_TOKENS . ' WHERE token = ?');
  $stmt->execute([$token]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Token non valido']);
    exit;
  }
  $uid = (int)$row['user_id'];
  $stmt = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE id = ? AND is_admin = 1');
  $stmt->execute([$uid]);
  if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accesso negato']);
    exit;
  }
  return $uid;
}

function handleSettingsHandler(string $method, string $uri): void {
  authenticateAdmin();
  $pdo = getSettingsPdo();

  if ($method === 'GET') {
    $stmt = $pdo->query('SELECT `key`, `value` FROM ' . TABLE_SETTINGS);
    $rows = $stmt->fetchAll();
    $settings = [];
    $hasSeed = false;
    foreach ($rows as $row) {
      if ($row['key'] === 'encryption_seed') {
        $hasSeed = !empty(trim($row['value']));
        continue;
      }
      $settings[$row['key']] = $row['value'];
    }
    $settings['encryption_seed_set'] = $hasSeed;
    echo json_encode($settings);
    return;
  }

  if ($method === 'PUT') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['error' => 'Formato non valido']);
      return;
    }

    // Se si sta modificando il seed, controlla che non ci siano carte cifrate
    if (array_key_exists('encryption_seed', $data)) {
      $stmt = $pdo->query("SELECT COUNT(*) FROM " . TABLE_CARDS . " WHERE card_number LIKE '##ENC##%'");
      $encryptedCount = (int)$stmt->fetchColumn();
      if ($encryptedCount > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Impossibile modificare il seed: ci sono ' . $encryptedCount . ' carte cifrate. Decifrale prima.']);
        return;
      }
    }
    $allowed = [
      'mail_mode', 'mail_from', 'mail_from_name', 'mail_reply_to', 'mail_return_path',
      'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption',
      'spf_record', 'dkim_record', 'dmarc_record',
      'allow_registration',
      'app_url',
      'app_name',
      'encryption_seed',
    ];
    $upsert = $pdo->prepare(
      'INSERT INTO ' . TABLE_SETTINGS . ' (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    foreach ($data as $key => $value) {
      if (!in_array($key, $allowed, true)) continue;
      $upsert->execute([$key, is_string($value) ? $value : '']);
    }
    echo json_encode(['success' => true]);
    return;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Metodo non consentito']);
}

function handle2faStatusHandler(string $method, string $uri): void {
  $uid = authenticateAdmin();
  $pdo = getSettingsPdo();
  $stmt = $pdo->prepare('SELECT 2fa_enabled FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$uid]);
  $row = $stmt->fetch();
  echo json_encode(['has_2fa' => $row && (int)$row['2fa_enabled'] === 1]);
}

function handleRevealSeedHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $uid = authenticateAdmin();
  $data = json_decode(file_get_contents('php://input'), true);
  $code = $data['code'] ?? '';

  $pdo = getSettingsPdo();
  $stmt = $pdo->prepare('SELECT 2fa_secret, 2fa_enabled FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$uid]);
  $user = $stmt->fetch();

  if (!$user || !(int)$user['2fa_enabled']) {
    http_response_code(403);
    echo json_encode(['error' => '2FA non configurato']);
    return;
  }

  require_once __DIR__ . '/auth.php';
  if (!totpVerify($user['2fa_secret'], $code)) {
    http_response_code(401);
    echo json_encode(['error' => 'Codice 2FA non valido']);
    return;
  }

  $stmt = $pdo->query("SELECT `value` FROM " . TABLE_SETTINGS . " WHERE `key` = 'encryption_seed'");
  $row = $stmt->fetch();
  $seed = $row ? trim($row['value']) : '';

  echo json_encode(['seed' => $seed]);
}

function handleInfoHandler(string $method, string $uri): void {
  $pdo = getSettingsPdo();
  $stmt = $pdo->query("SELECT `key`, `value` FROM " . TABLE_SETTINGS . " WHERE `key` IN ('app_name','encryption_seed')");
  $info = ['app_name' => '', 'encryption_seed_set' => false];
  foreach ($stmt->fetchAll() as $row) {
    if ($row['key'] === 'encryption_seed') {
      $info['encryption_seed_set'] = $row['value'] !== '';
    } else {
      $info[$row['key']] = $row['value'];
    }
  }
  echo json_encode($info);
}

function handleEncryptionStatusHandler(string $method, string $uri): void {
  authenticateAdmin();
  $pdo = getSettingsPdo();

  $stmt = $pdo->query("SELECT `value` FROM " . TABLE_SETTINGS . " WHERE `key` = 'encryption_seed'");
  $row = $stmt->fetch();
  $seedSet = $row && $row['value'] !== '';

  $stmt = $pdo->query("SELECT COUNT(*) FROM " . TABLE_CARDS . " WHERE card_number LIKE '##ENC##%'");
  $count = (int)$stmt->fetchColumn();

  echo json_encode([
    'encryption_seed_set' => $seedSet,
    'encrypted_cards_count' => $count,
  ]);
}

function handleDisableEncryptionHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $uid = authenticateAdmin();
  $data = json_decode(file_get_contents('php://input'), true);
  $code = $data['code'] ?? '';

  $pdo = getSettingsPdo();

  $stmt = $pdo->prepare('SELECT 2fa_secret, 2fa_enabled FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$uid]);
  $user = $stmt->fetch();

  if (!$user || !(int)$user['2fa_enabled']) {
    http_response_code(403);
    echo json_encode(['error' => '2FA non configurato']);
    return;
  }

  require_once __DIR__ . '/auth.php';
  if (!totpVerify($user['2fa_secret'], $code)) {
    http_response_code(401);
    echo json_encode(['error' => 'Codice 2FA non valido']);
    return;
  }

  $stmt = $pdo->query("SELECT `value` FROM " . TABLE_SETTINGS . " WHERE `key` = 'encryption_seed'");
  $row = $stmt->fetch();
  $seed = $row ? trim($row['value']) : '';

  require_once __DIR__ . '/cards.php';

  $stmt = $pdo->query("SELECT id, card_number FROM " . TABLE_CARDS);
  $cards = $stmt->fetchAll();

  $update = $pdo->prepare("UPDATE " . TABLE_CARDS . " SET card_number = ? WHERE id = ?");
  $decrypted = 0;
  foreach ($cards as $card) {
    if (!str_starts_with($card['card_number'], ENC_PREFIX)) continue;
    $plain = decryptCardNumberSafe($card['card_number'], $seed);
    $update->execute([$plain, $card['id']]);
    $decrypted++;
  }

  $pdo->prepare("DELETE FROM " . TABLE_SETTINGS . " WHERE `key` = 'encryption_seed'")->execute();

  echo json_encode(['success' => true, 'decrypted_count' => $decrypted]);
}
