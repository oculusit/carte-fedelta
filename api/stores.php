<?php

function handleStoresHandler(string $method, string $uri): void {
  $userId = storesAuthenticate();
  $isAdmin = storesIsAdmin($userId);
  $canManage = $isAdmin || storesIsModerator($userId);
  $db = storesGetDb();

  if ($method === 'GET') {
    listStores($db, $userId, $canManage);
  } elseif ($method === 'POST') {
    suggestStore($db, $userId, $canManage);
  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
  }
}

function handleStoreHandler(string $method, string $uri): void {
  $userId = storesAuthenticate();
  $isAdmin = storesIsAdmin($userId);
  $canManage = $isAdmin || storesIsModerator($userId);
  $db = storesGetDb();

  $parts = explode('/', trim($uri, '/'));
  $storeId = $parts[1] ?? null;
  $action = $parts[2] ?? null;

  if (!$storeId || !ctype_digit($storeId)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID negozio non valido']);
    return;
  }

  if ($method === 'POST' && $action === 'approve') {
    if (!$canManage) {
      http_response_code(403);
      echo json_encode(['error' => 'Non hai i permessi per approvare negozi']);
      return;
    }
    approveStore($db, $storeId);
  } elseif ($method === 'GET') {
    getStore($db, $storeId);
  } elseif ($method === 'PUT') {
    if (!$canManage) {
      http_response_code(403);
      echo json_encode(['error' => 'Non hai i permessi per modificare negozi']);
      return;
    }
    updateStore($db, $storeId);
  } elseif ($method === 'DELETE') {
    if (!$isAdmin) {
      http_response_code(403);
      echo json_encode(['error' => 'Solo l\'amministratore pu� eliminare negozi']);
      return;
    }
    deleteStore($db, $storeId);
  } else {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
  }
}

function listStoresBriefHandler(string $method, string $uri): void {
  $userId = storesAuthenticate();
  $db = storesGetDb();
  $stmt = $db->prepare('SELECT id, name, logo_type FROM ' . TABLE_STORES . ' WHERE status = ? ORDER BY name ASC');
  $stmt->execute(['approved']);
  echo json_encode($stmt->fetchAll());
}

function listOrphanStores(): void {
  storesAuthenticate();
  $db = storesGetDb();
  $stmt = $db->query("SELECT DISTINCT c.store_name FROM " . TABLE_CARDS . " c LEFT JOIN " . TABLE_STORES . " s ON LOWER(c.store_name) = LOWER(s.name) WHERE s.id IS NULL ORDER BY c.store_name ASC");
  echo json_encode(array_column($stmt->fetchAll(), 'store_name'));
}

function listStores(PDO $db, int $userId, bool $canManage): void {
  try {
    if ($canManage) {
      $stmt = $db->query("SELECT s.id, s.name, s.aliases, s.logo_type, s.created_by, s.status, s.admin_notes, s.created_at, s.updated_at, u.email AS created_by_email, LENGTH(s.logo_data) AS logo_size_bytes FROM " . TABLE_STORES . " s LEFT JOIN " . TABLE_USERS . " u ON s.created_by = u.id ORDER BY s.status ASC, s.name ASC");
    } else {
      $stmt = $db->prepare('SELECT id, name, aliases, logo_type, status, LENGTH(logo_data) AS logo_size_bytes FROM ' . TABLE_STORES . ' WHERE status = ? ORDER BY name ASC');
      $stmt->execute(['approved']);
    }
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
      $r['has_logo'] = ($r['logo_type'] ?? '') === 'upload';
      $r['logo_size_kb'] = $r['logo_size_bytes'] ? round((int)$r['logo_size_bytes'] / 1024, 1) : 0;
      $out[] = $r;
    }
    $json = json_encode($out);
    if ($json === false) {
      http_response_code(500);
      echo json_encode(['error' => 'json_encode fallito: ' . json_last_error_msg()]);
      return;
    }
    echo $json;
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Eccezione: ' . $e->getMessage()]);
  }
}

function getStore(PDO $db, string $storeId): void {
  $stmt = $db->prepare("SELECT *, LENGTH(logo_data) AS logo_size_bytes FROM " . TABLE_STORES . " WHERE id = ?");
  $stmt->execute([$storeId]);
  $store = $stmt->fetch();
  if (!$store) {
    http_response_code(404);
    echo json_encode(['error' => 'Negozio non trovato']);
    return;
  }
  $store['logo_size_kb'] = $store['logo_size_bytes'] ? round((int)$store['logo_size_bytes'] / 1024, 1) : 0;
  echo json_encode($store);
}

function suggestStore(PDO $db, int $userId, bool $autoApprove): void {
  $data = storesJsonBody();
  $name = trim($data['name'] ?? '');
  if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Il nome del negozio è obbligatorio']);
    return;
  }

  $stmt = $db->prepare('SELECT id FROM ' . TABLE_STORES . ' WHERE name = ?');
  $stmt->execute([$name]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Questo negozio esiste già']);
    return;
  }

  $logoType = $data['logo_type'] ?? 'predefined';
  $logoPath = $data['logo_path'] ?? '';
  $logoData = $data['logo_data'] ?? '';
  $aliases = $data['aliases'] ?? '';
  $status = $autoApprove ? 'approved' : 'pending';

  $stmt = $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, logo_type, logo_path, logo_data, aliases, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([$name, $logoType, $logoPath, $logoData, $aliases, $userId, $status]);

  $id = $db->lastInsertId();

  http_response_code(201);
  echo json_encode([
    'success' => true,
    'id' => $id,
    'name' => $name,
    'status' => $status,
    'message' => $autoApprove ? 'Negozio creato con successo' : 'Negozio suggerito. In attesa di approvazione.',
  ]);
}

  $stmt = $db->prepare('SELECT id FROM ' . TABLE_STORES . ' WHERE name = ?');
  $stmt->execute([$name]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Questo negozio esiste gi�']);
    return;
  }

  $logoType = $data['logo_type'] ?? 'predefined';
  $logoPath = $data['logo_path'] ?? '';
  $logoData = $data['logo_data'] ?? '';
  $status = $autoApprove ? 'approved' : 'pending';

  $stmt = $db->prepare('INSERT INTO ' . TABLE_STORES . ' (name, logo_type, logo_path, logo_data, created_by, status) VALUES (?, ?, ?, ?, ?, ?)');
  $stmt->execute([$name, $logoType, $logoPath, $logoData, $userId, $status]);

  $id = $db->lastInsertId();

  http_response_code(201);
  echo json_encode([
    'success' => true,
    'id' => $id,
    'name' => $name,
    'status' => $status,
    'message' => $autoApprove ? 'Negozio creato con successo' : 'Negozio suggerito. In attesa di approvazione.',
  ]);
}

function updateStore(PDO $db, string $storeId): void {
  $data = storesJsonBody();
  $fields = [];
  $params = [];
  $hasLogoUpdate = false;
  foreach (['name', 'logo_type', 'logo_path', 'logo_data', 'admin_notes', 'aliases'] as $f) {
    if (isset($data[$f])) {
      $fields[] = "$f = ?";
      $params[] = $data[$f];
      if ($f === 'logo_data') $hasLogoUpdate = true;
    }
  }
  if (!$fields) {
    http_response_code(400);
    echo json_encode(['error' => 'Nessun campo da aggiornare']);
    return;
  }
  $params[] = $storeId;
  $sql = 'UPDATE ' . TABLE_STORES . ' SET ' . implode(', ', $fields) . ' WHERE id = ?';
  $stmt = $db->prepare($sql);
  $stmt->execute($params);

  // Propagate logo changes to all cards using this store (check name + aliases)
  if ($hasLogoUpdate) {
    $s = $db->prepare('SELECT name, aliases, logo_type, logo_data FROM ' . TABLE_STORES . ' WHERE id = ?');
    $s->execute([$storeId]);
    $store = $s->fetch();
    if ($store) {
      $allNames = array_merge([$store['name']], array_filter(array_map('trim', explode("\n", $store['aliases'] ?? ''))));
      $placeholders = implode(',', array_fill(0, count($allNames), '?'));
      $uc = $db->prepare('UPDATE ' . TABLE_CARDS . ' SET logo_type = ?, logo_data = ? WHERE LOWER(store_name) IN (' . $placeholders . ')');
      $params = array_merge([$store['logo_type'], $store['logo_data']], array_map('strtolower', $allNames));
      $uc->execute($params);
    }
  }

  echo json_encode(['success' => true, 'message' => 'Negozio aggiornato']);
}

function deleteStore(PDO $db, string $storeId): void {
  $stmt = $db->prepare('DELETE FROM ' . TABLE_STORES . ' WHERE id = ?');
  $stmt->execute([$storeId]);
  echo json_encode(['success' => true, 'message' => 'Negozio eliminato']);
}

function approveStore(PDO $db, string $storeId): void {
  $data = storesJsonBody();
  $adminNotes = $data['admin_notes'] ?? '';
  $stmt = $db->prepare('UPDATE ' . TABLE_STORES . ' SET status = ?, admin_notes = ? WHERE id = ?');
  $stmt->execute(['approved', $adminNotes, $storeId]);
  echo json_encode(['success' => true, 'message' => 'Negozio approvato']);
}

// ── Helpers ──

function storesAuthenticate(): int {
  $headers = getallheaders();
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  if (strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Token mancante']);
    exit;
  }
  $token = substr($auth, 7);
  $db = storesGetDb();
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

function storesIsAdmin(int $userId): bool {
  $db = storesGetDb();
  $stmt = $db->prepare('SELECT is_admin FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  return $row && $row['is_admin'];
}

function storesIsModerator(int $userId): bool {
  $db = storesGetDb();
  $stmt = $db->prepare('SELECT is_moderator FROM ' . TABLE_USERS . ' WHERE id = ?');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  return $row && $row['is_moderator'];
}

function storesGetDb(): PDO {
  $host = defined('DB_HOST') ? DB_HOST : 'localhost';
  $port = defined('DB_PORT') ? DB_PORT : '3306';
  $name = defined('DB_NAME') ? DB_NAME : '';
  $user = defined('DB_USER') ? DB_USER : '';
  $pass = defined('DB_PASS') ? DB_PASS : '';
  $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function storesJsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
