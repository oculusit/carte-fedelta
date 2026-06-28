<?php
/**
 * Cron.php — Verifica periodica utenti in attesa e card senza logo.
 * Invocabile via CLI (php cron.php), via HTTP diretto, o via index.php route.
 */

function cronHandler(string $method, string $uri = ''): void {
  require_once __DIR__ . '/auth.php';
  $db = getDb();

  // 1) Utenti in attesa
  $stmt = $db->prepare("SELECT id, email, email_confirmed_at, created_at FROM " . TABLE_USERS . " WHERE status = 'pending'");
  $stmt->execute();
  $pendingUsers = $stmt->fetchAll();

  // 2) Card senza logo
  $stmt = $db->prepare("SELECT id, store_name, user_id FROM " . TABLE_CARDS . " WHERE logo_type = 'none' OR logo_type IS NULL OR logo_path = ''");
  $stmt->execute();
  $noLogoCards = $stmt->fetchAll();

  if (empty($pendingUsers) && empty($noLogoCards)) {
    echo json_encode(['message' => 'Nessuna segnalazione']);
    return;
  }

  $appName = getSetting('app_name', 'Carte Fedeltà');
  $subject = "Nuovi utenti o loghi da inserire in {$appName}";

  $body = '<html><body style="font-family:sans-serif;padding:20px">';
  $body .= '<h2>' . htmlspecialchars($subject) . '</h2>';
  $body .= '<p>Il sistema ha rilevato le seguenti attività in sospeso:</p>';
  $body .= '<hr>';

  if (!empty($pendingUsers)) {
    $body .= '<h3>Utenti in attesa di attivazione</h3>';
    $body .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
    $body .= '<tr><th>ID</th><th>Email</th><th>Email confermata</th><th>Data registrazione</th></tr>';
    foreach ($pendingUsers as $u) {
      $confirmed = $u['email_confirmed_at'] ? 'Sì' : 'No';
      $body .= '<tr>';
      $body .= '<td>' . (int)$u['id'] . '</td>';
      $body .= '<td>' . htmlspecialchars($u['email']) . '</td>';
      $body .= '<td>' . $confirmed . '</td>';
      $body .= '<td>' . htmlspecialchars($u['created_at'] ?? '') . '</td>';
      $body .= '</tr>';
    }
    $body .= '</table>';
  } else {
    $body .= '<p><em>Nessun utente in attesa.</em></p>';
  }

  $body .= '<hr>';

  if (!empty($noLogoCards)) {
    $body .= '<h3>Card senza logo da inserire</h3>';
    $body .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
    $body .= '<tr><th>ID Card</th><th>Negozio</th><th>ID Utente</th></tr>';
    foreach ($noLogoCards as $c) {
      $body .= '<tr>';
      $body .= '<td>' . htmlspecialchars($c['id']) . '</td>';
      $body .= '<td>' . htmlspecialchars($c['store_name']) . '</td>';
      $body .= '<td>' . (int)$c['user_id'] . '</td>';
      $body .= '</tr>';
    }
    $body .= '</table>';
  } else {
    $body .= '<p><em>Nessuna card senza logo.</em></p>';
  }

  $body .= '<hr>';
  $appUrl = getSetting('app_url', '');
  if ($appUrl) {
    $body .= '<p><a href="' . htmlspecialchars($appUrl) . '">Vai all\'applicazione</a></p>';
  }
  $body .= '<p style="color:#888;font-size:12px">Messaggio generato automaticamente dal cron di sistema.</p>';
  $body .= '</body></html>';

  $stmt = $db->prepare("SELECT email FROM " . TABLE_USERS . " WHERE (is_admin = 1 OR is_moderator = 1) AND is_active = 1 AND status = 'approved'");
  $stmt->execute();
  $recipients = $stmt->fetchAll();

  if (empty($recipients)) {
    echo json_encode(['error' => 'Nessun destinatario (admin/moderatore) trovato']);
    http_response_code(500);
    return;
  }

  $sent = 0;
  $errors = [];
  foreach ($recipients as $r) {
    try {
      sendMail($r['email'], $subject, $body);
      $sent++;
    } catch (Exception $e) {
      $errors[] = $r['email'] . ': ' . $e->getMessage();
    }
  }

  $res = ['sent' => $sent, 'pending_users' => count($pendingUsers), 'no_logo_cards' => count($noLogoCards)];
  if (!empty($errors)) {
    $res['errors'] = $errors;
  }
  echo json_encode($res);
}

// ─── Standalone esecuzione ───
$isStandalone = (PHP_SAPI === 'cli' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'cron.php');

if ($isStandalone) {
  $configPath = __DIR__ . '/config.php';
  if (!file_exists($configPath)) {
    $msg = 'config.php non trovato';
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $msg . "\n"); exit(1); }
    else                     { http_response_code(500); echo json_encode(['error' => $msg]); exit; }
  }
  require_once $configPath;

  if (!defined('TABLE_SETTINGS'))          define('TABLE_SETTINGS', DB_PREFIX . 'settings');
  if (!defined('TABLE_FAMILY_GROUPS'))     define('TABLE_FAMILY_GROUPS', DB_PREFIX . 'family_groups');
  if (!defined('TABLE_FAMILY_MEMBERS'))    define('TABLE_FAMILY_MEMBERS', DB_PREFIX . 'family_group_members');
  if (!defined('TABLE_CARD_GROUP_SHARES')) define('TABLE_CARD_GROUP_SHARES', DB_PREFIX . 'card_group_shares');
  if (!defined('ENC_PREFIX'))              define('ENC_PREFIX', '##ENC##');

  require_once __DIR__ . '/auth.php';

  // Auth per HTTP diretto (non via index.php)
  if (PHP_SAPI !== 'cli') {
    $userId = authenticate();
    $db = getDb();
    $stmt = $db->prepare('SELECT is_admin, is_moderator FROM ' . TABLE_USERS . ' WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || (!$user['is_admin'] && !$user['is_moderator'])) {
      http_response_code(403);
      echo json_encode(['error' => 'Solo admin e moderatori possono eseguire il cron']);
      exit;
    }
  }

  header('Content-Type: application/json; charset=utf-8');
  cronHandler('GET');

  if (PHP_SAPI === 'cli') { echo "\n"; }
}
