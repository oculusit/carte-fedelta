<?php

function getDb(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $pdo;
}

function jsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function hashPassword(string $password): string {
  return password_hash($password, PASSWORD_BCRYPT);
}

function generateToken(): string {
  return bin2hex(random_bytes(32));
}

// TOTP helpers
function generateSecret(): string {
  $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $secret = '';
  for ($i = 0; $i < 32; $i++) {
    $secret .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $secret;
}

function totp(string $secret, int $timeSlice = null): string {
  if ($timeSlice === null) {
    $timeSlice = intdiv(time(), 30);
  }
  $key = base32Decode($secret);
  $msg = pack('J', $timeSlice);
  $hash = hash_hmac('sha1', $msg, $key, true);
  $offset = ord($hash[19]) & 0x0f;
  $code = (
    (ord($hash[$offset]) & 0x7f) << 24 |
    (ord($hash[$offset + 1]) & 0xff) << 16 |
    (ord($hash[$offset + 2]) & 0xff) << 8 |
    (ord($hash[$offset + 3]) & 0xff)
  ) % 1000000;
  return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function totpVerify(string $secret, string $code): bool {
  $now = intdiv(time(), 30);
  for ($i = -1; $i <= 1; $i++) {
    if (totp($secret, $now + $i) === $code) {
      return true;
    }
  }
  return false;
}

function base32Decode(string $input): string {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $input = strtoupper($input);
  $input = str_replace('=', '', $input);
  $bits = '';
  for ($i = 0; $i < strlen($input); $i++) {
    $val = strpos($alphabet, $input[$i]);
    if ($val === false) continue;
    $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
  }
  $result = '';
  for ($i = 0; $i + 7 < strlen($bits); $i += 8) {
    $result .= chr(bindec(substr($bits, $i, 8)));
  }
  return $result;
}

function registerHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $email = trim($data['email'] ?? '');
  $password = $data['password'] ?? '';
  $privacyAccepted = !empty($data['privacy_accepted']);

  // Check if registration is allowed
  $allowReg = true;
  try {
    $dbCheck = getDb();
    $stmt = $dbCheck->prepare('SELECT `value` FROM ' . TABLE_SETTINGS . ' WHERE `key` = ?');
    $stmt->execute(['allow_registration']);
    $row = $stmt->fetch();
    if ($row && $row['value'] === '0') {
      $allowReg = false;
    }
  } catch (Exception $e) {}
  if (!$allowReg) {
    http_response_code(403);
    echo json_encode(['error' => 'Registrazione disabilitata dall\'amministratore']);
    return;
  }

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
  if (!$privacyAccepted) {
    http_response_code(400);
    echo json_encode(['error' => 'Devi accettare l\'informativa sulla privacy']);
    return;
  }

  $db = getDb();
  $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email già registrata']);
    return;
  }

  $confirmationToken = generateToken();
  $stmt = $db->prepare('INSERT INTO ' . TABLE_USERS . ' (email, password_hash, privacy_accepted, status, confirmation_token) VALUES (?, ?, 1, \'pending\', ?)');
  $stmt->execute([$email, hashPassword($password), $confirmationToken]);

  // Send confirmation email
  $appUrl = getAppUrl() . '/#/confirm-email/' . $confirmationToken;
  $subject = 'Conferma registrazione - Carte Fedeltà';
  $bodyHtml = '<html><body>';
  $bodyHtml .= '<h2>Conferma la tua registrazione</h2>';
  $bodyHtml .= '<p>Clicca sul link seguente per confermare il tuo indirizzo email:</p>';
  $bodyHtml .= '<p><a href="' . htmlspecialchars($appUrl) . '">' . htmlspecialchars($appUrl) . '</a></p>';
  $bodyHtml .= '<p>Se non ti sei registrato tu, ignora questa email.</p>';
  $bodyHtml .= '</body></html>';
  sendMail($email, $subject, $bodyHtml);

  echo json_encode(['success' => true, 'message' => 'Registrazione completata. Controlla la tua email per confermare l\'account.']);
}

function confirmEmailHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $token = trim($data['token'] ?? '');

  if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token mancante']);
    return;
  }

  $db = getDb();
  $stmt = $db->prepare('SELECT id, email_confirmed_at FROM ' . TABLE_USERS . ' WHERE confirmation_token = ?');
  $stmt->execute([$token]);
  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(400);
    echo json_encode(['error' => 'Token non valido']);
    return;
  }

  if ($user['email_confirmed_at']) {
    echo json_encode(['success' => true, 'message' => 'Email già confermata. Attendi l\'approvazione di un amministratore.']);
    return;
  }

  $stmt = $db->prepare('UPDATE ' . TABLE_USERS . ' SET email_confirmed_at = NOW(), confirmation_token = NULL WHERE id = ?');
  $stmt->execute([$user['id']]);

  echo json_encode(['success' => true, 'message' => 'Email confermata con successo! Attendi l\'approvazione di un amministratore per accedere.']);
}

function loginHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $email = trim($data['email'] ?? '');
  $password = $data['password'] ?? '';

  $db = getDb();
  $stmt = $db->prepare('SELECT id, email, password_hash, 2fa_enabled, 2fa_secret, is_admin, is_moderator, is_active, status, email_confirmed_at FROM ' . TABLE_USERS . ' WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenziali non valide']);
    return;
  }

  if (!$user['is_active']) {
    http_response_code(403);
    echo json_encode(['error' => 'Account disabilitato. Contatta l\'amministratore.']);
    return;
  }

  // Check status
  if (isset($user['status']) && $user['status'] !== 'approved') {
    if ($user['status'] === 'pending') {
      $msg = $user['email_confirmed_at']
        ? 'Registrazione in attesa di approvazione da parte di un amministratore.'
        : 'Devi prima confermare la tua email. Controlla la posta in arrivo.';
      http_response_code(403);
      echo json_encode(['error' => $msg]);
      return;
    }
    if ($user['status'] === 'rejected') {
      http_response_code(403);
      echo json_encode(['error' => 'La tua registrazione è stata rifiutata. Contatta l\'amministratore.']);
      return;
    }
  }

  if ($user['2fa_enabled']) {
    echo json_encode(['require_2fa' => true, 'user_id' => (int)$user['id']]);
    return;
  }

  $token = generateToken();
  $stmt = $db->prepare('INSERT INTO ' . TABLE_AUTH_TOKENS . ' (user_id, token) VALUES (?, ?)');
  $stmt->execute([$user['id'], $token]);

  echo json_encode([
    'token' => $token,
    'user_id' => (int)$user['id'],
    'email' => $user['email'],
    'is_admin' => (int)($user['is_admin'] ?? 0),
    'is_moderator' => (int)($user['is_moderator'] ?? 0),
  ]);
}

function setup2faHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $userId = authenticate();
  $db = getDb();

  $secret = generateSecret();
  $stmt = $db->prepare('UPDATE ' . TABLE_USERS . ' SET 2fa_secret = ?, 2fa_enabled = 0 WHERE id = ?');
  $stmt->execute([$secret, $userId]);

  $email = '';
  $stmt = $db->prepare('SELECT email FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  if ($row = $stmt->fetch()) {
    $email = $row['email'];
  }

  $issuer = rawurlencode('Carte Fedeltà');
  $userEnc = rawurlencode($email);
  $otpauth = "otpauth://totp/{$issuer}:{$userEnc}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";

  echo json_encode(['secret' => $secret, 'otpauth_url' => $otpauth]);
}

function verify2faHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $userId = (int)($data['user_id'] ?? 0);
  $code = $data['code'] ?? '';

  $db = getDb();
  $stmt = $db->prepare('SELECT id, email, 2fa_secret, 2fa_enabled, is_admin, is_moderator FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non trovato']);
    return;
  }

  if (!totpVerify($user['2fa_secret'], $code)) {
    http_response_code(401);
    echo json_encode(['error' => 'Codice 2FA non valido']);
    return;
  }

  if (!$user['2fa_enabled']) {
    $stmt = $db->prepare('UPDATE ' . TABLE_USERS . ' SET 2fa_enabled = 1 WHERE id = ?');
    $stmt->execute([$userId]);
  }

  $token = generateToken();
  $stmt = $db->prepare('INSERT INTO ' . TABLE_AUTH_TOKENS . ' (user_id, token) VALUES (?, ?)');
  $stmt->execute([$userId, $token]);

  echo json_encode([
    'token' => $token,
    'user_id' => (int)$user['id'],
    'email' => $user['email'],
    'is_admin' => (int)($user['is_admin'] ?? 0),
    'is_moderator' => (int)($user['is_moderator'] ?? 0),
  ]);
}

function meHandler(string $method, string $uri): void {
  if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $userId = authenticate();
  $db = getDb();
  $stmt = $db->prepare('SELECT id, email, 2fa_enabled, is_admin, is_moderator, is_active, status FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Utente non trovato']);
    return;
  }
  echo json_encode($user);
}

function forgotPasswordHandler(string $method, string $uri): void {
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

  $db = getDb();
  $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  // Always return success to avoid leaking whether the email exists
  if (!$user) {
    echo json_encode(['success' => true, 'message' => 'Se l\'email è registrata, riceverai un link per il reset della password.']);
    return;
  }

  $token = generateToken();
  $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

  $stmt = $db->prepare('INSERT INTO ' . TABLE_PASSWORD_RESETS . ' (email, token, expires_at) VALUES (?, ?, ?)');
  $stmt->execute([$email, $token, $expires]);

  $appUrl = getAppUrl() . '/#/reset-password/' . $token;
  $subject = 'Reset password - Carte Fedeltà';
  $bodyHtml = '<html><body>';
  $bodyHtml .= '<h2>Reset della password</h2>';
  $bodyHtml .= '<p>Hai richiesto il reset della password per il tuo account su Carte Fedeltà.</p>';
  $bodyHtml .= '<p>Clicca sul link seguente per impostare una nuova password (valido 1 ora):</p>';
  $bodyHtml .= '<p><a href="' . htmlspecialchars($appUrl) . '">' . htmlspecialchars($appUrl) . '</a></p>';
  $bodyHtml .= '<p>Se non hai richiesto tu il reset, ignora questa email.</p>';
  $bodyHtml .= '</body></html>';

  sendMail($email, $subject, $bodyHtml);

  echo json_encode(['success' => true, 'message' => 'Se l\'email è registrata, riceverai un link per il reset della password.']);
}

function resetPasswordHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }
  $data = jsonBody();
  $token = trim($data['token'] ?? '');
  $password = $data['password'] ?? '';

  if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token mancante']);
    return;
  }
  if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password troppo corta (min 6 caratteri)']);
    return;
  }

  $db = getDb();
  $stmt = $db->prepare('SELECT email, used, expires_at FROM ' . TABLE_PASSWORD_RESETS . ' WHERE token = ?');
  $stmt->execute([$token]);
  $row = $stmt->fetch();

  if (!$row) {
    http_response_code(400);
    echo json_encode(['error' => 'Token non valido']);
    return;
  }
  if ($row['used']) {
    http_response_code(400);
    echo json_encode(['error' => 'Token già utilizzato']);
    return;
  }
  if (strtotime($row['expires_at']) < time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Token scaduto. Richiedi un nuovo reset.']);
    return;
  }

  $hash = hashPassword($password);
  $db->beginTransaction();
  try {
    $stmt = $db->prepare('UPDATE ' . TABLE_USERS . ' SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $row['email']]);
    $stmt = $db->prepare('UPDATE ' . TABLE_PASSWORD_RESETS . ' SET used = 1 WHERE token = ?');
    $stmt->execute([$token]);
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Password reimpostata con successo. Puoi ora accedere con la nuova password.']);
  } catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Errore durante il reset: ' . $e->getMessage()]);
  }
}

function getMailSetting(string $key, string $default = ''): string {
  static $dbSettings = null;
  if ($dbSettings === null) {
    $dbSettings = [];
    try {
      if (defined('TABLE_SETTINGS')) {
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM " . TABLE_SETTINGS . " WHERE `key` LIKE ?");
        $stmt->execute(['mail_%']);
        foreach ($stmt->fetchAll() as $row) {
          $dbSettings[$row['key']] = $row['value'];
        }
      }
    } catch (Exception $e) {
      // DB settings unavailable, fall back to constants
    }
  }
  if (isset($dbSettings[$key])) {
    return $dbSettings[$key];
  }
  $constName = strtoupper($key);
  return defined($constName) ? constant($constName) : $default;
}

function sendMail(string $to, string $subject, string $bodyHtml): void {
  $mode = getMailSetting('mail_mode', 'mail');
  $from = getMailSetting('mail_from', 'noreply@localhost');
  $fromName = getMailSetting('mail_from_name', 'Carte Fedeltà');
  $replyTo = getMailSetting('mail_reply_to', $from);
  $returnPath = getMailSetting('mail_return_path', $from);

  $headers = [
    'From: ' . $fromName . ' <' . $from . '>',
    'Reply-To: ' . $replyTo,
    'Return-Path: ' . $returnPath,
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: Carte-Fedelta/1.0',
  ];

  if ($mode === 'smtp') {
    sendSmtpMailRaw($to, $subject, $bodyHtml, $headers, $from, $fromName);
  } else {
    $additionalParams = '-f ' . $returnPath . ' -F ' . escapeshellarg($fromName);
    mail($to, $subject, $bodyHtml, implode("\r\n", $headers), $additionalParams);
  }
}

function getSetting(string $key, string $default = ''): string {
  static $allSettings = null;
  if ($allSettings === null) {
    $allSettings = [];
    try {
      if (defined('TABLE_SETTINGS')) {
        $pdo = getDb();
        $stmt = $pdo->query("SELECT `key`, `value` FROM " . TABLE_SETTINGS);
        foreach ($stmt->fetchAll() as $row) {
          $allSettings[$row['key']] = $row['value'];
        }
      }
    } catch (Exception $e) {}
  }
  return $allSettings[$key] ?? $default;
}

function getAppUrl(): string {
  $dbUrl = getSetting('app_url', '');
  if ($dbUrl !== '') {
    return rtrim($dbUrl, '/');
  }
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  // API lives in /api/ subdirectory — go one level up for the app root
  $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
  if ($path === '/' || $path === '\\') {
    $path = '';
  }
  return $scheme . '://' . $host . $path;
}

function getSmtpSetting(string $key, string $default = ''): string {
  static $dbSettings = null;
  if ($dbSettings === null) {
    $dbSettings = [];
    try {
      if (defined('TABLE_SETTINGS')) {
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM " . TABLE_SETTINGS . " WHERE `key` LIKE ?");
        $stmt->execute(['smtp_%']);
        foreach ($stmt->fetchAll() as $row) {
          $dbSettings[$row['key']] = $row['value'];
        }
      }
    } catch (Exception $e) {
    }
  }
  if (isset($dbSettings[$key])) {
    return $dbSettings[$key];
  }
  $constName = strtoupper($key);
  return defined($constName) ? constant($constName) : $default;
}

function sendSmtpMailRaw(string $to, string $subject, string $bodyHtml, array $headers, string $from, string $fromName): void {
  $host = getSmtpSetting('smtp_host', '');
  $port = (int)getSmtpSetting('smtp_port', '587');
  $user = getSmtpSetting('smtp_user', '');
  $pass = getSmtpSetting('smtp_pass', '');
  $encryption = getSmtpSetting('smtp_encryption', 'tls');
  if (!$host) return;

  $errno = 0;
  $errstr = '';
  $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
  $socket = @stream_socket_client($remote, $errno, $errstr, 10);
  if (!$socket) return;

  fgets($socket, 512);
  fputs($socket, "EHLO carte-fedelta\r\n");
  $ehlo = '';
  while ($line = fgets($socket, 512)) {
    $ehlo .= $line;
    if (substr($line, 3, 1) === ' ') break;
  }
  if ($encryption === 'tls' && strpos($ehlo, 'STARTTLS') !== false) {
    fputs($socket, "STARTTLS\r\n");
    $resp = fgets($socket, 512);
    if (substr($resp, 0, 3) === '220') {
      stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
      fputs($socket, "EHLO carte-fedelta\r\n");
      while ($line = fgets($socket, 512)) {
        if (substr($line, 3, 1) === ' ') break;
      }
    }
  }
  if ($user && $pass) {
    fputs($socket, "AUTH LOGIN\r\n"); fgets($socket, 512);
    fputs($socket, base64_encode($user) . "\r\n"); fgets($socket, 512);
    fputs($socket, base64_encode($pass) . "\r\n"); fgets($socket, 512);
  }
  fputs($socket, "MAIL FROM:<$from>\r\n"); fgets($socket, 512);
  fputs($socket, "RCPT TO:<$to>\r\n"); fgets($socket, 512);
  fputs($socket, "DATA\r\n"); fgets($socket, 512);
  $msg = 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
  $msg .= implode("\r\n", $headers) . "\r\n\r\n" . $bodyHtml . "\r\n.\r\n";
  fputs($socket, $msg); fgets($socket, 512);
  fputs($socket, "QUIT\r\n");
  fclose($socket);
}

function registerStatusHandler(string $method, string $uri): void {
  $allowed = true;
  try {
    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT `value` FROM ' . TABLE_SETTINGS . ' WHERE `key` = ?');
    $stmt->execute(['allow_registration']);
    $row = $stmt->fetch();
    if ($row && $row['value'] === '0') {
      $allowed = false;
    }
  } catch (Exception $e) {}
  echo json_encode(['allow_registration' => $allowed]);
}

function authenticate(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = getDb();
  $stmt = $db->prepare('SELECT user_id FROM ' . TABLE_AUTH_TOKENS . ' WHERE token = ?');
  $stmt->execute([$token]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Token non valido']);
    exit;
  }
  return (int)$row['user_id'];
}
