<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../logos.php';
require_once __DIR__ . '/../../migrate.php';

if (!defined('BACKEND_VERSION')) define('BACKEND_VERSION', '1.2.5.back');

// ── Global error handler: intercept DB/duplicate errors and return a usable message ──
set_exception_handler(function (Throwable $e) {
  $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']);
  $isPdo = $e instanceof PDOException;
  $dupEntry = $isPdo && (int)$e->getCode() === 23000;

  if ($dupEntry) {
    $msg = 'C\'è già un negozio con lo stesso nome, oppure un dato duplicato nel database.';
    if (preg_match("/Duplicate entry '([^']*)' for key '([^']+)'/", $e->getMessage(), $m)) {
      $msg = 'Esiste già un record con il valore "' . $m[1] . '" (per il campo "' . $m[2] . '"). Puoi eliminare o modificare la riga errata dalla lista Loghi Approvati.';
    }
  } else {
    $msg = 'Si è verificato un errore nel database: ' . $e->getMessage();
  }

  if ($isPost) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg, 'reset' => true]);
    exit;
  }

  http_response_code(500);
  ?><!DOCTYPE html>
  <html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Errore - Carte Fedeltà</title>
  <style>
  *{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
  .card{background:#fff;border-radius:12px;padding:32px;width:480px;max-width:100%;box-shadow:0 2px 12px rgba(0,0,0,.08);text-align:center}
  h1{font-size:22px;color:#c5221f;margin-bottom:12px}
  p{font-size:14px;color:#555;margin-bottom:18px;line-height:1.5}
  .wrap{background:#fdecec;border:1px solid #f5c6c6;border-radius:8px;padding:12px;font-size:13px;color:#8a1f1f;margin-bottom:18px;word-break:break-word}
  .btn{display:inline-block;padding:10px 20px;background:#1a73e8;color:#fff;border:none;border-radius:8px;font-size:14px;text-decoration:none;cursor:pointer;margin:4px}
  .btn:hover{background:#1557b0}
  .btn.secondary{background:#6c757d}.btn.secondary:hover{background:#5a6268}
  </style>
  </head><body><div class="card">
  <h1>&#9888; Errore nel database</h1>
  <p>Non è stato possibile completare l'operazione a causa di un problema con i dati dei negozi.</p>
  <div class="wrap"><?= htmlspecialchars($msg) ?></div>
  <p style="font-size:13px;color:#777">Vai sui <strong>Loghi Approvati</strong>, trova il negozio indicato e modificane il nome o eliminalo con il pulsante <strong>Elimina</strong>, poi ricarica la pagina.</p>
  <a class="btn" href="?">&#8635; Torna al pannello</a>
  <a class="btn secondary" href="?action_reload=1" onclick="event.preventDefault();location.reload()">Ricarica pagina</a>
  </div></body></html><?php
  exit;
});
ignore_user_abort(false);

// Run migration on admin panel too (separate entry point from api/index.php)
try {
  $mDb = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  migrateRun($mDb);
  $mDb = null;
} catch (Exception $e) {}

$scriptUrl = strtok($_SERVER['REQUEST_URI'], '?');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $scheme . '://' . $host;
$scriptUrlDir = rtrim($scriptUrl, '/') . '/';

function panelDb(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function panelIsLoggedIn(): bool {
  return !empty($_SESSION['panel_user_id']);
}

function panelUser(): ?array {
  if (!panelIsLoggedIn()) return null;
  $db = panelDb();
  $stmt = $db->prepare('SELECT id, email, admin_role, is_active FROM ' . TABLE_USERS . ' WHERE id = ? AND is_admin = 1');
  $stmt->execute([$_SESSION['panel_user_id']]);
  return $stmt->fetch() ?: null;
}

function panelIsSuperAdmin(): bool {
  $u = panelUser();
  return $u && ($u['admin_role'] === 'superadmin' || $u['admin_role'] === null);
}

function toDataUrl($imageData): string {
  if (empty($imageData)) return '';
  if (preg_match('/^data:image\//', $imageData)) return $imageData;
  if (preg_match('/^[A-Za-z0-9+\/]/', $imageData) && strlen($imageData) > 100) {
    return 'data:image/webp;base64,' . $imageData;
  }
  return '';
}

// ── Handle login ──
if (isset($_POST['login'])) {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $db = panelDb();
  $stmt = $db->prepare('SELECT id, password_hash, is_admin, is_active, admin_role FROM ' . TABLE_USERS . ' WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if ($user && $user['is_admin'] && $user['is_active'] && password_verify($password, $user['password_hash'])) {
    $_SESSION['panel_user_id'] = (int)$user['id'];
    header('Location: ' . $baseUrl . $scriptUrlDir);
    exit;
  }
  header('Location: ' . $baseUrl . $scriptUrlDir . '?error=1');
  exit;
}

// ── Handle logout ──
if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: ' . $baseUrl . $scriptUrlDir);
  exit;
}

// ── Handle password recovery ──
if (isset($_POST['forgot_email_submit'])) {
  $email = trim($_POST['forgot_email_val'] ?? '');
  $db = panelDb();
  $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ? AND is_admin = 1');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $db->prepare('INSERT INTO ' . TABLE_PASSWORD_RESETS . ' (email, token, expires_at) VALUES (?, ?, ?)')->execute([$email, $token, $expires]);
    $resetUrl = $baseUrl . $scriptUrlDir . '?reset_token=' . $token;
    $subject = 'Reset password amministratore - Carte Fedeltà';
    $body = '<html><body><h2>Reset password</h2><p>Clicca per reimpostare:</p><p><a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($resetUrl) . '</a></p><p>Scade tra 1 ora.</p></body></html>';
    sendMail($email, $subject, $body);
  }
  header('Location: ' . $baseUrl . $scriptUrlDir . '?msg=email_inviata');
  exit;
}

if (isset($_GET['reset_token'])) {
  $token = $_GET['reset_token'];
  $db = panelDb();
  $stmt = $db->prepare('SELECT email, used, expires_at FROM ' . TABLE_PASSWORD_RESETS . ' WHERE token = ?');
  $stmt->execute([$token]);
  $row = $stmt->fetch();
  if ($row && !$row['used'] && strtotime($row['expires_at']) > time()) {
    $_SESSION['reset_token'] = $token;
  }
}

if (isset($_POST['reset_password'])) {
  $token = $_SESSION['reset_token'] ?? '';
  $newPass = $_POST['new_password'] ?? '';
  if ($token && strlen($newPass) >= 6) {
    $db = panelDb();
    $stmt = $db->prepare('SELECT email FROM ' . TABLE_PASSWORD_RESETS . ' WHERE token = ? AND used = 0 AND expires_at > NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
      $hash = password_hash($newPass, PASSWORD_BCRYPT);
      $db->prepare('UPDATE ' . TABLE_USERS . ' SET password_hash = ? WHERE email = ?')->execute([$hash, $row['email']]);
      $db->prepare('UPDATE ' . TABLE_PASSWORD_RESETS . ' SET used = 1 WHERE token = ?')->execute([$token]);
      unset($_SESSION['reset_token']);
      header('Location: ' . $baseUrl . $scriptUrlDir . '?msg=password_cambiata');
      exit;
    }
  }
  header('Location: ' . $baseUrl . $scriptUrlDir . '?error_reset=1');
  exit;
}

// ── Handle admin actions (AJAX) ──
if (panelIsLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  $db = panelDb();
  $action = $_POST['action'];

  if ($action === 'create_admin') {
    if (!panelIsSuperAdmin()) { echo json_encode(['error' => 'Permesso negato']); exit; }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
      echo json_encode(['error' => 'Dati non validi']); exit;
    }
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) { echo json_encode(['error' => 'Email già esistente']); exit; }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare('INSERT INTO ' . TABLE_USERS . ' (email, password_hash, is_admin, admin_role, status) VALUES (?, ?, 1, ?, \'approved\')')->execute([$email, $hash, $role === 'superadmin' ? 'superadmin' : 'admin']);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'delete_admin') {
    if (!panelIsSuperAdmin()) { echo json_encode(['error' => 'Permesso negato']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['panel_user_id']) { echo json_encode(['error' => 'Non puoi eliminare te stesso']); exit; }
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE is_admin = 1 AND (admin_role IS NULL OR admin_role = \'superadmin\') AND id != ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) { echo json_encode(['error' => 'Impossibile eliminare l\'ultimo superadmin']); exit; }
    $db->prepare('UPDATE ' . TABLE_USERS . ' SET is_admin = 0, admin_role = NULL WHERE id = ? AND is_admin = 1')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'approve_logo') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT id, store_name, image_data FROM ' . TABLE_PENDING_LOGOS . ' WHERE id = ? AND status = \'pending\'');
    $stmt->execute([$id]);
    $logo = $stmt->fetch();
    if (!$logo) { echo json_encode(['error' => 'Logo non trovato']); exit; }
    $uploadDir = __DIR__ . '/../../../uploads/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $safeName = str_replace(['/', '\\', "\0"], '_', $logo['store_name']) . '.webp';
    $logoData = null;
    $imageData = $logo['image_data'] ?? '';
    if (!empty($imageData)) {
      $raw = $imageData;
      if (strpos($raw, 'base64,') !== false) $raw = substr($raw, strpos($raw, 'base64,') + 7);
      $decoded = base64_decode($raw, true);
      if ($decoded !== false && strlen($decoded) > 100) {
        file_put_contents($uploadDir . $safeName, $decoded);
        $logoData = $imageData;
      }
    }
    // Upsert into cards_stores so "Negozi con Logo" shows it
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_STORES . ' WHERE LOWER(name) = LOWER(?)');
    $stmt->execute([$logo['store_name']]);
    $existing = $stmt->fetch();
    if ($existing) {
      $fields = ['logo_type = ?', 'logo_path = ?'];
      $params = ['upload', $safeName];
      if ($logoData) { $fields[] = 'logo_data = ?'; $params[] = $logoData; }
      $params[] = $existing['id'];
      $db->prepare('UPDATE ' . TABLE_STORES . ' SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    } else {
      $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, logo_type, logo_path, logo_data, status) VALUES (?, \'upload\', ?, ?, \'approved\')')->execute([$logo['store_name'], $safeName, $logoData]);
    }
    $db->prepare('UPDATE ' . TABLE_PENDING_LOGOS . ' SET status = \'approved\', reviewed_at = NOW() WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'reject_logo') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare('UPDATE ' . TABLE_PENDING_LOGOS . ' SET status = \'rejected\', reviewed_at = NOW() WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'delete_custom_logo') {
    $filename = basename($_POST['filename'] ?? '');
    $path = __DIR__ . '/../../../uploads/logos/' . $filename;
    if (file_exists($path)) unlink($path);
    $hidden = __DIR__ . '/../../../uploads/logos/' . pathinfo($filename, PATHINFO_FILENAME) . '.hidden';
    if (file_exists($hidden)) unlink($hidden);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'upload_store_logo') {
    if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
      echo json_encode(['error' => 'File non caricato']); exit;
    }
    $name = trim($_POST['store_name'] ?? '');
    $aliases = trim($_POST['aliases'] ?? '');
    $color = trim($_POST['color'] ?? '');
    if (!$name) { echo json_encode(['error' => 'Nome negozio obbligatorio']); exit; }
    if ($color === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = null;
    $f = $_FILES['logo_file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['webp','png','jpg','jpeg','svg'])) { echo json_encode(['error' => 'Formato non supportato (webp, png, jpg, svg)']); exit; }
    $uploadDir = __DIR__ . '/../../../uploads/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $safeName = str_replace(['/', '\\', "\0"], '_', $name) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], $uploadDir . $safeName);
    // Also store base64 in logo_data so logos.php lookup works
    $logoData = null;
    $fileData = file_get_contents($uploadDir . $safeName);
    if ($fileData !== false) {
      $mime = mime_content_type($uploadDir . $safeName) ?: 'image/' . $ext;
      $logoData = 'data:' . $mime . ';base64,' . base64_encode($fileData);
    }
    // Prevent duplicate name collision on insert
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_STORES . ' WHERE LOWER(name) = LOWER(?)');
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
      echo json_encode(['error' => 'Esiste già un negozio con il nome "' . $name . '"']); exit;
    }
    $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, logo_type, logo_path, logo_data, aliases, color, status) VALUES (?, \'upload\', ?, ?, ?, ?, \'approved\')')->execute([$name, $safeName, $logoData, $aliases, $color]);
    $reqId = (int)($_POST['request_id'] ?? 0);
    if ($reqId) {
      $db->prepare('UPDATE ' . DB_PREFIX . 'missing_logos SET resolved = 1 WHERE id = ?')->execute([$reqId]);
    }
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'update_store') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $aliases = trim($_POST['aliases'] ?? '');
    $color = trim($_POST['color'] ?? '');
    if (!$name || !$id) { echo json_encode(['error' => 'Dati mancanti']); exit; }
    if ($color === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = null;
    // Prevent duplicate name collision: if another store (different id) already has this name
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_STORES . ' WHERE LOWER(name) = LOWER(?) AND id != ?');
    $stmt->execute([$name, $id]);
    if ($stmt->fetch()) {
      echo json_encode(['error' => 'Esiste già un negozio con il nome "' . $name . '"']); exit;
    }
    $fields = ['name = ?', 'aliases = ?', 'color = ?'];
    $params = [$name, $aliases, $color];
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
      $f = $_FILES['logo_file'];
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['webp','png','jpg','jpeg','svg'])) {
        $uploadDir = __DIR__ . '/../../../uploads/logos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $safeName = str_replace(['/', '\\', "\0"], '_', $name) . '.' . $ext;
        move_uploaded_file($f['tmp_name'], $uploadDir . $safeName);
        $fields[] = 'logo_type = ?';
        $params[] = 'upload';
        $fields[] = 'logo_path = ?';
        $params[] = $safeName;
        // Update logo_data with base64
        $fileData = file_get_contents($uploadDir . $safeName);
        if ($fileData !== false) {
          $mime = mime_content_type($uploadDir . $safeName) ?: 'image/' . $ext;
          $fields[] = 'logo_data = ?';
          $params[] = 'data:' . $mime . ';base64,' . base64_encode($fileData);
        }
      }
    }
    $params[] = $id;
    $db->prepare('UPDATE ' . TABLE_STORES . ' SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'delete_store') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID mancante']); exit; }
    // Delete the logo file on disk too, otherwise the file-sync block re-creates the row
    try {
      $stmt = $db->prepare('SELECT id, name, logo_path FROM ' . TABLE_STORES . ' WHERE id = ?');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if ($row && !empty($row['logo_path'])) {
        $uploadDir = __DIR__ . '/../../../uploads/logos/';
        $file = basename($row['logo_path']);
        $path = $uploadDir . $file;
        if ($file && $file !== '.' && $file !== '..' && file_exists($path)) @unlink($path);
        // Also remove any file matching the store name with known extensions
        $safeCheck = str_replace(['/', '\\', "\0"], '_', $row['name']);
        foreach (['webp','png','jpg','jpeg','svg'] as $ext) {
          $p = $uploadDir . $safeCheck . '.' . $ext;
          if (file_exists($p)) @unlink($p);
        }
      }
    } catch (Exception $e) {}
    $db->prepare('DELETE FROM ' . TABLE_STORES . ' WHERE id = ?')->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'reset_user_password') {
    if (!panelIsSuperAdmin()) { echo json_encode(['error' => 'Permesso negato']); exit; }
    $uid = (int)($_POST['user_id'] ?? 0);
    $newPass = trim($_POST['new_password'] ?? '');
    if (!$uid || strlen($newPass) < 6) { echo json_encode(['error' => 'Dati non validi (min 6 caratteri)']); exit; }
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE id = ?');
    $stmt->execute([$uid]);
    if (!$stmt->fetch()) { echo json_encode(['error' => 'Utente non trovato']); exit; }
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $db->prepare('UPDATE ' . TABLE_USERS . ' SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'save_mail_config') {
    if (!panelIsSuperAdmin()) { echo json_encode(['error' => 'Permesso negato']); exit; }
    $allowed = ['mail_mode','mail_from','mail_from_name','mail_reply_to','mail_return_path','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption'];
    $upsert = $db->prepare('INSERT INTO ' . TABLE_SETTINGS . ' (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    foreach ($allowed as $key) {
      $val = $_POST[$key] ?? '';
      if ($key === 'smtp_port') $val = (string)(int)$val;
      $upsert->execute([$key, $val]);
    }
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'create_store_from_request') {
    $id = (int)($_POST['id'] ?? 0);
    $storeName = trim($_POST['store_name'] ?? '');
    if (!$storeName) { echo json_encode(['error' => 'Nome negozio obbligatorio']); exit; }
    // Prevent duplicate name collision
    $dup = $db->prepare('SELECT id FROM ' . TABLE_STORES . ' WHERE LOWER(name) = LOWER(?)');
    $dup->execute([$storeName]);
    if ($dup->fetch()) {
      echo json_encode(['error' => 'Esiste già un negozio con il nome "' . $storeName . '"']); exit;
    }
    $color = trim($_POST['color'] ?? '');
    if ($color === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = null;
    $logoData = null;
    if (!empty($_FILES['logo_file']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
      $allowed = ['webp','png','jpg','jpeg','svg'];
      if (!in_array($ext, $allowed)) { echo json_encode(['error' => 'Estensione non valida']); exit; }
      $safeName = str_replace(['/', '\\', "\0"], '_', $storeName) . '.' . $ext;
      $uploadDir = __DIR__ . '/../../../uploads/logos/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
      if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $safeName)) {
        $mime = mime_content_type($uploadDir . $safeName) ?: 'image/' . $ext;
        $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($uploadDir . $safeName));
        try {
          $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, logo_type, logo_path, logo_data, color, status) VALUES (?, \'upload\', ?, ?, ?, \'approved\')')->execute([$storeName, $safeName, $logoData, $color]);
        } catch (Exception $e) {
          echo json_encode(['error' => 'Esiste già un negozio con il nome "' . $storeName . '"']); exit;
        }
      }
    } else {
      try {
        $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, color, status) VALUES (?, ?, \'approved\')')->execute([$storeName, $color]);
      } catch (Exception $e) {
        echo json_encode(['error' => 'Esiste già un negozio con il nome "' . $storeName . '"']); exit;
      }
    }
    if ($id) {
      $db->prepare('UPDATE ' . DB_PREFIX . 'missing_logos SET resolved = 1 WHERE id = ?')->execute([$id]);
    }
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'dismiss_request') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      $db->prepare('UPDATE ' . DB_PREFIX . 'missing_logos SET resolved = 1 WHERE id = ?')->execute([$id]);
    }
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'test_mail') {
    if (!panelIsSuperAdmin()) { echo json_encode(['error' => 'Permesso negato']); exit; }
    $testEmail = trim($_POST['test_email'] ?? '');
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) { echo json_encode(['error' => 'Email non valida']); exit; }
    $ok = @mail($testEmail, 'Test Carte Fedeltà', '<h2>Test riuscito</h2><p>Data: ' . date('d/m/Y H:i') . '</p>', 'MIME-Version: 1.0' . "\r\n" . 'Content-Type: text/html; charset=UTF-8' . "\r\n" . 'From: ' . (getMailSetting('mail_from', 'noreply@localhost')));
    echo json_encode($ok ? ['success' => true, 'message' => 'Email inviata a ' . $testEmail] : ['error' => 'Invio fallito. Verifica mail() su questo server.']);
    exit;
  }

  if ($action === 'change_password') {
    $uid = $_SESSION['panel_user_id'];
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    if (strlen($newPass) < 6) { echo json_encode(['error' => 'Min 6 caratteri']); exit; }
    $stmt = $db->prepare('SELECT password_hash FROM ' . TABLE_USERS . ' WHERE id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($current, $user['password_hash'])) { echo json_encode(['error' => 'Password attuale errata']); exit; }
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $db->prepare('UPDATE ' . TABLE_USERS . ' SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
    echo json_encode(['success' => true]);
    exit;
  }

  if ($action === 'save_version_config') {
    if (!panelIsSuperAdmin()) { echo json_encode(['error' => 'Permesso negato']); exit; }
    $version = trim($_POST['app_version'] ?? '');
    $downloadUrl = trim($_POST['app_download_url'] ?? '');
    $table = TABLE_SETTINGS;
    $db->prepare("INSERT INTO `{$table}` (`key`, `value`) VALUES ('app_version', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$version]);
    $db->prepare("INSERT INTO `{$table}` (`key`, `value`) VALUES ('app_download_url', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$downloadUrl]);
    echo json_encode(['success' => true]);
    exit;
  }

  echo json_encode(['error' => 'Azione sconosciuta']);
  exit;
}

// ── Password reset form ──
if (isset($_SESSION['reset_token'])) {
  ?><!DOCTYPE html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password - Admin</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:12px;padding:32px;width:360px;box-shadow:0 2px 12px rgba(0,0,0,.08)}h1{font-size:20px;margin-bottom:16px}input{width:100%;padding:10px 14px;border:2px solid #ddd;border-radius:8px;font-size:14px;margin-bottom:12px}input:focus{outline:none;border-color:#1a73e8}button{width:100%;padding:10px;background:#1a73e8;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer}button:hover{background:#1557b0}.msg{color:#16a34a;font-size:13px;margin-bottom:12px}.err{color:#c5221f;font-size:13px;margin-bottom:12px}</style>
</head><body><div class="card">
<h1>Nuova Password</h1>
<?php if (isset($_GET['error_reset'])): ?><p class="err">Token non valido o scaduto.</p><?php endif; ?>
<form method="post"><input type="hidden" name="reset_password" value="1">
<input type="password" name="new_password" placeholder="Nuova password (min 6)" autofocus required />
<button type="submit">Reimposta Password</button></form>
</div></body></html><?php exit;
}

// ── Login page ──
if (!panelIsLoggedIn()) {
  ?><!DOCTYPE html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin - Carte Fedeltà</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:12px;padding:32px;width:360px;box-shadow:0 2px 12px rgba(0,0,0,.08)}h1{font-size:20px;margin-bottom:4px}.sub{color:#666;font-size:13px;margin-bottom:20px}input{width:100%;padding:10px 14px;border:2px solid #ddd;border-radius:8px;font-size:14px;margin-bottom:12px}input:focus{outline:none;border-color:#1a73e8}button{width:100%;padding:10px;background:#1a73e8;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer}button:hover{background:#1557b0}.err{color:#c5221f;font-size:13px;margin-bottom:12px}.links{margin-top:16px;text-align:center}.links a{color:#1a73e8;font-size:13px;text-decoration:none}.links a:hover{text-decoration:underline}</style>
</head><body><div class="card">
<h1>Area Amministrazione</h1>
<p class="sub">Carte Fedeltà</p>
<?php if (isset($_GET['error'])): ?><p class="err">Credenziali non valide</p><?php endif; ?>
<?php if (isset($_GET['msg'])): ?><p style="color:#16a34a;font-size:13px;margin-bottom:12px"><?php echo $_GET['msg'] === 'email_inviata' ? 'Email di reset inviata.' : 'Password cambiata con successo.'; ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="login" value="1">
<input type="email" name="email" placeholder="Email" autofocus required />
<input type="password" name="password" placeholder="Password" required />
<button type="submit">Accedi</button></form>
<div class="links"><a href="?show_forgot=1">Password dimenticata?</a></div>
</div></body></html><?php exit;
}

if (isset($_GET['show_forgot'])) {
  ?><!DOCTYPE html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recupero Password - Admin</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:12px;padding:32px;width:360px;box-shadow:0 2px 12px rgba(0,0,0,.08)}h1{font-size:20px;margin-bottom:16px}input{width:100%;padding:10px 14px;border:2px solid #ddd;border-radius:8px;font-size:14px;margin-bottom:12px}input:focus{outline:none;border-color:#1a73e8}button{width:100%;padding:10px;background:#1a73e8;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer}button:hover{background:#1557b0}.back{display:block;text-align:center;margin-top:16px;color:#1a73e8;font-size:13px;text-decoration:none}</style>
</head><body><div class="card">
<h1>Recupero Password</h1>
<p style="font-size:13px;color:#666;margin-bottom:16px">Inserisci la tua email. Riceverai un link per reimpostare la password.</p>
<form method="post"><input type="hidden" name="forgot_email" value="1">
<input type="email" name="forgot_email_val" placeholder="Email amministratore" required />
<button type="submit" name="forgot_email_submit" value="1">Invia link di reset</button></form>
<a class="back" href="<?php echo $baseUrl . $scriptUrlDir; ?>">Torna al login</a>
</div></body></html><?php exit;
}

// ── Authenticated panel ──
$user = panelUser();
$isSuper = panelIsSuperAdmin();
$db = panelDb();

$pendingCount = 0;
try { $r = $db->query('SELECT COUNT(*) FROM ' . TABLE_PENDING_LOGOS . ' WHERE status = \'pending\''); $pendingCount = (int)$r->fetchColumn(); } catch(Exception $e) {}
$adminCount = 0;
try { $r = $db->query('SELECT COUNT(*) FROM ' . TABLE_USERS . ' WHERE is_admin = 1'); $adminCount = (int)$r->fetchColumn(); } catch(Exception $e) {}
$customCount = 0;
try { $r = $db->query('SELECT COUNT(*) FROM ' . TABLE_STORES); $customCount = (int)$r->fetchColumn(); } catch(Exception $e) {}
$uploadDir = __DIR__ . '/../../../uploads/logos/';

$admins = [];
if ($isSuper) {
  $admins = $db->query('SELECT id, email, admin_role, is_active, created_at FROM ' . TABLE_USERS . ' WHERE is_admin = 1 ORDER BY created_at')->fetchAll();
}

$pendingLogos = $db->prepare('SELECT p.*, u.email as user_email FROM ' . TABLE_PENDING_LOGOS . ' p LEFT JOIN ' . TABLE_USERS . ' u ON u.id = p.user_id WHERE p.status = ? ORDER BY p.created_at DESC');
$pendingLogos->execute(['pending']);
$pendingLogos = $pendingLogos->fetchAll();

$mailSettings = [];
try { $mailSettings = $db->query('SELECT `key`, `value` FROM ' . TABLE_SETTINGS . ' WHERE `key` LIKE \'mail_%\' OR `key` LIKE \'smtp_%\'')->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e) {}

$missingStores = [];
$missingCount = 0;
try {
  $r = $db->query('SELECT COUNT(*) FROM ' . DB_PREFIX . 'missing_logos WHERE resolved = 0');
  $missingCount = (int)$r->fetchColumn();
  $missingStores = $db->query('SELECT id, store_name, reported_at FROM ' . DB_PREFIX . 'missing_logos WHERE resolved = 0 ORDER BY reported_at DESC')->fetchAll();
} catch(Exception $e) {}

$customFiles = is_dir($uploadDir) ? array_diff(scandir($uploadDir), ['.', '..']) : [];
$hiddenLogos = [];
foreach ($customFiles as $f) { if (preg_match('/^(.+)\.hidden$/', $f, $m)) $hiddenLogos[$m[1]] = true; }

// Sync: create cards_stores entries for logo files missing from the table
$imgExts = ['webp','png','jpg','jpeg','svg'];
$imgFiles = array_filter($customFiles, function($f) use ($imgExts) { return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $imgExts); });
if ($imgFiles) {
  $existingNames = [];
  try { $rows = $db->query('SELECT name FROM ' . TABLE_STORES)->fetchAll(); $existingNames = array_map('strtolower', array_map('trim', array_column($rows, 'name'))); } catch(Exception $e) {}
  foreach ($imgFiles as $f) {
    $storeName = trim(pathinfo($f, PATHINFO_FILENAME));
    if (in_array(strtolower($storeName), $existingNames)) continue;
    $mime = mime_content_type($uploadDir . $f) ?: 'image/' . pathinfo($f, PATHINFO_EXTENSION);
    $b64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($uploadDir . $f));
    try {
      $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, logo_type, logo_path, logo_data, status) VALUES (?, \'upload\', ?, ?, \'approved\')')->execute([$storeName, $f, $b64]);
    } catch (Exception $e) {
      // Skip if name already exists (trailing-space / collation edge cases)
    }
    $existingNames[] = strtolower($storeName);
  }
}

$predefined = getPredefinedLogos();

$sortMode = $_GET['sort'] ?? 'name';
$sortMode = in_array($sortMode, ['name', 'downloads'], true) ? $sortMode : 'name';
$orderBy = $sortMode === 'downloads' ? 'downloads DESC, name ASC' : 'name ASC';

// Letter filter (0 = non-alphabetic, a-z = first letter)
$letter = strtoupper(trim($_GET['letter'] ?? ''));
if ($letter !== '' && !preg_match('/^[A-Z0-9]$/', $letter)) $letter = '';
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = '';
$params = [];
if ($letter !== '') {
  if ($letter === '0') {
    $where = "WHERE (name NOT REGEXP '^[a-zA-Z]')";
  } else {
    $where = "WHERE name LIKE ? COLLATE utf8mb4_bin";
    $params[] = $letter . '%';
  }
}

$stores = [];
try {
  $countStmt = $db->prepare('SELECT COUNT(*) FROM ' . TABLE_STORES . ' ' . $where);
  $countStmt->execute($params);
  $totalStores = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalStores / $perPage));
  if ($page > $totalPages) $page = $totalPages;
  $offset = ($page - 1) * $perPage;
  $sql = 'SELECT id, name, aliases, color, downloads, logo_type, logo_path, logo_data, status, LENGTH(logo_data) AS logo_size_bytes FROM ' . TABLE_STORES . ' ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $stores = $stmt->fetchAll();
} catch(Exception $e) { $totalStores = 0; $totalPages = 1; }

// All stores (unfiltered, unpaginated) used only for the infographic
$allStores = [];
try { $allStores = $db->query('SELECT id, name, logo_type, logo_path, logo_data FROM ' . TABLE_STORES)->fetchAll(); } catch(Exception $e) {}
?><!DOCTYPE html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin - Carte Fedeltà</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f0f2f5;color:#1a1a2e;min-height:100vh}
.topbar{background:#1a73e8;color:#fff;padding:12px 24px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100}
.topbar h1{font-size:18px;font-weight:600}
.topbar-right{display:flex;align-items:center;gap:16px}
.topbar-right span{font-size:13px;opacity:.85}
.topbar a{color:#fff;text-decoration:none;font-size:13px;opacity:.8;cursor:pointer}
.topbar a:hover{opacity:1}
.nav{background:#fff;border-bottom:1px solid #e0e0e0;padding:0 24px;display:flex;gap:0;overflow-x:auto;position:sticky;top:48px;z-index:99}
.nav a{padding:12px 16px;font-size:13px;font-weight:500;color:#666;text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap;cursor:pointer}
.nav a:hover{color:#1a73e8}
.nav a.active{color:#1a73e8;border-bottom-color:#1a73e8}
.nav .badge{background:#e53935;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;vertical-align:middle}
.container{max-width:960px;margin:0 auto;padding:24px}
.section{display:none}.section.active{display:block}
.card{background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.card h2{font-size:16px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.card h3{font-size:14px;margin-bottom:12px;color:#555}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.stat{background:#f8f9fa;border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:background .15s,box-shadow .15s}
.stat:hover{background:#e8f0fe;box-shadow:0 2px 8px rgba(26,115,232,.15)}
.stat .num{font-size:28px;font-weight:700;color:#1a73e8}
.stat .label{font-size:12px;color:#888;margin-top:4px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=url],select{padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;width:100%;background:#fff;color:#1a1a2e}
textarea{padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;width:100%;background:#fff;color:#1a1a2e;min-height:90px;resize:vertical}
input:focus,select:focus{outline:none;border-color:#1a73e8}
label{display:block;font-size:13px;font-weight:500;color:#555;margin-bottom:4px}
.field{margin-bottom:14px}
.btn{padding:10px 20px;border-radius:8px;border:none;font-size:14px;cursor:pointer;font-weight:500;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:#1a73e8;color:#fff}.btn-primary:hover{background:#1557b0}
.btn-danger{background:#e53935;color:#fff}.btn-danger:hover{background:#c62828}
.btn-success{background:#16a34a;color:#fff}.btn-success:hover{background:#15803d}
.btn-outline{background:none;border:2px solid #ddd;color:#333}.btn-outline:hover{border-color:#999}
.btn-sm{padding:6px 14px;font-size:12px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #f0f0f0}
th{font-weight:600;color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
tr:hover td{background:#f8f9fa}
.tag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
.tag-pending{background:#fff3cd;color:#856404}
.tag-approved{background:#d4edda;color:#155724}
.tag-rejected{background:#f8d7da;color:#721c24}
.tag-super{background:#e8eaf6;color:#283593}
.letter-filter{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px}
.letter-filter a{display:inline-block;min-width:30px;padding:6px 8px;text-align:center;background:#f0f2f5;color:#333;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.letter-filter a:hover{background:#e0e4e9}
.letter-filter a.active{background:#1a73e8;color:#fff}
.pagination{display:flex;gap:4px;flex-wrap:wrap;margin-top:14px;justify-content:center}
.pagination a{display:inline-block;min-width:32px;padding:6px 10px;text-align:center;background:#f0f2f5;color:#333;text-decoration:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer}
.pagination a:hover{background:#e0e4e9}
.pagination a.active{background:#1a73e8;color:#fff}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:10px 24px;border-radius:8px;font-size:13px;z-index:9999;display:none}
.toast.show{display:block}
.logo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.logo-item{border:2px solid #eee;border-radius:8px;padding:12px;text-align:center;position:relative}
.logo-item .preview{width:56px;height:56px;border-radius:8px;margin:0 auto 8px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff}
.logo-item .preview img{width:100%;height:100%;object-fit:contain}
.logo-item .name{font-size:13px;font-weight:600;margin-bottom:2px}
.logo-item .sub{font-size:11px;color:#999}
.logo-item .del-btn{position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(220,38,38,.9);color:#fff;border:none;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.logo-item:hover .del-btn{opacity:1}
.pending-img{width:120px;height:75px;object-fit:contain;border-radius:6px;border:1px solid #eee;background:#f9f9f9}
.flex-row{display:flex;gap:8px;align-items:center}
.mt-8{margin-top:8px}.mt-16{margin-top:16px}
</style>
</head><body>

<div class="topbar">
  <h1>Carte Fedeltà — Admin</h1>
  <div class="topbar-right">
    <span><?= htmlspecialchars($user['email']) ?> (<?= $isSuper ? 'Superadmin' : 'Admin' ?>)</span>
    <span style="opacity:.6;font-size:12px">backend v<?= htmlspecialchars(BACKEND_VERSION) ?></span>
    <a href="?logout=1">Esci</a>
  </div>
</div>

<div class="nav">
  <a class="active" onclick="showSection('dashboard')">Dashboard</a>
  <a onclick="showSection('logos-queue')">Coda Loghi<?php if ($pendingCount > 0): ?><span class="badge"><?= $pendingCount ?></span><?php endif; ?></a>
  <a onclick="showSection('custom-logos')">Loghi Approvati</a>
  <a onclick="showSection('logo-infographic')">Infografica Loghi</a>
  <a onclick="showSection('missing-stores')">Negozi Richiesti<?php if ($missingCount > 0): ?><span class="badge"><?= $missingCount ?></span><?php endif; ?></a>
  <?php if ($isSuper): ?>
  <a onclick="showSection('admins')">Amministratori</a>
  <a onclick="showSection('email-config')">Email</a>
  <a onclick="showSection('updates')">Aggiornamenti</a>
  <?php endif; ?>
  <a onclick="showSection('password')">Password</a>
</div>

<div class="container">

<!-- Dashboard -->
<div id="dashboard" class="section active">
  <div class="grid">
    <div class="stat" style="cursor:pointer" onclick="showSection('logos-queue')"><div class="num"><?= $pendingCount ?></div><div class="label">Loghi in attesa</div></div>
    <div class="stat" style="cursor:pointer" onclick="showSection('custom-logos')"><div class="num"><?= $customCount ?></div><div class="label">Loghi approvati</div></div>
    <div class="stat" style="cursor:pointer" onclick="showSection('missing-stores')"><div class="num"><?= $missingCount ?></div><div class="label">Negozi richiesti</div></div>
    <?php if ($isSuper): ?>
    <div class="stat" style="cursor:pointer" onclick="showSection('admins')"><div class="num"><?= $adminCount ?></div><div class="label">Amministratori</div></div>
    <?php endif; ?>
  </div>
  <?php if ($pendingCount > 0): ?>
  <div class="card mt-16">
    <h2>Loghi da approvare</h2>
    <table>
      <tr><th>Negozio</th><th>Utente</th><th>Data</th><th>Azione</th></tr>
      <?php foreach ($pendingLogos as $p): ?>
      <tr>
        <td><strong><?= htmlspecialchars($p['store_name']) ?></strong></td>
        <td><?= htmlspecialchars($p['user_email'] ?? 'Anonimo') ?></td>
        <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
        <td class="flex-row">
          <?php $imgSrc = toDataUrl($p['image_data']); ?>
          <?php if ($imgSrc): ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" class="pending-img" />
          <?php else: ?>
            <span style="font-size:12px;color:#999">nessuna immagine</span>
          <?php endif; ?>
          <button class="btn btn-success btn-sm" onclick="approveLogo(<?= $p['id'] ?>)">Approva</button>
          <button class="btn btn-danger btn-sm" onclick="rejectLogo(<?= $p['id'] ?>)">Rifiuta</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pending logos queue -->
<div id="logos-queue" class="section">
  <div class="card">
    <h2>Coda Loghi in Attesa</h2>
    <?php if (empty($pendingLogos)): ?>
    <p style="color:#999;font-size:14px">Nessun logo in attesa di approvazione.</p>
    <?php else: ?>
    <table>
      <tr><th>Anteprima</th><th>Negozio</th><th>Utente</th><th>Data</th><th>Azioni</th></tr>
      <?php foreach ($pendingLogos as $p): ?>
      <tr>
        <td>
          <?php $imgSrc = toDataUrl($p['image_data']); ?>
          <?php if ($imgSrc): ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" class="pending-img" />
          <?php else: ?>
            <span style="font-size:12px;color:#999">nessuna immagine</span>
          <?php endif; ?>
        </td>
        <td><strong><?= htmlspecialchars($p['store_name']) ?></strong></td>
        <td><?= htmlspecialchars($p['user_email'] ?? 'Anonimo') ?></td>
        <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
        <td class="flex-row">
          <button class="btn btn-success btn-sm" onclick="approveLogo(<?= $p['id'] ?>)">✓ Approva</button>
          <button class="btn btn-danger btn-sm" onclick="rejectLogo(<?= $p['id'] ?>)">✕ Rifiuta</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
</div>

<!-- Missing stores requests -->
<div id="missing-stores" class="section">
  <div class="card">
    <h2>Negozi Richiesti dagli Utenti</h2>
    <?php if (empty($missingStores)): ?>
    <p style="color:#999;font-size:14px">Nessun negozio in attesa.</p>
    <?php else: ?>
    <table>
      <tr><th>Nome Negozio</th><th>Richiesto il</th><th>Azioni</th></tr>
      <?php foreach ($missingStores as $ms): ?>
      <tr>
        <td><strong><?= htmlspecialchars($ms['store_name']) ?></strong></td>
        <td><?= date('d/m/Y H:i', strtotime($ms['reported_at'])) ?></td>
        <td class="flex-row">
          <button class="btn btn-success btn-sm" onclick="createStoreFromRequest(<?= (int)$ms['id'] ?>, '<?= htmlspecialchars($ms['store_name'], ENT_QUOTES) ?>')">Crea Negozio</button>
          <button class="btn btn-danger btn-sm" onclick="dismissRequest(<?= (int)$ms['id'] ?>)">Ignora</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Custom logos + Stores -->
<div id="custom-logos" class="section">
  <div class="card">
    <h2>Carica Nuovo Logo</h2>
    <form onsubmit="uploadStore(event)" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
      <div class="field" style="flex:1;min-width:180px"><label>Nome negozio</label><input type="text" id="new-store-name" required placeholder="es. NaturaSì" /></div>
      <div class="field" style="flex:2;min-width:250px"><label>Alias (uno per riga)</label><textarea id="new-store-aliases" rows="4" placeholder="natura si&#10;naturasi&#10;natura sì" style="height:160px"></textarea></div>
      <div class="field" style="min-width:120px"><label>Colore</label><input type="color" id="new-store-color" value="#e30613" /></div>
      <div class="field" style="min-width:160px"><label>Logo (webp/png/jpg/svg)</label><input type="file" id="new-store-file" accept=".webp,.png,.jpg,.jpeg,.svg" required onchange="previewAndCrop(this, 'new')" /></div>
      <button type="submit" class="btn btn-primary">Carica</button>
    </form>
    <div id="crop-preview-new" style="margin-top:12px;display:none"></div>
  </div>

  <div class="card">
    <h2>Loghi Approvati (<?= $totalStores ?>)</h2>
    <?php if (empty($stores)): ?>
    <p style="color:#999;font-size:14px">Nessun negozio registrato.</p>
    <?php else: ?>
      <div class="letter-filter">
        <a href="?sort=<?= $sortMode ?>">Tutti</a>
        <a href="?letter=0&sort=<?= $sortMode ?>" class="<?= $letter === '0' ? 'active' : '' ?>">0</a>
        <?php foreach (range('A', 'Z') as $L): ?>
        <a href="?letter=<?= $L ?>&sort=<?= $sortMode ?>" class="<?= $letter === $L ? 'active' : '' ?>"><?= $L ?></a>
        <?php endforeach; ?>
      </div>
      <table>
      <tr>
        <th><a href="?sort=name&letter=<?= $letter ?>&page=<?= $page ?>" style="text-decoration:none;color:inherit">Negozio <?= $sortMode === 'name' ? '↓' : '' ?></a></th>
        <th>Logo</th>
        <th>Alias</th>
        <th>Colore</th>
        <th><a href="?sort=downloads&letter=<?= $letter ?>&page=<?= $page ?>" style="text-decoration:none;color:inherit" title="Ordina per numero di download">Download <?= $sortMode === 'downloads' ? '↓' : '' ?></a></th>
        <th></th>
      </tr>
      <?php foreach ($stores as $s):
        $logoFile = $s['logo_path'] ?? '';
        $logoExists = $logoFile && file_exists($uploadDir . $logoFile);
        // Fallback: check if a logo file exists matching store name (various extensions)
        if (!$logoExists && is_dir($uploadDir)) {
          $safeCheck = str_replace(['/', '\\', "\0"], '_', $s['name']);
          foreach (['webp','png','jpg','jpeg','svg'] as $ext) {
            if (file_exists($uploadDir . $safeCheck . '.' . $ext)) {
              $logoFile = $safeCheck . '.' . $ext;
              $logoExists = true;
              break;
            }
          }
        }
        // Also check logo_data (base64)
        $logoDataSrc = null;
        if (!$logoExists && !empty($s['logo_data']) && preg_match('/^data:image\//', $s['logo_data'])) {
          $logoDataSrc = $s['logo_data'];
        }
        $aliasList = array_filter(array_map('trim', explode("\n", $s['aliases'] ?? '')));
      ?>
      <tr id="store-<?= $s['id'] ?>">
        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
        <td>
          <?php if ($logoExists): ?>
            <img src="../../../uploads/logos/<?= htmlspecialchars($logoFile) ?>" style="width:40px;height:40px;object-fit:contain;border-radius:4px;background:#f5f5f5;padding:2px" />
          <?php elseif ($logoDataSrc): ?>
            <img src="<?= htmlspecialchars($logoDataSrc) ?>" style="width:40px;height:40px;object-fit:contain;border-radius:4px;background:#f5f5f5;padding:2px" />
          <?php else: ?>
            <span style="color:#ccc;font-size:12px">nessuno</span>
          <?php endif; ?>
        </td>
        <td style="max-width:260px">
          <?php if ($aliasList): ?>
            <?php foreach ($aliasList as $a): ?>
              <span class="tag tag-approved" style="margin:1px"><?= htmlspecialchars($a) ?></span>
            <?php endforeach; ?>
          <?php else: ?>
            <span style="color:#ccc">-</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($s['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $s['color'])): ?>
            <span style="display:inline-block;width:18px;height:18px;border-radius:4px;background:<?= htmlspecialchars($s['color']) ?>;border:1px solid #ddd;vertical-align:middle"></span>
            <span style="font-size:12px;color:#666;margin-left:4px"><?= htmlspecialchars($s['color']) ?></span>
          <?php else: ?>
            <span style="color:#ccc;font-size:12px">-</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center;font-weight:600"><?= (int)($s['downloads'] ?? 0) ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-outline btn-sm" onclick="editStore(<?= (int)$s['id'] ?>)">Modifica</button>
          <button class="btn btn-danger btn-sm" onclick="deleteStore(<?= (int)$s['id'] ?>)">Elimina</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?sort=<?= $sortMode ?>&letter=<?= $letter ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?sort=<?= $sortMode ?>&letter=<?= $letter ?>&page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?sort=<?= $sortMode ?>&letter=<?= $letter ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Edit Store Modal -->
<div id="edit-store-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;display:none;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:480px;max-width:95vw;max-height:85vh;overflow-y:auto;position:relative">
    <button onclick="closeEditStore()" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    <h2 style="margin-bottom:16px">Modifica Negozio</h2>
    <form onsubmit="saveStore(event)" enctype="multipart/form-data">
      <input type="hidden" id="edit-store-id" />
      <div class="field"><label>Nome negozio</label><input type="text" id="edit-store-name" required /></div>
      <div class="field"><label>Alias (uno per riga)</label><textarea id="edit-store-aliases" rows="4" style="height:160px"></textarea></div>
      <div class="field"><label>Colore</label><input type="color" id="edit-store-color" value="#e30613" /></div>
      <div class="field"><label>Logo (lascia vuoto per non cambiare)</label><input type="file" id="edit-store-file" accept=".webp,.png,.jpg,.jpeg,.svg" onchange="previewAndCrop(this, 'edit')" /></div>
      <div id="edit-store-preview" style="margin-bottom:12px"></div>
      <div id="crop-preview-edit" style="margin-top:8px;display:none"></div>
      <button type="submit" class="btn btn-primary">Salva Modifiche</button>
    </form>
  </div>
</div>

<!-- Crop Modal -->
<div id="crop-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:300;display:none;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:20px;max-width:520px;width:95vw;position:relative">
    <h3 style="margin-bottom:12px">Ritaglia Logo (8:5.5)</h3>
    <div style="position:relative;overflow:hidden;background:#1a1a2e;border-radius:8px;touch-action:none" id="crop-viewport">
      <canvas id="crop-canvas" style="display:block;width:100%"></canvas>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-top:12px">
      <span style="font-size:12px;color:#666">Zoom</span>
      <input type="range" id="crop-zoom" min="0.2" max="4" step="0.01" value="1" style="flex:1" oninput="cropZoomChanged()" />
      <span id="crop-zoom-pct" style="font-size:12px;color:#666">100%</span>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
      <button class="btn btn-outline btn-sm" onclick="closeCropModal()">Annulla</button>
      <button class="btn btn-primary btn-sm" onclick="applyCropModal()">Applica Ritaglio</button>
    </div>
  </div>
</div>

<?php if ($isSuper): ?>
<!-- Admin management -->
<div id="admins" class="section">
  <div class="card">
    <h2>Gestione Amministratori</h2>
    <table>
      <tr><th>Email</th><th>Ruolo</th><th>Stato</th><th>Creato</th><th></th></tr>
      <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= htmlspecialchars($a['email']) ?></td>
        <td><span class="tag <?= $a['admin_role'] === 'superadmin' ? 'tag-super' : 'tag-approved' ?>"><?= $a['admin_role'] ?: 'superadmin' ?></span></td>
        <td><span class="tag <?= $a['is_active'] ? 'tag-approved' : 'tag-rejected' ?>"><?= $a['is_active'] ? 'Attivo' : 'Disattivo' ?></span></td>
        <td><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
        <td style="white-space:nowrap">
          <button class="btn btn-outline btn-sm" onclick="resetUserPassword(<?= $a['id'] ?>, '<?= htmlspecialchars($a['email']) ?>')">Reset Password</button>
          <?php if ($a['id'] !== $_SESSION['panel_user_id']): ?>
          <button class="btn btn-danger btn-sm" onclick="if(confirm('Rimuovere i privilegi di amministratore?'))deleteAdmin(<?= $a['id'] ?>)">Rimuovi</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="card mt-16" style="background:#f8f9fa;box-shadow:none;border:1px solid #e0e0e0">
      <h3>Nuovo Amministratore</h3>
      <form onsubmit="createAdmin(event)">
        <div class="field"><label>Email</label><input type="email" id="new-admin-email" required /></div>
        <div class="field"><label>Password</label><input type="password" id="new-admin-pass" required minlength="6" /></div>
        <div class="field"><label>Ruolo</label><select id="new-admin-role"><option value="admin">Admin (solo loghi)</option><option value="superadmin">Superadmin (tutto)</option></select></div>
        <button type="submit" class="btn btn-primary">Crea Amministratore</button>
      </form>
    </div>
  </div>
</div>

<!-- Email config -->
<div id="email-config" class="section">
  <div class="card">
    <h2>Configurazione Email</h2>
    <form onsubmit="saveMailConfig(event)">
      <div class="field"><label>Modalità</label>
        <select id="mail-mode"><option value="mail"<?= ($mailSettings['mail_mode'] ?? '') === 'mail' ? ' selected' : '' ?>>PHP mail()</option><option value="smtp"<?= ($mailSettings['mail_mode'] ?? '') === 'smtp' ? ' selected' : '' ?>>SMTP</option></select>
      </div>
      <div class="field"><label>Mittente (From)</label><input type="email" id="mail-from" value="<?= htmlspecialchars($mailSettings['mail_from'] ?? '') ?>" /></div>
      <div class="field"><label>Nome mittente</label><input type="text" id="mail-from-name" value="<?= htmlspecialchars($mailSettings['mail_from_name'] ?? 'Carte Fedeltà') ?>" /></div>
      <div class="field"><label>Reply-To</label><input type="email" id="mail-reply-to" value="<?= htmlspecialchars($mailSettings['mail_reply_to'] ?? '') ?>" /></div>
      <div class="field"><label>Return-Path</label><input type="email" id="mail-return-path" value="<?= htmlspecialchars($mailSettings['mail_return_path'] ?? '') ?>" /></div>

      <h3 class="mt-16">SMTP (se applicabile)</h3>
      <div class="field"><label>Host SMTP</label><input type="text" id="smtp-host" value="<?= htmlspecialchars($mailSettings['smtp_host'] ?? '') ?>" /></div>
      <div class="field"><label>Porta</label><input type="number" id="smtp-port" value="<?= htmlspecialchars($mailSettings['smtp_port'] ?? '587') ?>" /></div>
      <div class="field"><label>Utente</label><input type="text" id="smtp-user" value="<?= htmlspecialchars($mailSettings['smtp_user'] ?? '') ?>" /></div>
      <div class="field"><label>Password</label><input type="password" id="smtp-pass" value="<?= htmlspecialchars($mailSettings['smtp_pass'] ?? '') ?>" /></div>
      <div class="field"><label>Crittografia</label><select id="smtp-encryption"><option value="tls"<?= ($mailSettings['smtp_encryption'] ?? '') === 'tls' ? ' selected' : '' ?>>TLS</option><option value="ssl"<?= ($mailSettings['smtp_encryption'] ?? '') === 'ssl' ? ' selected' : '' ?>>SSL</option><option value="">Nessuna</option></select></div>

      <div class="flex-row mt-8">
        <button type="submit" class="btn btn-primary">Salva Configurazione</button>
        <button type="button" class="btn btn-outline" onclick="testMail()">Invia Email di Test</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Aggiornamenti -->
<?php if ($isSuper): ?>
<?php $versionSettings = []; try { $versionSettings = $db->query('SELECT `key`, `value` FROM ' . TABLE_SETTINGS . ' WHERE `key` IN (\'app_version\', \'app_download_url\')')->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e) {} ?>
<div id="updates" class="section">
  <div class="card" style="max-width:400px">
    <h2>Aggiornamenti App</h2>
    <p style="font-size:13px;color:#666;margin-bottom:16px">Configura la versione disponibile per gli utenti. Quando viene rilevata una versione diversa da quella installata, l'app mostrerà un popup con il link di download.</p>
    <form onsubmit="saveVersionConfig(event)">
      <div class="field"><label>Versione disponibile</label><input type="text" id="app-version" value="<?= htmlspecialchars($versionSettings['app_version'] ?? '1.2.5') ?>" placeholder="es. 1.2.5" /></div>
      <div class="field"><label>URL di download</label><input type="url" id="app-download-url" value="<?= htmlspecialchars($versionSettings['app_download_url'] ?? '') ?>" placeholder="https://..." /></div>
      <button type="submit" class="btn btn-primary">Salva Configurazione</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Logo Infographic -->
<div id="logo-infographic" class="section">
  <div class="card">
    <h2>Infografica Loghi</h2>
    <p style="font-size:13px;color:#666;margin-bottom:16px">Genera un'immagine con tutti i loghi approvati disposti in modo casuale. Ogni generazione produce un risultato diverso.</p>
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button class="btn btn-primary" onclick="generateInfographic()">Genera Infografica</button>
      <button class="btn btn-success" id="infographic-download-btn" style="display:none" onclick="downloadInfographic()">Scarica Immagine</button>
    </div>
    <div id="infographic-preview" style="text-align:center"></div>
  </div>
</div>

<!-- Password -->
<div id="password" class="section">
  <div class="card" style="max-width:400px">
    <h2>Cambia Password</h2>
    <form onsubmit="changePassword(event)">
      <div class="field"><label>Password attuale</label><input type="password" id="cur-pass" required /></div>
      <div class="field"><label>Nuova password</label><input type="password" id="new-pass" required minlength="6" /></div>
      <button type="submit" class="btn btn-primary">Aggiorna Password</button>
    </form>
  </div>
</div>

</div>

<div id="toast" class="toast"></div>

<script>
function showSection(id) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  const el = document.getElementById(id);
  if (el) el.classList.add('active');
  document.querySelectorAll('.nav a').forEach(a => {
    a.classList.remove('active');
    if (a.getAttribute('onclick') && a.getAttribute('onclick').includes("'" + id + "'")) {
      a.classList.add('active');
    }
  });
}

function reloadToSection(id) {
  sessionStorage.setItem('admin_section', id);
  location.reload();
}

// Restore section after reload
(function() {
  const params = new URLSearchParams(location.search);
  if (params.has('sort')) {
    showSection('custom-logos');
  } else {
    const saved = sessionStorage.getItem('admin_section');
    if (saved) {
      sessionStorage.removeItem('admin_section');
      showSection(saved);
    }
  }
})();

function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}

async function postAction(action, data) {
  const fd = new FormData();
  fd.append('action', action);
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const res = await fetch('', { method: 'POST', body: fd });
  return res.json();
}

function handleActionError(r) {
  const msg = r.error || 'Errore sconosciuto';
  toast('Errore: ' + msg);
  if (r.reset) {
    setTimeout(function(){ location.href = '?'; }, 2500);
  }
}

async function approveLogo(id) {
  const r = await postAction('approve_logo', { id });
  if (r.success) { toast('Logo approvato!'); reloadToSection('logos-queue'); } else { handleActionError(r); }
}

async function rejectLogo(id) {
  if (!confirm('Rifiutare questo logo?')) return;
  const r = await postAction('reject_logo', { id });
  if (r.success) { toast('Logo rifiutato'); reloadToSection('logos-queue'); } else { handleActionError(r); }
}

let pendingRequestId = null;

function createStoreFromRequest(id, storeName) {
  pendingRequestId = id;
  document.getElementById('new-store-name').value = storeName;
  document.getElementById('new-store-aliases').value = '';
  document.getElementById('new-store-color').value = '#e30613';
  document.getElementById('new-store-file').value = '';
  document.getElementById('crop-preview-new').style.display = 'none';
  croppedBlob = null;
  cropTarget = null;
  showSection('custom-logos');
  const fileInput = document.getElementById('new-store-file');
  fileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
  fileInput.focus();
  toast('Negozio "' + storeName + '" precompilato. Carica il logo e premi Carica.');
}

async function dismissRequest(id) {
  const r = await postAction('dismiss_request', { id });
  if (r.success) { toast('Richiesta ignorata'); reloadToSection('missing-stores'); } else { handleActionError(r); }
}

async function createAdmin(e) {
  e.preventDefault();
  const r = await postAction('create_admin', {
    email: document.getElementById('new-admin-email').value,
    password: document.getElementById('new-admin-pass').value,
    role: document.getElementById('new-admin-role').value,
  });
  if (r.success) { toast('Amministratore creato!'); reloadToSection('admins'); } else { handleActionError(r); }
}

async function deleteAdmin(id) {
  const r = await postAction('delete_admin', { id });
  if (r.success) { toast('Amministratore rimosso'); reloadToSection('admins'); } else { handleActionError(r); }
}

async function saveMailConfig(e) {
  e.preventDefault();
  const r = await postAction('save_mail_config', {
    mail_mode: document.getElementById('mail-mode').value,
    mail_from: document.getElementById('mail-from').value,
    mail_from_name: document.getElementById('mail-from-name').value,
    mail_reply_to: document.getElementById('mail-reply-to').value,
    mail_return_path: document.getElementById('mail-return-path').value,
    smtp_host: document.getElementById('smtp-host').value,
    smtp_port: document.getElementById('smtp-port').value,
    smtp_user: document.getElementById('smtp-user').value,
    smtp_pass: document.getElementById('smtp-pass').value,
    smtp_encryption: document.getElementById('smtp-encryption').value,
  });
  toast(r.success ? 'Configurazione salvata!' : 'Errore');
}

async function testMail() {
  const email = prompt('Invia email di test a:');
  if (!email) return;
  const r = await postAction('test_mail', { test_email: email });
  toast(r.success ? r.message : (r.error || 'Errore'));
}

async function changePassword(e) {
  e.preventDefault();
  const r = await postAction('change_password', {
    current_password: document.getElementById('cur-pass').value,
    new_password: document.getElementById('new-pass').value,
  });
  if (r.success) { toast('Password aggiornata!'); document.getElementById('cur-pass').value = ''; document.getElementById('new-pass').value = ''; }
  else { handleActionError(r); }
}

async function uploadStore(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'upload_store_logo');
  fd.append('store_name', document.getElementById('new-store-name').value);
  fd.append('aliases', document.getElementById('new-store-aliases').value);
  fd.append('color', document.getElementById('new-store-color').value);
  if (pendingRequestId) fd.append('request_id', pendingRequestId);
  fd.append('logo_file', document.getElementById('new-store-file').files[0]);
  const res = await fetch('', { method: 'POST', body: fd });
  const r = await res.json();
  if (r.success) {
    pendingRequestId = null;
    toast('Negozio creato!');
    reloadToSection('custom-logos');
  } else { handleActionError(r); }
}

function editStore(store) {
  if (typeof store === 'number') store = STORE_DATA[store] || null;
  if (!store) { toast('Errore: negozio non trovato'); return; }
  document.getElementById('edit-store-id').value = store.id;
  document.getElementById('edit-store-name').value = store.name;
  document.getElementById('edit-store-aliases').value = (store.aliases || '').replace(/\n/g, '\n');
  document.getElementById('edit-store-color').value = store.color || '#e30613';
  const preview = document.getElementById('edit-store-preview');
  if (store.logo_path) {
    preview.innerHTML = '<img src="../../../uploads/logos/' + escapeHtml(store.logo_path) + '" style="width:48px;height:48px;object-fit:contain;border-radius:6px;background:#f5f5f5;padding:4px" />';
  } else {
    preview.innerHTML = '<span style="font-size:12px;color:#999">Nessun logo caricato</span>';
  }
  document.getElementById('edit-store-file').value = '';
  document.getElementById('crop-preview-edit').style.display = 'none';
  croppedBlob = null;
  cropTarget = null;
  const modal = document.getElementById('edit-store-modal');
  modal.style.display = 'flex';
}

function closeEditStore() {
  document.getElementById('edit-store-modal').style.display = 'none';
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

async function saveStore(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'update_store');
  fd.append('id', document.getElementById('edit-store-id').value);
  fd.append('name', document.getElementById('edit-store-name').value);
  fd.append('aliases', document.getElementById('edit-store-aliases').value);
  fd.append('color', document.getElementById('edit-store-color').value);
  if (croppedBlob && cropTarget === 'edit') {
    fd.append('logo_file', croppedBlob, 'logo.webp');
  } else {
    const file = document.getElementById('edit-store-file').files[0];
    if (file) fd.append('logo_file', file);
  }
  const res = await fetch('', { method: 'POST', body: fd });
  const r = await res.json();
  if (r.success) { toast('Negozio aggiornato!'); reloadToSection('custom-logos'); } else { handleActionError(r); }
}

async function deleteStore(id) {
  if (!confirm('Eliminare questo negozio e il suo logo?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_store');
  fd.append('id', id);
  const res = await fetch('', { method: 'POST', body: fd });
  const r = await res.json();
  if (r.success) { document.getElementById('store-' + id)?.remove(); toast('Negozio eliminato'); } else { handleActionError(r); }
}

async function resetUserPassword(userId, email) {
  const newPass = prompt('Inserisci la nuova password per ' + email + ' (min 6 caratteri):');
  if (!newPass || newPass.length < 6) { if (newPass !== null) toast('Minimo 6 caratteri'); return; }
  const fd = new FormData();
  fd.append('action', 'reset_user_password');
  fd.append('user_id', userId);
  fd.append('new_password', newPass);
  const res = await fetch('', { method: 'POST', body: fd });
  const r = await res.json();
  if (r.success) { toast('Password aggiornata!'); } else { handleActionError(r); }
}

// ── Crop logic ──
const CROP_ASPECT = 8 / 5.5;
let cropCtx = null, cropCanvas = null, cropImg = null;
let cropZoom = 1, cropPanX = 0, cropPanY = 0;
let cropDrag = null;
let cropTarget = null; // 'new' or 'edit'
let cropFileInput = null;
let croppedBlob = null;

function previewAndCrop(input, target) {
  const file = input.files?.[0];
  if (!file) return;
  cropTarget = target;
  cropFileInput = input;
  croppedBlob = null;
  const reader = new FileReader();
  reader.onload = (e) => {
    const img = new Image();
    img.onload = () => {
      cropImg = img;
      openCropModal();
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function openCropModal() {
  cropCanvas = document.getElementById('crop-canvas');
  const vp = document.getElementById('crop-viewport');
  const vpW = Math.min(480, window.innerWidth - 60);
  const vpH = Math.round(vpW / CROP_ASPECT);
  vp.style.width = vpW + 'px';
  vp.style.height = vpH + 'px';
  cropCanvas.width = vpW * 2; // 2x for quality
  cropCanvas.height = vpH * 2;
  cropCanvas.style.width = vpW + 'px';
  cropCanvas.style.height = vpH + 'px';
  cropCtx = cropCanvas.getContext('2d');
  cropZoom = 1; cropPanX = 0; cropPanY = 0;
  document.getElementById('crop-zoom').value = 1;
  document.getElementById('crop-zoom-pct').textContent = '100%';
  drawCrop();
  // Event listeners
  vp.addEventListener('mousedown', cropPointerDown);
  vp.addEventListener('touchstart', cropTouchStart, { passive: false });
  window.addEventListener('mousemove', cropPointerMove);
  window.addEventListener('mouseup', cropPointerUp);
  window.addEventListener('touchmove', cropTouchMove, { passive: false });
  window.addEventListener('touchend', cropTouchEnd);
  document.getElementById('crop-modal').style.display = 'flex';
}

function closeCropModal() {
  document.getElementById('crop-modal').style.display = 'none';
  const vp = document.getElementById('crop-viewport');
  vp.removeEventListener('mousedown', cropPointerDown);
  vp.removeEventListener('touchstart', cropTouchStart);
  window.removeEventListener('mousemove', cropPointerMove);
  window.removeEventListener('mouseup', cropPointerUp);
  window.removeEventListener('touchmove', cropTouchMove);
  window.removeEventListener('touchend', cropTouchEnd);
}

function drawCrop() {
  if (!cropCtx || !cropImg) return;
  const cw = cropCanvas.width, ch = cropCanvas.height;
  cropCtx.clearRect(0, 0, cw, ch);
  // Draw dark background
  cropCtx.fillStyle = '#1a1a2e';
  cropCtx.fillRect(0, 0, cw, ch);
  // Draw image centered + pan + zoom
  const imgW = cropImg.naturalWidth * cropZoom * 2;
  const imgH = cropImg.naturalHeight * cropZoom * 2;
  const x = (cw - imgW) / 2 + cropPanX * 2;
  const y = (ch - imgH) / 2 + cropPanY * 2;
  cropCtx.drawImage(cropImg, x, y, imgW, imgH);
  // Draw mask overlay (darken outside crop box)
  const boxW = cw * 0.85;
  const boxH = boxW / CROP_ASPECT;
  const boxX = (cw - boxW) / 2;
  const boxY = (ch - boxH) / 2;
  cropCtx.fillStyle = 'rgba(0,0,0,0.5)';
  // Top
  cropCtx.fillRect(0, 0, cw, boxY);
  // Bottom
  cropCtx.fillRect(0, boxY + boxH, cw, ch - boxY - boxH);
  // Left
  cropCtx.fillRect(0, boxY, boxX, boxH);
  // Right
  cropCtx.fillRect(boxX + boxW, boxY, cw - boxX - boxW, boxH);
  // Draw crop box border
  cropCtx.strokeStyle = '#fff';
  cropCtx.lineWidth = 3;
  cropCtx.strokeRect(boxX, boxY, boxW, boxH);
}

function cropZoomChanged() {
  cropZoom = parseFloat(document.getElementById('crop-zoom').value);
  document.getElementById('crop-zoom-pct').textContent = Math.round(cropZoom * 100) + '%';
  drawCrop();
}

function cropPointerDown(e) {
  cropDrag = { sx: e.clientX, sy: e.clientY, px: cropPanX, py: cropPanY };
}
function cropPointerMove(e) {
  if (!cropDrag) return;
  cropPanX = cropDrag.px + (e.clientX - cropDrag.sx);
  cropPanY = cropDrag.py + (e.clientY - cropDrag.sy);
  drawCrop();
}
function cropPointerUp() { cropDrag = null; }

let cropTouchDist0 = null;
function cropTouchStart(e) {
  e.preventDefault();
  if (e.touches.length === 2) {
    const dx = e.touches[0].clientX - e.touches[1].clientX;
    const dy = e.touches[0].clientY - e.touches[1].clientY;
    cropTouchDist0 = Math.sqrt(dx*dx + dy*dy);
    cropDrag = { type: 'pinch', zoom: cropZoom, px: cropPanX, py: cropPanY,
      mx: (e.touches[0].clientX + e.touches[1].clientX)/2,
      my: (e.touches[0].clientY + e.touches[1].clientY)/2 };
  } else if (e.touches.length === 1) {
    cropDrag = { sx: e.touches[0].clientX, sy: e.touches[0].clientY, px: cropPanX, py: cropPanY };
  }
}
function cropTouchMove(e) {
  e.preventDefault();
  if (!cropDrag) return;
  if (cropDrag.type === 'pinch' && e.touches.length === 2 && cropTouchDist0) {
    const dx = e.touches[0].clientX - e.touches[1].clientX;
    const dy = e.touches[0].clientY - e.touches[1].clientY;
    const newDist = Math.sqrt(dx*dx + dy*dy);
    cropZoom = Math.min(4, Math.max(0.1, cropDrag.zoom * (newDist / cropTouchDist0)));
    const mx = (e.touches[0].clientX + e.touches[1].clientX)/2;
    const my = (e.touches[0].clientY + e.touches[1].clientY)/2;
    cropPanX = cropDrag.px + (mx - cropDrag.mx);
    cropPanY = cropDrag.py + (my - cropDrag.my);
    document.getElementById('crop-zoom').value = cropZoom;
    document.getElementById('crop-zoom-pct').textContent = Math.round(cropZoom * 100) + '%';
    drawCrop();
  } else if (e.touches.length === 1) {
    cropPanX = cropDrag.px + (e.touches[0].clientX - cropDrag.sx);
    cropPanY = cropDrag.py + (e.touches[0].clientY - cropDrag.sy);
    drawCrop();
  }
}
function cropTouchEnd() { cropDrag = null; cropTouchDist0 = null; }

function applyCropModal() {
  const cw = cropCanvas.width, ch = cropCanvas.height;
  const boxW = cw * 0.85;
  const boxH = boxW / CROP_ASPECT;
  const boxX = (cw - boxW) / 2;
  const boxY = (ch - boxH) / 2;
  // Create output canvas
  const outW = 400;
  const outH = Math.round(outW / CROP_ASPECT);
  const out = document.createElement('canvas');
  out.width = outW; out.height = outH;
  const ctx = out.getContext('2d');
  // Map crop box to original image coordinates
  const imgW = cropImg.naturalWidth * cropZoom * 2;
  const imgH = cropImg.naturalHeight * cropZoom * 2;
  const imgX = (cw - imgW) / 2 + cropPanX * 2;
  const imgY = (ch - imgH) / 2 + cropPanY * 2;
  const sx = (boxX - imgX) / (imgW / cropImg.naturalWidth);
  const sy = (boxY - imgY) / (imgH / cropImg.naturalHeight);
  const sw = boxW / (imgW / cropImg.naturalWidth);
  const sh = boxH / (imgH / cropImg.naturalHeight);
  ctx.drawImage(cropImg, sx, sy, sw, sh, 0, 0, outW, outH);
  out.toBlob((blob) => {
    croppedBlob = blob;
    // Show preview
    const previewDiv = document.getElementById('crop-preview-' + cropTarget);
    const url = URL.createObjectURL(blob);
    previewDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px"><img src="' + url + '" style="width:64px;height:auto;border-radius:6px;border:1px solid #eee" /><span style="font-size:12px;color:#16a34a">Logo ritagliato</span></div>';
    previewDiv.style.display = 'block';
    closeCropModal();
    toast('Logo ritagliato!');
  }, 'image/webp', 0.9);
}

// Override uploadStore to use cropped blob if available
const origUploadStore = uploadStore;
uploadStore = async function(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'upload_store_logo');
  fd.append('store_name', document.getElementById('new-store-name').value);
  fd.append('aliases', document.getElementById('new-store-aliases').value);
  fd.append('color', document.getElementById('new-store-color').value);
  if (pendingRequestId) fd.append('request_id', pendingRequestId);
  if (croppedBlob) {
    fd.append('logo_file', croppedBlob, 'logo.webp');
  } else {
    fd.append('logo_file', document.getElementById('new-store-file').files[0]);
  }
  const res = await fetch('', { method: 'POST', body: fd });
  const r = await res.json();
  if (r.success) {
    pendingRequestId = null;
    toast('Negozio creato!');
    reloadToSection('custom-logos');
  } else { handleActionError(r); }
};

async function saveVersionConfig(e) {
  e.preventDefault();
  const r = await postAction('save_version_config', {
    app_version: document.getElementById('app-version').value,
    app_download_url: document.getElementById('app-download-url').value,
  });
  toast(r.success ? 'Configurazione salvata!' : 'Errore');
}

// ── Logo Infographic ──
const LOGO_DATA = <?php
  $logoList = [];
  foreach ($allStores as $s) {
    $src = '';
    if (!empty($s['logo_data']) && preg_match('/^data:image\//', $s['logo_data'])) {
      $src = $s['logo_data'];
    } else if ($s['logo_path']) {
      $src = '../../../uploads/logos/' . $s['logo_path'];
    }
    if ($src) $logoList[] = ['name' => $s['name'], 'src' => $src];
  }
  echo json_encode($logoList);
?>;

// Store records for the "Modifica" dialog (keyed by id). Built in a <script> block
// (not inside onclick), so names with apostrophes/quotes can't break the HTML.
const STORE_DATA = <?php
  $storeMap = [];
  foreach ($stores as $s) {
    $storeMap[$s['id']] = [
      'id' => $s['id'],
      'name' => $s['name'],
      'aliases' => $s['aliases'] ?? '',
      'color' => $s['color'] ?? '',
      'logo_path' => $s['logo_path'] ?? '',
    ];
  }
  echo json_encode($storeMap);
?>;

async function generateInfographic() {
  if (!LOGO_DATA.length) { toast('Nessun logo disponibile'); return; }
  const container = document.getElementById('infographic-preview');
  container.innerHTML = '<p style="color:#666">Generazione in corso...</p>';
  document.getElementById('infographic-download-btn').style.display = 'none';

  const W = 1200, H = 900;
  const canvas = document.createElement('canvas');
  canvas.width = W; canvas.height = H;
  const ctx = canvas.getContext('2d');

  // White background
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, W, H);

  const logos = [...LOGO_DATA];
  // Shuffle
  for (let i = logos.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [logos[i], logos[j]] = [logos[j], logos[i]];
  }

  const logoSize = 160;
  const padding = 30;
  const cols = Math.ceil(Math.sqrt(logos.length * (W / H)));
  const rows = Math.ceil(logos.length / cols);
  const cellW = (W - padding * 2) / cols;
  const cellH = (H - padding * 2) / rows;

  const loaded = await Promise.all(logos.map(logo => {
    return new Promise(resolve => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = logo.src;
    });
  }));

  for (let i = 0; i < logos.length; i++) {
    const img = loaded[i];
    if (!img) continue;
    const col = i % cols;
    const row = Math.floor(i / cols);
    const cx = padding + cellW * col + cellW / 2;
    const cy = padding + cellH * row + cellH / 2;
    const offsetX = (Math.random() - 0.5) * cellW * 0.4;
    const offsetY = (Math.random() - 0.5) * cellH * 0.4;
    const angle = (Math.random() - 0.5) * 0.5;
    const scale = 0.6 + Math.random() * 0.4;

    ctx.save();
    ctx.translate(cx + offsetX, cy + offsetY);
    ctx.rotate(angle);

    // Shadow
    ctx.shadowColor = 'rgba(0,0,0,0.2)';
    ctx.shadowBlur = 6 + Math.random() * 5;
    ctx.shadowOffsetX = 1 + Math.random() * 2;
    ctx.shadowOffsetY = 2 + Math.random() * 2;

    // Logo image
    const imgW = logoSize;
    const imgH = logoSize;
    const ratio = Math.min(imgW / img.width, imgH / img.height);
    const w = img.width * ratio;
    const h = img.height * ratio;
    ctx.fillStyle = '#fff';
    ctx.fillRect(-w / 2, -h / 2, w, h);
    ctx.drawImage(img, -w / 2, -h / 2, w, h);

    ctx.shadowColor = 'transparent';

    ctx.restore();
  }

  // Watermark
  ctx.fillStyle = 'rgba(0,0,0,0.12)';
  ctx.font = 'bold 14px system-ui, sans-serif';
  ctx.textAlign = 'right';
  ctx.fillText('FidAPPti — fidappti.altervista.org', W - 16, H - 12);

  container.innerHTML = '';
  canvas.style.maxWidth = '100%';
  canvas.style.borderRadius = '8px';
  canvas.style.boxShadow = '0 2px 12px rgba(0,0,0,0.15)';
  container.appendChild(canvas);
  document.getElementById('infographic-download-btn').style.display = 'inline-flex';
  window._infographicCanvas = canvas;
}

function downloadInfographic() {
  const canvas = window._infographicCanvas;
  if (!canvas) return;
  const link = document.createElement('a');
  link.download = 'fidappti-loghi-' + new Date().toISOString().slice(0, 10) + '.png';
  link.href = canvas.toDataURL('image/png');
  link.click();
}
</script>
</body></html>
