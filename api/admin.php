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

// ── Admin panel endpoints ──

function requireSuperAdmin(): int {
  $uid = authenticateAdmin();
  $pdo = getSettingsPdo();
  $stmt = $pdo->prepare('SELECT admin_role FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$uid]);
  $row = $stmt->fetch();
  if ($row && $row['admin_role'] === 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo il super-amministratore può eseguire questa operazione']);
    exit;
  }
  return $uid;
}

function handleAdminUsersHandler(string $method, string $uri): void {
  if ($method === 'GET') {
    requireSuperAdmin();
    $pdo = getSettingsPdo();
    $stmt = $pdo->prepare('SELECT id, email, admin_role, is_active, created_at FROM ' . TABLE_USERS . ' WHERE is_admin = 1 ORDER BY created_at');
    $stmt->execute();
    echo json_encode($stmt->fetchAll());
    return;
  }

  if ($method === 'POST') {
    $uid = requireSuperAdmin();
    $data = jsonBody();
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'admin';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      http_response_code(400);
      echo json_encode(['error' => 'Email non valida']);
      return;
    }
    if (strlen($password) < 6) {
      http_response_code(400);
      echo json_encode(['error' => 'Password troppo corta (min 6 caratteri)']);
      return;
    }
    if (!in_array($role, ['superadmin', 'admin'])) $role = 'admin';

    $pdo = getSettingsPdo();
    $stmt = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      http_response_code(409);
      echo json_encode(['error' => 'Email già registrata']);
      return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO ' . TABLE_USERS . ' (email, password_hash, is_admin, admin_role, status) VALUES (?, ?, 1, ?, \'approved\')');
    $stmt->execute([$email, $hash, $role]);

    echo json_encode(['success' => true, 'message' => 'Amministratore creato: ' . $email]);
    return;
  }

  if ($method === 'DELETE') {
    $uid = requireSuperAdmin();
    preg_match('#/admin/users/(.+)#', $uri, $m);
    $targetId = (int)($m[1] ?? 0);
    if ($targetId === $uid) {
      http_response_code(400);
      echo json_encode(['error' => 'Non puoi eliminare te stesso']);
      return;
    }
    $pdo = getSettingsPdo();
    $stmt = $pdo->prepare('UPDATE ' . TABLE_USERS . ' SET is_admin = 0, admin_role = NULL WHERE id = ? AND is_admin = 1');
    $stmt->execute([$targetId]);
    echo json_encode(['success' => true]);
    return;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Metodo non consentito']);
}

function handlePendingLogosHandler(string $method, string $uri): void {
  $uid = authenticateAdmin();
  $pdo = getSettingsPdo();

  if ($method === 'GET') {
    $status = $_GET['status'] ?? 'pending';
    if (!in_array($status, ['pending', 'approved', 'rejected'])) $status = 'pending';
    $stmt = $pdo->prepare('SELECT p.*, u.email as user_email FROM ' . TABLE_PENDING_LOGOS . ' p LEFT JOIN ' . TABLE_USERS . ' u ON u.id = p.user_id WHERE p.status = ? ORDER BY p.created_at DESC');
    $stmt->execute([$status]);
    echo json_encode($stmt->fetchAll());
    return;
  }

  if ($method === 'POST') {
    $data = jsonBody();
    $action = $data['action'] ?? '';
    $id = (int)($data['id'] ?? 0);

    if ($action === 'approve') {
      $stmt = $pdo->prepare('SELECT id, store_name, image_data FROM ' . TABLE_PENDING_LOGOS . ' WHERE id = ? AND status = \'pending\'');
      $stmt->execute([$id]);
      $logo = $stmt->fetch();
      if (!$logo) {
        http_response_code(404);
        echo json_encode(['error' => 'Logo non trovato o già processato']);
        return;
      }

      $uploadDir = __DIR__ . '/../uploads/logos/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

      $safeName = str_replace(['/', '\\', "\0"], '_', $logo['store_name']) . '.webp';
      $imageData = $logo['image_data'];
      if (strpos($imageData, 'base64,') !== false) {
        $imageData = substr($imageData, strpos($imageData, 'base64,') + 7);
      }
      $decoded = base64_decode($imageData, true);
      if ($decoded !== false) {
        file_put_contents($uploadDir . $safeName, $decoded);
      }

      $stmt = $pdo->prepare('UPDATE ' . TABLE_PENDING_LOGOS . ' SET status = \'approved\', reviewed_at = NOW() WHERE id = ?');
      $stmt->execute([$id]);
      echo json_encode(['success' => true, 'message' => 'Logo approvato e pubblicato']);
      return;
    }

    if ($action === 'reject') {
      $stmt = $pdo->prepare('UPDATE ' . TABLE_PENDING_LOGOS . ' SET status = \'rejected\', reviewed_at = NOW() WHERE id = ?');
      $stmt->execute([$id]);
      echo json_encode(['success' => true]);
      return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Azione non valida']);
    return;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Metodo non consentito']);
}

function handleAdminChangePasswordHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $uid = authenticateAdmin();
  $data = jsonBody();
  $currentPassword = $data['current_password'] ?? '';
  $newPassword = $data['new_password'] ?? '';

  if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'La nuova password deve essere di almeno 6 caratteri']);
    return;
  }

  $pdo = getSettingsPdo();
  $stmt = $pdo->prepare('SELECT password_hash FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$uid]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Password attuale errata']);
    return;
  }

  $hash = password_hash($newPassword, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare('UPDATE ' . TABLE_USERS . ' SET password_hash = ? WHERE id = ?');
  $stmt->execute([$hash, $uid]);
  echo json_encode(['success' => true, 'message' => 'Password aggiornata con successo']);
}

function handleAdminForgotPasswordHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $email = trim($data['email'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email non valida']);
    return;
  }

  $pdo = getSettingsPdo();
  $stmt = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ? AND is_admin = 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  // Always return success
  if (!$user) {
    echo json_encode(['success' => true, 'message' => 'Se l\'email è associata a un account amministratore, riceverai un link per il reset.']);
    return;
  }

  $token = bin2hex(random_bytes(32));
  $expires = date('Y-m-d H:i:s', time() + 3600);
  $stmt = $pdo->prepare('INSERT INTO ' . TABLE_PASSWORD_RESETS . ' (email, token, expires_at) VALUES (?, ?, ?)');
  $stmt->execute([$email, $token, $expires]);

  require_once __DIR__ . '/auth.php';
  $appUrl = getAppUrl();
  $resetUrl = $appUrl . '/api/admin/reset-password?token=' . $token;
  $subject = 'Reset password amministratore - Carte Fedeltà';
  $bodyHtml = '<html><body>';
  $bodyHtml .= '<h2>Reset password amministratore</h2>';
  $bodyHtml .= '<p>Hai richiesto il reset della password per il tuo account amministratore.</p>';
  $bodyHtml .= '<p><a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($resetUrl) . '</a></p>';
  $bodyHtml .= '<p>Questo link scade tra 1 ora.</p>';
  $bodyHtml .= '</body></html>';
  sendMail($email, $subject, $bodyHtml);

  echo json_encode(['success' => true, 'message' => 'Se l\'email è associata a un account amministratore, riceverai un link per il reset.']);
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
