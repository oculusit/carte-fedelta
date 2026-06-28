<?php

function listUsersHandler(string $method, string $uri): void {
  if ($method !== 'GET') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  $userId = usersAuth();
  usersRequireAdminOrModerator($userId);
  $db = usersDb();
  $stmt = $db->query(
    'SELECT u.id, u.email, u.is_admin, u.is_moderator, u.is_active, u.2fa_enabled, u.privacy_accepted, u.status, u.email_confirmed_at, u.created_at, COUNT(c.id) AS card_count ' .
    'FROM ' . TABLE_USERS . ' u ' .
    'LEFT JOIN ' . TABLE_CARDS . ' c ON c.user_id = u.id ' .
    'GROUP BY u.id ORDER BY u.id ASC'
  );
  echo json_encode($stmt->fetchAll());
}

function createUserHandler(string $method, string $uri): void {
  if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  $userId = usersAuth();
  $db = usersDb();
  $checkStmt = $db->prepare('SELECT is_admin FROM ' . TABLE_USERS . ' WHERE id = ?');
  $checkStmt->execute([$userId]);
  $currentUser = $checkStmt->fetch();
  if (!$currentUser || !$currentUser['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo l\'amministratore può creare utenti']);
    return;
  }
  $data = usersJsonBody();
  $email = trim($data['email'] ?? '');
  $password = $data['password'] ?? '';
  $notify = !empty($data['notify']);
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
  $stmt = $db->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email già registrata']);
    return;
  }
  $stmt = $db->prepare('INSERT INTO ' . TABLE_USERS . ' (email, password_hash, privacy_accepted, is_active, status) VALUES (?, ?, 1, 1, \'approved\')');
  $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
  $newUserId = $db->lastInsertId();
  if ($notify) {
    require_once __DIR__ . '/auth.php';
    $appUrl = getAppUrl() . '/';
    $subject = 'Account creato - Carte Fedeltà';
    $bodyHtml = '<html><body>';
    $bodyHtml .= '<h2>Il tuo account è stato creato</h2>';
    $bodyHtml .= '<p>Un amministratore ha creato un account per te su Carte Fedeltà.</p>';
    $bodyHtml .= '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>';
    $bodyHtml .= '<p><strong>Password:</strong> ' . htmlspecialchars($password) . '</p>';
    $bodyHtml .= '<p><a href="' . htmlspecialchars($appUrl) . '">Accedi ora</a></p>';
    $bodyHtml .= '</body></html>';
    sendMail($email, $subject, $bodyHtml);
  }
  echo json_encode(['success' => true, 'message' => ($notify ? 'Utente creato. Email inviata a ' . $email : 'Utente creato con successo'), 'id' => $newUserId]);
}

function getUserCardsHandler(string $method, string $uri): void {
  if ($method !== 'GET') { http_response_code(405); echo json_encode(['error' => 'Metodo non consentito']); return; }
  $adminId = usersAuth();
  usersRequireAdminOrModerator($adminId);
  $parts = explode('/', trim($uri, '/'));
  $targetId = $parts[1] ?? null;
  if (!$targetId || !ctype_digit($targetId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID utente non valido']);
    return;
  }
  $db = usersDb();
  $stmt = $db->prepare('SELECT id, store_name, holder_name, barcode_type, logo_type, logo_path, notes, color, created_at, updated_at FROM ' . TABLE_CARDS . ' WHERE user_id = ? ORDER BY store_name ASC');
  $stmt->execute([$targetId]);
  echo json_encode($stmt->fetchAll());
}

function handleUserHandler(string $method, string $uri): void {
  $userId = usersAuth();
  // Check if admin or moderator first
  $db = usersDb();
  $checkStmt = $db->prepare('SELECT is_admin, is_moderator FROM ' . TABLE_USERS . ' WHERE id = ?');
  $checkStmt->execute([$userId]);
  $currentUser = $checkStmt->fetch();
  if (!$currentUser || (!$currentUser['is_admin'] && !$currentUser['is_moderator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permesso negato']);
    exit;
  }
  $parts = explode('/', trim($uri, '/'));
  $targetId = $parts[1] ?? null;
  if (!$targetId || !ctype_digit($targetId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID utente non valido']);
    return;
  }
  $targetId = (int)$targetId;

  if ($method === 'PUT') {
    updateUser($userId, $targetId, (bool)$currentUser['is_admin']);
  } elseif ($method === 'DELETE') {
    if (!$currentUser['is_admin']) {
      http_response_code(403);
      echo json_encode(['error' => 'Solo l\'amministratore può eliminare utenti']);
      return;
    }
    deleteUser($userId, $targetId);
  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
  }
}

function updateUser(int $adminId, int $targetId, bool $isAdmin): void {
  $data = usersJsonBody();
  $db = usersDb();

  $targetStmt = $db->prepare('SELECT is_admin, is_active, status FROM ' . TABLE_USERS . ' WHERE id = ?');
  $targetStmt->execute([$targetId]);
  $target = $targetStmt->fetch();
  if (!$target) {
    http_response_code(404);
    echo json_encode(['error' => 'Utente non trovato']);
    return;
  }

  // Approve/reject action (allowed for moderator and admin)
  if (isset($data['status']) && in_array($data['status'], ['approved', 'rejected'])) {
    $fields = [];
    $params = [];
    if ($data['status'] === 'approved') {
      $fields[] = 'status = \'approved\'';
      $fields[] = 'is_active = 1';
    } else {
      $fields[] = 'status = \'rejected\'';
      $fields[] = 'is_active = 0';
    }
    $params[] = $targetId;
    $sql = 'UPDATE ' . TABLE_USERS . ' SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'message' => 'Utente ' . ($data['status'] === 'approved' ? 'approvato' : 'rifiutato')]);
    return;
  }

  // For non-status changes, require full admin
  if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo l\'amministratore può eseguire questa azione']);
    return;
  }

  // Prevent disabling the last admin
  if (isset($data['is_active']) && !$data['is_active'] && $target['is_admin']) {
    $countStmt = $db->query('SELECT COUNT(*) AS cnt FROM ' . TABLE_USERS . ' WHERE is_admin = 1');
    $row = $countStmt->fetch();
    if ((int)$row['cnt'] <= 1) {
      http_response_code(400);
      echo json_encode(['error' => 'Non puoi disabilitare l\'unico amministratore']);
      return;
    }
  }

  // Prevent removing admin role from the last admin
  if (isset($data['is_admin']) && !$data['is_admin'] && $target['is_admin']) {
    $countStmt = $db->query('SELECT COUNT(*) AS cnt FROM ' . TABLE_USERS . ' WHERE is_admin = 1');
    $row = $countStmt->fetch();
    if ((int)$row['cnt'] <= 1) {
      http_response_code(400);
      echo json_encode(['error' => 'Non puoi rimuovere l\'unico amministratore']);
      return;
    }
  }

  $fields = [];
  $params = [];

  if (isset($data['is_active'])) {
    $fields[] = 'is_active = ?';
    $params[] = $data['is_active'] ? 1 : 0;
  }
  if (isset($data['is_admin'])) {
    $fields[] = 'is_admin = ?';
    $params[] = $data['is_admin'] ? 1 : 0;
  }
  if (isset($data['is_moderator'])) {
    $fields[] = 'is_moderator = ?';
    $params[] = $data['is_moderator'] ? 1 : 0;
  }

  if (isset($data['reset_2fa']) && $data['reset_2fa']) {
    $fields[] = '2fa_secret = NULL';
    $fields[] = '2fa_enabled = 0';
  }

  if (isset($data['password'])) {
    $pass = $data['password'];
    if (strlen($pass) < 6) {
      http_response_code(400);
      echo json_encode(['error' => 'Password troppo corta (min 6 caratteri)']);
      return;
    }
    $fields[] = 'password_hash = ?';
    $params[] = password_hash($pass, PASSWORD_DEFAULT);
  }

  if (!$fields) {
    http_response_code(400);
    echo json_encode(['error' => 'Nessun campo da aggiornare']);
    return;
  }

  $params[] = $targetId;
  $sql = 'UPDATE ' . TABLE_USERS . ' SET ' . implode(', ', $fields) . ' WHERE id = ?';
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  echo json_encode(['success' => true, 'message' => 'Utente aggiornato']);
}

function deleteUser(int $adminId, int $targetId): void {
  if ($targetId === $adminId) {
    http_response_code(400);
    echo json_encode(['error' => 'Non puoi eliminare il tuo account']);
    return;
  }
  $db = usersDb();

  $targetStmt = $db->prepare('SELECT is_admin FROM ' . TABLE_USERS . ' WHERE id = ?');
  $targetStmt->execute([$targetId]);
  $target = $targetStmt->fetch();
  if (!$target) {
    http_response_code(404);
    echo json_encode(['error' => 'Utente non trovato']);
    return;
  }

  // Prevent deleting the last admin
  if ($target['is_admin']) {
    $countStmt = $db->query('SELECT COUNT(*) AS cnt FROM ' . TABLE_USERS . ' WHERE is_admin = 1');
    $row = $countStmt->fetch();
    if ((int)$row['cnt'] <= 1) {
      http_response_code(400);
      echo json_encode(['error' => 'Non puoi eliminare l\'unico amministratore']);
      return;
    }
  }

  $db->beginTransaction();
  try {
    $stmt = $db->prepare('DELETE FROM ' . TABLE_CARDS . ' WHERE user_id = ?');
    $stmt->execute([$targetId]);
    $stmt = $db->prepare('DELETE FROM ' . TABLE_CUSTOM_LOGOS . ' WHERE user_id = ?');
    $stmt->execute([$targetId]);
    $stmt = $db->prepare('UPDATE ' . TABLE_STORES . ' SET created_by = NULL WHERE created_by = ?');
    $stmt->execute([$targetId]);
    $stmt = $db->prepare('DELETE FROM ' . TABLE_AUTH_TOKENS . ' WHERE user_id = ?');
    $stmt->execute([$targetId]);
    $stmt = $db->prepare('DELETE FROM ' . TABLE_USERS . ' WHERE id = ?');
    $stmt->execute([$targetId]);
    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Utente e carte associate eliminate']);
  } catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Errore durante l\'eliminazione: ' . $e->getMessage()]);
  }
}

// ── Helpers ──

function usersAuth(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = usersDb();
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

function usersRequireAdminOrModerator(int $userId): void {
  $db = usersDb();
  $stmt = $db->prepare('SELECT is_admin, is_moderator FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  if (!$row || (!$row['is_admin'] && !$row['is_moderator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permesso negato']);
    exit;
  }
}

function usersDb(): PDO {
  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  return new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function usersJsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
