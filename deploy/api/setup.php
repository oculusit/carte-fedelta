<?php

function checkHandler(string $method): void {
  $configPath = __DIR__ . '/config.php';
  $configured = file_exists($configPath);

  $config = getDefaultConfig();
  $dbConnected = null;
  $tablesExist = null;

  if ($configured) {
    require_once $configPath;
    $config = getCurrentConfig();
    try {
      $pdo = createDbConnection(
        DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
      );
      $dbConnected = true;
      $tablesExist = checkTablesExist($pdo, DB_PREFIX);
    } catch (Exception $e) {
      $dbConnected = false;
      $tablesExist = false;
    }
  } else {
    // Check if PHP extensions exist for mail
    $config['extensions']['mail'] = function_exists('mail');
    $config['extensions']['openssl'] = extension_loaded('openssl');
    $config['extensions']['sockets'] = extension_loaded('sockets') || extension_loaded('openssl');
  }

  echo json_encode([
    'configured' => $configured,
    'db_connected' => $dbConnected,
    'tables_exist' => $tablesExist,
    'config' => $config,
    'php_version' => phpversion(),
    'extensions' => [
      'pdo_mysql' => extension_loaded('pdo_mysql'),
      'mail' => function_exists('mail'),
      'openssl' => extension_loaded('openssl'),
    ],
  ]);
}

function testDbHandler(string $method): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }

  $body = jsonBody();
  $host = isset($body['host']) ? $body['host'] : 'localhost';
  $port = isset($body['port']) ? $body['port'] : '3306';
  $name = isset($body['name']) ? $body['name'] : '';
  $user = isset($body['user']) ? $body['user'] : '';
  $pass = isset($body['pass']) ? $body['pass'] : '';
  $prefix = isset($body['prefix']) ? $body['prefix'] : 'cards_';

  try {
    $pdo = createDbConnection($host, $port, $name, $user, $pass, 'utf8mb4');

    // Try to create tables
    $created = createTablesIfNotExist($pdo, $prefix);
    runMigrations($pdo, $prefix);

    echo json_encode([
      'success' => true,
      'message' => 'Connessione al database riuscita.' . ($created ? ' Tabelle create con prefisso "' . $prefix . '".' : ''),
      'tables_exist' => checkTablesExist($pdo, $prefix),
      'info' => getDbInfo($pdo),
    ]);
  } catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'Errore di connessione: ' . $e->getMessage(),
    ]);
  }
}

function testMailHandler(string $method): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }

  $body = jsonBody();
  $mode = isset($body['mode']) ? $body['mode'] : 'mail';
  $testEmail = isset($body['test_email']) ? $body['test_email'] : '';

  if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Inserisci un indirizzo email valido per il test.']);
    return;
  }

  try {
    if ($mode === 'smtp') {
      $result = testSmtpConnection($body);
      if (!$result['success']) {
        echo json_encode($result);
        return;
      }
      // If connection works, try to send
      $sendResult = sendSmtpMail($body, $testEmail);
      echo json_encode($sendResult);
    } else {
      $result = sendPhpMail($body, $testEmail);
      echo json_encode($result);
    }
  } catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'Errore: ' . $e->getMessage(),
    ]);
  }
}

function saveHandler(string $method): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }

  $body = jsonBody();
  $db = isset($body['db']) ? $body['db'] : array();
  $mail = isset($body['mail']) ? $body['mail'] : array();

  // Validate required fields
  $required = ['host', 'port', 'name', 'user', 'pass', 'prefix'];
  $missing = [];
  foreach ($required as $field) {
    if (empty($db[$field]) && $db[$field] !== '') {
      $missing[] = $field;
    }
  }
  if ($missing) {
    http_response_code(400);
    echo json_encode(['error' => 'Campi database mancanti: ' . implode(', ', $missing)]);
    return;
  }

  // Test DB connection first
  try {
    $pdo = createDbConnection($db['host'], $db['port'], $db['name'], $db['user'], $db['pass'], 'utf8mb4');
    createTablesIfNotExist($pdo, $db['prefix']);
    runMigrations($pdo, $db['prefix']);
  } catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
      'error' => 'Impossibile connettersi al database',
      'detail' => $e->getMessage(),
    ]);
    return;
  }

  // Build config content
  $configContent = buildConfigContent($db, $mail);

  // Write config file
  $configPath = __DIR__ . '/config.php';
  $configDir = dirname($configPath);
  if (!is_writable($configDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'La directory ' . $configDir . ' non è scrivibile dal server.']);
    return;
  }

  // Backup existing config if any
  if (file_exists($configPath)) {
    copy($configPath, $configPath . '.backup.' . date('YmdHis'));
  }

  $written = file_put_contents($configPath, $configContent, LOCK_EX);
  if ($written === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Impossibile scrivere il file di configurazione.']);
    return;
  }

  echo json_encode([
    'success' => true,
    'message' => 'Configurazione salvata con successo! Le tabelle sono state create con prefisso "' . $db['prefix'] . '".',
  ]);
}

// ── Helper functions ──

function jsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function createDbConnection(string $host, string $port, string $name, string $user, string $pass, string $charset): PDO {
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}

function getDefaultConfig(): array {
  return [
    'db' => [
      'host' => 'localhost',
      'port' => '3306',
      'name' => 'carte_fedelta',
      'user' => 'root',
      'pass' => '',
      'prefix' => 'cards_',
    ],
    'mail' => [
      'mode' => 'mail',
      'from' => '',
      'from_name' => 'Carte Fedeltà',
      'reply_to' => '',
      'return_path' => '',
      'smtp_host' => '',
      'smtp_port' => '587',
      'smtp_user' => '',
      'smtp_pass' => '',
      'smtp_encryption' => 'tls',
    ],
    'extensions' => [
      'mail' => function_exists('mail'),
      'openssl' => extension_loaded('openssl'),
      'sockets' => true,
    ],
  ];
}

function getCurrentConfig(): array {
  $config = getDefaultConfig();
  if (defined('DB_HOST')) $config['db']['host'] = DB_HOST;
  if (defined('DB_PORT')) $config['db']['port'] = DB_PORT;
  if (defined('DB_NAME')) $config['db']['name'] = DB_NAME;
  if (defined('DB_USER')) $config['db']['user'] = DB_USER;
  if (defined('DB_PASS')) $config['db']['pass'] = DB_PASS;
  if (defined('DB_PREFIX')) $config['db']['prefix'] = DB_PREFIX;
  if (defined('MAIL_MODE')) $config['mail']['mode'] = MAIL_MODE;
  if (defined('MAIL_FROM')) $config['mail']['from'] = MAIL_FROM;
  if (defined('MAIL_FROM_NAME')) $config['mail']['from_name'] = MAIL_FROM_NAME;
  if (defined('MAIL_REPLY_TO')) $config['mail']['reply_to'] = MAIL_REPLY_TO;
  if (defined('MAIL_RETURN_PATH')) $config['mail']['return_path'] = MAIL_RETURN_PATH;
  if (defined('SMTP_HOST')) $config['mail']['smtp_host'] = SMTP_HOST;
  if (defined('SMTP_PORT')) $config['mail']['smtp_port'] = (string)SMTP_PORT;
  if (defined('SMTP_USER')) $config['mail']['smtp_user'] = SMTP_USER;
  if (defined('SMTP_PASS')) $config['mail']['smtp_pass'] = SMTP_PASS;
  if (defined('SMTP_ENCRYPTION')) $config['mail']['smtp_encryption'] = SMTP_ENCRYPTION;
  return $config;
}

function buildConfigContent(array $db, array $mail): string {
  $prefix = isset($db['prefix']) ? $db['prefix'] : 'cards_';
  $dbHost = isset($db['host']) ? $db['host'] : '';
  $dbPort = isset($db['port']) ? $db['port'] : '3306';
  $dbName = isset($db['name']) ? $db['name'] : '';
  $dbUser = isset($db['user']) ? $db['user'] : '';
  $dbPass = isset($db['pass']) ? $db['pass'] : '';

  $from = addslashes(isset($mail['from']) ? $mail['from'] : '');
  $fromName = addslashes(isset($mail['from_name']) ? $mail['from_name'] : 'Carte Fedeltà');
  $replyTo = addslashes(isset($mail['reply_to']) ? $mail['reply_to'] : '');
  $returnPath = addslashes(isset($mail['return_path']) ? $mail['return_path'] : '');
  $smtpHost = addslashes(isset($mail['smtp_host']) ? $mail['smtp_host'] : '');
  $smtpPortValue = isset($mail['smtp_port']) ? $mail['smtp_port'] : '587';
  $smtpUser = addslashes(isset($mail['smtp_user']) ? $mail['smtp_user'] : '');
  $smtpPass = addslashes(isset($mail['smtp_pass']) ? $mail['smtp_pass'] : '');
  $smtpEnc = addslashes(isset($mail['smtp_encryption']) ? $mail['smtp_encryption'] : 'tls');
  $mailMode = isset($mail['mode']) ? $mail['mode'] : 'mail';

  return <<<PHP
<?php

// ─── Database ───
define('DB_HOST', '{$dbHost}');
define('DB_PORT', '{$dbPort}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');
define('DB_PREFIX', '{$prefix}');
define('DB_CHARSET', 'utf8mb4');

define('TABLE_USERS', DB_PREFIX . 'users');
define('TABLE_AUTH_TOKENS', DB_PREFIX . 'auth_tokens');
define('TABLE_CARDS', DB_PREFIX . 'cards');
define('TABLE_CUSTOM_LOGOS', DB_PREFIX . 'custom_logos');
define('TABLE_STORES', DB_PREFIX . 'stores');
define('TABLE_PASSWORD_RESETS', DB_PREFIX . 'password_resets');
define('TABLE_FAMILY_GROUPS', DB_PREFIX . 'family_groups');
define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');
define('TABLE_SETTINGS', DB_PREFIX . 'settings');

// ─── Mail ───
define('MAIL_MODE', '{$mailMode}'); // 'mail' or 'smtp'

define('MAIL_FROM', '{$from}');
define('MAIL_FROM_NAME', '{$fromName}');
define('MAIL_REPLY_TO', '{$replyTo}');
define('MAIL_RETURN_PATH', '{$returnPath}');

define('SMTP_HOST', '{$smtpHost}');
define('SMTP_PORT', {$smtpPortValue});
define('SMTP_USER', '{$smtpUser}');
define('SMTP_PASS', '{$smtpPass}');
define('SMTP_ENCRYPTION', '{$smtpEnc}'); // 'tls' or 'ssl' or ''

// ─── Paths ───
define('UPLOAD_DIR', __DIR__ . '/../uploads/logos/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

PHP;
}

function checkTablesExist(PDO $pdo, string $prefix): array {
  $stmt = $pdo->query('SHOW TABLES LIKE "' . $prefix . '%"');
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  $expected = [
    $prefix . 'users',
    $prefix . 'auth_tokens',
    $prefix . 'cards',
    $prefix . 'custom_logos',
    $prefix . 'stores',
    $prefix . 'password_resets',
  ];

  $result = [];
  foreach ($expected as $table) {
    $result[$table] = in_array($table, $existing);
  }
  return $result;
}

function createTablesIfNotExist(PDO $pdo, string $prefix): bool {
  $p = $prefix;

  $tables = [
    "CREATE TABLE IF NOT EXISTS `{$p}users` (
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
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `{$p}auth_tokens` (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      token VARCHAR(128) NOT NULL UNIQUE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `{$p}cards` (
      id VARCHAR(64) NOT NULL PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      store_name VARCHAR(255) NOT NULL,
      card_number VARCHAR(255) NOT NULL,
      holder_name VARCHAR(255) DEFAULT '',
      barcode_type VARCHAR(32) NOT NULL DEFAULT 'CODE128',
      logo_type ENUM('predefined','upload','none') NOT NULL DEFAULT 'none',
      logo_path VARCHAR(512) DEFAULT '',
      notes TEXT,
      color VARCHAR(7) DEFAULT '#ffffff',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE,
      INDEX idx_user (user_id)
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `{$p}custom_logos` (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      store_name VARCHAR(255) NOT NULL,
      filename VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES `{$p}users`(id) ON DELETE CASCADE,
      UNIQUE KEY uk_user_store (user_id, store_name)
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `{$p}stores` (
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
      FOREIGN KEY (created_by) REFERENCES `{$p}users`(id) ON DELETE SET NULL,
      INDEX idx_status (status)
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS `{$p}password_resets` (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(255) NOT NULL,
      token VARCHAR(128) NOT NULL UNIQUE,
      used TINYINT(1) NOT NULL DEFAULT 0,
      expires_at TIMESTAMP NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_token (token),
      INDEX idx_email (email)
    ) ENGINE=InnoDB",
  ];

  foreach ($tables as $sql) {
    $pdo->exec($sql);
  }

  return true;
}

function checkAdminHandler(string $method, string $uri): void {
  $hasAdmin = false;
  $error = null;
  $configPath = __DIR__ . '/config.php';
  if (file_exists($configPath)) {
    try {
      require_once $configPath;
      $pdo = createDbConnection(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
      // Check if admin exists (don't run full migration — that's handled by index.php)
      $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM ' . TABLE_USERS . ' WHERE is_admin = 1');
      $row = $stmt->fetch();
      $hasAdmin = $row && (int)$row['cnt'] > 0;
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  } else {
    $error = 'config.php non trovato';
  }
  $response = ['has_admin' => $hasAdmin];
  if ($error) $response['error'] = $error;
  echo json_encode($response);
}

function patchConfigFile(): void {
  $path = __DIR__ . '/config.php';
  if (!file_exists($path)) return;

  $content = file_get_contents($path);
  $patched = false;

  if (strpos($content, "TABLE_STORES") === false) {
    $content = str_replace(
      "define('TABLE_CUSTOM_LOGOS', DB_PREFIX . 'custom_logos');",
      "define('TABLE_CUSTOM_LOGOS', DB_PREFIX . 'custom_logos');\ndefine('TABLE_STORES', DB_PREFIX . 'stores');",
      $content,
      $count
    );
    if ($count > 0) $patched = true;
  }

  if (strpos($content, "TABLE_FAMILY_GROUPS") === false) {
    $content = str_replace(
      "define('TABLE_PASSWORD_RESETS', DB_PREFIX . 'password_resets');",
      "define('TABLE_PASSWORD_RESETS', DB_PREFIX . 'password_resets');\ndefine('TABLE_FAMILY_GROUPS', DB_PREFIX . 'family_groups');\ndefine('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');",
      $content,
      $count
    );
    if ($count > 0) $patched = true;
  }

  if (strpos($content, "TABLE_SETTINGS") === false) {
    $content = str_replace(
      "define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');",
      "define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');\ndefine('TABLE_SETTINGS', DB_PREFIX . 'settings');",
      $content,
      $count
    );
    if ($count > 0) $patched = true;
  }

  if ($patched) {
    file_put_contents($path, $content);
  }
}

function runMigrations(PDO $pdo, string $prefix): void {
  patchConfigFile();
  // Add is_admin column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add is_moderator column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN is_moderator TINYINT(1) NOT NULL DEFAULT 0");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add is_active column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add stores table if missing
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}stores` (
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
      FOREIGN KEY (created_by) REFERENCES `{$prefix}users`(id) ON DELETE SET NULL,
      INDEX idx_status (status)
    ) ENGINE=InnoDB");
  } catch (Exception $e) {
    // Table already exists, ignore
  }
  // Add password_resets table if missing
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}password_resets` (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(255) NOT NULL,
      token VARCHAR(128) NOT NULL UNIQUE,
      used TINYINT(1) NOT NULL DEFAULT 0,
      expires_at TIMESTAMP NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_token (token),
      INDEX idx_email (email)
    ) ENGINE=InnoDB");
  } catch (Exception $e) {
    // Table already exists, ignore
  }
  // Add privacy_accepted column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN privacy_accepted TINYINT(1) NOT NULL DEFAULT 0");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add status column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add confirmation_token column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN confirmation_token VARCHAR(128) DEFAULT NULL");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add email_confirmed_at column if missing
  try {
    $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN email_confirmed_at TIMESTAMP NULL DEFAULT NULL");
  } catch (Exception $e) {
    // Column already exists, ignore
  }
  // Add settings table if missing
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
      `key` VARCHAR(64) NOT NULL PRIMARY KEY,
      `value` TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
  } catch (Exception $e) {
    // Table already exists, ignore
  }
}

function createAdminHandler(string $method, string $uri): void {
  if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    return;
  }

  $configPath = __DIR__ . '/config.php';
  if (!file_exists($configPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Configurazione non trovata. Completa prima il setup.']);
    return;
  }
  require_once $configPath;

  $data = jsonBody();
  $email = trim($data['email'] ?? '');
  $password = $data['password'] ?? '';

  if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Inserisci un indirizzo email valido']);
    return;
  }
  if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'La password deve essere di almeno 6 caratteri']);
    return;
  }

  try {
    $pdo = createDbConnection(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);
    runMigrations($pdo, DB_PREFIX);

    $stmt = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      http_response_code(409);
      echo json_encode(['error' => 'Email gi� registrata']);
      return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO ' . TABLE_USERS . ' (email, password_hash, is_admin) VALUES (?, ?, 1)');
    $stmt->execute([$email, $hash]);

    echo json_encode([
      'success' => true,
      'message' => 'Account amministratore creato con successo. Puoi ora accedere con email ' . $email,
    ]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore durante la creazione: ' . $e->getMessage()]);
  }
}

function getDbInfo(PDO $pdo): array {
  $stmt = $pdo->query('SELECT VERSION() AS version');
  $row1 = $stmt->fetch();
  $version = isset($row1['version']) ? $row1['version'] : '';

  $stmt = $pdo->query('SELECT DATABASE() AS dbname');
  $row2 = $stmt->fetch();
  $dbname = isset($row2['dbname']) ? $row2['dbname'] : '';

  return [
    'version' => $version,
    'dbname' => $dbname,
  ];
}

// ─── Mail helpers ───

function sendPhpMail(array $config, string $testEmail): array {
  $from = isset($config['from']) ? $config['from'] : 'noreply@localhost';
  $fromName = isset($config['from_name']) ? $config['from_name'] : 'Carte Fedeltà';
  $replyTo = isset($config['reply_to']) ? $config['reply_to'] : $from;
  $returnPath = isset($config['return_path']) ? $config['return_path'] : $from;

  $subject = 'Test configurazione email - Carte Fedeltà';
  $message = "<html><body><h2>Test email</h2><p>Se ricevi questa email, la configurazione email funziona correttamente.</p><p>Data: " . date('d/m/Y H:i:s') . "</p></body></html>";

  $headers = [
    'From: ' . $fromName . ' <' . $from . '>',
    'Reply-To: ' . $replyTo,
    'Return-Path: ' . $returnPath,
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: Carte-Fedelta/1.0',
    'Message-ID: <' . uniqid('carte-') . '@' . parse_url($from, PHP_URL_HOST) . '>',
  ];

  $additionalParams = '-f ' . $returnPath . ' -F ' . escapeshellarg($fromName);

  $sent = mail($testEmail, $subject, $message, implode("\r\n", $headers), $additionalParams);

  if ($sent) {
    return [
      'success' => true,
      'message' => 'Email di test inviata a ' . $testEmail . '. Controlla la posta (incluso spam).',
    ];
  } else {
    return [
      'success' => false,
      'message' => 'Invio fallito. Verifica la configurazione del server PHP mail().',
    ];
  }
}

function testSmtpConnection(array $config): array {
  $host = isset($config['smtp_host']) ? $config['smtp_host'] : '';
  $port = (int)(isset($config['smtp_port']) ? $config['smtp_port'] : 587);
  $user = isset($config['smtp_user']) ? $config['smtp_user'] : '';
  $pass = isset($config['smtp_pass']) ? $config['smtp_pass'] : '';
  $encryption = isset($config['smtp_encryption']) ? $config['smtp_encryption'] : 'tls';

  if (empty($host)) {
    return ['success' => false, 'message' => 'Host SMTP non specificato.'];
  }

  $errno = 0;
  $errstr = '';

  if ($encryption === 'ssl') {
    $remote = 'ssl://' . $host . ':' . $port;
  } else {
    $remote = $host . ':' . $port;
  }

  $socket = @stream_socket_client($remote, $errno, $errstr, 10);
  if (!$socket) {
    return ['success' => false, 'message' => 'Connessione a ' . $host . ':' . $port . ' fallita: ' . $errstr];
  }

  // Read server banner
  $banner = fgets($socket, 512);

  // EHLO
  fputs($socket, "EHLO carte-fedelta\r\n");
  $ehloResponse = '';
  while ($line = fgets($socket, 512)) {
    $ehloResponse .= $line;
    if (substr($line, 3, 1) === ' ') break;
  }

  // STARTTLS if needed
  if ($encryption === 'tls' && strpos($ehloResponse, 'STARTTLS') !== false) {
    fputs($socket, "STARTTLS\r\n");
    $starttlsResponse = fgets($socket, 512);
    if (substr($starttlsResponse, 0, 3) !== '220') {
      fclose($socket);
      return ['success' => false, 'message' => 'STARTTLS fallito: ' . $starttlsResponse];
    }
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    // Re-EHLO after STARTTLS
    fputs($socket, "EHLO carte-fedelta\r\n");
    $ehloResponse = '';
    while ($line = fgets($socket, 512)) {
      $ehloResponse .= $line;
      if (substr($line, 3, 1) === ' ') break;
    }
  }

  // AUTH LOGIN
  if (!empty($user) && !empty($pass)) {
    fputs($socket, "AUTH LOGIN\r\n");
    $authResponse = fgets($socket, 512);
    if (substr($authResponse, 0, 3) !== '334') {
      fclose($socket);
      return ['success' => false, 'message' => 'AUTH LOGIN non supportato dal server.'];
    }

    fputs($socket, base64_encode($user) . "\r\n");
    $userResponse = fgets($socket, 512);
    if (substr($userResponse, 0, 3) !== '334') {
      fclose($socket);
      return ['success' => false, 'message' => 'Autenticazione fallita: utente non riconosciuto.'];
    }

    fputs($socket, base64_encode($pass) . "\r\n");
    $passResponse = fgets($socket, 512);
    if (substr($passResponse, 0, 3) !== '235') {
      fclose($socket);
      return ['success' => false, 'message' => 'Autenticazione fallita: password errata.'];
    }
  }

  // Send test email
  $from = isset($config['from']) ? $config['from'] : $user;
  $fromName = isset($config['from_name']) ? $config['from_name'] : 'Carte Fedeltà';
  $replyTo = isset($config['reply_to']) ? $config['reply_to'] : $from;

  $subject = '=?UTF-8?B?' . base64_encode('Test configurazione email - Carte Fedeltà') . '?=';
  $body = "<html><body><h2>Test email</h2><p>Se ricevi questa email, la configurazione SMTP funziona correttamente.</p><p>Data: " . date('d/m/Y H:i:s') . "</p></body></html>";

  $headers = [
    'From: ' . $fromName . ' <' . $from . '>',
    'Reply-To: ' . $replyTo,
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: Carte-Fedelta/1.0',
    'Message-ID: <' . uniqid('carte-') . '@' . parse_url($from, PHP_URL_HOST) . '>',
  ];

  fputs($socket, "MAIL FROM:<" . $from . ">\r\n");
  $mfResponse = fgets($socket, 512);
  if (substr($mfResponse, 0, 3) !== '250') {
    fclose($socket);
    return ['success' => false, 'message' => 'MAIL FROM fallito: ' . trim($mfResponse)];
  }

  fputs($socket, "RCPT TO:<" . $testEmail . ">\r\n");
  $rcptResponse = fgets($socket, 512);
  if (substr($rcptResponse, 0, 3) !== '250') {
    fclose($socket);
    return ['success' => false, 'message' => 'RCPT TO fallito per ' . $testEmail . ': ' . trim($rcptResponse)];
  }

  fputs($socket, "DATA\r\n");
  $dataResponse = fgets($socket, 512);
  if (substr($dataResponse, 0, 3) !== '354') {
    fclose($socket);
    return ['success' => false, 'message' => 'DATA fallito: ' . trim($dataResponse)];
  }

  $message = 'Subject: ' . $subject . "\r\n";
  $message .= implode("\r\n", $headers) . "\r\n";
  $message .= "\r\n";
  $message .= $body;
  $message .= "\r\n.\r\n";

  fputs($socket, $message);
  $endResponse = fgets($socket, 512);

  fputs($socket, "QUIT\r\n");
  fclose($socket);

  if (substr($endResponse, 0, 3) === '250') {
    return [
      'success' => true,
      'message' => 'Email di test inviata con successo a ' . $testEmail . ' via SMTP.',
    ];
  } else {
    return [
      'success' => false,
      'message' => 'Invio email fallito: ' . trim($endResponse),
    ];
  }
}

function sendSmtpMail(array $config, string $testEmail): array {
  return testSmtpConnection($config);
}
